<?php
// Site-specific доп. код (не путать с bitrix/ — тот общий с dverimarket.ru).

AddEventHandler('main', 'OnBuildGlobalMenu', 'eportaOnBuildGlobalMenu');

// Автогенерация WebP сразу при сохранении элемента через админку (Этап 5 перфоманса,
// 2026-08-08) — раньше WebP появлялся только через разовый батч (scripts/import/convert_webp.php,
// теперь ещё и по крону раз в сутки как подстраховка), из-за чего свежезалитые баннеры/фото
// сутки висели без WebP-варианта. Хук точечный: срабатывает на КАЖДОЕ сохранение элемента
// в инсталляции (общей с dverimarket.ru), поэтому сразу отсеиваем чужие IBLOCK_ID —
// картинки не наших разделов не трогаем и не грузим GD зря. 19 = каталог товаров,
// 27 = баннеры слайдера главной (см. index.php).
AddEventHandler('iblock', 'OnAfterIBlockElementAdd', 'eportaOnIBlockElementSaveGenerateWebp');
AddEventHandler('iblock', 'OnAfterIBlockElementUpdate', 'eportaOnIBlockElementSaveGenerateWebp');

function eportaOnIBlockElementSaveGenerateWebp(&$arFields) {
    static $eportaWebpIblockIds = [19, 27];
    if (empty($arFields['IBLOCK_ID']) || !in_array((int)$arFields['IBLOCK_ID'], $eportaWebpIblockIds, true)) {
        return;
    }
    if (empty($arFields['ID'])) {
        return;
    }
    require_once($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_import/webp_convert.php');

    $fileIds = [];
    if (!empty($arFields['DETAIL_PICTURE'])) $fileIds[] = $arFields['DETAIL_PICTURE'];
    if (!empty($arFields['PREVIEW_PICTURE'])) $fileIds[] = $arFields['PREVIEW_PICTURE'];
    if (!$fileIds) {
        // Значения картинок не всегда попадают в $arFields (зависит от того, что реально
        // менялось при Update) — на всякий случай перечитываем элемент, это дешёвая операция
        // (один SELECT), а без неё часть сохранений вообще не породит WebP.
        $el = CIBlockElement::GetByID((int)$arFields['ID'])->GetNext();
        if ($el) {
            if (!empty($el['DETAIL_PICTURE'])) $fileIds[] = $el['DETAIL_PICTURE'];
            if (!empty($el['PREVIEW_PICTURE'])) $fileIds[] = $el['PREVIEW_PICTURE'];
        }
    }
    foreach (array_unique($fileIds) as $fileId) {
        $path = CFile::GetPath((int)$fileId);
        if ($path) {
            eportaWebpConvertFile($_SERVER['DOCUMENT_ROOT'] . $path);
        }
    }
}

// Поиск по артикулу (CML2_ARTICLE) — общий хелпер для search/index.php и search/suggest.php,
// чтобы страница поиска и подсказки в шапке не расходились в результатах (та же причина,
// по которой suggest.php был переписан на CSearch — см. его комментарий). Артикул отмечен
// SEARCHABLE=N по умолчанию (вендорское свойство CML2_ARTICLE не индексируется), а
// переиндексация полнотекстового поиска — тяжёлая и не мгновенная операция, поэтому здесь —
// прямой точный/частичный поиск по свойству, а не ожидание индекса CSearch.
// Возвращает ID элементов по убыванию релевантности: сначала точное совпадение артикула,
// затем частичное (артикул содержит запрос), без дублей.
// Склонение числительных для русских подписей ("N товар/товара/товаров") — используется в
// каталоге (catalog/index.php, подпись "Найдено N товаров (M моделей)").
function eportaPluralRu(int $n, string $one, string $few, string $many): string {
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $many;
    }
    if ($n1 > 1 && $n1 < 5) {
        return $few;
    }
    if ($n1 === 1) {
        return $one;
    }
    return $many;
}

function eportaFindArticleMatches(string $query, int $iblockId, int $limit = 50): array {
    $query = trim($query);
    if ($query === '' || $iblockId <= 0 || !CModule::IncludeModule('iblock')) {
        return [];
    }
    $ids = [];

    $exactRes = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'PROPERTY_CML2_ARTICLE' => $query],
        false,
        ['nTopCount' => $limit],
        ['ID']
    );
    while ($row = $exactRes->Fetch()) {
        $ids[(int)$row['ID']] = true;
    }

    if (count($ids) < $limit) {
        $partialRes = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', '%PROPERTY_CML2_ARTICLE' => $query],
            false,
            ['nTopCount' => $limit],
            ['ID']
        );
        while ($row = $partialRes->Fetch()) {
            $ids[(int)$row['ID']] = true;
        }
    }

    return array_slice(array_keys($ids), 0, $limit);
}

function eportaOnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu) {
    global $USER;
    if (!CModule::IncludeModule('iblock')) {
        return;
    }
    if (!$USER->IsAdmin() && CIBlock::GetPermission(19) < 'W') {
        return;
    }
    $aModuleMenu[] = [
        'parent_menu' => 'global_menu_content',
        'sort' => 700,
        'text' => 'Загрузка товаров из 1С',
        'title' => 'Загрузка товаров из xlsx в каталог (IBLOCK 19)',
        'icon' => 'iblock_menu_icon',
        'page_icon' => 'iblock_menu_icon',
        'items_id' => 'menu_eporta_import',
        'url' => '/local/admin_tools/eporta_import/',
        'more_url' => ['/local/admin_tools/eporta_import/'],
    ];
    $aModuleMenu[] = [
        'parent_menu' => 'global_menu_content',
        'sort' => 710,
        'text' => 'Баннеры главной (категории/коллекции)',
        'title' => 'Замена картинок плиток "Каталог по категориям" и "Коллекции фабрики"',
        'icon' => 'iblock_menu_icon',
        'page_icon' => 'iblock_menu_icon',
        'items_id' => 'menu_eporta_banners',
        'url' => '/local/admin_tools/eporta_banners/',
        'more_url' => ['/local/admin_tools/eporta_banners/'],
    ];
}
