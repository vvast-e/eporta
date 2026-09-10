<?php
// Общая логика табов "Хиты / Распродажа / Новинки" на главной (Этап 3) — подключается и из
// index.php (публичный рендер), и из local/admin_tools/eporta_home_tabs/lib.php (админка).
// Настройки хранятся не в новом инфоблоке/сущности, а одним JSON в COption (псевдо-модуль
// "eporta.home", опция "tabs") — см. eportaHomeTabsGetConfig().
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

const EPORTA_HOME_TABS_IBLOCK_ID = 19;
const EPORTA_HOME_TABS_OPTION_MODULE = 'eporta.home';
const EPORTA_HOME_TABS_OPTION_NAME = 'tabs';
// Право на редактирование — та же модель, что у баннеров/импорта (запись в каталог IBLOCK 19).
const EPORTA_HOME_TABS_PERMISSION_IBLOCK_ID = 19;

// Порядок табов фиксирован (не настраивается в админке — заказчик просил именно эти три,
// в этом порядке); ключ таба одновременно и его "признак" автоотбора (см. eportaHomeTabsAutoFilter).
function eportaHomeTabsKeys(): array {
    return ['hit', 'sale', 'new'];
}

// limit — 10 по умолчанию и жёсткий потолок (см. eportaHomeTabsGetConfig ниже): блок теперь
// один ряд с горизонтальной прокруткой, а не сетка на N страниц, больше 10 карточек в ряду
// не задумано дизайном (заявка заказчика 10.09.2026).
const EPORTA_HOME_TABS_MAX_LIMIT = 10;

function eportaHomeTabsDefaults(): array {
    return [
        'hit' => ['title' => 'Хиты', 'limit' => EPORTA_HOME_TABS_MAX_LIMIT, 'pinned' => []],
        'sale' => ['title' => 'Распродажа', 'limit' => EPORTA_HOME_TABS_MAX_LIMIT, 'pinned' => []],
        'new' => ['title' => 'Новинки', 'limit' => EPORTA_HOME_TABS_MAX_LIMIT, 'pinned' => []],
    ];
}

// Enum ID List-свойства IBLOCK 19 по CODE+XML_ID — та же логика, что в catalog/index.php
// (eportaGetIblock19EnumId) и local/admin_tools/eporta_showcase/lib.php; не переиспользуем их
// функцию напрямую, чтобы этот файл не тянул catalog/index.php (не является подключаемым),
// поэтому своя копия под тем же именем через function_exists (см. комментарий там же про
// PROPERTY_CODE_VALUE vs PROPERTY_CODE_ENUM_ID).
if (!function_exists('eportaGetIblock19EnumId')) {
    function eportaGetIblock19EnumId(string $propertyCode, string $xmlId): ?int {
        static $cache = [];
        $key = $propertyCode . ':' . $xmlId;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $propRow = \CIBlockProperty::GetList([], ['IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID, 'CODE' => $propertyCode])->Fetch();
        if (!$propRow) {
            return $cache[$key] = null;
        }
        $enumRow = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propRow['ID'], 'XML_ID' => $xmlId])->Fetch();
        return $cache[$key] = $enumRow ? (int)$enumRow['ID'] : null;
    }
}

// Читает и нормализует настройки табов — недостающие/битые поля заполняются дефолтами, лимит
// зажимается в разумные рамки (1..60), pinned приводится к массиву уникальных int ID.
function eportaHomeTabsGetConfig(): array {
    $raw = \COption::GetOptionString(EPORTA_HOME_TABS_OPTION_MODULE, EPORTA_HOME_TABS_OPTION_NAME, '');
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    $config = eportaHomeTabsDefaults();
    if (is_array($decoded)) {
        foreach ($config as $key => $default) {
            if (!isset($decoded[$key]) || !is_array($decoded[$key])) {
                continue;
            }
            $title = trim((string)($decoded[$key]['title'] ?? ''));
            $config[$key]['title'] = $title !== '' ? $title : $default['title'];
            $limit = (int)($decoded[$key]['limit'] ?? $default['limit']);
            $config[$key]['limit'] = $limit > 0 ? min($limit, EPORTA_HOME_TABS_MAX_LIMIT) : $default['limit'];
            $pinned = $decoded[$key]['pinned'] ?? [];
            $config[$key]['pinned'] = is_array($pinned)
                ? array_values(array_unique(array_map('intval', $pinned)))
                : [];
        }
    }
    return $config;
}

