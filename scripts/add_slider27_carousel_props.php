<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 27;

$propObj = new CIBlockProperty;
$newProps = [
    'SUBTITLE' => ['NAME' => 'Подзаголовок баннера', 'SORT' => 10],
    'LINK' => ['NAME' => 'Ссылка баннера', 'SORT' => 20],
    'CTA_TEXT' => ['NAME' => 'Текст кнопки', 'SORT' => 30],
];

foreach ($newProps as $code => $p) {
    $res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $code]);
    if ($res->Fetch()) {
        echo "Свойство $code уже существует, пропускаю\n";
        continue;
    }
    $id = $propObj->Add([
        'IBLOCK_ID' => $IBLOCK_ID,
        'NAME' => $p['NAME'],
        'CODE' => $code,
        'PROPERTY_TYPE' => 'S',
        'ROW_COUNT' => 1,
        'COL_COUNT' => 30,
        'MULTIPLE' => 'N',
        'IS_REQUIRED' => 'N',
        'SORT' => $p['SORT'],
    ]);
    echo $id ? "Свойство $code создано, ID=$id\n" : "Ошибка $code: {$propObj->LAST_ERROR}\n";
}

$demoData = [
    'Eporta - dveri na zakaz' => [
        'SUBTITLE' => 'Модерн · экошпон · рассрочка 0%',
        'LINK' => '/catalog/',
        'CTA_TEXT' => 'Смотреть каталог →',
    ],
    'Novaya kollekciya Vilis' => [
        'SUBTITLE' => 'Новая коллекция уже в наличии',
        'LINK' => '/collection/',
        'CTA_TEXT' => 'Смотреть коллекцию →',
    ],
    'Skidki do 20%' => [
        'SUBTITLE' => 'Акция действует до конца месяца',
        'LINK' => '/catalog/',
        'CTA_TEXT' => 'Перейти к акции →',
    ],
];

$elObj = new CIBlockElement;
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID', 'NAME']);
while ($el = $res->Fetch()) {
    if (!isset($demoData[$el['NAME']])) {
        continue;
    }
    $d = $demoData[$el['NAME']];
    $ok = $elObj->Update($el['ID'], [
        'PROPERTY_VALUES' => [
            'SUBTITLE' => $d['SUBTITLE'],
            'LINK' => $d['LINK'],
            'CTA_TEXT' => $d['CTA_TEXT'],
        ],
    ]);
    echo $ok ? "Элемент {$el['ID']} ({$el['NAME']}) обновлён\n" : "Ошибка обновления {$el['ID']}: {$elObj->LAST_ERROR}\n";
}

echo "Готово.\n";
