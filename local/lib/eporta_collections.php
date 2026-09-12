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
        ['ID', 'NAME', 'CODE', 'DESCRIPTION', 'SORT', 'ACTIVE', 'PICTURE', 'DETAIL_PICTURE', 'IBLOCK_SECTION_ID', 'UF_BANNER_OVERLAY', 'UF_BANNER_CTA_TEXT', 'UF_BANNER_CTA_LINK', 'UF_SQUARE_CARDS']
    );
    while ($row = $res->GetNext()) {
        if ((int)$row['IBLOCK_SECTION_ID'] !== EPORTA_COLLECTIONS_PARENT_SECTION_ID) {
            continue;
        }
        // Защита от испорченных данных, обнаруженных на проде 05.09.2026: раздел 183
        // "Коллекции" сам на себя ссылается как на родителя (IBLOCK_SECTION_ID=183 у своей же
        // записи) — без этой проверки родитель попадал бы в список как 12-я "коллекция". Плюс
        // сиротская секция (ID=192, пустой CODE, 0 элементов, дубль неудачного первого запуска
        // add_collections_dorsum_f_eco.php от 10.08.2026) — без CODE у неё нет ни слота баннера,
        // ни рабочей ссылки /catalog/collections//.
        if ((int)$row['ID'] === EPORTA_COLLECTIONS_PARENT_SECTION_ID || $row['CODE'] === '') {
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
    // Две отдельные ловушки нашлись здесь (обнаружено на проде 06.09.2026 — карточки коллекций
    // на главной все по 0 моделей):
    // 1) "SECTION_ID" в фильтре — валидный ключ (резолвится через M:N-таблицу
    //    b_iblock_section_element), но как ПОЛЕ для группировки/выборки не существует —
    //    группировка по нему тихо ломается, GetList отдаёт вырожденные строки без реальных
    //    данных. Настоящее имя поля элемента — "IBLOCK_SECTION_ID".
    // 2) "CNT" обязателен в списке полей при группировке (4-й параметр) — без него Bitrix не
    //    считает агрегат, $row['CNT'] всегда пуст и ?? 0 подставлял ноль для каждой коллекции.
    $res = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID, 'SECTION_ID' => $sectionIds, 'ACTIVE' => 'Y'],
        ['IBLOCK_SECTION_ID'],
        false,
        ['ID', 'IBLOCK_SECTION_ID', 'CNT']
    );
    while ($row = $res->Fetch()) {
        $sectionId = (int)$row['IBLOCK_SECTION_ID'];
        $counts[$sectionId] = (int)($row['CNT'] ?? 0);
    }
    $cache[$cacheKey] = $counts;
    return $counts;
}

// Квадратные карточки товара (задача 12.09.2026, коллекция Invi — скрытые двери, интерьерные
// фото 1200x1200/520x520 вместо обычных портретных) — UF-поле секции, см.
// scripts/add_section_square_cards_ufield.php. Тем же полем позже помечаются входные двери и
// перегородки (тот же формат фото), поэтому проверка вынесена в отдельную функцию, а не
// захардкожена по CODE коллекции.
function eportaCollectionHasSquareCards(array $collection): bool {
    return ($collection['UF_SQUARE_CARDS'] ?? '') === 'Y';
}

// Код слота баннера (IBLOCK 27, свойство PLACEMENT) для коллекции с данным CODE секции —
// единая точка склейки, раньше дублировалась в index.php и collection/index.php.
function eportaCollectionSlotCode(string $collectionCode): string {
    return 'coll_' . $collectionCode;
}

// Склонение "товар/товара/товаров" по числу — используется на плитках коллекций.
function eportaCollectionsDeclension(int $count): string {
    $mod100 = $count % 100;
    if ($mod100 >= 11 && $mod100 <= 14) {
        return 'товаров';
    }
    switch ($count % 10) {
        case 1:
            return 'товар';
        case 2:
        case 3:
        case 4:
            return 'товара';
        default:
            return 'товаров';
    }
}
