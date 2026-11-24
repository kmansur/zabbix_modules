<?php

namespace Modules\ZabbixReports\Lib;

require_once __DIR__ . '/ReportViewHelper.php';
require_once __DIR__ . '/LanguageManager.php';

/**
 * PDF / HTML export generator
 * 
 * Reuses ReportViewHelper's CTag components to build the export content,
 * avoiding duplicated HTML concatenation and staying identical to the page view.
 */
class PdfGenerator {
    
    private $data;
    private $title;
    
    public function __construct($title = 'Zabbix Report') {
        $this->title = $title;
    }
    
    public function setData($data) {
        $this->data = $data;
    }
    
    public function generate() {
        if (class_exists('TCPDF')) {
            return $this->generateTCPDF();
        }

        // FPDF's built-in Arial font cannot display UTF-8 accented (pt_BR) text.
        if (class_exists('FPDF') && LanguageManager::getCurrentLanguage() !== 'pt_BR') {
            return $this->generateFPDF();
        }

        return $this->generateHTML();
    }
    
    private function generateTCPDF() {
        $pdf = new \TCPDF();
        $pdf->SetCreator('Zabbix Reports');
        $pdf->SetAuthor('Zabbix');
        $pdf->SetTitle($this->title);
        $pdf->SetSubject('Zabbix Report');
        
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        if (LanguageManager::getCurrentLanguage() === 'pt_BR') {
            $pdf->SetFont('dejavusans', '', 10);
        }
        
        $pdf->AddPage();
        
        $html = $this->generateHTML();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        return $pdf->Output('', 'S');
    }
    
    private function generateFPDF() {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $this->title, 0, 1, 'C');
        $pdf->Ln(10);
        
        $pdf->SetFont('Arial', '', 12);
        
        // Add basic content
        if (isset($this->data['problemCount'])) {
            $pdf->Cell(0, 8, LanguageManager::t('Problem Count') . ': ' . $this->data['problemCount'], 0, 1);
        }
        if (isset($this->data['resolvedCount'])) {
            $pdf->Cell(0, 8, LanguageManager::t('Resolved Count') . ': ' . $this->data['resolvedCount'], 0, 1);
        }
        
        $pdf->Ln(5);
        
        if (!empty($this->data['topHosts'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, LanguageManager::t('Top Problem Hosts') . ':', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            
            foreach ($this->data['topHosts'] as $host => $count) {
                $pdf->Cell(0, 6, "  $host: $count " . LanguageManager::t('problems'), 0, 1);
            }
        }
        
        return $pdf->Output('', 'S');
    }

    /**
     * Map export data keys to view data keys so ReportViewHelper can be reused
     */
    private function normalizeData(): array {
        $d = $this->data;
        return [
            'problem_count'      => $d['problemCount']  ?? $d['problem_count']     ?? 0,
            'resolved_count'     => $d['resolvedCount'] ?? $d['resolved_count']    ?? 0,
            'alert_info'         => $d['alertInfo']     ?? $d['alert_info']        ?? [],
            'hosts_by_group'     => $d['hostsByGroup']  ?? $d['hosts_by_group']    ?? [],
            'top_problem_hosts'  => $d['topHosts']      ?? $d['top_problem_hosts'] ?? [],
            'top_cpu_hosts'      => $d['topCpuHosts']   ?? $d['top_cpu_hosts']     ?? [],
            'top_mem_hosts'      => $d['topMemHosts']   ?? $d['top_mem_hosts']     ?? [],
            'cpu_total'          => $d['cpuTotal']      ?? $d['cpu_total']         ?? [],
            'mem_total'          => $d['memTotal']      ?? $d['mem_total']         ?? [],
        ];
    }
    
    /**
     * Generate a standalone HTML document — reusing ReportViewHelper's CTag components
     * Shares the same styles and DOM structure as the page view
     */
    private function generateHTML() {
        $viewData = $this->normalizeData();

        // ===== HTML document head =====
        $language = str_replace('_', '-', LanguageManager::getCurrentLanguage());
        $html = '<!DOCTYPE html><html lang="' . $language . '"><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>' . htmlspecialchars($this->title) . '</title>';

        // Reuse the ReportViewHelper common styles
        $html .= ReportViewHelper::getStyleTag()->toString();

        // Add the basic body styles needed by a standalone document
        $html .= '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans SC",sans-serif;'
            . 'background:var(--rpt-bg);color:var(--rpt-text);padding:30px;margin:0}'
            . '.rpt-container{max-width:1100px}'
            . '</style>';

        $html .= '</head><body>';

        // ===== Build the content container (CTag approach, consistent with the view page) =====
        $container = (new \CDiv())->addClass('rpt-container');

        // Page title
        $title = (new \CTag('h1', true))
            ->addClass('rpt-title')
            ->addItem((new \CSpan("\u{1F4CA}"))->addClass('rpt-title-icon'))
            ->addItem($this->title);
        $container->addItem(
            (new \CDiv())->addClass('rpt-header')->addItem($title)
        );

        // Summary statistics cards
        $container->addItem(ReportViewHelper::buildStatsGrid($viewData));

        // Top CPU / Memory in two columns
        $container->addItem(ReportViewHelper::buildTopResourceSection($viewData));

        // Severity radar + host group problem distribution, two-column charts
        $container->addItem(ReportViewHelper::buildChartsRow($viewData['alert_info'], $viewData['hosts_by_group']));

        // Problem host tags
        $container->addItem(ReportViewHelper::buildTopProblemHosts($viewData['top_problem_hosts']));

        // Alarm information
        $container->addItem(ReportViewHelper::buildAlertSection($viewData['alert_info']));

        // Host group information
        $container->addItem(ReportViewHelper::buildHostGroupSection($viewData['hosts_by_group']));

        // Footer
        $footer = (new \CDiv())->addClass('rpt-footer');
        $footer->addItem(
            LanguageManager::t('Generated by Zabbix Reports Module') . ' - ' . date('Y-m-d H:i:s') . ' | '
        );
        $footer->addItem(
            (new \CTag('a', true, 'https://github.com/X-Mars/zabbix_modules'))
                ->setAttribute('href', 'https://github.com/X-Mars/zabbix_modules')
                ->setAttribute('target', '_blank')
                ->setAttribute('style', 'color:var(--rpt-primary);text-decoration:none;')
        );
        $container->addItem($footer);

        $html .= $container->toString();

        // JS enhancements (search, sorting, number animation)
        $html .= ReportViewHelper::getScriptTag();

        $html .= '</body></html>';
        
        return $html;
    }
}
