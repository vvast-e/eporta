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
}
