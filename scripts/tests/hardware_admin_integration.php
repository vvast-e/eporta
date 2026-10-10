<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Интеграционная проверка админских функций фурнитуры (этап 4) на боевом окружении: создаёт ВРЕМЕННЫЙ тип
// «ZZ Тест-тип» и товары ZZ-HWA-*, проверяет админку типов, автоотбор табов главной (без фурнитуры) и
// популярные запросы с типом — и всё УДАЛЯЕТ. Запуск: /opt/php83/bin/php scripts/tests/hardware_admin_integration.php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_import/lib.php');
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_hardware_kinds/lib.php');
require($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/eporta_home_tabs_common.php');
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_quick_queries/lib.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/eporta_collections.php');

$failures = 0;
function check(string $name, bool $cond, string $info = ''): void {
    global $failures;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond || $info === '' ? '' : " — $info") . "\n";
    if (!$cond) $failures++;
}
function cleanup(): void {
    $r = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, '%PROPERTY_CML2_ARTICLE' => 'ZZ-HWA-'], false, false, ['ID']);
    while ($x = $r->Fetch()) CIBlockElement::Delete($x['ID']);
    $s = CIBlockSection::GetList([], ['IBLOCK_ID' => 19, 'NAME' => ['ZZ Тест-тип', 'ZZ Тест-тип 2']], false, ['ID']);
    while ($x = $s->Fetch()) CIBlockSection::Delete($x['ID']);
}

$collectionsBefore = array_column(eportaCollections(true), 'ID');
$kindsBefore = count(eportaHardwareKinds());
cleanup();
try {
    $err = null;
    $id = eportaHardwareKindsCreate('ZZ Тест-тип', $err);
    check('создание типа', (bool)$id, (string)$err);
    $dup = eportaHardwareKindsCreate(' zz тест-тип ', $err);
    check('дубль названия отклоняется', $dup === false && $err !== null, (string)$err);
    check('пустое название отклоняется', eportaHardwareKindsCreate('  ', $err) === false);

    $list = eportaHardwareKindsAdminList();
    check('тип в админском списке (12 штук)', count($list) === $kindsBefore + 1, 'в списке: ' . count($list));
    $mine = array_values(array_filter($list, function ($k) use ($id) { return (int)$k['ID'] === (int)$id; }))[0] ?? null;
    check('код сгенерирован, активен, в конце списка', $mine && $mine['CODE'] !== '' && $mine['ACTIVE'] === 'Y' && (int)$mine['SORT'] > 110, json_encode($mine, JSON_UNESCAPED_UNICODE));
    check('тип не попал в коллекции дверей', !in_array($id, array_column(eportaCollections(true), 'ID')));

    $ok = eportaHardwareKindsUpdate($id, 'ZZ Тест-тип 2', 555, false, $err);
    check('переименование/порядок/скрытие', $ok, (string)$err);
    $mine = array_values(array_filter(eportaHardwareKindsAdminList(), function ($k) use ($id) { return (int)$k['ID'] === (int)$id; }))[0];
    check('изменения сохранены, код прежний', $mine['NAME'] === 'ZZ Тест-тип 2' && (int)$mine['SORT'] === 555 && $mine['ACTIVE'] === 'N' && $mine['CODE'] === $mine['CODE'], json_encode($mine, JSON_UNESCAPED_UNICODE));
    check('скрытый тип не виден витрине', count(eportaHardwareKinds()) === $kindsBefore);
    check('чужой раздел (коллекция 184) через админку типов не меняется', eportaHardwareKindsUpdate(184, 'Взлом', 1, true, $err) === false);
    check('переименование в занятое имя отклоняется', eportaHardwareKindsUpdate($id, 'Ручки', 555, true, $err) === false);

    // Автоотбор табов главной: фурнитура с рейтингом 5 и без рейтинга не попадает, закреплённая — попадает.
    $base = ['category' => 'Фурнитура', 'price' => 900.0, 'discount' => 0, 'brand' => 'ZZ'];
    $r1 = eportaImportOneProduct(['article' => 'ZZ-HWA-1', 'model' => 'M1', 'name' => 'ZZ фурнитура хит', 'collection' => 'Ручки', 'rating' => 5] + $base);
    $r2 = eportaImportOneProduct(['article' => 'ZZ-HWA-2', 'model' => 'M2', 'name' => 'ZZ фурнитура новая', 'collection' => 'Ручки', 'rating' => 0] + $base);
    check('импорт тестовой фурнитуры', $r1['status'] === 'created' && $r2['status'] === 'created', $r1['message'] . $r2['message']);
    $hw = [];
    $res = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, '%PROPERTY_CML2_ARTICLE' => 'ZZ-HWA-'], false, false, ['ID']);
    while ($x = $res->Fetch()) $hw[] = (int)$x['ID'];
    foreach (['hit', 'new'] as $tab) {
        $ids = eportaHomeTabsResolveIds($tab, ['limit' => 60, 'pinned' => []]);
        check("таб $tab: автоотбор без фурнитуры", count(array_intersect($ids, $hw)) === 0 && count($ids) > 0, 'найдено ' . count($ids));
    }
    $pinned = eportaHomeTabsResolveIds('hit', ['limit' => 8, 'pinned' => [$hw[0]]]);
    check('таб hit: закреплённая фурнитура показывается', in_array($hw[0], $pinned, true));
    check('таб sale: автоотбор не сломан (без ошибки)', is_array(eportaHomeTabsResolveIds('sale', ['limit' => 8, 'pinned' => []])));

    // Популярные запросы: тип в вариантах и сохраняется только для фурнитуры.
    $opts = eportaQuickQueriesFilterOptions();
    check('запросы: типы в вариантах (Ручки)', ($opts['type']['ruchki'] ?? '') === 'Ручки');
    check('запросы: скрытого типа нет', !in_array('ZZ Тест-тип 2', $opts['type'], true));
    $allowed = eportaQuickQueriesAllowedFromOptions($opts);
    $q = eportaQuickQueryNormalizeOne(['label' => 'Ручки', 'filters' => ['category' => 'hardware', 'type' => 'ruchki']], $allowed);
    check('запросы: URL с типом', eportaQuickQueryUrl($q) === '/catalog/?category=hardware&type=ruchki', eportaQuickQueryUrl($q));
} finally {
    cleanup();
}
$left = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, '%PROPERTY_CML2_ARTICLE' => 'ZZ-HWA-'], false, false, ['ID'])->SelectedRowsCount();
check('временные товары удалены', $left === 0, "осталось $left");
eportaHardwareAllSections(false, true);
check('временный тип удалён', count(eportaHardwareKindsAdminList()) === $kindsBefore);
check('список коллекций дверей не изменился', array_column(eportaCollections(true), 'ID') === $collectionsBefore);
echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
