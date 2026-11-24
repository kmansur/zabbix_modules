<?php

namespace Modules\ZabbixIm\Lib;

/**
 * Write operations go through the Zabbix frontend API wrapper (so transactions are committed correctly),
 * and the real error message is retrieved when it returns false.
 */
class ZabbixApiHelper {

    public static function canCreateUsers(): bool {
        if (defined('USER_TYPE_SUPER_ADMIN') && class_exists('\CWebUser')) {
            return \CWebUser::getType() >= USER_TYPE_SUPER_ADMIN;
        }
        return false;
    }

    /**
     * Create users through the frontend wrapper (transactions are managed by the wrapper).
     *
     * @return array{ok:bool,data:mixed,error:string}
     */
    public static function createUser(array $userParams): array {
        return self::callWrapper('User', 'create', $userParams);
    }

    /**
     * @return array{ok:bool,data:mixed,error:string}
     */
    public static function callWrapper(string $service, string $method, array $params): array {
        if (!class_exists('\API') || !method_exists('\API', $service)) {
            return ['ok' => false, 'data' => null, 'error' => 'Zabbix API class not available'];
        }

        self::clearFrontendMessages();

        try {
            $api = call_user_func(['\API', $service]);
            if (!is_object($api)) {
                return ['ok' => false, 'data' => null, 'error' => 'API service not available: ' . $service];
            }

            // Note: create/update/delete etc. of CFrontendApiWrapper are implemented through the magic method __call,
            // so method_exists() cannot be used; it would wrongly report the method as missing.
            $result = $api->$method($params);
        } catch (\Throwable $e) {
            $detail = self::collectFrontendMessages();
            $message = $e->getMessage();
            if ($detail !== '') {
                $message = $message === '' ? $detail : ($message . '; ' . $detail);
            }
            self::logCall($service, $method, $params, 'exception', $message);
            return ['ok' => false, 'data' => null, 'error' => $message];
        }

        if ($result === false) {
            $error = self::resolveFalseError($service . '.' . $method);
            self::logCall($service, $method, $params, 'false', $error);
            return [
                'ok'    => false,
                'data'  => false,
                'error' => $error,
            ];
        }

        self::logCall($service, $method, $params, 'ok', '', $result);
        return ['ok' => true, 'data' => $result, 'error' => ''];
    }

    /**
     * @param mixed $result
     */
    private static function logCall(string $service, string $method, array $params, string $outcome, string $error = '', $result = null): void {
        $safeParams = $params;
        if (isset($safeParams['passwd'])) {
            $safeParams['passwd'] = '***';
        }

        $line = '[Zabbix IM API] ' . $service . '.' . $method
            . ' outcome=' . $outcome
            . ' super_admin=' . (self::canCreateUsers() ? '1' : '0')
            . ' params=' . json_encode($safeParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($outcome === 'ok') {
            $line .= ' result=' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $line .= ' error=' . $error;
        }

        error_log($line);
    }

    private static function resolveFalseError(string $action): string {
        $detail = self::collectFrontendMessages();
        if ($detail !== '') {
            return $detail;
        }

        if (!self::canCreateUsers()) {
            return LanguageManager::t('Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.');
        }

        return LanguageManager::t('Zabbix API call failed') . ': ' . $action
            . ' (' . LanguageManager::t('check Zabbix PHP log or Authentication password policy') . ')';
    }

    /**
     * Retrieve and clear the Zabbix frontend error messages (written by error() when the API returns false).
     */
    private static function collectFrontendMessages(): string {
        $messages = [];

        if (function_exists('get_and_clear_messages')) {
            foreach ((array) get_and_clear_messages() as $entry) {
                if (is_array($entry) && isset($entry['message'])) {
                    $messages[] = (string) $entry['message'];
                } elseif (is_string($entry)) {
                    $messages[] = $entry;
                }
            }
        } elseif (class_exists('\CMessageHelper')) {
            foreach (\CMessageHelper::getMessages() as $entry) {
                if (is_array($entry) && isset($entry['message'])) {
                    $messages[] = (string) $entry['message'];
                }
            }
            \CMessageHelper::clear();
        }

        $messages = array_values(array_unique(array_filter(array_map('trim', $messages), static function ($m) {
            return $m !== '';
        })));

        return implode('; ', $messages);
    }

    private static function clearFrontendMessages(): void {
        if (function_exists('get_and_clear_messages')) {
            get_and_clear_messages();
        } elseif (class_exists('\CMessageHelper')) {
            \CMessageHelper::clear();
        }
    }
}
