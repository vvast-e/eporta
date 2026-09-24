<?php
// Общие константы/хелперы блока "Наши работы" (секция на главной) — подключается и из публичной
// части (index.php), и из кастомной админки (local/admin_tools/eporta_works/lib.php). По паттерну
// eporta_articles_common.php. См. scripts/create_iblock_works.php.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// TODO: заменить на реальный ID после запуска scripts/create_iblock_works.php на проде.
// Пока 0 — eportaWorksList() отдаёт пустой массив, секция на главной не рендерится (см. index.php).
const EPORTA_WORKS_IBLOCK_ID = 0;

// Транслитерация заголовка в CODE — тот же паттерн, что eportaArticlesGenerateCode().
function eportaWorksGenerateCode(string $name): string {
    $code = \CUtil::translit($name, 'ru', [
        'max_len' => 100,
        'change_case' => 'L',
        'replace_space' => '-',
        'replace_other' => '-',
        'delete_repeat_replace' => true,
    ]);
    return $code !== '' ? $code : 'work-' . time();
}

// Активные работы для главной, отсортированные по SORT — используется и на публичной странице,
// и (без лимита) в списке админки.
function eportaWorksList(?int $limit = null): array {
    if (EPORTA_WORKS_IBLOCK_ID <= 0) {
        return [];
    }
    $res = CIBlockElement::GetList(
        ['SORT' => 'ASC', 'ID' => 'DESC'],
        ['IBLOCK_ID' => EPORTA_WORKS_IBLOCK_ID, 'ACTIVE' => 'Y'],
        false,
        $limit ? ['nTopCount' => $limit] : false,
        ['ID', 'NAME', 'CODE', 'ACTIVE', 'SORT', 'PREVIEW_PICTURE', 'PREVIEW_TEXT', 'PROPERTY_CITY', 'PROPERTY_COLLECTION']
    );
    $items = [];
    while ($el = $res->Fetch()) {
        $el['PREVIEW_PICTURE_SRC'] = $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : '';
        $el['CITY'] = (string)($el['PROPERTY_CITY_VALUE'] ?? '');
        $el['COLLECTION'] = (string)($el['PROPERTY_COLLECTION_VALUE'] ?? '');
        $items[] = $el;
    }
    return $items;
}

// Полный список (включая неактивные) для админки.
function eportaWorksAdminList(): array {
    if (EPORTA_WORKS_IBLOCK_ID <= 0) {
        return [];
    }
    $res = CIBlockElement::GetList(
        ['SORT' => 'ASC', 'ID' => 'DESC'],
        ['IBLOCK_ID' => EPORTA_WORKS_IBLOCK_ID],
        false, false,
        ['ID', 'NAME', 'CODE', 'ACTIVE', 'SORT', 'PREVIEW_PICTURE', 'PREVIEW_TEXT', 'PROPERTY_CITY', 'PROPERTY_COLLECTION']
    );
    $items = [];
    while ($el = $res->Fetch()) {
        $el['PREVIEW_PICTURE_SRC'] = $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : '';
        $el['CITY'] = (string)($el['PROPERTY_CITY_VALUE'] ?? '');
        $el['COLLECTION'] = (string)($el['PROPERTY_COLLECTION_VALUE'] ?? '');
        $items[] = $el;
    }
    return $items;
}

function eportaWorksGet(int $id): ?array {
    if (EPORTA_WORKS_IBLOCK_ID <= 0) {
        return null;
    }
    $el = CIBlockElement::GetList(
        [], ['IBLOCK_ID' => EPORTA_WORKS_IBLOCK_ID, 'ID' => $id], false, false,
        ['ID', 'NAME', 'CODE', 'ACTIVE', 'SORT', 'PREVIEW_PICTURE', 'PREVIEW_TEXT', 'PROPERTY_CITY', 'PROPERTY_COLLECTION']
    )->Fetch();
    if (!$el) {
        return null;
    }
    $el['PREVIEW_PICTURE_SRC'] = $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : '';
    $el['CITY'] = (string)($el['PROPERTY_CITY_VALUE'] ?? '');
    $el['COLLECTION'] = (string)($el['PROPERTY_COLLECTION_VALUE'] ?? '');
    return $el;
}
