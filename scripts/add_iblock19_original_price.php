<?php
// Свойство ORIGINAL_PRICE (число) для IBLOCK 19 — исходная цена товара ДО скидки (колонка «Цена»
// выгрузки 1С). Задача 08.10.2026: зачёркнутая цена на витрине берётся из него, а цена со скидкой
// считается от него и лежит в BASE. Только консоль. Запуск: /opt/php83/bin/php scripts/add_iblock19_original_price.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;

if (CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'ORIGINAL_PRICE'])->Fetch()) {
    echo "Свойство ORIGINAL_PRICE уже существует, пропускаю создание\n";
    exit;
}

$propObj = new CIBlockProperty;
$propId = $propObj->Add([
    'IBLOCK_ID' => $IBLOCK_ID,
    'NAME' => 'Исходная цена (до скидки)',
    'CODE' => 'ORIGINAL_PRICE',
    'PROPERTY_TYPE' => 'N',
    'MULTIPLE' => 'N',
    'IS_REQUIRED' => 'N',
    'SORT' => 540,
]);
if (!$propId) {
    die("Ошибка создания свойства ORIGINAL_PRICE: {$propObj->LAST_ERROR}\n");
}
echo "Свойство ORIGINAL_PRICE создано, ID=$propId\n";
