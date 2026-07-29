<?php
// Site-specific доп. код (не путать с bitrix/ — тот общий с dverimarket.ru).

AddEventHandler('main', 'OnBuildGlobalMenu', 'eportaOnBuildGlobalMenu');

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
