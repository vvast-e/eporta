<?php
// Админка «Популярные запросы» главной. Требует уже подключенный prolog_before.php (модули
// main/iblock). Права и поиск товаров переиспользуют eporta_home_tabs (та же модель: право записи
// в IBLOCK 19), хранение и нормализация — local/lib/eporta_quick_queries.php.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/eporta_home_tabs_common.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_home_tabs/lib.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/eporta_quick_queries.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/templates/eporta/inc/categories.php');

function eportaQuickQueriesUserHasAccess(): bool {
    return eportaHomeTabsUserHasAccess();
}

// Варианты для выбора фильтров — те же свойства, что в сайдбаре каталога (catalog/index.php):
// STYLE «Стиль», COATING «Покрытие», MAIN_COLOR «Цвет», SIZES (CSV «ШxВ[:надбавка]» в строке),
// категории из inc/categories.php. Возвращает [группа => [значение => подпись]].
function eportaQuickQueriesFilterOptions(): array {
    \Bitrix\Main\Loader::includeModule('iblock');
    $options = ['style' => [], 'coating' => [], 'color' => [], 'size' => [], 'category' => []];
    $props = ['style' => 'STYLE', 'coating' => 'COATING', 'color' => 'MAIN_COLOR'];
    foreach ($props as $key => $code) {
        $res = \CIBlockPropertyEnum::GetList(['SORT' => 'ASC', 'VALUE' => 'ASC'], ['IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID, 'CODE' => $code]);
        while ($enum = $res->Fetch()) {
            $options[$key][(int)$enum['ID']] = $enum['VALUE'];
        }
    }
    $sizes = [];
    $res = \CIBlockElement::GetList([], ['IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID, 'ACTIVE' => 'Y'], false, false, ['ID', 'PROPERTY_SIZES']);
    while ($row = $res->Fetch()) {
        $raw = trim((string)($row['PROPERTY_SIZES_VALUE'] ?? ''));
        if ($raw === '') {
            continue;
        }
        foreach (explode(',', $raw) as $token) {
            $token = str_replace(['х', 'Х', 'X'], 'x', trim($token));
            $token = trim(explode(':', $token, 2)[0]);
            if (preg_match('/^\d{2,5}x\d{2,5}$/', $token)) {
                $sizes[$token] = true;
            }
        }
    }
    $sizes = array_keys($sizes);
    usort($sizes, function ($a, $b) {
        return array_map('intval', explode('x', $a, 2)) <=> array_map('intval', explode('x', $b, 2));
    });
    foreach ($sizes as $size) {
        $options['size'][$size] = $size;
    }
    foreach (eportaGetCategoryMap() as $key => $category) {
        $options['category'][$key] = $category['LABEL'];
    }
    return $options;
}

// Допустимые значения для eportaQuickQueriesNormalize() — отсекает всё, чего нет в вариантах выше.
function eportaQuickQueriesAllowedFromOptions(array $options): array {
    return [
        'style' => array_keys($options['style']),
        'coating' => array_keys($options['coating']),
        'color' => array_keys($options['color']),
        'size' => array_keys($options['size']),
        'category' => array_map('strval', array_keys($options['category'])),
    ];
}

// Сохранение: нормализация по реальным вариантам фильтров, закреплённые — только существующие
// активные товары каталога (в присланном порядке). Возвращает сохранённый список с готовыми URL.
function eportaQuickQueriesSanitizeAndSave(array $raw, array $options): array {
    $list = eportaQuickQueriesNormalize($raw, eportaQuickQueriesAllowedFromOptions($options));
    $allPinned = [];
    foreach ($list as $query) {
        $allPinned = array_merge($allPinned, $query['pinned']);
    }
    $info = eportaHomeTabsGetProductsInfo($allPinned);
    foreach ($list as $i => $query) {
        $list[$i]['pinned'] = array_values(array_filter($query['pinned'], function ($id) use ($info) {
            return isset($info[$id]) && $info[$id]['active'];
        }));
    }
    eportaQuickQueriesSave($list);
    return $list;
}
