<?php

namespace Modules\ZabbixJumpserver\Actions;

use CController,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';
require_once dirname(__DIR__) . '/lib/JumpserverClient.php';

use Modules\ZabbixJumpserver\Lib\LanguageManager;
use Modules\ZabbixJumpserver\Lib\ConfigManager;
use Modules\ZabbixJumpserver\Lib\JumpserverClient;

class JumpserverPush extends CController {

    /** @var JumpserverClient */
    private $client;

    /** name => node id cache */
    private $nodeMap = [];

    /** name => {pk, protocols} platform cache */
    private $platformMap = [];

    /** name => asset asset cache */
    private $assetMap = [];

    /** Account template ID (blank means accounts are not linked automatically) */
    private $accountTemplateId = '';

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'mode'    => 'string',
            'hostids' => 'string',
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
    }

    protected function doAction(): void {
        if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
            $this->respond(false, LanguageManager::t('No permission.'));
            return;
        }

        $config = ConfigManager::load();
        if (!ConfigManager::isConfigured()) {
            $this->respond(false, LanguageManager::t('JumpServer is not configured. Please edit data/config.json.'));
            return;
        }

        $this->client = new JumpserverClient($config);
        $this->accountTemplateId = ConfigManager::getAccountTemplateId();

        $mode = trim((string) $this->getInput('mode', ''));

        try {
            if ($mode === 'all_groups') {
                $this->loadNodes();
                $summary = $this->pushGroups();
                $this->respond(true, LanguageManager::t('Push completed'), $summary);
                return;
            }

            if ($mode === 'all_hosts' || $mode === 'selected') {
                $hostIds = [];
                if ($mode === 'selected') {
                    $raw = trim((string) $this->getInput('hostids', ''));
                    $hostIds = array_values(array_filter(array_map('trim', explode(',', $raw))));
                    if (empty($hostIds)) {
                        $this->respond(false, LanguageManager::t('Invalid input parameters.'));
                        return;
                    }
                }

                $this->loadNodes();
                $this->loadPlatforms();
                $this->loadAssets();
                $summary = $this->pushHosts($hostIds);
                $this->respond(true, LanguageManager::t('Push completed'), $summary);
                return;
            }

            $this->respond(false, LanguageManager::t('Invalid input parameters.'));
        } catch (\Throwable $e) {
            error_log('JumpServer push error: ' . $e->getMessage());
            $this->respond(false, LanguageManager::t('Push failed') . ': ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    //  Loading the JumpServer-side cache
    // ─────────────────────────────────────────────

    private function loadNodes(): void {
        $this->nodeMap = [];
        foreach ($this->client->getNodes() as $node) {
            $key = (string) ($node['value'] ?? ($node['name'] ?? ''));
            if ($key !== '' && isset($node['id'])) {
                $this->nodeMap[$key] = (string) $node['id'];
            }
        }
    }

    private function loadPlatforms(): void {
        $this->platformMap = [];
        foreach ($this->client->getPlatforms() as $platform) {
            $name = (string) ($platform['name'] ?? '');
            if ($name === '' || !isset($platform['id'])) {
                continue;
            }

            $protocols = [];
            foreach (($platform['protocols'] ?? []) as $protocol) {
                if (!isset($protocol['name'])) {
                    continue;
                }
                $protocols[] = [
                    'name' => (string) $protocol['name'],
                    'port' => (int) ($protocol['port'] ?? 0),
                ];
            }

            $this->platformMap[$name] = [
                'pk'        => (string) $platform['id'],
                'protocols' => $protocols,
            ];
        }
    }

    private function loadAssets(): void {
        $this->assetMap = [];
        foreach ($this->client->getHosts() as $asset) {
            $name = (string) ($asset['name'] ?? '');
            if ($name !== '') {
                $this->assetMap[$name] = $asset;
            }
        }
    }

    /**
     * Make sure the corresponding node (group) exists in JumpServer, creating it if missing
     */
    private function ensureNode(string $name): ?string {
        if ($name === '') {
            return null;
        }
        if (isset($this->nodeMap[$name])) {
            return $this->nodeMap[$name];
        }

        $node = $this->client->createNode($name);
        if ($node !== null && isset($node['id'])) {
            $this->nodeMap[$name] = (string) $node['id'];
            return $this->nodeMap[$name];
        }

        return null;
    }

    // ─────────────────────────────────────────────
    //  Push logic
    // ─────────────────────────────────────────────

    private function pushGroups(): array {
        $created = 0;
        $existing = 0;
        $failed = 0;

        foreach ($this->getZabbixGroups() as $name) {
            if (isset($this->nodeMap[$name])) {
                $existing++;
                continue;
            }
            $nodeId = $this->ensureNode($name);
            if ($nodeId !== null) {
                $created++;
            } else {
                $failed++;
            }
        }

        return [
            'type'     => 'groups',
            'created'  => $created,
            'existing' => $existing,
            'failed'   => $failed,
        ];
    }

    private function pushHosts(array $hostIds): array {
        $hosts = $this->getZabbixHosts($hostIds);
        $platformByHost = $this->getHostPlatforms(array_column($hosts, 'hostid'));

        $created = 0;
        $updated = 0;
        $failed = 0;
        $accountsLinked = 0;
        $accountsFailed = 0;

        foreach ($hosts as $host) {
            $hostid = (string) $host['hostid'];
            $name = (string) ($host['host'] ?? $host['name']);
            $ip = $this->resolveHostIp($host);

            // Resolve the platform and map it to the JumpServer platform
            $zbxPlatform = $platformByHost[$hostid] ?? 'Linux';
            $platform = $this->platformMap[$zbxPlatform]
                ?? ($this->platformMap['Linux'] ?? null);

            // Build the node list (missing groups are created automatically)
            $nodes = [];
            $groups = $host['hostgroups'] ?? ($host['groups'] ?? []);
            foreach ($groups as $g) {
                $gname = (string) ($g['name'] ?? '');
                $nodeId = $this->ensureNode($gname);
                if ($nodeId !== null) {
                    $nodes[] = ['pk' => $nodeId];
                }
            }

            $existingAsset = $this->assetMap[$name] ?? null;
            $isNewAsset = !(is_array($existingAsset) && isset($existingAsset['id']));

            // Account linking: only when creating an asset, the template is embedded through the accounts field,
            // and JumpServer fills in the username/secret from the template. Accounts are not changed when updating assets.
            $attachAccount = ($this->accountTemplateId !== '' && $isNewAsset);

            $data = [
                'name'      => $name,
                'address'   => $ip,
                'is_active' => true,
                'nodes'     => $nodes,
            ];
            // The account template is embedded only when creating assets; updates do not send the accounts field, so existing accounts are not affected
            if ($attachAccount) {
                $data['accounts'] = [['template' => $this->accountTemplateId]];
            }
            if ($platform !== null) {
                $data['platform'] = ['pk' => $platform['pk']];
                if (!empty($platform['protocols'])) {
                    $data['protocols'] = $platform['protocols'];
                }
            }

            $assetId = '';
            if (!$isNewAsset) {
                $assetId = (string) $existingAsset['id'];
                $result = $this->client->updateHost($assetId, $data);
                if ($result['ok']) {
                    $updated++;
                } else {
                    $failed++;
                    error_log('JumpServer update host failed: ' . $result['error']);
                    continue;
                }
            } else {
                $result = $this->client->createHost($data);
                if ($result['ok'] && isset($result['data']['id'])) {
                    $assetId = (string) $result['data']['id'];
                    $created++;
                } else {
                    $failed++;
                    error_log('JumpServer create host failed: ' . $result['error']);
                    continue;
                }
            }

            if ($assetId !== '') {
                $this->writeBackAssetId($host, $assetId);

                if ($attachAccount) {
                    // The account template was embedded only on asset creation; mark the account as linked
                    $accountsLinked++;
                }
            }
        }

        return [
            'type'            => 'hosts',
            'created'         => $created,
            'updated'         => $updated,
            'failed'          => $failed,
            'accounts_linked' => $accountsLinked,
            'accounts_failed' => $accountsFailed,
        ];
    }

    /**
     * Write the JumpServer asset ID back to the Zabbix host as a tag (keeping existing tags)
     */
    private function writeBackAssetId(array $host, string $assetId): void {
        $assetTag = ConfigManager::ASSET_TAG;
        $tags = [];
        foreach (($host['tags'] ?? []) as $tag) {
            if (($tag['tag'] ?? '') === $assetTag) {
                continue;
            }
            $tags[] = [
                'tag'   => (string) ($tag['tag'] ?? ''),
                'value' => (string) ($tag['value'] ?? ''),
            ];
        }
        $tags[] = ['tag' => $assetTag, 'value' => $assetId];

        try {
            API::Host()->update([
                'hostid' => (string) $host['hostid'],
                'tags'   => $tags,
            ]);
        } catch (\Throwable $e) {
            error_log('JumpServer: write back asset id failed for host ' . $host['hostid'] . ': ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    //  Fetching data on the Zabbix side
    // ─────────────────────────────────────────────

    private function getZabbixGroups(): array {
        try {
            $groups = API::HostGroup()->get([
                'output'    => ['groupid', 'name'],
                'sortfield' => 'name',
                'sortorder' => 'ASC',
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $names = [];
        foreach ((is_array($groups) ? $groups : []) as $g) {
            $name = (string) ($g['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function getZabbixHosts(array $hostIds): array {
        $params = [
            'output'           => ['hostid', 'host', 'name', 'status'],
            'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
            'selectHostGroups' => ['groupid', 'name'],
            'selectTags'       => ['tag', 'value'],
            'sortfield'        => 'name',
            'sortorder'        => 'ASC',
        ];
        if (!empty($hostIds)) {
            $params['hostids'] = $hostIds;
        }

        try {
            $hosts = API::Host()->get($params);
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($hosts) ? $hosts : [];
    }

    private function resolveHostIp(array $host): string {
        $interfaces = $host['interfaces'] ?? [];
        foreach ($interfaces as $iface) {
            if ((int) ($iface['main'] ?? 0) === 1) {
                return ($iface['ip'] ?? '') !== '' ? (string) $iface['ip'] : (string) ($iface['dns'] ?? '');
            }
        }
        if (!empty($interfaces)) {
            $first = $interfaces[0];
            return ($first['ip'] ?? '') !== '' ? (string) $first['ip'] : (string) ($first['dns'] ?? '');
        }
        return '';
    }

    /**
     * Identify each host's platform (Linux / Windows / Other) from the "Operating system" item
     */
    private function getHostPlatforms(array $hostIds): array {
        $platforms = [];
        if (empty($hostIds)) {
            return $platforms;
        }

        try {
            $items = API::Item()->get([
                'output'  => ['hostid', 'name', 'key_', 'lastvalue'],
                'hostids' => $hostIds,
                'search'  => ['name' => 'Operating system'],
            ]);
        } catch (\Throwable $e) {
            $items = [];
        }

        foreach ((is_array($items) ? $items : []) as $item) {
            $hostid = (string) ($item['hostid'] ?? '');
            $value = (string) ($item['lastvalue'] ?? '');
            if ($hostid === '' || $value === '' || isset($platforms[$hostid])) {
                continue;
            }
            $platforms[$hostid] = $this->mapPlatform($value);
        }

        return $platforms;
    }

    private function mapPlatform(string $value): string {
        $lower = strtolower($value);
        if (strpos($lower, 'windows') !== false) {
            return 'Windows';
        }
        if (strpos($lower, 'linux') !== false) {
            return 'Linux';
        }
        return 'Linux';
    }

    private function respond(bool $ok, string $message, array $summary = []): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => $ok,
            'message' => $message,
            'summary' => $summary,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
