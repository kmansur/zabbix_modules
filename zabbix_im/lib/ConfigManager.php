<?php

namespace Modules\ZabbixIm\Lib;
require_once __DIR__.'/ModuleNotice.php';


require_once __DIR__ . '/LanguageManager.php';
require_once __DIR__ . '/SyncRegistry.php';

class ConfigManager {

    public const PROVIDERS = ['wecom', 'feishu', 'dingtalk'];
    public const USER_MATCH_FIELDS = ['username', 'email', 'alias'];

    /** Credential fields required by each platform (for the form and validation) */
    public const PROVIDER_FIELDS = [
        'wecom'    => ['corp_id', 'corp_secret'],
        'feishu'   => ['app_id', 'app_secret'],
        'dingtalk' => ['app_key', 'app_secret'],
    ];

    /** All credential fields that may appear (for normalizing settings) */
    private const CREDENTIAL_FIELDS = ['corp_id', 'corp_secret', 'app_id', 'app_secret', 'app_key'];

    /** Fields treated as secrets: masked in the list and kept unchanged when left blank */
    private const SECRET_FIELDS = ['corp_secret', 'app_secret'];

    private static $config = null;

    public static function getConfigPath(): string {
        return dirname(__DIR__) . '/data/config.json';
    }

    /**
     * Defaults of the global options (excluding settings).
     *
     * @return array<string, mixed>
     */
    private static function defaultGlobals(): array {
        return [
            'use_full_path'       => false,
            'path_separator'      => '/',
            'username_lowercase'  => true,
            'remove_orphans'      => true,
            'remove_orphan_users' => true,
            'auto_create_users'   => true,
            'auto_update_users'   => true,
            'default_roleid'      => '',
            'default_user_type'   => 2,
            'verify_ssl'          => true,
        ];
    }

