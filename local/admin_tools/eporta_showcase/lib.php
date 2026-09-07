<?php
// Логика "Витрина моделей" — ручной выбор витринного варианта (свойство SHOWCASE, IBLOCK 19)
// и видимости варианта в общих списках (SHOW_IN_LIST) для каждой модели коллекции. Требует уже
// подключенный prolog_before.php (модули main/iblock).
// UI перенесён в local/admin_tools/eporta_collections/ (задача 06.09.2026, "всё управление
// коллекцией в одном месте") — этот файл теперь только логика, подключается оттуда через
// require_once в eporta_collections/lib.php. Доступ и список коллекций для UI берутся напрямую
// у eporta_collections (eportaCollectionsUserHasAccess()/eportaCollections()), не отсюда.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

const EPORTA_SHOWCASE_IBLOCK_ID = 19;

// Enum ID List-свойства IBLOCK 19 по CODE+XML_ID. ВАЖНО (найдено 06.09.2026): "PROPERTY_CODE_VALUE"
// в CIBlockElement::GetList — это VALUE энума ("Да"/"Нет", человекочитаемый текст для админки), а
// НЕ XML_ID ("Y"/"N") — сравнивать нужно "PROPERTY_CODE_ENUM_ID" с ID, полученным отсюда, иначе
// сравнение с "Y"/"N" никогда не совпадает и любой выбор в этой же админке молча не учитывается
// при чтении (при этом запись через eportaShowcaseSetListProperty ниже работает верно — баг был
// только в чтении). Та же функция продублирована в catalog/index.php (eportaGetIblock19EnumId) —
// два независимых файла, не связанных общим includes.
function eportaShowcaseGetEnumId(string $propertyCode, string $xmlId): ?int {
    static $cache = [];
    $key = $propertyCode . ':' . $xmlId;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $propRow = CIBlockProperty::GetList([], ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID, 'CODE' => $propertyCode])->Fetch();
    if (!$propRow) {
        return $cache[$key] = null;
    }
    $enumRow = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propRow['ID'], 'XML_ID' => $xmlId])->Fetch();
    return $cache[$key] = $enumRow ? (int)$enumRow['ID'] : null;
}

// Модели коллекции: группировка по PROPERTY_MODEL (тот же ключ, что и в catalog/index.php),
// внутри каждой модели — все варианты (цвета) с фото и текущим флагом SHOWCASE.
function eportaShowcaseGetModels(int $sectionId): array {
    $res = CIBlockElement::GetList(
        ['ID' => 'ASC'],
        ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID, 'ACTIVE' => 'Y', 'SECTION_ID' => $sectionId],
        false,
        false,
        ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PROPERTY_MODEL', 'PROPERTY_RATING', 'PROPERTY_SHOWCASE', 'PROPERTY_COATING_COLOR', 'PROPERTY_GLAZING', 'PROPERTY_SHOW_IN_LIST']
    );
    $showcaseYEnumId = eportaShowcaseGetEnumId('SHOWCASE', 'Y');
    $hideFromListEnumId = eportaShowcaseGetEnumId('SHOW_IN_LIST', 'N');
    $models = [];
    while ($row = $res->Fetch()) {
        $modelKey = $row['PROPERTY_MODEL_VALUE'] ?: ('__id_' . $row['ID']);
        $photoId = $row['PREVIEW_PICTURE'] ?: $row['DETAIL_PICTURE'];
        $models[$modelKey]['name'] = $row['PROPERTY_MODEL_VALUE'] ?: $row['NAME'];
        $models[$modelKey]['variants'][] = [
            'id' => (int)$row['ID'],
            'color' => $row['PROPERTY_COATING_COLOR_VALUE'] ?? '',
            'glazing' => $row['PROPERTY_GLAZING_VALUE'] ?? '',
            'rating' => (float)($row['PROPERTY_RATING_VALUE'] ?? 0),
            'photo' => $photoId ? CFile::GetPath($photoId) : '',
            'is_showcase' => $showcaseYEnumId !== null
                && (int)($row['PROPERTY_SHOWCASE_ENUM_ID'] ?? 0) === $showcaseYEnumId,
            // Отсутствие значения = показывать (см. комментарий в scripts/add_iblock19_show_in_list.php
            // и catalog/index.php $eportaScopeFilter) — скрыт только явный "N".
            'show_in_list' => !($hideFromListEnumId !== null
                && (int)($row['PROPERTY_SHOW_IN_LIST_ENUM_ID'] ?? 0) === $hideFromListEnumId),
        ];
    }
    return $models;
}

// ВАЖНО (инцидент 29.08.2026): CIBlockElement::Update() с PROPERTY_VALUES в классическом API
// Bitrix ПОЛНОСТЬЮ ЗАМЕНЯЕТ набор свойств элемента, а не мержит только переданный ключ — вызов
// Update($id, ['PROPERTY_VALUES' => ['SHOWCASE' => 'Y']]) стирает MODEL/RATING/COATING_COLOR и
// все остальные свойства товара. Единственный безопасный способ точечно обновить одно свойство —
// SetPropertyValuesEx с числовыми PROPERTY_ID/ENUM_ID, см. ниже.
function eportaShowcaseSetListProperty(int $elementId, string $propertyCode, string $enumXmlId): bool {
    static $propIdByCode = null;
    if ($propIdByCode === null) {
        $propIdByCode = [];
        $res = CIBlockProperty::GetList([], ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID]);
        while ($p = $res->Fetch()) {
            $propIdByCode[$p['CODE']] = (int)$p['ID'];
        }
    }
    $propId = $propIdByCode[$propertyCode] ?? null;
    if (!$propId) {
        return false;
    }
    $enumRow = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'XML_ID' => $enumXmlId])->Fetch();
    if (!$enumRow) {
        return false;
    }
    CIBlockElement::SetPropertyValuesEx($elementId, EPORTA_SHOWCASE_IBLOCK_ID, [$propId => (int)$enumRow['ID']]);
    return true;
}

// Проставляет SHOWCASE=Y выбранному варианту и снимает флаг со всех остальных вариантов той же
// модели (только внутри переданного списка ID — вызывающий код передаёт все варианты модели).
function eportaShowcaseSetVariant(int $selectedId, array $allVariantIds): bool {
    $ok = true;
    foreach ($allVariantIds as $variantId) {
        $value = ($variantId === $selectedId) ? 'Y' : 'N';
        $ok = eportaShowcaseSetListProperty($variantId, 'SHOWCASE', $value) && $ok;
    }
    return $ok;
}

// Показывать/скрывать один конкретный вариант в общих списках (каталог/"Все товары коллекции") —
// в отличие от SHOWCASE это НЕ эксклюзивный выбор "один из группы": у модели может быть скрыто
// сразу несколько вариантов (только самые ходовые остаются в списке), поэтому переключаем только
// переданный ID, без затрагивания остальных вариантов модели. См. catalog/index.php
// $eportaScopeFilter (!PROPERTY_SHOW_IN_LIST) — фильтрует только явное значение "N".
function eportaShowcaseSetShowInList(int $variantId, bool $show): bool {
    return eportaShowcaseSetListProperty($variantId, 'SHOW_IN_LIST', $show ? 'Y' : 'N');
}
