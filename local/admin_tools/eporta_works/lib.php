<?php
// Админка блока "Наши работы" — точка входа + lib.php + ajax.php, без Bitrix-обвязки, по
// паттерну local/admin_tools/eporta_articles (см. project memory: feedback_bitrix_override_gotchas).
// Требует уже подключенный prolog_before.php.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/eporta_works_common.php');

// Право на запись — тот же критерий, что и у остальных кастомных админок сайта (право записи
// в каталог IBLOCK 19): те же контент-менеджеры, отдельной модели прав не заводили.
const EPORTA_WORKS_PERMISSION_IBLOCK_ID = 19;

function eportaWorksUserHasAccess(): bool {
    global $USER;
    if (!$USER->IsAuthorized()) {
        return false;
    }
    if ($USER->IsAdmin()) {
        return true;
    }
    return CIBlock::GetPermission(EPORTA_WORKS_PERMISSION_IBLOCK_ID) >= 'W';
}

function eportaWorksTmpDir(): string {
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/local/tmp/eporta_works';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
    return $dir;
}
