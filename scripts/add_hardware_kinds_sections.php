<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Создаёт в IBLOCK 19 раздел верхнего уровня «Фурнитура» (CODE hardware) и под ним 11 типов изделий
// (eportaHardwareKindDefs). Типы лежат ВНЕ раздела 183 «Коллекции», поэтому на витрину дверей не влияют.
// Типы нужно создать ДО импорта фурнитуры: колонка «Коллекция» выгрузки = тип, импорт ищет его по названию.
// Идемпотентно (ищет по CODE). Запуск: /opt/php83/bin/php scripts/add_hardware_kinds_sections.php [--dry-run]
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/eporta_hardware.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;
$dry = in_array('--dry-run', $argv, true);
$section = new CIBlockSection;

// Раздел ищем по CODE в PHP: фильтр GetList по IBLOCK_SECTION_ID ненадёжен, CODE — достаточно.
function findSectionByCode(int $iblockId, string $code): ?array {
    $row = CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code], false, ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID'])->Fetch();
    return $row ?: null;
}

$parent = findSectionByCode($IBLOCK_ID, EPORTA_HARDWARE_KINDS_PARENT_CODE);
if ($parent) {
    $parentId = (int)$parent['ID'];
    echo "Раздел «Фурнитура» уже есть, ID=$parentId\n";
} elseif ($dry) {
    $parentId = 0;
    echo "(dry-run) был бы создан раздел «Фурнитура» (CODE=" . EPORTA_HARDWARE_KINDS_PARENT_CODE . ")\n";
} else {
    $parentId = (int)$section->Add(['IBLOCK_ID' => $IBLOCK_ID, 'NAME' => 'Фурнитура', 'CODE' => EPORTA_HARDWARE_KINDS_PARENT_CODE, 'ACTIVE' => 'Y', 'SORT' => 900]);
    if ($parentId <= 0) {
        die('Ошибка создания раздела «Фурнитура»: ' . $section->LAST_ERROR . "\n");
    }
    echo "Раздел «Фурнитура» создан, ID=$parentId\n";
}

$sort = 10;
foreach (eportaHardwareKindDefs() as $def) {
    $existing = findSectionByCode($IBLOCK_ID, $def['CODE']);
    if ($existing) {
        if ($parentId > 0 && (int)$existing['IBLOCK_SECTION_ID'] !== $parentId) {
            echo "ВНИМАНИЕ: раздел с кодом {$def['CODE']} (ID={$existing['ID']}) уже есть, но не под «Фурнитурой» — пропускаю\n";
        } else {
            echo "Тип «{$def['NAME']}» уже есть, ID={$existing['ID']}\n";
        }
    } elseif ($dry) {
        echo "(dry-run) был бы создан тип «{$def['NAME']}» (CODE={$def['CODE']}, SORT=$sort)\n";
    } else {
        $id = (int)$section->Add(['IBLOCK_ID' => $IBLOCK_ID, 'IBLOCK_SECTION_ID' => $parentId, 'NAME' => $def['NAME'], 'CODE' => $def['CODE'], 'ACTIVE' => 'Y', 'SORT' => $sort]);
        if ($id <= 0) {
            die("Ошибка создания типа «{$def['NAME']}»: {$section->LAST_ERROR}\n");
        }
        echo "Тип «{$def['NAME']}» создан, ID=$id\n";
    }
    $sort += 10;
}
