<?php
// Админка табов "Хиты / Распродажа / Новинки" на главной. Требует уже подключенный
// prolog_before.php (модули main/iblock) и eporta_home_tabs_common.php (константы/
// eportaHomeTabsGetConfig/eportaHomeTabsSaveConfig/eportaHomeTabsUserHasAccess — общие с
// публичным рендером в index.php). Тот же паттерн прав, что у eporta_banners/eporta_import
// (право записи в IBLOCK 19).

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// Поиск товара по названию или артикулу для добавления в закреплённые. Классический
// CIBlockElement::GetList не поддерживает вложенные подфильтры с LOGIC=>OR (см. комментарий
// в catalog/index.php у фильтра "Новинки") — поэтому два отдельных запроса, слитых по ID.
function eportaHomeTabsSearchProducts(string $query, int $limit = 20): array {
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    \Bitrix\Main\Loader::includeModule('iblock');
    $select = ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PROPERTY_CML2_ARTICLE'];
    $byId = [];

    $res = \CIBlockElement::GetList(
        ['sort' => 'asc'],
        ['IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID, 'ACTIVE' => 'Y', '%NAME' => $query],
        false, ['nTopCount' => $limit], $select
    );
    while ($row = $res->Fetch()) {
        $byId[(int)$row['ID']] = $row;
    }

    if (count($byId) < $limit) {
        $res = \CIBlockElement::GetList(
            ['sort' => 'asc'],
            ['IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID, 'ACTIVE' => 'Y', '%PROPERTY_CML2_ARTICLE' => $query],
            false, ['nTopCount' => $limit], $select
        );
        while ($row = $res->Fetch()) {
            $byId[(int)$row['ID']] = $row;
        }
    }

    $items = [];
    foreach ($byId as $id => $row) {
        $photoId = $row['PREVIEW_PICTURE'] ?: $row['DETAIL_PICTURE'];
        $items[] = [
            'id' => $id,
            'name' => $row['NAME'],
            'article' => $row['PROPERTY_CML2_ARTICLE_VALUE'] ?? '',
            'photo' => $photoId ? \CFile::GetPath($photoId) : '',
        ];
        if (count($items) >= $limit) {
            break;
        }
    }
    return $items;
}

// Карточки товаров по списку ID (для отрисовки уже закреплённых при открытии страницы) —
// ключ результата = ID, порядок вызывающая сторона восстанавливает сама по своему списку pinned.
function eportaHomeTabsGetProductsInfo(array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    \Bitrix\Main\Loader::includeModule('iblock');
    $res = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID, 'ID' => $ids],
        false, false,
        ['ID', 'NAME', 'ACTIVE', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PROPERTY_CML2_ARTICLE']
    );
    $byId = [];
    while ($row = $res->Fetch()) {
        $photoId = $row['PREVIEW_PICTURE'] ?: $row['DETAIL_PICTURE'];
        $byId[(int)$row['ID']] = [
            'id' => (int)$row['ID'],
            'name' => $row['NAME'],
            'article' => $row['PROPERTY_CML2_ARTICLE_VALUE'] ?? '',
            'photo' => $photoId ? \CFile::GetPath($photoId) : '',
            'active' => $row['ACTIVE'] === 'Y',
        ];
    }
    return $byId;
}

// Санитизация конфига, присланного из формы, перед сохранением — те же правила, что и
// eportaHomeTabsGetConfig() при чтении (лимит 1..60, pinned — уникальные существующие активные
// ID, в присланном порядке), плюс отсечение неизвестных ключей табов.
function eportaHomeTabsSanitizeConfig(array $raw): array {
    $config = eportaHomeTabsDefaults();
    foreach (eportaHomeTabsKeys() as $tabKey) {
        if (!isset($raw[$tabKey]) || !is_array($raw[$tabKey])) {
            continue;
        }
        $title = trim((string)($raw[$tabKey]['title'] ?? ''));
        $config[$tabKey]['title'] = $title !== '' ? mb_substr($title, 0, 60) : $config[$tabKey]['title'];

        $limit = (int)($raw[$tabKey]['limit'] ?? $config[$tabKey]['limit']);
        $config[$tabKey]['limit'] = $limit > 0 ? min($limit, EPORTA_HOME_TABS_MAX_LIMIT) : $config[$tabKey]['limit'];

        $pinnedRaw = is_array($raw[$tabKey]['pinned'] ?? null) ? $raw[$tabKey]['pinned'] : [];
        $pinnedIds = array_values(array_unique(array_map('intval', $pinnedRaw)));
        if ($pinnedIds) {
            $info = eportaHomeTabsGetProductsInfo($pinnedIds);
            $pinnedIds = array_values(array_filter($pinnedIds, function ($id) use ($info) {
                return isset($info[$id]) && $info[$id]['active'];
            }));
        }
        $config[$tabKey]['pinned'] = $pinnedIds;
    }
    return $config;
}
