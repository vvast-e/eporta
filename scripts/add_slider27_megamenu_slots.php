<?php
// Разово добавляет в IBLOCK 27 (баннеры) enum-значения свойства PLACEMENT для двух промо-плиток
// мегаменю "Каталог" в шапке (megamenu_sale/megamenu_new) — см. local/admin_tools/eporta_banners/.
// Не создаёт элементов, только значения enum (дальше элементы заводятся через саму админку
// баннеров при первом сохранении). По аналогии с add_slider27_home_slots.php.
// Запуск: php scripts/add_slider27_megamenu_slots.php
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

$slots = [
    'megamenu_sale' => ['Мегаменю: промо-плитка 1', 1200],
    'megamenu_new' => ['Мегаменю: промо-плитка 2', 1210],
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
