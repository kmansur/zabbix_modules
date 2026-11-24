<?php

namespace Modules\ZabbixCmdb\Lib;

/**
 * View rendering layer for Zabbix 7.0
 * Provides a unified page rendering interface
 */
class ViewRenderer {
    
    /**
     * Create and display the page (Zabbix 7.0 uses CHtmlPage)
     * 
     * @param string $title page title
     * @param CTag $styleTag style tag (optional)
     * @param CDiv $content content
     */
    public static function render($title, $styleTag, $content) {
        // Zabbix 7.0 uses CHtmlPage
        if (class_exists('CHtmlPage')) {
            $page = new \CHtmlPage();
            if ($title) {
                $page->setTitle($title);
            }
            if ($styleTag) {
                $page->addItem($styleTag);
            }
            $page->addItem($content);
            $page->show();
            return;
        }
        
        // Fallback: output HTML directly
        echo '<html><head><title>' . htmlspecialchars($title) . '</title></head><body>';
        if ($styleTag) {
            echo $styleTag->toString();
        }
        echo $content->toString();
        echo '</body></html>';
    }
}
