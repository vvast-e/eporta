<?php
// Хелперы блока "Карта салонов" на главной. В отличие от "Наши работы"/"Отзывы" — без своего
// инфоблока и без кастомной админки: данные берутся из штатного модуля catalog (b_catalog_store,
// тот же источник, что и страница /stores/, bitrix:catalog.store), наполняется через штатную
// админку Bitrix (Магазины → Склады, /bitrix/admin/). На 24.09.2026 там 0 записей — секция
// на главной не рендерится, пока не появится хотя бы один активный склад с координатами.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// Ключ Яндекс.Карт — читается из стандартной опции модуля fileman (то же место, где его
// проверяли на 24.09.2026: пусто). Пока ключа нет, блок остаётся со списком салонов без JS-карты
// (см. eportaStoresMapHasKey() в index.php) — сайт не ломается, просто карта не грузится.
function eportaStoresMapApiKey(): string {
    return trim((string)\Bitrix\Main\Config\Option::get('fileman', 'yandex_map_api_key', ''));
}

// Активные склады с координатами (без координат — на карте показать нечего, в списке всё равно
// нужны, поэтому не фильтруем по GPS на уровне выборки, а помечаем HAS_COORDS для шаблона).
function eportaStoresMapList(): array {
    if (!CModule::IncludeModule('catalog')) {
        return [];
    }
    $res = \CCatalogStore::GetList(
        ['SORT' => 'ASC'],
        ['ACTIVE' => 'Y'],
        false,
        false,
        ['ID', 'TITLE', 'ADDRESS', 'PHONE', 'SCHEDULE', 'GPS_N', 'GPS_S']
    );
    $items = [];
    while ($s = $res->Fetch()) {
        $lat = trim((string)($s['GPS_N'] ?? ''));
        $lon = trim((string)($s['GPS_S'] ?? ''));
        $items[] = [
            'ID' => (int)$s['ID'],
            'TITLE' => (string)$s['TITLE'],
            'ADDRESS' => (string)$s['ADDRESS'],
            'PHONE' => (string)$s['PHONE'],
            'SCHEDULE' => (string)$s['SCHEDULE'],
            'LAT' => $lat,
            'LON' => $lon,
            'HAS_COORDS' => ($lat !== '' && $lon !== ''),
        ];
    }
    return $items;
}
