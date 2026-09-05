<?php
// Настройка коллекций фабрики под заявку заказчика 05.09.2026: заводит недостающую секцию
// Vetus-Loft, проставляет заданный порядок показа (SORT) 11 коллекциям, добавляет недостающие
// enum-значения PLACEMENT в IBLOCK 27 для коллекций без слота баннера (Vetus/Invi/Dorsum-F/
// Dorsum-Eco/Vetus-Loft) и компенсирует инверсию семантики свойства OVERLAY (пусто теперь
// значит "выключено", было наоборот) — проставляет явное "Да" всем баннерам IBLOCK 27, где
// OVERLAY ещё не заполнено, чтобы существующие баннеры не потеряли затенение молча.
//
// Идемпотентно: повторный запуск ничего не ломает.
// Запуск: php scripts/setup_collections_order.php          — dry-run (только просмотр)
//         php scripts/setup_collections_order.php --apply  — реальные изменения
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require($_SERVER['DOCUMENT_ROOT'].'/local/admin_tools/eporta_collections/lib.php');

CModule::IncludeModule('iblock');

$apply = in_array('--apply', $argv, true);
echo $apply ? "Режим: APPLY (реальные изменения)\n" : "Режим: DRY-RUN (только просмотр, без записи; добавьте --apply для реального запуска)\n";
echo str_repeat('-', 60) . "\n";

// Порядок задан заказчиком: Dorsum-Eco, Invi, Lacuna, Vetus-Loft, Vilis, Tabula, Dorsum-F,
// Actus, Dorsum, Vetus, Vitrum.
$order = [
    'dorsum-eco' => 100,
    'invi' => 200,
    'lacuna' => 300,
    'vetus-loft' => 400,
    'vilis' => 500,
    'tabula' => 600,
    'dorsum-f' => 700,
    'actus' => 800,
    'dorsum' => 900,
    'vetus' => 1000,
    'vitrum' => 1100,
];

