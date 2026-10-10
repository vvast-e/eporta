<?php
// «Популярные запросы» на главной — кнопки-ссылки в каталог с заранее выставленными фильтрами и
// (необязательно) закреплёнными вручную дверями. Подключается и из index.php (вывод), и из
// catalog/index.php (закрепление дверей в выдаче), и из админки local/admin_tools/eporta_quick_queries/.
// Настройки хранятся одним JSON в COption (псевдо-модуль "eporta.home", опция "quick_queries") — тот
// же приём, что у табов "Хиты/Распродажа/Новинки" (eporta_home_tabs_common.php), без новых таблиц.
// Функции нормализации/сборки URL/порядка выдачи — чистые (без БД), покрыты
// scripts/tests/quick_queries_test.php.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

const EPORTA_QUICK_QUERIES_OPTION_MODULE = 'eporta.home';
const EPORTA_QUICK_QUERIES_OPTION_NAME = 'quick_queries';
const EPORTA_QUICK_QUERIES_LABEL_MAX = 60;
const EPORTA_QUICK_QUERIES_PINNED_MAX = 200;

// Ключи фильтров каталога, которые умеет выставлять запрос: те же GET-параметры, что разбирает
// catalog/index.php (style/coating/color/size — массивы, price_min/price_max — числа, category —
// ключ из inc/categories.php, sale/new — флаги).
function eportaQuickQueriesListFilterKeys(): array {
    return ['style', 'coating', 'color', 'size'];
}

// Приводит один запрос к каноническому виду. $allowed (необязательный) сужает значения до реально
// существующих: ['style' => [ID...], 'coating' => [...], 'color' => [...], 'size' => ['900x2000'...],
// 'category' => ['mkd'...]] — так админка отсекает мусор, а чтение из БД остаётся толерантным.
function eportaQuickQueryNormalizeOne(array $raw, ?array $allowed = null): ?array {
    $label = trim((string)($raw['label'] ?? ''));
    if ($label === '') {
        return null;
    }
    $label = mb_substr($label, 0, EPORTA_QUICK_QUERIES_LABEL_MAX);
    $rawFilters = is_array($raw['filters'] ?? null) ? $raw['filters'] : [];

    $filters = [];
    foreach (['style', 'coating', 'color'] as $key) {
        $values = array_values(array_unique(array_filter(array_map('intval', (array)($rawFilters[$key] ?? [])), function ($v) {
            return $v > 0;
        })));
        if ($allowed !== null) {
            $values = array_values(array_intersect($values, array_map('intval', (array)($allowed[$key] ?? []))));
        }
        $filters[$key] = $values;
    }
    $sizes = [];
    foreach ((array)($rawFilters['size'] ?? []) as $size) {
        $size = trim((string)$size);
        if ($size !== '' && preg_match('/^\d{2,5}x\d{2,5}$/', $size)) {
            $sizes[$size] = true;
        }
    }
    $sizes = array_keys($sizes);
    if ($allowed !== null) {
        $sizes = array_values(array_intersect($sizes, array_map('strval', (array)($allowed['size'] ?? []))));
    }
    $filters['size'] = $sizes;

    $priceMin = (int)($rawFilters['price_min'] ?? 0);
    $priceMax = (int)($rawFilters['price_max'] ?? 0);
    $filters['price_min'] = $priceMin > 0 ? $priceMin : 0;
    $filters['price_max'] = $priceMax > 0 ? $priceMax : 0;
    if ($filters['price_min'] && $filters['price_max'] && $filters['price_min'] > $filters['price_max']) {
        $filters['price_min'] = 0;
        $filters['price_max'] = 0;
    }

    $category = trim((string)($rawFilters['category'] ?? ''));
    if ($category !== '' && $allowed !== null && !in_array($category, (array)($allowed['category'] ?? []), true)) {
        $category = '';
    }
    $filters['category'] = preg_match('/^[a-z0-9_-]{1,30}$/', $category) ? $category : '';
    // Фурнитура: «Стиль» и «Цвет» (MAIN_COLOR) — дверные свойства, у фурнитуры в каталоге «Цвет» — другое свойство
    // (COATING_COLOR), поэтому ID из дверного списка дали бы чужой фильтр — сбрасываем. Добавляется «Тип» (код раздела).
    $isHardware = $filters['category'] === 'hardware';
    if ($isHardware) {
        $filters['style'] = [];
        $filters['color'] = [];
    }
    $type = trim((string)($rawFilters['type'] ?? ''));
    if ($allowed !== null && !in_array($type, array_map('strval', (array)($allowed['type'] ?? [])), true)) {
        $type = '';
    }
    $filters['type'] = ($isHardware && preg_match('/^[a-z0-9_-]{1,40}$/', $type)) ? $type : '';
    $filters['sale'] = !empty($rawFilters['sale']);
    $filters['new'] = !empty($rawFilters['new']);

    $pinned = array_values(array_unique(array_filter(array_map('intval', (array)($raw['pinned'] ?? [])), function ($v) {
        return $v > 0;
    })));

    return [
        'id' => (int)($raw['id'] ?? 0),
        'label' => $label,
        'filters' => $filters,
        'pinned' => array_slice($pinned, 0, EPORTA_QUICK_QUERIES_PINNED_MAX),
    ];
}

