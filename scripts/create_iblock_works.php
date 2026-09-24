<?php
// Разовый скрипт создания инфоблока "Наши работы" (блок на главной) — запускается один раз на
// проде через CLI. Паттерн полностью повторяет scripts/create_iblock_articles.php: тип "news",
// auto-increment ID (полученный ID нужно вручную вписать как EPORTA_WORKS_IBLOCK_ID в
// local/php_interface/include/eporta_works_common.php), плюс два свойства CITY/COLLECTION.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');

$existing = CIBlock::GetList([], ['CODE' => 'works'])->Fetch();
if ($existing) {
    echo "Инфоблок 'works' уже существует, ID=" . $existing['ID'] . PHP_EOL;
    exit;
}

$ib = new CIBlock;
$id = $ib->Add([
    'ACTIVE' => 'Y',
    'NAME' => 'Наши работы',
    'CODE' => 'works',
    'IBLOCK_TYPE_ID' => 'news',
    'SITE_ID' => ['s1'],
    'VERSION' => 2,
    'RIGHTS_MODE' => 'S',
    'GROUP_ID' => [2 => 'X'],
]);

if (!$id) {
    die('Ошибка создания инфоблока: ' . $ib->LAST_ERROR . PHP_EOL);
}

echo "Создан инфоблок 'Наши работы', ID={$id}" . PHP_EOL;

// Свойства: город/объект и коллекция — обе строковые (свободный ввод в админке, без справочника,
// чтобы не тянуть зависимость на секции IBLOCK 19).
$propObj = new CIBlockProperty;
foreach ([
    ['CODE' => 'CITY', 'NAME' => 'Город / объект', 'SORT' => 100],
    ['CODE' => 'COLLECTION', 'NAME' => 'Коллекция', 'SORT' => 200],
] as $prop) {
    $propId = $propObj->Add([
        'IBLOCK_ID' => $id,
        'NAME' => $prop['NAME'],
        'CODE' => $prop['CODE'],
        'PROPERTY_TYPE' => 'S',
        'ROW_COUNT' => 1,
        'COL_COUNT' => 30,
        'SORT' => $prop['SORT'],
        'ACTIVE' => 'Y',
    ]);
    if (!$propId) {
        echo 'Ошибка создания свойства ' . $prop['CODE'] . ': ' . $propObj->LAST_ERROR . PHP_EOL;
    } else {
        echo "Создано свойство {$prop['CODE']}, ID={$propId}" . PHP_EOL;
    }
}

echo "Впишите ID инфоблока как EPORTA_WORKS_IBLOCK_ID в local/php_interface/include/eporta_works_common.php" . PHP_EOL;
