<?php
// Разово добавляет в IBLOCK 27 (баннеры) enum-значения свойства PLACEMENT для слотов
// плиток главной "Каталог по категориям" и "Коллекции фабрики" — см. local/admin_tools/
// eporta_banners/. Не создаёт элементов, только значения enum (дальше элементы заводятся
// через саму админку баннеров). Запуск: php scripts/add_slider27_home_slots.php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 27;

$propRes = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'PLACEMENT']);
$prop = $propRes->Fetch();
if (!$prop) {
    die("Свойство PLACEMENT не найдено в IBLOCK $IBLOCK_ID\n");
}
$propId = $prop['ID'];

// XML_ID => [значение, сортировка]. cat_* — 6 плиток "Каталог по категориям" (index.php:137-199),
// coll_* — 6 плиток "Коллекции фабрики" (index.php:201-238), ключи совпадают с $eportaHomeCollections["CODE"].
$slots = [
    'cat_mkd' => ['Категория: Межкомнатные', 1000],
    'cat_hidden' => ['Категория: Скрытые', 1010],
    'cat_sliding' => ['Категория: Раздвижные', 1020],
    'cat_entrance' => ['Категория: Входные', 1030],
    'cat_arch' => ['Категория: Арки и порталы', 1040],
    'cat_hardware' => ['Категория: Фурнитура', 1050],
    'coll_dorsum' => ['Коллекция: Dorsum', 1100],
    'coll_vilis' => ['Коллекция: Vilis', 1110],
    'coll_actus' => ['Коллекция: Actus', 1120],
    'coll_vitrum' => ['Коллекция: Vitrum', 1130],
    'coll_tabula' => ['Коллекция: Tabula', 1140],
    'coll_lacuna' => ['Коллекция: Lacuna', 1150],
];

$existingRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'PLACEMENT']);
$existingXmlIds = [];
while ($e = $existingRes->Fetch()) {
    $existingXmlIds[$e['XML_ID']] = true;
}

$enumObj = new CIBlockPropertyEnum;
foreach ($slots as $xmlId => [$value, $sort]) {
    if (isset($existingXmlIds[$xmlId])) {
        echo "Слот $xmlId уже существует, пропускаю\n";
        continue;
    }
    $enumId = $enumObj->Add([
        'PROPERTY_ID' => $propId,
        'VALUE' => $value,
        'XML_ID' => $xmlId,
        'SORT' => $sort,
    ]);
    echo $enumId ? "Слот $xmlId создан, enum ID=$enumId\n" : "Ошибка создания $xmlId: {$enumObj->LAST_ERROR}\n";
}

echo "Готово.\n";
