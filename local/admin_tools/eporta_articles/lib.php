<?php
// Админка написания статей раздела /articles/ — по паттерну local/admin_tools/eporta_banners
// (см. feedback_bitrix_override_gotchas/project memory): точка входа + lib.php + ajax.php,
// без Bitrix-обвязки, самописный HTML. Требует уже подключенный prolog_before.php.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/eporta_articles_common.php');

// Право на запись статей — тот же критерий, что и у остальных кастомных админок сайта (право
// записи в каталог IBLOCK 19): те же контент-менеджеры, отдельной модели прав не заводили.
const EPORTA_ARTICLES_PERMISSION_IBLOCK_ID = 19;

function eportaArticlesUserHasAccess(): bool {
    global $USER;
    if (!$USER->IsAuthorized()) {
        return false;
    }
    if ($USER->IsAdmin()) {
        return true;
    }
    return CIBlock::GetPermission(EPORTA_ARTICLES_PERMISSION_IBLOCK_ID) >= 'W';
}

function eportaArticlesTmpDir(): string {
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/local/tmp/eporta_articles';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
    return $dir;
}

function eportaArticlesList(): array {
    $res = CIBlockElement::GetList(
        ['SORT' => 'ASC', 'ID' => 'DESC'],
        ['IBLOCK_ID' => EPORTA_ARTICLES_IBLOCK_ID],
        false, false,
        ['ID', 'NAME', 'CODE', 'ACTIVE', 'PREVIEW_PICTURE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'DATE_ACTIVE_FROM']
    );
    $items = [];
    while ($el = $res->Fetch()) {
        $el['PREVIEW_PICTURE_SRC'] = $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : '';
        $items[] = $el;
    }
    return $items;
}

function eportaArticlesGet(int $id): ?array {
    $el = CIBlockElement::GetList(
        [], ['IBLOCK_ID' => EPORTA_ARTICLES_IBLOCK_ID, 'ID' => $id], false, false,
        ['ID', 'NAME', 'CODE', 'ACTIVE', 'PREVIEW_PICTURE', 'PREVIEW_TEXT', 'DETAIL_TEXT']
    )->Fetch();
    if (!$el) {
        return null;
    }
    $el['PREVIEW_PICTURE_SRC'] = $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : '';
    return $el;
}