// Список запросов целиком: порядок сохраняется как прислан, пустые подписи отбрасываются, ID
// должны быть уникальными положительными — недостающие/повторные назначаются как max+1.
function eportaQuickQueriesNormalize(array $rawList, ?array $allowed = null): array {
    $list = [];
    foreach ($rawList as $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $query = eportaQuickQueryNormalizeOne($raw, $allowed);
        if ($query !== null) {
            $list[] = $query;
        }
    }
    $used = [];
    $maxId = 0;
    foreach ($list as $query) {
        $maxId = max($maxId, $query['id']);
    }
    foreach ($list as $i => $query) {
        if ($query['id'] <= 0 || isset($used[$query['id']])) {
            $maxId++;
            $list[$i]['id'] = $maxId;
        }
        $used[$list[$i]['id']] = true;
    }
    return $list;
}

// null — настройки ещё ни разу не сохранялись (на главной показываем прежние кнопки), иначе
// нормализованный список (возможно пустой — админ сознательно всё удалил).
function eportaQuickQueriesGet(): ?array {
    $raw = \COption::GetOptionString(EPORTA_QUICK_QUERIES_OPTION_MODULE, EPORTA_QUICK_QUERIES_OPTION_NAME, '');
    if ($raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    return eportaQuickQueriesNormalize($decoded);
}

function eportaQuickQueriesSave(array $list): void {
    \COption::SetOptionString(EPORTA_QUICK_QUERIES_OPTION_MODULE, EPORTA_QUICK_QUERIES_OPTION_NAME, json_encode(array_values($list), JSON_UNESCAPED_UNICODE));
}

// Ссылка кнопки: /catalog/ + выставленные фильтры. Параметр pq (ID запроса) добавляется только если
// у запроса есть закреплённые двери — именно по нему каталог ставит их в начало выдачи; при смене
// фильтра пользователем AJAX-форма каталога pq не передаёт, и закреплённые двери пропадают.
function eportaQuickQueryUrl(array $query): string {
    $params = [];
    $filters = $query['filters'] ?? [];
    foreach (eportaQuickQueriesListFilterKeys() as $key) {
        if (!empty($filters[$key])) {
            $params[$key] = array_values($filters[$key]);
        }
    }
    if (!empty($filters['price_min'])) {
        $params['price_min'] = (int)$filters['price_min'];
    }
    if (!empty($filters['price_max'])) {
        $params['price_max'] = (int)$filters['price_max'];
    }
    if (!empty($filters['category'])) {
        $params['category'] = $filters['category'];
    }
    if (!empty($filters['type']) && ($filters['category'] ?? '') === 'hardware') {
        $params['type'] = $filters['type'];
    }
    if (!empty($filters['sale'])) {
        $params['sale'] = 1;
    }
    if (!empty($filters['new'])) {
        $params['new'] = 1;
    }
    if (!empty($query['pinned'])) {
        $params['pq'] = (int)$query['id'];
    }
    return '/catalog/' . ($params ? '?' . http_build_query($params) : '');
}

// Порядок выдачи: закреплённые двери (в заданном админом порядке) первыми, затем остальная выдача
// без повторов. Закреплённая дверь добавляется, даже если сама под фильтр не попала — «закреплённые
// двери + результат фильтра».
function eportaQuickQueryPinnedFirst(array $orderedIds, array $pinnedIds): array {
    $pinnedIds = array_values(array_unique(array_map('intval', $pinnedIds)));
    $rest = array_values(array_diff(array_map('intval', $orderedIds), $pinnedIds));
    return array_merge($pinnedIds, $rest);
}
