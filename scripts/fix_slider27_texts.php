<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 27;

$positionEnumId = null;
$res = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'POSITION', 'XML_ID' => 'center']);
if ($e = $res->Fetch()) {
    $positionEnumId = $e['ID'];
}

$dataByName = [
    'Eporta - dveri na zakaz' => [
        'SUBTITLE' => 'Модерн · экошпон · рассрочка 0%',
        'LINK' => '/catalog/',
        'CTA_TEXT' => 'Смотреть каталог →',
    ],
    'Rassrochka 0% na 12 mesyacev' => [
        'SUBTITLE' => 'Без переплат и справок',
        'LINK' => '/catalog/',
        'CTA_TEXT' => 'Узнать условия →',
    ],
    'Novaya kollekciya Vilis' => [
        'SUBTITLE' => 'Новая коллекция уже в наличии',
        'LINK' => '/collection/',
        'CTA_TEXT' => 'Смотреть коллекцию →',
    ],
    'Garantiya 5 let' => [
        'SUBTITLE' => 'На фурнитуру и полотно',
        'LINK' => '/catalog/',
        'CTA_TEXT' => 'Подробнее →',
    ],
    'Skidki do 20%' => [
        'SUBTITLE' => 'Акция действует до конца месяца',
        'LINK' => '/catalog/',
        'CTA_TEXT' => 'Перейти к акции →',
    ],
    'Dostavka za 1-3 dnya' => [
        'SUBTITLE' => 'По Москве и области',
        'LINK' => '/catalog/',
        'CTA_TEXT' => 'Подробнее →',
    ],
];

$elObj = new CIBlockElement;
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID', 'NAME']);
while ($el = $res->Fetch()) {
    if (!isset($dataByName[$el['NAME']])) {
        continue;
    }
    $d = $dataByName[$el['NAME']];
    $propertyValues = [
        'SUBTITLE' => $d['SUBTITLE'],
        'LINK' => $d['LINK'],
        'CTA_TEXT' => $d['CTA_TEXT'],
    ];
    if ($positionEnumId) {
        $propertyValues['POSITION'] = $positionEnumId;
    }
    $ok = $elObj->Update($el['ID'], ['PROPERTY_VALUES' => $propertyValues]);
    echo $ok ? "Элемент {$el['ID']} ({$el['NAME']}) обновлён\n" : "Ошибка {$el['ID']}: {$elObj->LAST_ERROR}\n";
}

echo "Готово.\n";
