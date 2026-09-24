<?php
// Админка блока "Отзывы" — точка входа + lib.php + ajax.php, без Bitrix-обвязки, по паттерну
// local/admin_tools/eporta_works. Наполнение только через эту админку — публичной формы
// "оставить отзыв" нет (решение пользователя 24.09.2026). Требует уже подключенный prolog_before.php.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/eporta_reviews_common.php');

// Право на запись — тот же критерий, что и у остальных кастомных админок сайта.
const EPORTA_REVIEWS_PERMISSION_IBLOCK_ID = 19;

function eportaReviewsUserHasAccess(): bool {
    global $USER;
    if (!$USER->IsAuthorized()) {
        return false;
    }
    if ($USER->IsAdmin()) {
        return true;
    }
    return CIBlock::GetPermission(EPORTA_REVIEWS_PERMISSION_IBLOCK_ID) >= 'W';
}
