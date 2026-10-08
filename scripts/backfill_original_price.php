<?php
// Заполняет ORIGINAL_PRICE у товаров, загруженных до появления свойства: исходная = BASE / (1 − скидка).
// Приближённо (±1 ₽ из-за округления BASE); точное значение придёт при следующем импорте из 1С.
// Существующие непустые ORIGINAL_PRICE не трогает. По умолчанию — только отчёт; запись с --apply.
// Только консоль. Запуск: /opt/php83/bin/php scripts/backfill_original_price.php [--apply]
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/local/lib/eporta_price.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

$apply = in_array('--apply', $argv, true);
$IBLOCK_ID = 19;
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, '>PROPERTY_DISCOUNT' => 0, 'PROPERTY_ORIGINAL_PRICE' => false],
    false, false, ['ID', 'PROPERTY_DISCOUNT', 'CATALOG_PRICE_1']);
$done = 0;
$skipped = 0;
while ($row = $res->Fetch()) {
    $old = eportaPriceOld((float)$row['CATALOG_PRICE_1'], (float)$row['PROPERTY_DISCOUNT_VALUE']);
    if ($old <= 0) {
        $skipped++;
        continue;
    }
    if ($apply) {
        CIBlockElement::SetPropertyValuesEx($row['ID'], $IBLOCK_ID, ['ORIGINAL_PRICE' => $old]);
    }
    $done++;
}
echo ($apply ? 'Записано' : 'Будет записано (без --apply)') . ": $done, пропущено (нет цены/скидки): $skipped\n";
