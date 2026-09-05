<?php
// Единый источник коллекций фабрики — до этого файла список ID секций-коллекций был
// захардкожен в четырёх независимых местах (collection/index.php, catalog/index.php,
// index.php, eporta_showcase/lib.php) и слоты баннеров — ещё в одном (eporta_banners/lib.php);
// рассинхрон между копиями тихо ломал слот новой коллекции. Теперь коллекции читаются из
// базы (разделы IBLOCK 19 под родителем 183 "Коллекции") в одном месте, добавление новой
// коллекции через админку local/admin_tools/eporta_collections/ подхватывается везде без
// правки кода.
// Требует уже подключенный prolog_before.php (модуль iblock).

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

const EPORTA_COLLECTIONS_IBLOCK_ID = 19;
const EPORTA_COLLECTIONS_PARENT_SECTION_ID = 183;

// Коллекции (подразделы 183), отсортированные по SORT. Поля: ID, NAME, CODE, DESCRIPTION, SORT,
// ACTIVE, PICTURE, DETAIL_PICTURE. По умолчанию только активные (витрина сайта); с
// $includeInactive=true — все, включая скрытые (нужно админке коллекций, иначе скрытую
// коллекцию было бы невозможно включить обратно — она просто пропала бы из списка).
//
// ВАЖНО: фильтр CIBlockSection::GetList по IBLOCK_SECTION_ID не работает как ожидалось — он
// возвращает вообще все секции инфоблока, а не только дочерние (см. памятку
// feedback_bitrix_section_filter_gotcha). Поэтому берём IBLOCK_SECTION_ID полем в выборке и
// фильтруем в PHP.
function eportaCollections(bool $includeInactive = false): array {
    static $cache = [];
    $cacheKey = $includeInactive ? 'all' : 'active';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    CModule::IncludeModule('iblock');
    $filter = ['IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID];
    if (!$includeInactive) {
        $filter['ACTIVE'] = 'Y';
    }
    $collections = [];
    $res = CIBlockSection::GetList(
        ['SORT' => 'ASC'],
        $filter,
        false,
        ['ID', 'NAME', 'CODE', 'DESCRIPTION', 'SORT', 'ACTIVE', 'PICTURE', 'DETAIL_PICTURE', 'IBLOCK_SECTION_ID']
    );
    while ($row = $res->GetNext()) {
        if ((int)$row['IBLOCK_SECTION_ID'] !== EPORTA_COLLECTIONS_PARENT_SECTION_ID) {
            continue;
        }
        $collections[] = $row;
    }
    $cache[$cacheKey] = $collections;
    return $collections;
}

// ID => количество активных товаров в коллекции. Один запрос с группировкой вместо отдельного
// SelectedRowsCount() на каждую коллекцию (было 10 запросов на /collection/, 6 на главной).
// $includeInactive=true — считать и по скрытым коллекциям тоже (нужно админке коллекций).
function eportaCollectionsElementCounts(bool $includeInactive = false): array {
    static $cache = [];
    $cacheKey = $includeInactive ? 'all' : 'active';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    CModule::IncludeModule('iblock');
    $counts = [];
    $sectionIds = array_column(eportaCollections($includeInactive), 'ID');
    if (!$sectionIds) {
        $cache[$cacheKey] = $counts;
        return $counts;
    }
    $res = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID, 'SECTION_ID' => $sectionIds, 'ACTIVE' => 'Y'],
        ['SECTION_ID'],
        false,
        ['ID', 'SECTION_ID']
    );
    while ($row = $res->Fetch()) {
        $sectionId = (int)$row['SECTION_ID'];
        $counts[$sectionId] = (int)($row['CNT'] ?? 0);
    }
    $cache[$cacheKey] = $counts;
    return $counts;
}

// Код слота баннера (IBLOCK 27, свойство PLACEMENT) для коллекции с данным CODE секции —
// единая точка склейки, раньше дублировалась в index.php и collection/index.php.
function eportaCollectionSlotCode(string $collectionCode): string {
    return 'coll_' . $collectionCode;
}

// Склонение "модель/модели/моделей" по числу — используется на плитках коллекций.
function eportaCollectionsDeclension(int $count): string {
    $mod100 = $count % 100;
    if ($mod100 >= 11 && $mod100 <= 14) {
        return 'моделей';
    }
    switch ($count % 10) {
        case 1:
            return 'модель';
        case 2:
        case 3:
        case 4:
            return 'модели';
        default:
            return 'моделей';
    }
}