function eportaHomeTabsSaveConfig(array $config): void {
    \COption::SetOptionString(EPORTA_HOME_TABS_OPTION_MODULE, EPORTA_HOME_TABS_OPTION_NAME, json_encode($config, JSON_UNESCAPED_UNICODE));
}

function eportaHomeTabsUserHasAccess(): bool {
    global $USER;
    if (!$USER->IsAuthorized()) {
        return false;
    }
    if ($USER->IsAdmin()) {
        return true;
    }
    return \CIBlock::GetPermission(EPORTA_HOME_TABS_PERMISSION_IBLOCK_ID) >= 'W';
}

// Фильтр автоотбора для таба — критерии из плана Этапа 3:
// hit — RATING >= 4.8 (тот же порог, что даёт плашку "ХИТ" на карточке);
// sale — явное поле SALE = "Y" (то же, что фильтр /catalog/?sale=1, catalog/index.php:287);
// new — RATING <= 0 (тот же, что плашка "Новинка").
function eportaHomeTabsAutoFilter(string $tabKey): array {
    $filter = ['IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID, 'ACTIVE' => 'Y'];
    if ($tabKey === 'sale') {
        $saleYEnumId = eportaGetIblock19EnumId('SALE', 'Y');
        // Свойство ещё может отсутствовать на окружении, где не прогнан add_iblock19_sale.php —
        // тогда фильтр по несуществующему ID честно отдаёт пустой список (см. тот же приём
        // в catalog/index.php).
        $filter['PROPERTY_SALE'] = $saleYEnumId ?: 0;
    } elseif ($tabKey === 'new') {
        $filter['<=PROPERTY_RATING'] = 0;
    } else {
        $filter['>=PROPERTY_RATING'] = 4.8;
    }
    return $filter;
}

// Итоговый список ID товаров для таба: сначала закреплённые (в заданном порядке, только
// реально существующие активные), затем автоотбор до лимита с исключением уже закреплённых.
function eportaHomeTabsResolveIds(string $tabKey, array $tabConfig): array {
    \Bitrix\Main\Loader::includeModule('iblock');
    $limit = (int)($tabConfig['limit'] ?? 8) ?: 8;
    $pinnedRequested = array_values(array_unique(array_map('intval', $tabConfig['pinned'] ?? [])));

    $ids = [];
    if ($pinnedRequested) {
        $existing = [];
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID, 'ACTIVE' => 'Y', 'ID' => $pinnedRequested],
            false, false,
            ['ID']
        );
        while ($row = $res->Fetch()) {
            $existing[(int)$row['ID']] = true;
        }
        // Порядок — как задан в настройках (drag-порядок из админки), не порядок из БД.
        foreach ($pinnedRequested as $pid) {
            if (isset($existing[$pid])) {
                $ids[] = $pid;
            }
        }
    }

    $remaining = $limit - count($ids);
    if ($remaining > 0) {
        $filter = eportaHomeTabsAutoFilter($tabKey);
        if ($ids) {
            $filter['!ID'] = $ids;
        }
        $res = \CIBlockElement::GetList(
            ['sort' => 'asc', 'id' => 'desc'],
            $filter,
            false,
            ['nTopCount' => $remaining],
            ['ID']
        );
        while ($row = $res->Fetch()) {
            $ids[] = (int)$row['ID'];
        }
    }

    return array_slice($ids, 0, $limit);
}

