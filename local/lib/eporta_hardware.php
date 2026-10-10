<?php
// Фурнитура (категория hardware, заявка заказчика 07.10, п.14): общая логика витрины.
// Чистые функции без обращения к БД (кроме карты категорий) — тесты в scripts/tests/hardware_storefront_test.php.
// Раздел каталога с фурнитурой — те же товары IBLOCK 19, что и двери, отличаются свойством CATEGORY.

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/templates/eporta/inc/categories.php');

// Значение свойства CATEGORY (строка из выгрузки) относится к фурнитуре? Регистр не важен.
function eportaIsHardwareCategory(string $category): bool {
    $category = mb_strtolower(trim($category));
    if ($category === '') {
        return false;
    }
    $aliases = eportaGetCategoryMap()['hardware']['ALIASES'] ?? [];
    return in_array($category, array_map('mb_strtolower', $aliases), true);
}

// Группы чекбокс-фильтров сайдбара для фурнитуры (у дверей — Стиль/Покрытие/Цвет, см. catalog/index.php).
// TYPE 'enum' — свойство-список (фильтр по ID варианта), 'string' — строковое свойство (фильтр по точному
// значению, значения берутся из товаров текущей области): Бренд и Материал в IBLOCK 19 не списки.
// «Цвет» у фурнитуры — COATING_COLOR («Матовый никель/хром»): у дверей фильтр идёт по MAIN_COLOR
// («Оттенок»), но у фурнитуры оттенок не заполняется, а отделка — это и есть цвет.
function eportaHardwareFilterDefs(): array {
    return [
        'brand' => ['CODE' => 'BRAND', 'LABEL' => 'Бренд', 'FILTER_KEY' => '=PROPERTY_BRAND', 'TYPE' => 'string'],
        'series' => ['CODE' => 'SERIES', 'LABEL' => 'Серия', 'FILTER_KEY' => 'PROPERTY_SERIES', 'TYPE' => 'enum'],
        'coating' => ['CODE' => 'COATING', 'LABEL' => 'Покрытие', 'FILTER_KEY' => 'PROPERTY_COATING', 'TYPE' => 'enum'],
        'color' => ['CODE' => 'COATING_COLOR', 'LABEL' => 'Цвет', 'FILTER_KEY' => 'PROPERTY_COATING_COLOR', 'TYPE' => 'enum'],
        'material' => ['CODE' => 'MATERIAL', 'LABEL' => 'Материал', 'FILTER_KEY' => '=PROPERTY_MATERIAL', 'TYPE' => 'string'],
    ];
}

// Схлопывание выдачи по модели: из каждой группы берётся первый (лучший по текущей сортировке) товар.
// $groups — [ключ модели => [['ID'=>, 'SORT'=>], ...]] в порядке показа; внутри группы уже отсортировано.
// Возвращает плоский список ID в порядке групп.
function eportaCollapseModelGroups(array $groups): array {
    $ids = [];
    foreach ($groups as $items) {
        if (!empty($items)) {
            $ids[] = $items[0]['ID'];
        }
    }
    return $ids;
}
