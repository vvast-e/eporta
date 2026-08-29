<?php
// Общая логика страницы "Витрина моделей" — ручной выбор витринного варианта (свойство
// SHOWCASE, IBLOCK 19) для каждой модели коллекции. Требует уже подключенный prolog_before.php
// (модули main/iblock). По паттерну local/admin_tools/eporta_banners/lib.php — та же модель
// прав (право записи в IBLOCK 19), те же контент-менеджеры.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

const EPORTA_SHOWCASE_IBLOCK_ID = 19;

// Список ID коллекций (раздел 183 "Коллекции") — тот же явный whitelist, что в
// collection/index.php и catalog/index.php (CIBlockSection::GetList по IBLOCK_SECTION_ID не
// фильтрует — возвращает все секции инфоблока, см. памятку по этому гочу).
const EPORTA_SHOWCASE_COLLECTION_IDS = [184, 185, 186, 187, 188, 189, 190, 191, 193, 194];

function eportaShowcaseUserHasAccess(): bool {
    global $USER;
    if (!$USER->IsAuthorized()) {
        return false;
    }
    if ($USER->IsAdmin()) {
        return true;
    }
    return CIBlock::GetPermission(EPORTA_SHOWCASE_IBLOCK_ID) >= 'W';
}

// Список коллекций для селектора — только те, где реально есть активные элементы.
function eportaShowcaseGetCollections(): array {
    $result = [];
    $res = CIBlockSection::GetList(
        ['SORT' => 'ASC'],
        ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID, 'ID' => EPORTA_SHOWCASE_COLLECTION_IDS, 'ACTIVE' => 'Y'],
        false,
        ['ID', 'NAME', 'CODE']
    );
    while ($section = $res->GetNext()) {
        $result[] = $section;
    }
    return $result;
}

// Модели коллекции: группировка по PROPERTY_MODEL (тот же ключ, что и в catalog/index.php),
// внутри каждой модели — все варианты (цвета) с фото и текущим флагом SHOWCASE.
function eportaShowcaseGetModels(int $sectionId): array {
    $res = CIBlockElement::GetList(
        ['ID' => 'ASC'],
        ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID, 'ACTIVE' => 'Y', 'SECTION_ID' => $sectionId],
        false,
        false,
        ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PROPERTY_MODEL', 'PROPERTY_RATING', 'PROPERTY_SHOWCASE', 'PROPERTY_COATING_COLOR']
    );
    $models = [];
    while ($row = $res->Fetch()) {
        $modelKey = $row['PROPERTY_MODEL_VALUE'] ?: ('__id_' . $row['ID']);
        $photoId = $row['PREVIEW_PICTURE'] ?: $row['DETAIL_PICTURE'];
        $models[$modelKey]['name'] = $row['PROPERTY_MODEL_VALUE'] ?: $row['NAME'];
        $models[$modelKey]['variants'][] = [
            'id' => (int)$row['ID'],
            'color' => $row['PROPERTY_COATING_COLOR_VALUE'] ?? '',
            'rating' => (float)($row['PROPERTY_RATING_VALUE'] ?? 0),
            'photo' => $photoId ? CFile::GetPath($photoId) : '',
            'is_showcase' => ($row['PROPERTY_SHOWCASE_VALUE'] ?? '') === 'Y',
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
