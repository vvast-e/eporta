<?php
// Разово заводит в IBLOCK 27 (баннеры) раздел "Плитки главной" (CODE=home_tiles) и переносит в
// него уже существующие элементы, у которых PROPERTY_PLACEMENT — один из слотов плиток "Категории"/
// "Коллекции" (см. eportaBannersSlots() в local/admin_tools/eporta_banners/lib.php). Нужен, чтобы
// разделить в общем списке элементов ИБ 27 слайды главной карусели (без раздела, PLACEMENT
// main/side1/side2) от слотовых баннеров плиток — см. index.php (баг: баннер "Категории"
// показывался и как плитка, и как большой слайд карусели). Запуск:
// php scripts/add_iblock27_tiles_section.php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');
require_once($_SERVER['DOCUMENT_ROOT'].'/local/admin_tools/eporta_banners/lib.php');

$IBLOCK_ID = EPORTA_BANNERS_IBLOCK_ID; // 27
$SECTION_CODE = 'home_tiles';

$sectionRes = CIBlockSection::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $SECTION_CODE], false, ['ID']);
$section = $sectionRes->Fetch();
if ($section) {
    $sectionId = (int)$section['ID'];
    echo "Раздел $SECTION_CODE уже существует, ID=$sectionId\n";
} else {
    $sectionObj = new CIBlockSection;
    $sectionId = $sectionObj->Add([
        'IBLOCK_ID' => $IBLOCK_ID,
        'NAME' => 'Плитки главной',
        'CODE' => $SECTION_CODE,
        'ACTIVE' => 'Y',
        'SORT' => 100,
    ]);
    if (!$sectionId) {
        die('Ошибка создания раздела: ' . $sectionObj->LAST_ERROR . "\n");
    }
    echo "Раздел $SECTION_CODE создан, ID=$sectionId\n";
}

// Enum ID -> XML_ID для PLACEMENT, чтобы отличить слотовые баннеры (cat_*/coll_*) от слайдов
// карусели (main/side1/side2/пусто) — тот же приём, что в eportaBannersGetSlotElements().
$enumRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'PLACEMENT']);
$enumIdToXmlId = [];
while ($e = $enumRes->Fetch()) {
    $enumIdToXmlId[$e['ID']] = $e['XML_ID'];
}

$slotCodes = array_keys(eportaBannersSlots());

$elRes = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => $IBLOCK_ID, 'ACTIVE' => 'Y'],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_PLACEMENT', 'IBLOCK_SECTION_ID']
);
$elObj = new CIBlockElement;
$moved = 0;
while ($el = $elRes->Fetch()) {
    $xmlId = $enumIdToXmlId[$el['PROPERTY_PLACEMENT_ENUM_ID'] ?? null] ?? null;
    if ($xmlId === null || !in_array($xmlId, $slotCodes, true)) {
        continue;
    }
    if ((int)$el['IBLOCK_SECTION_ID'] === $sectionId) {
        continue;
    }
    $ok = $elObj->Update((int)$el['ID'], ['IBLOCK_SECTION_ID' => $sectionId]);
    echo $ok
        ? "Элемент {$el['ID']} ({$el['NAME']}, слот $xmlId) перенесён в раздел\n"
        : "Ошибка переноса элемента {$el['ID']}: {$elObj->LAST_ERROR}\n";
    if ($ok) $moved++;
}

echo "Готово. Перенесено элементов: $moved\n";
