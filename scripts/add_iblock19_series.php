<?php
// Свойство SERIES (список) для IBLOCK 19 — «Серия» фурнитуры (колонка «Серия» выгрузки, напр. Legend).
// Список, а не строка: по нему строится фильтр сайдбара. Значения создаёт импорт. Задача 10.10.2026.
// Только консоль. Запуск: /opt/php83/bin/php scripts/add_iblock19_series.php
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

if (CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'SERIES'])->Fetch()) {
    echo "Свойство SERIES уже существует, пропускаю создание\n";
    exit;
}

$propObj = new CIBlockProperty;
$propId = $propObj->Add([
    'IBLOCK_ID' => $IBLOCK_ID,
    'NAME' => 'Серия',
    'CODE' => 'SERIES',
    'PROPERTY_TYPE' => 'L',
    'LIST_TYPE' => 'L',
    'MULTIPLE' => 'N',
    'IS_REQUIRED' => 'N',
    'SORT' => 545,
    'SMART_FILTER' => 'Y',
]);
if (!$propId) {
    die("Ошибка создания свойства SERIES: {$propObj->LAST_ERROR}\n");
}
echo "Свойство SERIES создано, ID=$propId\n";
