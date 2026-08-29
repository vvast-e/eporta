<?php
// Свойство SHOWCASE (список Y/N) для IBLOCK 19 — ручной выбор "витринного" варианта модели,
// показываемого в блоке "Модели коллекции" (catalog/index.php), см. local/admin_tools/
// eporta_showcase/. По умолчанию не заполняется ни у одного элемента — фолбэк на прежнюю логику
// (максимум RATING) остаётся в силе, пока контент-менеджер явно не выберет вариант.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;

$res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'SHOWCASE']);
if ($res->Fetch()) {
    echo "Свойство SHOWCASE уже существует, пропускаю создание\n";
    exit;
}

$propObj = new CIBlockProperty;
$propId = $propObj->Add([
    'IBLOCK_ID' => $IBLOCK_ID,
    'NAME' => 'Витринный вариант модели',
    'CODE' => 'SHOWCASE',
    'PROPERTY_TYPE' => 'L',
    'LIST_TYPE' => 'C',
    'ROW_COUNT' => 1,
    'COL_COUNT' => 30,
    'MULTIPLE' => 'N',
    'IS_REQUIRED' => 'N',
    'SORT' => 510,
]);
if (!$propId) {
    die("Ошибка создания свойства SHOWCASE: {$propObj->LAST_ERROR}\n");
}
echo "Свойство SHOWCASE создано, ID=$propId\n";

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