// 1) Vetus-Loft — создать, если ещё нет.
echo "Шаг 1: секция Vetus-Loft\n";
$existingVetusLoft = CIBlockSection::GetList([], ['IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID, 'CODE' => 'vetus-loft'], false, ['ID'])->Fetch();
if ($existingVetusLoft) {
    echo "  Уже существует, ID={$existingVetusLoft['ID']}\n";
} elseif (!$apply) {
    echo "  (dry-run) Была бы создана секция 'Vetus-Loft' (CODE=vetus-loft) под родителем " . EPORTA_COLLECTIONS_PARENT_SECTION_ID . "\n";
} else {
    $sectionObj = new CIBlockSection;
    $newId = $sectionObj->Add([
        'IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID,
        'IBLOCK_SECTION_ID' => EPORTA_COLLECTIONS_PARENT_SECTION_ID,
        'NAME' => 'Vetus-Loft',
        'CODE' => 'vetus-loft',
        'SORT' => $order['vetus-loft'],
        'ACTIVE' => 'Y',
    ]);
    echo $newId ? "  Создана, ID=$newId\n" : "  ОШИБКА: {$sectionObj->LAST_ERROR}\n";
}

// 2) SORT для всех коллекций из таблицы.
echo "\nШаг 2: порядок показа (SORT)\n";
$allSections = CIBlockSection::GetList([], ['IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID, '=IBLOCK_SECTION_ID' => EPORTA_COLLECTIONS_PARENT_SECTION_ID], false, ['ID', 'CODE', 'NAME', 'SORT']);
$byCode = [];
while ($s = $allSections->Fetch()) {
    $byCode[$s['CODE']] = $s;
}
foreach ($order as $code => $sort) {
    $section = $byCode[$code] ?? null;
    if (!$section) {
        echo "  Пропуск: секция с CODE=$code не найдена (создастся выше на следующем запуске, если это vetus-loft)\n";
        continue;
    }
    if ((int)$section['SORT'] === $sort) {
        echo "  {$section['NAME']}: уже SORT=$sort\n";
        continue;
    }
    if (!$apply) {
        echo "  (dry-run) {$section['NAME']}: SORT {$section['SORT']} -> $sort\n";
        continue;
    }
    $sectionObj = new CIBlockSection;
    $ok = $sectionObj->Update((int)$section['ID'], ['SORT' => $sort]);
    echo $ok ? "  {$section['NAME']}: SORT -> $sort\n" : "  ОШИБКА для {$section['NAME']}: {$sectionObj->LAST_ERROR}\n";
}

// 3) Недостающие enum-значения PLACEMENT в IBLOCK 27 для слотов баннеров.
echo "\nШаг 3: слоты баннеров (enum PLACEMENT в IBLOCK 27)\n";
$allSections2 = CIBlockSection::GetList([], ['IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID, '=IBLOCK_SECTION_ID' => EPORTA_COLLECTIONS_PARENT_SECTION_ID], false, ['ID', 'CODE', 'NAME']);
while ($s = $allSections2->Fetch()) {
    $slotCode = eportaCollectionSlotCode($s['CODE']);
    $propRes = CIBlockProperty::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => 'PLACEMENT']);
    $prop = $propRes->Fetch();
    $existingEnum = $prop ? CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID'], 'XML_ID' => $slotCode])->Fetch() : null;
    if ($existingEnum) {
        echo "  {$s['NAME']} ($slotCode): слот уже существует\n";
        continue;
    }
    if (!$apply) {
        echo "  (dry-run) {$s['NAME']}: был бы создан слот $slotCode\n";
        continue;
    }
    $ok = eportaCollectionsEnsurePlacementEnum($s['CODE'], $s['NAME']);
    echo $ok ? "  {$s['NAME']}: слот $slotCode создан\n" : "  ОШИБКА создания слота для {$s['NAME']}\n";
}

// 4) Компенсация инверсии OVERLAY — существующие баннеры с пустым OVERLAY получают явное "Да",
// чтобы не потерять затенение молча (пусто теперь трактуется как "выключено", раньше — наоборот).
// ВАЖНО: только через SetPropertyValuesEx (см. eportaBannersSetListProperty в eporta_banners/
// lib.php) — Update()+PROPERTY_VALUES стирает остальные свойства элемента (инцидент 29.08.2026).
echo "\nШаг 4: компенсация инверсии OVERLAY (пусто -> явное \"Да\" для существующих баннеров)\n";
$overlayProp = CIBlockProperty::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => 'OVERLAY'])->Fetch();
if (!$overlayProp) {
    echo "  Свойство OVERLAY не найдено в IBLOCK " . EPORTA_BANNERS_IBLOCK_ID . " — пропуск\n";
} else {
    $overlayEnumRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => 'OVERLAY']);
    $overlayEnumIdToXmlId = [];
    while ($e = $overlayEnumRes->Fetch()) {
        $overlayEnumIdToXmlId[$e['ID']] = $e['XML_ID'];
    }
    $elRes = CIBlockElement::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID], false, false, ['ID', 'NAME', 'PROPERTY_OVERLAY']);
    $updated = 0;
    $checked = 0;
    while ($el = $elRes->Fetch()) {
        $checked++;
        $enumId = $el['PROPERTY_OVERLAY_ENUM_ID'] ?? null;
        $xmlId = $enumId ? ($overlayEnumIdToXmlId[$enumId] ?? '') : '';
        if ($xmlId !== '') {
            continue; // уже заполнено явно (Y или N) — не трогаем.
        }
        if (!$apply) {
            echo "  (dry-run) элемент #{$el['ID']} ({$el['NAME']}): OVERLAY пусто -> Да\n";
            continue;
        }
        $ok = eportaBannersSetListProperty((int)$el['ID'], 'OVERLAY', 'Y');
        if ($ok) {
            $updated++;
        } else {
            echo "  ОШИБКА для элемента #{$el['ID']}\n";
        }
    }
    echo "  Проверено элементов: $checked" . ($apply ? ", обновлено: $updated" : "") . "\n";
}

echo "\nГотово.\n";
