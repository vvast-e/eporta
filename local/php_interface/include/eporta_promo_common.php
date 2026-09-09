<?php
// Общие константы/хелперы раздела "Акции" — подключается и из публичной части (promo/index.php),
// и из кастомной админки (local/admin_tools/eporta_promo/lib.php), чтобы не тянуть админский
// lib.php (с его загрузкой файлов и т.п.) в публичный код. Полная копия
// eporta_articles_common.php под отдельный инфоблок. См. scripts/create_iblock_promo.php.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// ID подтверждён прогоном scripts/create_iblock_promo.php на проде (09.09.2026).
const EPORTA_PROMO_IBLOCK_ID = 29;

// Транслитерация заголовка в CODE (тот же паттерн, что eportaArticlesGenerateCode()).
function eportaPromoGenerateCode(string $name): string {
    $code = \CUtil::translit($name, 'ru', [
        'max_len' => 100,
        'change_case' => 'L',
        'replace_space' => '-',
        'replace_other' => '-',
        'delete_repeat_replace' => true,
    ]);
    return $code !== '' ? $code : 'promo-' . time();
}