// Рендер одной плитки-грида таба существующим компонентом bitrix:catalog.section/.default —
// три вызова (по одному на таб) с разными глобальными фильтрами (разные имена переменных,
// поэтому и разные ключи компонентного кэша сами по себе) + CACHE_FILTER=>"Y", чтобы кэш также
// учитывал СОДЕРЖИМОЕ фильтра (список ID) — иначе после правки закреплённых товаров в админке
// компонент час отдавал бы старый набор из managed cache. Порядок вывода (закреплённые в
// заданном порядке, потом автоотбор) компонентными параметрами не выражается — прокидывается
// через global $arrEportaHomeTabOrder, который читает шаблон catalog.section/.default/template.php
// и один раз использует для usort() перед выводом (см. комментарий там же).
function eportaHomeTabsRenderCatalogSection(string $tabKey, string $filterGlobalName, array $ids): void {
    global $APPLICATION;

    global $$filterGlobalName;
    $$filterGlobalName = [
        'IBLOCK_ID' => EPORTA_HOME_TABS_IBLOCK_ID,
        'ACTIVE' => 'Y',
        // Пустой ID-фильтр в классическом API молча игнорируется (отдаёт вообще все товары) —
        // подставляем заведомо несуществующий ID, чтобы честно получить пустой список.
        'ID' => $ids ?: [0],
    ];

    global $arrEportaHomeTabOrder;
    $arrEportaHomeTabOrder = $ids;

    $APPLICATION->IncludeComponent(
        'bitrix:catalog.section',
        '.default',
        [
            'IBLOCK_TYPE' => 'catalog',
            'IBLOCK_ID' => (string)EPORTA_HOME_TABS_IBLOCK_ID,
            'SECTION_ID' => false,
            'SECTION_CODE' => '',
            'SECTION_USER_FIELDS' => [],
            'ELEMENT_SORT_FIELD' => 'sort',
            'ELEMENT_SORT_ORDER' => 'asc',
            'ELEMENT_SORT_FIELD2' => 'id',
            'ELEMENT_SORT_ORDER2' => 'desc',
            'FILTER_NAME' => $filterGlobalName,
            'HIDE_NOT_AVAILABLE' => 'N',
            'HIDE_NOT_AVAILABLE_OFFERS' => 'N',
            // Один ряд с горизонтальной прокруткой (не пейджинг) — PAGE_ELEMENT_COUNT равен
            // максимально допустимому лимиту (EPORTA_HOME_TABS_MAX_LIMIT), чтобы компонент не
            // обрезал список раньше, чем это сделает сам $ids. LINE_ELEMENT_COUNT на вывод не
            // влияет — обёртка .eporta-product-grid отключена через display:contents в CSS
            // (.home-tabs-banner__scroll), карточки — обычные flex-элементы прокручиваемой строки.
            'PAGE_ELEMENT_COUNT' => (string)EPORTA_HOME_TABS_MAX_LIMIT,
            'LINE_ELEMENT_COUNT' => (string)EPORTA_HOME_TABS_MAX_LIMIT,
            'PROPERTY_CODE' => ['STYLE', 'COATING_COLOR', 'GLAZING', 'MAIN_COLOR', 'PRODUCT_DAY', 'RATING', 'VOTE_COUNT', 'CML2_ARTICLE'],
            'OFFERS_FIELD_CODE' => [],
            'OFFERS_PROPERTY_CODE' => [],
            'BACKGROUND_IMAGE' => '-',
            'LABEL_PROP' => '-',
            'PRODUCT_SUBSCRIPTION' => 'N',
            'SHOW_DISCOUNT_PERCENT' => 'Y',
            'SHOW_OLD_PRICE' => 'Y',
            'PRICE_CODE' => ['BASE'],
            'USE_PRICE_COUNT' => 'N',
            'SHOW_PRICE_COUNT' => '1',
            'PRICE_VAT_INCLUDE' => 'Y',
            'CONVERT_CURRENCY' => 'N',
            'BASKET_URL' => '/personal/cart/',
            'ACTION_VARIABLE' => 'action',
            'PRODUCT_ID_VARIABLE' => 'id',
            'PRODUCT_QUANTITY_VARIABLE' => 'quantity',
            'ADD_PROPERTIES_TO_BASKET' => 'Y',
            'PRODUCT_PROPS_VARIABLE' => 'prop',
            'PARTIAL_PRODUCT_PROPERTIES' => 'N',
            'USE_PRODUCT_QUANTITY' => 'N',
            'CACHE_TYPE' => 'A',
            'CACHE_TIME' => '3600',
            'CACHE_GROUPS' => 'N',
            'CACHE_FILTER' => 'Y',
            'DISPLAY_COMPARE' => 'N',
            'SET_TITLE' => 'N',
            'SET_STATUS_404' => 'N',
            'SEF_MODE' => 'N',
            'PAGER_TEMPLATE' => 'round',
            'DISPLAY_TOP_PAGER' => 'N',
            'DISPLAY_BOTTOM_PAGER' => 'N',
            'PAGER_TITLE' => 'Товары',
            'PAGER_SHOW_ALWAYS' => 'N',
            'PAGER_SHOW_ALL' => 'N',
            'ADD_SECTIONS_CHAIN' => 'N',
            'COMPATIBLE_MODE' => 'Y',
            'AJAX_MODE' => 'N',
            'TEMPLATE_THEME' => 'site',
        ],
        false
    );
}
