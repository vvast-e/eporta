<?php
// Разовый скрипт создания инфоблока "Отзывы" (блок на главной) — запускается один раз на проде
// через CLI. Паттерн повторяет scripts/create_iblock_works.php. Старый IBLOCK_ID=33, на который
// ссылалась легаси-страница /reviews/index.php (dresscode-шаблон), на проде не существует
// (проверено 24.09.2026) — заводим новый инфоблок, а не переиспользуем несуществующий ID.
// Полученный ID нужно вручную вписать как EPORTA_REVIEWS_IBLOCK_ID в
// local/php_interface/include/eporta_reviews_common.php.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');

$existing = CIBlock::GetList([], ['CODE' => 'eporta_reviews'])->Fetch();
if ($existing) {
    echo "Инфоблок 'eporta_reviews' уже существует, ID=" . $existing['ID'] . PHP_EOL;
    exit;
}

$ib = new CIBlock;
$id = $ib->Add([
    'ACTIVE' => 'Y',
    'NAME' => 'Отзывы',
    'CODE' => 'eporta_reviews',
    'IBLOCK_TYPE_ID' => 'news',
    'SITE_ID' => ['s1'],
    'VERSION' => 2,
    'RIGHTS_MODE' => 'S',
    'GROUP_ID' => [2 => 'X'],
]);

if (!$id) {
    die('Ошибка создания инфоблока: ' . $ib->LAST_ERROR . PHP_EOL);
}

echo "Создан инфоблок 'Отзывы', ID={$id}" . PHP_EOL;

// Свойства: RATING (1-5, строка — валидируется в ajax.php) и CITY (свободный ввод). NAME элемента
// используется как имя автора, PREVIEW_TEXT — текст отзыва, ACTIVE_FROM — дата (штатные поля).
$propObj = new CIBlockProperty;
foreach ([
    ['CODE' => 'RATING', 'NAME' => 'Оценка (1-5)', 'SORT' => 100],
    ['CODE' => 'CITY', 'NAME' => 'Город', 'SORT' => 200],
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

echo "Впишите ID инфоблока как EPORTA_REVIEWS_IBLOCK_ID в local/php_interface/include/eporta_reviews_common.php" . PHP_EOL;
