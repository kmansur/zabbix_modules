<?php

namespace Modules\ZabbixGraphTrees\Lib;

/**
 * Zabbix version detection and compatibility helper
 * Detects the Zabbix version automatically and provides a unified API
 */
class ZabbixVersion {
    
    private static $version = null;
    private static $isVersion6 = null;
    private static $isVersion7 = null;
    
    /**
     * Detect the Zabbix version
     */
    public static function detect(): string {
        if (self::$version !== null) {
            return self::$version;
        }
        
        // Method 1: check whether the namespace exists
        if (class_exists('Zabbix\Core\CModule')) {
            self::$version = '7.0';
            self::$isVersion7 = true;
            self::$isVersion6 = false;
            return self::$version;
        }
        
        if (class_exists('Core\CModule')) {
            self::$version = '6.0';
            self::$isVersion6 = true;
            self::$isVersion7 = false;
            return self::$version;
        }
        
        // Method 2: check constants
        if (defined('ZABBIX_VERSION')) {
            $version = ZABBIX_VERSION;
            if (version_compare($version, '7.0', '>=')) {
                self::$version = '7.0';
                self::$isVersion7 = true;
                self::$isVersion6 = false;
            } else {
                self::$version = '6.0';
                self::$isVersion6 = true;
                self::$isVersion7 = false;
            }
            return self::$version;
        }
        
        // Assume 6.0 by default
        self::$version = '6.0';
        self::$isVersion6 = true;
        self::$isVersion7 = false;
        return self::$version;
    }
    
    /**
     * Whether this is Zabbix 6.0
     */
    public static function isVersion6(): bool {
        if (self::$isVersion6 === null) {
            self::detect();
        }
        return self::$isVersion6;
    }
    
    /**
     * Whether this is Zabbix 7.0+
     */
    public static function isVersion7(): bool {
        if (self::$isVersion7 === null) {
            self::detect();
        }
        return self::$isVersion7;
    }
    
    /**
     * Get the CModule base class name
     */
    public static function getModuleBaseClass(): string {
        if (self::isVersion7()) {
            return '\Zabbix\Core\CModule';
        }
        return '\Core\CModule';
    }
    
    /**
     * Get the name of the method that disables CSRF validation
     */
    public static function getDisableCsrfMethod(): string {
        if (self::isVersion7()) {
            return 'disableCsrfValidation';
        }
        return 'disableSIDvalidation';
    }
}
