<?php
/** Vendored into each module so modules remain independently installable. PHP 7.3+. */
if (!class_exists('ZabbixModuleNotice', false)) {
    final class ZabbixModuleNotice {

        private const PORTUGUESE = [
            'If saving fails, change the directory ownership and check SELinux: ' => 'Se o salvamento falhar, altere o proprietário do diretório e verifique o SELinux: ',
            'Directory ownership reminder: ' => 'Lembrete sobre o proprietário do diretório: ',
            'Please change the directory ownership and check SELinux.' => 'Altere o proprietário do diretório e verifique o SELinux.',
            'Configuration file: ' => 'Arquivo de configuração: ',
            'Invalid JSON' => 'JSON inválido',
            'Invalid config.json' => 'config.json inválido',
            'Invalid provider' => 'Provedor inválido',
            'Invalid region' => 'Região inválida',
            'Account not found' => 'Conta não encontrada',
            'Name is required.' => 'O nome é obrigatório.',
            'Access key is required.' => 'A chave de acesso é obrigatória.',
            'Secret key is required.' => 'A chave secreta é obrigatória.',
            'Cannot lock config file' => 'Não foi possível bloquear o arquivo de configuração',
            'Failed to initialize branding storage. Review the web server error log for details.' => 'Falha ao inicializar o armazenamento da identidade visual. Consulte o log de erros do servidor web para mais detalhes.',
            'Failed to encode branding settings as JSON.' => 'Falha ao codificar as configurações de identidade visual como JSON.',
            'Please select dates' => 'Selecione as datas',
            'Help URL: only HTTP and HTTPS URLs are allowed.' => 'URL de ajuda: somente URLs HTTP e HTTPS são permitidas.',
            'Server name: enter a name, or choose Default or Hidden.' => 'Nome do servidor: informe um nome ou escolha Padrão ou Oculto.',
            'file too large.' => 'arquivo grande demais.',
            'upload was only partially completed.' => 'o upload foi concluído apenas parcialmente.',
            'no file was uploaded.' => 'nenhum arquivo foi enviado.',
            'missing temporary upload directory.' => 'diretório temporário de upload ausente.',
            'could not write file to disk.' => 'não foi possível gravar o arquivo em disco.',
            'upload blocked by a PHP extension.' => 'upload bloqueado por uma extensão do PHP.',
            'unknown upload error.' => 'erro de upload desconhecido.',
            'invalid upload payload.' => 'conteúdo de upload inválido.',
            'invalid file type. Allowed: PNG, JPG, GIF, ICO.' => 'tipo de arquivo inválido. Permitidos: PNG, JPG, GIF, ICO.',
            'file too large. Maximum: 2 MB.' => 'arquivo grande demais. Máximo: 2 MB.',
            'file content does not match the selected image format.' => 'o conteúdo do arquivo não corresponde ao formato de imagem selecionado.',
            'cpu_usage rules are required' => 'Uso de CPU requer pelo menos uma regra.',
            'memory_usage rules are required' => 'Uso de memória requer pelo menos uma regra.',
        ];

        public static function language(): string {
            $locale = '';
            try {
                if (class_exists('CWebUser')) {
                    $locale = (string) (\CWebUser::$data['lang'] ?? '');
                    if ($locale === '' && method_exists('CWebUser', 'get')) {
                        $locale = (string) \CWebUser::get('lang');
                    }
                }
            } catch (\Throwable $e) {}
            if ($locale === '' || strtolower($locale) === 'default') {
                try {
                    if (class_exists('CSettingsHelper')) {
                        $locale = method_exists('CSettingsHelper', 'getPublic')
                            ? (string) \CSettingsHelper::getPublic('default_lang')
                            : (string) \CSettingsHelper::get('default_lang');
                    } else {
                        $locale = (string) ($GLOBALS['ZBX_DEFAULT_LANG'] ?? 'en_US');
                    }
                } catch (\Throwable $e) { $locale = 'en_US'; }
            }
            $locale = trim($locale);
            // Supported languages: Brazilian Portuguese (pt*) and English (everything else).
            return preg_match('/^pt(?:[-_]|$)/i', $locale) ? 'pt_BR' : 'en_US';
        }
        public static function text(string $english): string {
            return self::language() === 'pt_BR' ? (self::PORTUGUESE[$english] ?? $english) : $english;
        }
        public static function escape(string $text): string {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        public static function administrator(): bool {
            try {
                return class_exists('CWebUser') && defined('USER_TYPE_SUPER_ADMIN')
                    && (method_exists('CWebUser', 'getType') ? \CWebUser::getType()
                        : (\CWebUser::$data['type'] ?? 0)) >= USER_TYPE_SUPER_ADMIN;
            } catch (\Throwable $e) { return false; }
        }
        public static function runtime(): array {
            $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
            $gid = function_exists('posix_getegid') ? posix_getegid() : null;
            if ($uid === null && is_readable('/proc/self/status')) {
                $status = (string) @file_get_contents('/proc/self/status');
                if (preg_match('/^Uid:\s+\d+\s+(\d+)/m', $status, $m)) { $uid = (int) $m[1]; }
                if (preg_match('/^Gid:\s+\d+\s+(\d+)/m', $status, $m)) { $gid = (int) $m[1]; }
            }
            $user = $uid !== null && function_exists('posix_getpwuid') ? posix_getpwuid($uid) : false;
            $group = $gid !== null && function_exists('posix_getgrgid') ? posix_getgrgid($gid) : false;
            // Minimal PHP installations may omit POSIX; resolve only the effective
            // process IDs through the public account databases, never the script owner.
            if (!$user && $uid !== null && is_readable('/etc/passwd')) {
                foreach (@file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $fields = explode(':', $line);
                    if (isset($fields[2]) && $fields[2] === (string) $uid) { $user = ['name' => $fields[0]]; break; }
                }
            }
            if (!$group && $gid !== null && is_readable('/etc/group')) {
                foreach (@file('/etc/group', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $fields = explode(':', $line);
                    if (isset($fields[2]) && $fields[2] === (string) $gid) { $group = ['name' => $fields[0]]; break; }
                }
            }
            return ['uid' => $uid, 'gid' => $gid,
                'user' => $user['name'] ?? ($uid === null ? '<PHP_USER>' : (string) $uid),
                'group' => $group['name'] ?? ($gid === null ? '<PHP_GROUP>' : (string) $gid)];
        }
        public static function ownershipCommand(string $module): string {
            $account = self::runtime();
            return 'chown -R -- '.escapeshellarg($account['user'].':'.$account['group'])
                .' '.escapeshellarg(realpath($module) ?: $module);
        }
        public static function saveHint(string $module): string {
            return self::text('If saving fails, change the directory ownership and check SELinux: ')
                .self::ownershipCommand($module).'; getenforce';
        }
        public static function reminder(string $module): string {
            // Save errors still need their hint when the ownership reminder is absent.
            $hint = '<span class="module-save-hint" hidden data-save-hint="'
                .self::escape(self::saveHint($module)).'"></span>';
            $account = self::runtime();
            clearstatcache(true, $module);
            $owner = @fileowner($module);
            $group = @filegroup($module);
            if (!is_dir($module) || $owner === false || $group === false
                    || $account['uid'] === null || $account['gid'] === null
                    || ($owner === $account['uid'] && $group === $account['gid'])) {
                return $hint;
            }
            return $hint.'<p class="module-ownership-reminder" role="note" style="margin:12px 0;overflow-wrap:anywhere">'
                .self::escape(self::text('Directory ownership reminder: '))
                .'<code>'.self::escape(self::ownershipCommand($module)).'</code></p>';
        }
        public static function storageText(string $module, array $targets): string {
            $commands = [];
            $account = self::runtime();
            foreach ($targets as $target) {
                $spec = is_array($target) ? $target : ['path' => $target];
                $path = $spec['path'];
                // Do not recommend recursive ownership changes to shared system directories.
                if ($path === '' || $path[0] !== '/' || in_array(rtrim($path, '/') ?: '/',
                    ['/', '/tmp', '/var/tmp', '/var', '/usr', '/usr/share', '/usr/share/zabbix', '/usr/share/zabbix/ui', '/etc', '/opt', '/home', '/root'], true)) {
                    continue;
                }
                $commands[] = 'chown '.(isset($spec['recursive']) && !$spec['recursive'] ? '' : '-R ')
                    .'-- '.escapeshellarg($account['user'].':'.$account['group']).' '.escapeshellarg($path);
            }
            return self::text('Please change the directory ownership and check SELinux.')
                ."\n".implode("\n", $commands ?: [self::ownershipCommand($module)])."\ngetenforce";
        }
        public static function failure(\Throwable $error, string $module, array $targets): string {
            return self::translate($error->getMessage())."\n".self::storageText($module, $targets);
        }
        public static function translate(string $message): string {
            return self::language() === 'pt_BR' ? (self::PORTUGUESE[$message] ?? $message) : $message;
        }
    }
}
