<?php
// Свойство SALE (список Да/Нет) для IBLOCK 19 — флаг "товар участвует в распродаже".
// Задача 09.09.2026 (заказчик): раньше распродажа определялась косвенно через
// ">PROPERTY_DISCOUNT" > 0 (catalog/index.php $eportaScopeFilter при ?sale=1) — заказчик
// попросил явное поле, отдельное от скидки. Бейдж на карточках НЕ добавляется (решение
// заказчика 09.09.2026) — только фильтр каталога и отбор в табах главной (см.
// local/admin_tools/eporta_home_tabs/). По умолчанию у существующих товаров значение не
// проставлено — трактуется как "N" (нет в распродаже), чтобы не включить разом все 800+
// позиций при появлении свойства.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;

$res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'SALE']);
if ($res->Fetch()) {
    echo "Свойство SALE уже существует, пропускаю создание\n";
    exit;
}

$propObj = new CIBlockProperty;
$propId = $propObj->Add([
    'IBLOCK_ID' => $IBLOCK_ID,
    'NAME' => 'Распродажа',
    'CODE' => 'SALE',
    'PROPERTY_TYPE' => 'L',
    'LIST_TYPE' => 'C',
    'ROW_COUNT' => 1,
    'COL_COUNT' => 30,
    'MULTIPLE' => 'N',
    'IS_REQUIRED' => 'N',
    'SORT' => 530,
]);
if (!$propId) {
    die("Ошибка создания свойства SALE: {$propObj->LAST_ERROR}\n");
}
echo "Свойство SALE создано, ID=$propId\n";

$enumObj = new CIBlockPropertyEnum;
$enumValues = [
    ['VALUE' => 'Да', 'XML_ID' => 'Y', 'SORT' => 100],
    ['VALUE' => 'Нет', 'XML_ID' => 'N', 'DEF' => 'Y', 'SORT' => 200],
];
foreach ($enumValues as $e) {
    $enumId = $enumObj->Add(array_merge(['PROPERTY_ID' => $propId], $e));
    echo "  enum {$e['XML_ID']} -> ID=$enumId\n";
}

echo "Готово.\n";
