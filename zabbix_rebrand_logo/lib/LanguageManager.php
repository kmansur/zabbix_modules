<?php
namespace Modules\ZabbixRebrandLogo\Lib;
require_once __DIR__.'/ModuleNotice.php';
class LanguageManager {

    private const PORTUGUESE = [
    	'Default is the Zabbix server name, which is not set (ZBX_SERVER_NAME in Docker, $ZBX_SERVER_NAME in zabbix.conf.php).' => 'O padrão é o nome do servidor Zabbix, que não está definido (ZBX_SERVER_NAME no Docker, $ZBX_SERVER_NAME no zabbix.conf.php).',
    	'Zabbix Rebrand Logo' => 'Logo personalizado do Zabbix',
    	'Login page logo' => 'Logo da página de login',
    	'Sidebar logo' => 'Logo da barra lateral',
    	'Compact sidebar icon' => 'Ícone da barra lateral recolhida',
    	'Browser favicon' => 'Favicon do navegador',
    	'Remove current logo' => 'Remover logo atual',
    	'Remove current favicon' => 'Remover favicon atual',
    	'Recommended: 120 x 30 pixels. Formats: PNG, JPG, GIF.' => 'Recomendado: 120 x 30 pixels. Formatos: PNG, JPG, GIF.',
    	'Recommended: 91 x 24 pixels. Formats: PNG, JPG, GIF.' => 'Recomendado: 91 x 24 pixels. Formatos: PNG, JPG, GIF.',
    	'Recommended: 24 x 24 pixels. Formats: PNG, JPG, GIF, ICO.' => 'Recomendado: 24 x 24 pixels. Formatos: PNG, JPG, GIF, ICO.',
    	'Saved to assets/logos/favicon.ico. Requires a one-time symlink from /usr/share/zabbix/favicon.ico to that file — see the module README. Recommended: 32 x 32 pixels. Formats: ICO, PNG, GIF, JPG.' => 'Salvo em assets/logos/favicon.ico. Requer um link simbólico único de /usr/share/zabbix/favicon.ico para esse arquivo — consulte o README do módulo. Recomendado: 32 x 32 pixels. Formatos: ICO, PNG, GIF, JPG.',
    	'Default' => 'Padrão',
    	'Custom' => 'Personalizado',
    	'Hidden' => 'Oculto',
    	'e.g. Production monitoring' => 'ex.: Monitoramento de produção',
    	'Shown under the sidebar logo and before the page title in the browser tab.' => 'Exibido abaixo do logo da barra lateral e antes do título da página na aba do navegador.',
    	'The sign-in page always shows the default, because Zabbix loads modules only after sign-in.' => 'A página de login sempre exibe o padrão, porque o Zabbix só carrega módulos após o login.',
    	'Server name' => 'Nome do servidor',
    	'Footer text' => 'Texto do rodapé',
    	'e.g. My Company' => 'ex.: Minha Empresa',
    	'Help URL' => 'URL de ajuda',
    	'e.g. https://github.com/X-Mars/zabbix_modules' => 'ex.: https://github.com/X-Mars/zabbix_modules',
    	'Logo settings' => 'Configurações de logo',
    	'General settings' => 'Configurações gerais',
    	'Update' => 'Atualizar',
    	'Invalid input.' => 'Entrada inválida.',
    	'Cannot update branding.' => 'Não foi possível atualizar a identidade visual.',
    	'Branding updated successfully. Clear your browser cache to see the changes.' => 'Identidade visual atualizada com sucesso. Limpe o cache do navegador para ver as alterações.',
    	'Branding update failed.' => 'Falha ao atualizar a identidade visual.',
    	'Check write access for local/conf and review the web server error log.' => 'Verifique a permissão de escrita em local/conf e consulte o log de erros do servidor web.',
    	'Preview' => 'Pré-visualização',
    	'Image scale' => 'Escala da imagem',
    	'Image scaled for upload.' => 'Imagem redimensionada para upload.',
    	'Crop image' => 'Recortar imagem',
    	'Apply crop' => 'Aplicar recorte',
    	'Zoom' => 'Zoom',
    	'Horizontal position' => 'Posição horizontal',
    	'Vertical position' => 'Posição vertical',
    	'Unable to load image.' => 'Não foi possível carregar a imagem.',
    	'Crop applied.' => 'Recorte aplicado.',
    	'Logo size (4:1, %s)' => 'Tamanho do logo (4:1, %s)',
    	'Install the local login view described in the module README to enable custom dimensions.' => 'Instale a view de login local descrita no README do módulo para habilitar dimensões personalizadas.',
    	'The cropped image exceeds the upload limit.' => 'A imagem recortada excede o limite de upload.',
    	'Image resized for upload.' => 'Imagem reduzida para upload.',
    	'Preparing image for upload…' => 'Preparando imagem para upload…',
    	'Check the selected images and try again.' => 'Verifique as imagens selecionadas e tente novamente.',
    	'e.g. https://example.com/help' => 'ex.: https://example.com/help',
    ];

    public static function t(string $text): string {
        $language = \ZabbixModuleNotice::language();
        if ($language === 'pt_BR') {
            return self::PORTUGUESE[$text] ?? $text;
        }

        return $text;
    }
}
