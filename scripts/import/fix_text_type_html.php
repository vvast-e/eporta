<?php
// Одноразовая миграция: DETAIL_TEXT/PREVIEW_TEXT у товаров IBLOCK 19 хранят реальную
// HTML-разметку из 1С-выгрузки (<p>...</p>), но исходный импортёр не проставлял
// DETAIL_TEXT_TYPE/PREVIEW_TEXT_TYPE => "html", поэтому Bitrix считал их обычным
// текстом и экранировал теги на выводе (карточка товара показывала буквально "&lt;p&gt;").
// import_products.php уже поправлен на будущее — этот скрипт чинит то, что уже
// накопилось в базе. Сам HTML-контент не трогает, меняет только флаг типа поля.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;
$fixed = 0;
$skipped = 0;

$res = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => $IBLOCK_ID],
    false,
    false,
    ['ID', 'DETAIL_TEXT_TYPE', 'PREVIEW_TEXT_TYPE']
);
while ($el = $res->Fetch()) {
    $update = [];
    if ($el['DETAIL_TEXT_TYPE'] !== 'html') {
        $update['DETAIL_TEXT_TYPE'] = 'html';
    }
    if ($el['PREVIEW_TEXT_TYPE'] !== 'html') {
        $update['PREVIEW_TEXT_TYPE'] = 'html';
    }
    if (!$update) {
        $skipped++;
        continue;
    }
    $obj = new CIBlockElement;
    if ($obj->Update((int)$el['ID'], $update)) {
        $fixed++;
    } else {
        echo "Ошибка ID {$el['ID']}: {$obj->LAST_ERROR}\n";
    }
}

echo "Готово. Обновлено: $fixed, уже было ок: $skipped\n";
