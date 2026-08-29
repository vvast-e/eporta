<?php
// Диагностика IBLOCK 27 (баннеры) — read-only, ничего не меняет. Печатает по каждому элементу
// картину, нужную для разбора "почему верхние баннеры не показывают фото": PLACEMENT (raw enum
// ID + XML_ID), наличие DETAIL_PICTURE/PREVIEW_PICTURE, физическое наличие файла и его .webp
// варианта на диске.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 27;

$placementXmlIdByEnumId = [];
$rs = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'PLACEMENT']);
while ($e = $rs->Fetch()) {
    $placementXmlIdByEnumId[$e['ID']] = $e['XML_ID'];
    echo "PLACEMENT enum: ID={$e['ID']} XML_ID='{$e['XML_ID']}' VALUE='{$e['VALUE']}'\n";
}
echo str_repeat('-', 80) . "\n";

$res = CIBlockElement::GetList(
    ['SORT' => 'ASC'],
    ['IBLOCK_ID' => $IBLOCK_ID],
    false,
    false,
    ['ID', 'NAME', 'ACTIVE', 'IBLOCK_SECTION_ID', 'DETAIL_PICTURE', 'PREVIEW_PICTURE', 'PROPERTY_PLACEMENT']
);
while ($el = $res->Fetch()) {
    $enumId = $el['PROPERTY_PLACEMENT_ENUM_ID'] ?? null;
    $xmlId = $enumId ? ($placementXmlIdByEnumId[$enumId] ?? '???') : '(нет свойства)';

    $detailPath = $el['DETAIL_PICTURE'] ? CFile::GetPath($el['DETAIL_PICTURE']) : null;
    $previewPath = $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : null;

    $detailExists = $detailPath && is_file($_SERVER['DOCUMENT_ROOT'] . $detailPath);
    $detailWebp = $detailPath ? (substr($detailPath, 0, -strlen(pathinfo($detailPath, PATHINFO_EXTENSION))) . 'webp') : null;
    $detailWebpExists = $detailWebp && is_file($_SERVER['DOCUMENT_ROOT'] . $detailWebp);

    echo "ID={$el['ID']} NAME='{$el['NAME']}' ACTIVE={$el['ACTIVE']} SECTION=" . ($el['IBLOCK_SECTION_ID'] ?: '-') . "\n";
    echo "  PLACEMENT: enumId=" . ($enumId ?: '-') . " xmlId='$xmlId'\n";
    echo "  DETAIL_PICTURE: id=" . ($el['DETAIL_PICTURE'] ?: '-') . " path=" . ($detailPath ?: '-') . " existsOnDisk=" . ($detailExists ? 'YES' : 'NO') . "\n";
    echo "  DETAIL webp: path=" . ($detailWebp ?: '-') . " existsOnDisk=" . ($detailWebpExists ? 'YES' : 'NO') . "\n";
    echo "  PREVIEW_PICTURE: id=" . ($el['PREVIEW_PICTURE'] ?: '-') . " path=" . ($previewPath ?: '-') . "\n";
    echo "\n";
}

echo "Готово.\n";
