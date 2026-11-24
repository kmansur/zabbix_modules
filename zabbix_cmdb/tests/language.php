<?php
// Standalone language regression tests. Does not modify any Zabbix settings or rules.
require_once dirname(__DIR__).'/lib/LanguageManager.php';
require_once dirname(__DIR__).'/lib/ItemConfig.php';
use Modules\ZabbixCmdb\Lib\LanguageManager as LM;
use Modules\ZabbixCmdb\Lib\ItemConfig;
class CWebUser { public static $data = []; }
class CSettingsHelper {
    public static $language = 'en_US';
    public static function get($key) { return self::$language; }
}
function checkLanguage($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
foreach ([['pt_BR','en_US','pt_BR'], ['en_US','pt_BR','en_US'], ['default','pt_BR','pt_BR'],
        ['default','en_US','en_US'], ['en-GB','pt_BR','en_US'], ['pt-BR','en_US','pt_BR'],
        ['pt_PT','en_US','pt_BR'], ['zh_CN','en_US','en_US'], ['ru_RU','pt_BR','en_US'],
        ['','pt_BR','pt_BR'], ['fr_FR','pt_BR','en_US']] as $case) {
    CWebUser::$data['lang'] = $case[0]; CSettingsHelper::$language = $case[1]; LM::resetLanguage();
    checkLanguage(LM::detectLanguage() === $case[2], 'User/system language: '.implode(' / ', $case));
}
$property = new ReflectionProperty(LM::class, 'translations');
if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
$translations = $property->getValue();
checkLanguage(array_keys($translations) === ['en_US', 'pt_BR'], 'Only English and Brazilian Portuguese are bundled');
checkLanguage(array_diff(array_keys($translations['en_US']), array_keys($translations['pt_BR'])) === [],
    'Every English key has a Portuguese translation');
foreach ($translations['en_US'] as $key => $english) {
    checkLanguage(!preg_match('/\p{Han}/u', $english.$translations['pt_BR'][$key]), 'No Chinese text: '.$key);
    preg_match_all('/%\d*\$?[dsf]/', $english, $enPlaceholders);
    preg_match_all('/%\d*\$?[dsf]/', $translations['pt_BR'][$key], $ptPlaceholders);
    if ($enPlaceholders[0] !== $ptPlaceholders[0]) {
        throw new RuntimeException('Placeholder mismatch: '.$key);
    }
}
$labels = [
    'en_US' => ['Available', 'Active Hosts', 'Item Configuration'],
    'pt_BR' => ['Disponível', 'Hosts ativos', 'Configuração de itens']
];
foreach ($labels as $language => [$available, $active, $configuration]) {
    CWebUser::$data['lang'] = $language; LM::resetLanguage();
    checkLanguage(LM::t('Available') === $available, 'Availability: '.$language);
    checkLanguage(LM::t('Active Hosts') === $active, 'Active host label: '.$language);
    checkLanguage(LM::t('Item Configuration') === $configuration, 'Configuration title: '.$language);
    checkLanguage(strpos(LM::tf('Showing %d to %d of %d hosts', 1, 10, 20), '20') !== false, 'Pagination placeholders: '.$language);
    try { ItemConfig::validate([], ['cpu_count']); throw new LogicException('Invalid configuration accepted'); }
    catch (InvalidArgumentException $e) {
        checkLanguage(!preg_match('/[\p{Han}\p{Cyrillic}]/u', $e->getMessage()), 'Validation error language: '.$language);
    }
}
echo "All CMDB language tests passed.\n";
