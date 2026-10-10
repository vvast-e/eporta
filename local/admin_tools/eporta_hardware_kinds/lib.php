<?php
// Админка «Типы фурнитуры»: список, переименование, порядок, видимость, добавление типа. Типы — разделы
// IBLOCK 19 под разделом «Фурнитура» (CODE hardware, scripts/add_hardware_kinds_sections.php), вне раздела
// 183 «Коллекции». Требует уже подключенный prolog_before.php. Права — те же, что у остальных админок
// витрины (запись в IBLOCK 19).

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/eporta_hardware.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_banners/lib.php');

function eportaHardwareKindsUserHasAccess(): bool {
    return eportaBannersUserHasAccess();
}

// Типы для админки: все (включая скрытые) по SORT, с числом активных товаров.
function eportaHardwareKindsAdminList(): array {
    $parentId = eportaHardwareKindsParentId();
    if ($parentId <= 0) {
        return [];
    }
    $kinds = array_values(array_filter(eportaHardwareAllSections(false), function ($row) use ($parentId) {
        return (int)$row['IBLOCK_SECTION_ID'] === $parentId;
    }));
    $counts = [];
    if ($kinds) {
        $res = \CIBlockElement::GetList([], ['IBLOCK_ID' => 19, 'SECTION_ID' => array_map('intval', array_column($kinds, 'ID'))], ['IBLOCK_SECTION_ID'], false, ['ID', 'IBLOCK_SECTION_ID', 'CNT']);
        while ($row = $res->Fetch()) {
            $counts[(int)$row['IBLOCK_SECTION_ID']] = (int)($row['CNT'] ?? 0);
        }
    }
    foreach ($kinds as $i => $kind) {
        $kinds[$i]['COUNT'] = $counts[(int)$kind['ID']] ?? 0;
    }
    return $kinds;
}

function eportaHardwareKindsUniqueCode(string $name): string {
    $base = \CUtil::translit($name, 'ru', ['max_len' => 40, 'change_case' => 'L', 'replace_space' => '-', 'replace_other' => '-', 'delete_repeat_replace' => true]);
    $base = $base !== '' ? $base : 'type';
    $code = $base;
    $i = 2;
    while (\CIBlockSection::GetList([], ['IBLOCK_ID' => 19, 'CODE' => $code], false, ['ID'])->Fetch()) {
        $code = $base . '-' . $i++;
    }
    return $code;
}

// Новый тип в конец списка. Возвращает ID или false (текст — в $error).
function eportaHardwareKindsCreate(string $name, ?string &$error = null) {
    $parentId = eportaHardwareKindsParentId();
    if ($parentId <= 0) {
        $error = 'Раздел «Фурнитура» не создан — запустите scripts/add_hardware_kinds_sections.php';
        return false;
    }
    $error = eportaHardwareKindValidateName(eportaHardwareAllSections(false), $parentId, $name);
    if ($error !== null) {
        return false;
    }
    $maxSort = 0;
    foreach (eportaHardwareAllSections(false) as $row) {
        if ((int)$row['IBLOCK_SECTION_ID'] === $parentId) {
            $maxSort = max($maxSort, (int)$row['SORT']);
        }
    }
    $section = new \CIBlockSection;
    $id = $section->Add(['IBLOCK_ID' => 19, 'IBLOCK_SECTION_ID' => $parentId, 'NAME' => trim($name), 'CODE' => eportaHardwareKindsUniqueCode($name), 'SORT' => $maxSort + 10, 'ACTIVE' => 'Y']);
    if (!$id) {
        $error = $section->LAST_ERROR ?: 'Не удалось создать тип';
        return false;
    }
    return (int)$id;
}

// Название/порядок/видимость. CODE не меняется: на него завязаны ссылки ?type=<code>. Менять можно только
// детей раздела «Фурнитура» (чужой ID — отказ).
function eportaHardwareKindsUpdate(int $id, string $name, int $sort, bool $active, ?string &$error = null): bool {
    $parentId = eportaHardwareKindsParentId();
    $isKind = false;
    foreach (eportaHardwareAllSections(false) as $row) {
        if ((int)$row['ID'] === $id && $parentId > 0 && (int)$row['IBLOCK_SECTION_ID'] === $parentId) {
            $isKind = true;
        }
    }
    if (!$isKind) {
        $error = 'Тип не найден';
        return false;
    }
    $error = eportaHardwareKindValidateName(eportaHardwareAllSections(false), $parentId, $name, $id);
    if ($error !== null) {
        return false;
    }
    $section = new \CIBlockSection;
    if (!$section->Update($id, ['NAME' => trim($name), 'SORT' => $sort, 'ACTIVE' => $active ? 'Y' : 'N'])) {
        $error = $section->LAST_ERROR ?: 'Не удалось сохранить тип';
        return false;
    }
    return true;
}
