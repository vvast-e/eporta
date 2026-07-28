<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 27;

// enum ID вместо XML_ID строки — CIBlockElement::Update для типа "L" не принимает
// произвольную строку как значение, только ID варианта (или массив с VALUE=>ID).
$enumIdByXmlId = [];
$res = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'PLACEMENT']);
while ($e = $res->Fetch()) {
    $enumIdByXmlId[$e['XML_ID']] = $e['ID'];
}
if (empty($enumIdByXmlId)) {
    die("Не нашёл enum-значения свойства PLACEMENT\n");
}
print_r($enumIdByXmlId);

$placementByName = [
    'Eporta - dveri na zakaz' => 'main',
    'Rassrochka 0% na 12 mesyacev' => 'main',
    'Novaya kollekciya Vilis' => 'side1',
    'Garantiya 5 let' => 'side1',
    'Skidki do 20%' => 'side2',
    'Dostavka za 1-3 dnya' => 'side2',
];

$elObj = new CIBlockElement;
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID', 'NAME']);
while ($el = $res->Fetch()) {
    if (!isset($placementByName[$el['NAME']])) {
        continue;
    }
    $xmlId = $placementByName[$el['NAME']];
    $enumId = $enumIdByXmlId[$xmlId] ?? null;
    if (!$enumId) {
        echo "Нет enum ID для $xmlId, пропускаю {$el['ID']}\n";
        continue;
    }
    $ok = $elObj->Update($el['ID'], [
        'PROPERTY_VALUES' => ['PLACEMENT' => $enumId],
    ]);
    echo $ok
        ? "Элемент {$el['ID']} ({$el['NAME']}) -> $xmlId (enum ID $enumId)\n"
        : "Ошибка обновления {$el['ID']}: {$elObj->LAST_ERROR}\n";
}

echo "Готово.\n";
