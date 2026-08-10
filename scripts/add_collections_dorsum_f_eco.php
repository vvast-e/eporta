<?php
// Разово заводит 2 новые коллекции — "Dorsum-F" и "Dorsum-Eco" — как секции IBLOCK 19 под
// родителем 183 ("Коллекции"), затем переносит в них уже существующие товары по значению
// строкового свойства COLLECTION (проставляется импортёром из 1С, см.
// local/admin_tools/eporta_import/lib.php:512). Секции коллекций — не отдельный инфоблок,
// см. collection/index.php и catalog/index.php ($eportaCollectionIds).
//
// Запуск на сервере: php scripts/add_collections_dorsum_f_eco.php          — dry-run (только счёт)
//                     php scripts/add_collections_dorsum_f_eco.php --apply  — реально создаёт/переносит
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;
$PARENT_SECTION_ID = 183;
$apply = in_array('--apply', $argv, true);

$collections = [
    'Dorsum-F' => 'dorsum-f',
    'Dorsum-Eco' => 'dorsum-eco',
];

echo $apply ? "Режим: APPLY (реальные изменения)\n" : "Режим: DRY-RUN (только просмотр, без записи; добавьте --apply для реального запуска)\n";
echo str_repeat('-', 60) . "\n";

foreach ($collections as $collectionName => $code) {
    echo "Коллекция \"$collectionName\" (CODE=$code)\n";

    // Товары с этим значением свойства COLLECTION, ещё не находящиеся в целевой секции.
    $matchRes = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $IBLOCK_ID, 'PROPERTY_COLLECTION' => $collectionName, 'ACTIVE' => 'Y'],
        false,
        false,
        ['ID', 'NAME', 'IBLOCK_SECTION_ID']
    );
    $matchedIds = [];
    while ($el = $matchRes->Fetch()) {
        $matchedIds[] = (int)$el['ID'];
    }
    $count = count($matchedIds);
    echo "  Найдено товаров с PROPERTY_COLLECTION=\"$collectionName\": $count\n";
    if ($count === 0) {
        echo "  Товаров пока нет — секция создаётся заранее (по решению пользователя), наполнится при следующей загрузке из 1С с правильным COLLECTION.\n";
    }

    if (!$apply) {
        echo "  (dry-run) Секция создана бы не была" . ($count ? ", товары не переносились бы" : "") . ".\n\n";
        continue;
    }

    // Секция уже может существовать (повторный запуск) — не дублируем.
    $existingRes = CIBlockSection::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $code], false, ['ID', 'NAME']);
    $existing = $existingRes->Fetch();

    if ($existing) {
        $sectionId = (int)$existing['ID'];
        echo "  Секция уже существует, ID=$sectionId\n";
    } else {
        $sectionObj = new CIBlockSection;
        $sectionId = $sectionObj->Add([
            'IBLOCK_ID' => $IBLOCK_ID,
            'IBLOCK_SECTION_ID' => $PARENT_SECTION_ID,
            'NAME' => $collectionName,
            'CODE' => $code,
            'ACTIVE' => 'Y',
        ]);
        if (!$sectionId) {
            echo "  ОШИБКА создания секции: {$sectionObj->LAST_ERROR}\n\n";
            continue;
        }
        echo "  Секция создана, ID=$sectionId\n";
    }

    $moved = 0;
    foreach ($matchedIds as $elId) {
        $ok = CIBlockElement::SetElementSection($elId, [$sectionId]);
        if ($ok) {
            $moved++;
        } else {
            echo "  ОШИБКА привязки элемента $elId к секции $sectionId\n";
        }
    }
    echo "  Перенесено товаров: $moved / $count\n";
    echo "  ВАЖНО: не забыть добавить ID=$sectionId в \$eportaCollectionIds в collection/index.php и catalog/index.php\n\n";
}

echo "Готово.\n";