    public static function load(): array {
        if (self::$config !== null) {
            return self::$config;
        }

        $decoded = [];
        $path = self::getConfigPath();
        if (is_file($path) && is_readable($path)) {
            $raw = file_get_contents($path);
            $tmp = json_decode((string) $raw, true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }

        $globals = array_replace(self::defaultGlobals(), array_intersect_key($decoded, self::defaultGlobals()));

        if (isset($decoded['settings']) && is_array($decoded['settings'])) {
            $settings = self::normalizeSettings($decoded['settings']);
        } else {
            // Compatible with the legacy format (top-level provider + wecom/feishu/dingtalk).
            $settings = self::migrateLegacy($decoded);
        }

        $globals['settings'] = self::ensureSingleEnabled($settings);

        self::$config = $globals;
        return self::$config;
    }

    /**
     * Legacy migration: platforms with configured credentials become settings, and the former provider is enabled.
     *
     * @param array<string, mixed> $decoded
     * @return array<int, array<string, mixed>>
     */
    private static function migrateLegacy(array $decoded): array {
        $legacyProvider = strtolower(trim((string) ($decoded['provider'] ?? '')));
        $settings = [];

        foreach (self::PROVIDERS as $provider) {
            $sub = is_array($decoded[$provider] ?? null) ? $decoded[$provider] : [];
            if ($sub === []) {
                continue;
            }

            $hasCreds = false;
            foreach (self::PROVIDER_FIELDS[$provider] as $field) {
                if (trim((string) ($sub[$field] ?? '')) !== '') {
                    $hasCreds = true;
                    break;
                }
            }
            if (!$hasCreds) {
                continue;
            }

            $settings[] = self::normalizeSetting([
                'id'                 => self::generateId(),
                'name'               => self::providerLabelFor($provider),
                'provider'           => $provider,
                'enabled'            => $provider === $legacyProvider,
                'corp_id'            => $sub['corp_id'] ?? '',
                'corp_secret'        => $sub['corp_secret'] ?? '',
                'app_id'             => $sub['app_id'] ?? '',
                'app_secret'         => $sub['app_secret'] ?? '',
                'app_key'            => $sub['app_key'] ?? '',
                'root_department_id' => $sub['root_department_id'] ?? '',
            ]);
        }

        return $settings;
    }

    /**
     * @param array<int, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeSettings(array $settings): array {
        $result = [];
        foreach ($settings as $setting) {
            if (is_array($setting)) {
                $result[] = self::normalizeSetting($setting);
            }
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $setting
     * @return array<string, mixed>
     */
    private static function normalizeSetting(array $setting): array {
        $provider = strtolower(trim((string) ($setting['provider'] ?? 'wecom')));
        if (!in_array($provider, self::PROVIDERS, true)) {
            $provider = 'wecom';
        }

        $id = trim((string) ($setting['id'] ?? ''));
        if ($id === '') {
            $id = self::generateId();
        }

        $name = trim((string) ($setting['name'] ?? ''));
        if ($name === '') {
            $name = self::providerLabelFor($provider);
        }

        $normalized = [
            'id'                 => $id,
            'name'               => $name,
            'provider'           => $provider,
            'enabled'            => !empty($setting['enabled']),
            'root_department_id' => trim((string) ($setting['root_department_id'] ?? '')),
        ];

        foreach (self::CREDENTIAL_FIELDS as $field) {
            $normalized[$field] = trim((string) ($setting[$field] ?? ''));
        }

        return $normalized;
    }

    /**
     * Keep at most one enabled setting (the first enabled one).
     *
     * @param array<int, array<string, mixed>> $settings
     * @return array<int, array<string, mixed>>
     */
    private static function ensureSingleEnabled(array $settings): array {
        $seenEnabled = false;
        foreach ($settings as $i => $setting) {
            if (!empty($setting['enabled'])) {
                if ($seenEnabled) {
                    $settings[$i]['enabled'] = false;
                } else {
                    $seenEnabled = true;
                }
            }
        }
        return array_values($settings);
    }

    private static function generateId(): string {
        try {
            return 'set_' . bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            return 'set_' . substr(md5(uniqid('', true)), 0, 12);
        }
    }

    public static function providerLabelFor(string $provider): string {
        switch (strtolower(trim($provider))) {
            case 'feishu':
                return LanguageManager::t('Feishu');
            case 'dingtalk':
                return LanguageManager::t('DingTalk');
            case 'wecom':
            default:
                return LanguageManager::t('WeCom');
        }
    }

    /* ===================== Reading settings ===================== */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getSettings(): array {
        $config = self::load();
        return is_array($config['settings'] ?? null) ? $config['settings'] : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getActiveSetting(): ?array {
        foreach (self::getSettings() as $setting) {
            if (!empty($setting['enabled'])) {
                return $setting;
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findSetting(string $id): ?array {
        foreach (self::getSettings() as $setting) {
            if ((string) $setting['id'] === $id) {
                return $setting;
            }
        }
        return null;
    }

    public static function getActiveSettingName(): string {
        $active = self::getActiveSetting();
        return $active !== null ? (string) $active['name'] : '';
    }

    /* ===================== Writing settings ===================== */

    /**
     * Create or update a setting. A blank secret field keeps the current value.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed> normalized setting
     */
    public static function saveSetting(array $input): array {
        $config = self::load();
        $settings = $config['settings'];

        $id = trim((string) ($input['id'] ?? ''));
        $existing = null;
        if ($id !== '') {
            foreach ($settings as $setting) {
                if ((string) $setting['id'] === $id) {
                    $existing = $setting;
                    break;
                }
            }
        }
        if ($id === '' || $existing === null) {
            $id = self::generateId();
        }

        $merged = [
            'id'                 => $id,
            'name'               => $input['name'] ?? ($existing['name'] ?? ''),
            'provider'           => $input['provider'] ?? ($existing['provider'] ?? 'wecom'),
            'enabled'            => !empty($input['enabled']),
            'root_department_id' => $input['root_department_id'] ?? ($existing['root_department_id'] ?? ''),
        ];

        foreach (self::CREDENTIAL_FIELDS as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value === '' && in_array($field, self::SECRET_FIELDS, true) && $existing !== null) {
                // Blank secret field: keep the current value.
                $value = (string) ($existing[$field] ?? '');
            }
            $merged[$field] = $value;
        }

        $setting = self::normalizeSetting($merged);

        $replaced = false;
        foreach ($settings as $i => $s) {
            if ((string) $s['id'] === $id) {
                $settings[$i] = $setting;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $settings[] = $setting;
        }

        if (!empty($setting['enabled'])) {
            foreach ($settings as $i => $s) {
                $settings[$i]['enabled'] = ((string) $s['id'] === $id);
            }
        }

        $config['settings'] = self::ensureSingleEnabled($settings);
        self::persist($config);

        return $setting;
    }

    public static function deleteSetting(string $id): bool {
        $config = self::load();
        $settings = $config['settings'];

        $found = false;
        $result = [];
        foreach ($settings as $setting) {
            if ((string) $setting['id'] === $id) {
                $found = true;
                continue;
            }
            $result[] = $setting;
        }

        if (!$found) {
            return false;
        }

        $config['settings'] = self::ensureSingleEnabled($result);
        self::persist($config);
        return true;
    }

    /**
     * Enable the given setting and disable the others.
     */
    public static function enableSetting(string $id): bool {
        $config = self::load();
        $settings = $config['settings'];

        $found = false;
        foreach ($settings as $i => $setting) {
            $isTarget = ((string) $setting['id'] === $id);
            $settings[$i]['enabled'] = $isTarget;
            if ($isTarget) {
                $found = true;
            }
        }

        if (!$found) {
            return false;
        }

        $config['settings'] = self::ensureSingleEnabled($settings);
        self::persist($config);
        return true;
    }

    public static function disableSetting(string $id): bool {
        $config = self::load();
        $settings = $config['settings'];

        $found = false;
        foreach ($settings as $i => $setting) {
            if ((string) $setting['id'] === $id) {
                $settings[$i]['enabled'] = false;
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

        $config['settings'] = $settings;
        self::persist($config);
        return true;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function persist(array $config): void {
        $path = self::getConfigPath();
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException(LanguageManager::t('Failed to encode config'));
        }
        if (@file_put_contents($path, $json) === false) {
            throw new \RuntimeException(\ZabbixModuleNotice::administrator() ? \ZabbixModuleNotice::storageText(dirname(__DIR__), [dirname($path)]) : LanguageManager::tf('Failed to write config file: %s', $path));
        }
        self::$config = null;
    }

    /* ===================== Runtime configuration ===================== */

    /**
     * Check that a setting has the required credentials.
     *
     * @param array<string, mixed> $setting
     */
    public static function isSettingConfigured(array $setting): bool {
        $provider = strtolower(trim((string) ($setting['provider'] ?? '')));
        if (!isset(self::PROVIDER_FIELDS[$provider])) {
            return false;
        }
        foreach (self::PROVIDER_FIELDS[$provider] as $field) {
            if (trim((string) ($setting[$field] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Convert a setting into the credential structure required by the platform client.
     *
     * @param array<string, mixed> $setting
     * @return array<string, mixed>
     */
    public static function providerCredentials(array $setting): array {
        $provider = strtolower(trim((string) ($setting['provider'] ?? 'wecom')));
        $root = (string) ($setting['root_department_id'] ?? '');

        switch ($provider) {
            case 'feishu':
                return [
                    'app_id'             => (string) ($setting['app_id'] ?? ''),
                    'app_secret'         => (string) ($setting['app_secret'] ?? ''),
                    'root_department_id' => $root !== '' ? $root : '0',
                ];
            case 'dingtalk':
                return [
                    'app_key'            => (string) ($setting['app_key'] ?? ''),
                    'app_secret'         => (string) ($setting['app_secret'] ?? ''),
                    'root_department_id' => $root !== '' ? $root : '1',
                ];
            case 'wecom':
            default:
                return [
                    'corp_id'            => (string) ($setting['corp_id'] ?? ''),
                    'corp_secret'        => (string) ($setting['corp_secret'] ?? ''),
                    'root_department_id' => $root !== '' ? $root : '1',
                ];
        }
    }

    /**
     * Runtime configuration for ImSyncService / ImProviderFactory (injects the provider and credentials of the enabled setting).
     *
     * @return array<string, mixed>
     */
    public static function getRuntimeConfig(): array {
        $config = self::load();
        $runtime = $config;
        unset($runtime['settings']);

        $active = self::getActiveSetting();
        if ($active === null) {
            $runtime['provider'] = '';
            return $runtime;
        }

        $provider = strtolower(trim((string) $active['provider']));
        if (!in_array($provider, self::PROVIDERS, true)) {
            $provider = 'wecom';
        }
        $runtime['provider'] = $provider;
        $runtime[$provider] = self::providerCredentials($active);
        return $runtime;
    }

    /* ===================== Legacy interface compatibility ===================== */

    public static function getProvider(): string {
        $active = self::getActiveSetting();
        $provider = $active !== null ? strtolower(trim((string) $active['provider'])) : 'wecom';
        return in_array($provider, self::PROVIDERS, true) ? $provider : 'wecom';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getProviderConfig(): array {
        $active = self::getActiveSetting();
        return $active !== null ? self::providerCredentials($active) : [];
    }

    public static function isConfigured(): bool {
        $active = self::getActiveSetting();
        return $active !== null && self::isSettingConfigured($active);
    }

    public static function getGroupPrefix(): string {
        return '';
    }

    public static function useFullPath(): bool {
        $config = self::load();
        return (bool) ($config['use_full_path'] ?? false);
    }

    public static function getPathSeparator(): string {
        $config = self::load();
        $sep = (string) ($config['path_separator'] ?? '/');
        return $sep !== '' ? $sep : '/';
    }

    public static function getUserMatchField(): string {
        return 'username';
    }

    public static function shouldRemoveOrphans(): bool {
        $config = self::load();
        return (bool) ($config['remove_orphans'] ?? true);
    }

    public static function shouldRemoveOrphanUsers(): bool {
        $config = self::load();
        return (bool) ($config['remove_orphan_users'] ?? true);
    }

    public static function shouldAutoCreateUsers(): bool {
        $config = self::load();
        return (bool) ($config['auto_create_users'] ?? true);
    }

    public static function shouldAutoUpdateUsers(): bool {
        $config = self::load();
        return (bool) ($config['auto_update_users'] ?? true);
    }

    public static function getDefaultRoleId(): string {
        $config = self::load();
        return trim((string) ($config['default_roleid'] ?? ''));
    }

    public static function getDefaultUserType(): int {
        $config = self::load();
        return (int) ($config['default_user_type'] ?? 2);
    }

    public static function getDefaultPassword(): string {
        $config = self::load();
        return (string) ($config['default_password'] ?? '');
    }

    public static function getRegistryPath(): string {
        return SyncRegistry::getRegistryPath();
    }

    public static function shouldLowercaseUsername(): bool {
        $config = self::load();
        return (bool) ($config['username_lowercase'] ?? true);
    }

    public static function getVerifySsl(): bool {
        $config = self::load();
        return (bool) ($config['verify_ssl'] ?? true);
    }

    public static function getProviderLabel(): string {
        if (self::getActiveSetting() === null) {
            return '';
        }
        return self::providerLabelFor(self::getProvider());
    }
}
