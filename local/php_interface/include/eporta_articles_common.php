<?php
// Общие константы/хелперы раздела "Статьи" — подключается и из публичной части (articles/index.php),
// и из кастомной админки (local/admin_tools/eporta_articles/lib.php), чтобы не тянуть админский
// lib.php (с его загрузкой файлов и т.п.) в публичный код. См. scripts/create_iblock_articles.php.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

const EPORTA_ARTICLES_IBLOCK_ID = 28;

// Транслитерация заголовка в CODE (тот же паттерн, что eportaImportGenerateCode() в
// local/admin_tools/eporta_import/lib.php), без привязки к артикулу — у статьи его нет.
function eportaArticlesGenerateCode(string $name): string {
    $code = \CUtil::translit($name, 'ru', [
        'max_len' => 100,
        'change_case' => 'L',
        'replace_space' => '-',
        'replace_other' => '-',
        'delete_repeat_replace' => true,
    ]);
    return $code !== '' ? $code : 'article-' . time();
}
