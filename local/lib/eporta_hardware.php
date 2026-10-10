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

// Типы изделий фурнитуры (этап 3): разделы IBLOCK 19 — дети отдельного раздела «Фурнитура» (CODE hardware),
// вне раздела 183 «Коллекции», поэтому eportaCollections() и страницы дверей их не видят.
const EPORTA_HARDWARE_KINDS_PARENT_CODE = 'hardware';

// Окончательный список типов в порядке показа (решение заказчика 10.10). Источник для скрипта создания разделов.
function eportaHardwareKindDefs(): array {
    return [
        ['CODE' => 'ruchki', 'NAME' => 'Ручки'],
        ['CODE' => 'petli', 'NAME' => 'Петли'],
        ['CODE' => 'zashchelki', 'NAME' => 'Защёлки'],
        ['CODE' => 'fiksatory', 'NAME' => 'Фиксаторы'],
        ['CODE' => 'ogranichiteli', 'NAME' => 'Ограничители'],
        ['CODE' => 'nakladki', 'NAME' => 'Накладки'],
        ['CODE' => 'tsilindry', 'NAME' => 'Цилиндры'],
        ['CODE' => 'zamki', 'NAME' => 'Замки'],
        ['CODE' => 'dovodchiki', 'NAME' => 'Доводчики'],
        ['CODE' => 'zadvizhki', 'NAME' => 'Задвижки'],
        ['CODE' => 'porogi', 'NAME' => 'Пороги'],
    ];
}

// Строки разделов (ID, NAME, CODE, IBLOCK_SECTION_ID) -> карта «имя в нижнем регистре => ID» только по детям
// $parentId. Родителя фильтруем в PHP: CIBlockSection::GetList по IBLOCK_SECTION_ID не фильтрует.
function eportaSectionNameMap(array $rows, int $parentId): array {
    $map = [];
    foreach ($rows as $row) {
        if ((int)($row['IBLOCK_SECTION_ID'] ?? 0) !== $parentId || (int)$row['ID'] === $parentId) {
            continue;
        }
        $map[mb_strtolower(trim((string)$row['NAME']))] = (int)$row['ID'];
    }
    return $map;
}

// Все разделы IBLOCK 19 (кэш на запрос) — выбор по родителю делается в PHP.
function eportaHardwareAllSections(bool $onlyActive = true): array {
    static $cache = [];
    $key = $onlyActive ? 'a' : 'all';
    if (!isset($cache[$key])) {
        \CModule::IncludeModule('iblock');
        $filter = ['IBLOCK_ID' => 19];
        if ($onlyActive) {
            $filter['ACTIVE'] = 'Y';
        }
        $rows = [];
        $res = \CIBlockSection::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], $filter, false, ['ID', 'NAME', 'CODE', 'SORT', 'ACTIVE', 'IBLOCK_SECTION_ID']);
        while ($row = $res->Fetch()) {
            $rows[] = $row;
        }
        $cache[$key] = $rows;
    }
    return $cache[$key];
}

// ID раздела «Фурнитура» (0, если ещё не создан).
function eportaHardwareKindsParentId(): int {
    foreach (eportaHardwareAllSections(false) as $row) {
        if ($row['CODE'] === EPORTA_HARDWARE_KINDS_PARENT_CODE && (int)$row['IBLOCK_SECTION_ID'] === 0) {
            return (int)$row['ID'];
        }
    }
    return 0;
}

// Активные типы фурнитуры по SORT: [ID, NAME, CODE, ...].
function eportaHardwareKinds(): array {
    $parentId = eportaHardwareKindsParentId();
    if ($parentId <= 0) {
        return [];
    }
    return array_values(array_filter(eportaHardwareAllSections(true), function ($row) use ($parentId) {
        return (int)$row['IBLOCK_SECTION_ID'] === $parentId && $row['CODE'] !== '';
    }));
}

// Проверка названия типа (админка типов): непустое, не длиннее 100, не дублирует другой тип того же родителя
// (импорт ищет тип по названию без учёта регистра — дубль сделал бы выбор неоднозначным). null — можно.
function eportaHardwareKindValidateName(array $rows, int $parentId, string $name, int $exceptId = 0): ?string {
    $name = trim($name);
    if ($name === '') {
        return 'Название не может быть пустым';
    }
    if (mb_strlen($name) > 100) {
        return 'Название длиннее 100 символов';
    }
    foreach ($rows as $row) {
        if ((int)$row['IBLOCK_SECTION_ID'] === $parentId && (int)$row['ID'] !== $exceptId && (int)$row['ID'] !== $parentId
            && mb_strtolower(trim((string)$row['NAME'])) === mb_strtolower($name)) {
            return 'Тип с таким названием уже есть';
        }
    }
    return null;
}

function eportaHardwareKindUrl(string $code): string {
    return '/catalog/?category=hardware' . ($code !== '' ? '&type=' . rawurlencode($code) : '');
}

// Тип по коду из GET (null — неизвестный/пустой).
function eportaHardwareFindKind(array $kinds, string $code): ?array {
    if ($code === '') {
        return null;
    }
    foreach ($kinds as $kind) {
        if ($kind['CODE'] === $code) {
            return $kind;
        }
    }
    return null;
}

// ID типа => число товаров: один запрос с группировкой по разделу в области $scopeFilter (категория без пользовательских фильтров).
function eportaHardwareKindCounts(array $scopeFilter, array $kinds): array {
    $ids = array_map('intval', array_column($kinds, 'ID'));
    $counts = [];
    if (!$ids) {
        return $counts;
    }
    $res = \CIBlockElement::GetList([], $scopeFilter + ['IBLOCK_ID' => 19, 'ACTIVE' => 'Y', 'SECTION_ID' => $ids], ['IBLOCK_SECTION_ID'], false, ['ID', 'IBLOCK_SECTION_ID', 'CNT']);
    while ($row = $res->Fetch()) {
        $counts[(int)$row['IBLOCK_SECTION_ID']] = (int)($row['CNT'] ?? 0);
    }
    return $counts;
}

// Кнопки полоски типов: только типы с товарами; активный — со ссылкой на всю категорию (клик снимает тип).
function eportaHardwareKindsNav(array $kinds, array $counts, string $activeCode): array {
    $items = [];
    foreach ($kinds as $kind) {
        $count = (int)($counts[(int)$kind['ID']] ?? 0);
        if ($count < 1) {
            continue;
        }
        $active = $activeCode !== '' && $kind['CODE'] === $activeCode;
        $items[] = [
            'NAME' => (string)$kind['NAME'],
            'CODE' => (string)$kind['CODE'],
            'COUNT' => $count,
            'ACTIVE' => $active,
            'URL' => $active ? eportaHardwareKindUrl('') : eportaHardwareKindUrl((string)$kind['CODE']),
        ];
    }
    return $items;
}

// Крошки фурнитуры. $kind — выбранный тип (null — страница категории); $last — текст последнего звена (товар), иначе последним идёт тип/«Фурнитура».
function eportaHardwareBreadcrumb(?array $kind, ?string $last = null): array {
    $items = [['Главная', '/'], ['Каталог', '/catalog/']];
    if ($kind === null && $last === null) {
        $items[] = ['Фурнитура'];
        return $items;
    }
    $items[] = ['Фурнитура', eportaHardwareKindUrl('')];
    if ($kind !== null) {
        $items[] = $last === null ? [(string)$kind['NAME']] : [(string)$kind['NAME'], eportaHardwareKindUrl((string)$kind['CODE'])];
    }
    if ($last !== null) {
        $items[] = [$last];
    }
    return $items;
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
