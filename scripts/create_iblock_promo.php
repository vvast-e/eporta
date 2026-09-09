<?php
// Разовый скрипт создания инфоблока "Акции" (раздел /promo/) — запускается один раз на проде
// через CLI. Полная копия scripts/create_iblock_articles.php под отдельный инфоблок (не общий
// со статьями — заказчик согласовал отдельный раздел с собственным списком/деталью).
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');

$existing = CIBlock::GetList([], ['CODE' => 'promo'])->Fetch();
if ($existing) {
    echo "Инфоблок 'promo' уже существует, ID=" . $existing['ID'] . PHP_EOL;
    exit;
}

$ib = new CIBlock;
$id = $ib->Add([
    'ACTIVE' => 'Y',
    'NAME' => 'Акции',
    'CODE' => 'promo',
    // Тот же штатный тип "news", что и у статей — тип инфоблока в проекте нигде не читается
    // вручную, только сам IBLOCK_ID.
    'IBLOCK_TYPE_ID' => 'news',
    'SITE_ID' => ['s1'],
    'LIST_PAGE_URL' => '/promo/',
    'DETAIL_PAGE_URL' => '/promo/#ELEMENT_CODE#.html',
    'VERSION' => 2,
    'RIGHTS_MODE' => 'S',
    'GROUP_ID' => [2 => 'X'],
]);

if (!$id) {
    die('Ошибка создания инфоблока: ' . $ib->LAST_ERROR . PHP_EOL);
}

echo "Создан инфоблок 'Акции', ID={$id}" . PHP_EOL;
echo "Впишите это число как EPORTA_PROMO_IBLOCK_ID в local/php_interface/include/eporta_promo_common.php" . PHP_EOL;
