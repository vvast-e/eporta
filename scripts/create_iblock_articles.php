<?php
// Разовый скрипт создания инфоблока "Статьи" (раздел /articles/) — запускается один раз на
// проде через CLI. По паттерну scripts/create_iblock27_slider.php, но БЕЗ принудительного ID —
// auto-increment сам даёт следующий свободный ID (в отличие от IBLOCK 27, которому нужен был
// конкретный номер под уже написанный код). Полученный ID нужно вручную вписать как
// EPORTA_ARTICLES_IBLOCK_ID в local/php_interface/include/eporta_articles_common.php.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');

$existing = CIBlock::GetList([], ['CODE' => 'articles'])->Fetch();
if ($existing) {
    echo "Инфоблок 'articles' уже существует, ID=" . $existing['ID'] . PHP_EOL;
    exit;
}

$ib = new CIBlock;
$id = $ib->Add([
    'ACTIVE' => 'Y',
    'NAME' => 'Статьи',
    'CODE' => 'articles',
    // Переиспользуем штатный тип "news" (уже есть в системе, отдельный тип заводить незачем —
    // тип инфоблока в этом проекте нигде не читается вручную, только сам IBLOCK_ID).
    'IBLOCK_TYPE_ID' => 'news',
    'SITE_ID' => ['s1'],
    'LIST_PAGE_URL' => '/articles/',
    'DETAIL_PAGE_URL' => '/articles/#ELEMENT_CODE#.html',
    'VERSION' => 2,
    'RIGHTS_MODE' => 'S',
    'GROUP_ID' => [2 => 'X'],
]);

if (!$id) {
    die('Ошибка создания инфоблока: ' . $ib->LAST_ERROR . PHP_EOL);
}

echo "Создан инфоблок 'Статьи', ID={$id}" . PHP_EOL;
echo "Впишите это число как EPORTA_ARTICLES_IBLOCK_ID в local/php_interface/include/eporta_articles_common.php" . PHP_EOL;
