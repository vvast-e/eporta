<?php
// Бэкфилл: у уже загруженных товаров (IBLOCK 19), где заполнено свойство "Кромка" (EDGE),
// но текущий NAME был собран ДО того, как eportaImportComposeName() стала добавлять кромку
// в хвост названия (см. lib.php), — пересобирает NAME по той же формуле.
// Задача 14.09.2026 (карточки Invi 1.0 Стандарт: "..., AL-кромка чёрная с 4-х сторон").
//
// Меняет ТОЛЬКО NAME — CODE (символьный код, от него зависит SEF-ссылка /catalog/<code>.html)
// не трогаем, чтобы не ломать уже проиндексированные/расшаренные ссылки на карточки.
//
// Запуск на сервере: php backfill_edge_in_name.php [--dry-run]
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;
$dryRun = in_array('--dry-run', $argv ?? [], true);

// Та же логика, что eportaImportComposeName() в local/admin_tools/eporta_import/lib.php —
// продублирована, т.к. этот CLI-скрипт её не подключает (см. такой же приём в
// scripts/import/import_products.php).
function backfillComposeName(string $collection, string $modelPart, string $coating, string $color, string $edge, string $article): string {
    if ($collection !== '' && $modelPart !== '' && stripos($modelPart, $collection) === 0) {
        $head = $modelPart;
    } else {
        $head = trim($collection.' '.$modelPart);
    }
    $coatingColor = trim(implode(' ', array_filter([$coating, $color])));
    $tail = trim(implode(', ', array_filter([$coatingColor, $edge])));

    $name = $tail !== '' ? ($head !== '' ? $head.', '.$tail : $tail) : $head;
    return $name !== '' ? $name : $article;
}

$res = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => $IBLOCK_ID, '!PROPERTY_EDGE' => false, 'CHECK_PERMISSIONS' => 'N'],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_EDGE', 'PROPERTY_MODEL', 'PROPERTY_COLLECTION', 'PROPERTY_COATING', 'PROPERTY_COATING_COLOR', 'PROPERTY_CML2_ARTICLE']
);

$total = 0;
$updated = 0;
$skippedEmptyEdge = 0;
while ($el = $res->Fetch()) {
    $total++;
    $edge = trim((string)($el['PROPERTY_EDGE_VALUE'] ?? ''));
    if ($edge === '') {
        // GetList с "!PROPERTY_EDGE" => false отдаёт "свойство заполнено хоть каким-то значением
        // хоть у одного варианта мультисвойства" — на всякий случай перепроверяем пустую строку.
        $skippedEmptyEdge++;
        continue;
    }

    $collection = trim((string)($el['PROPERTY_COLLECTION_VALUE'] ?? ''));
    $modelPart = trim((string)($el['PROPERTY_MODEL_VALUE'] ?? ''));
    $coating = trim((string)($el['PROPERTY_COATING_VALUE'] ?? ''));
    $color = trim((string)($el['PROPERTY_COATING_COLOR_VALUE'] ?? ''));
    $article = trim((string)($el['PROPERTY_CML2_ARTICLE_VALUE'] ?? ''));

    $expectedName = backfillComposeName($collection, $modelPart, $coating, $color, $edge, $article ?: (string)$el['ID']);
    $currentName = (string)$el['NAME'];

    if ($expectedName === $currentName || $expectedName === '') {
        continue;
    }

    echo "ID {$el['ID']}: \"{$currentName}\" -> \"{$expectedName}\"\n";
    if (!$dryRun) {
        $bxElement = new CIBlockElement;
        $ok = $bxElement->Update((int)$el['ID'], ['NAME' => $expectedName]);
        if (!$ok) {
            echo "  ОШИБКА: {$bxElement->LAST_ERROR}\n";
            continue;
        }
    }
    $updated++;
}

echo "\nВсего просмотрено: $total, без кромки: $skippedEmptyEdge, ".($dryRun ? "будет обновлено" : "обновлено").": $updated\n";
