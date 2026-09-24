<?php
// Общие константы/хелперы блока "Отзывы" (секция на главной) — по паттерну
// eporta_works_common.php. См. scripts/create_iblock_reviews.php.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// TODO: заменить на реальный ID после запуска scripts/create_iblock_reviews.php на проде.
// Пока 0 — eportaReviewsList() отдаёт пустой массив, секция на главной не рендерится.
const EPORTA_REVIEWS_IBLOCK_ID = 0;

function eportaReviewsFields(): array {
    return ['ID', 'NAME', 'ACTIVE', 'SORT', 'ACTIVE_FROM', 'PREVIEW_TEXT', 'PROPERTY_RATING', 'PROPERTY_CITY'];
}

function eportaReviewsMap(array $el): array {
    $el['RATING'] = max(1, min(5, (int)($el['PROPERTY_RATING_VALUE'] ?? 5)));
    $el['CITY'] = (string)($el['PROPERTY_CITY_VALUE'] ?? '');
    return $el;
}

// Активные отзывы для главной, новые сверху (по дате) — используется и на публичной части,
// и (без лимита) в списке админки.
function eportaReviewsList(?int $limit = null): array {
    if (EPORTA_REVIEWS_IBLOCK_ID <= 0) {
        return [];
    }
    $res = CIBlockElement::GetList(
        ['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'],
        ['IBLOCK_ID' => EPORTA_REVIEWS_IBLOCK_ID, 'ACTIVE' => 'Y'],
        false,
        $limit ? ['nTopCount' => $limit] : false,
        eportaReviewsFields()
    );
    $items = [];
    while ($el = $res->Fetch()) {
        $items[] = eportaReviewsMap($el);
    }
    return $items;
}

// Полный список (включая неактивные) для админки.
function eportaReviewsAdminList(): array {
    if (EPORTA_REVIEWS_IBLOCK_ID <= 0) {
        return [];
    }
    $res = CIBlockElement::GetList(
        ['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'],
        ['IBLOCK_ID' => EPORTA_REVIEWS_IBLOCK_ID],
        false, false,
        eportaReviewsFields()
    );
    $items = [];
    while ($el = $res->Fetch()) {
        $items[] = eportaReviewsMap($el);
    }
    return $items;
}

function eportaReviewsGet(int $id): ?array {
    if (EPORTA_REVIEWS_IBLOCK_ID <= 0) {
        return null;
    }
    $el = CIBlockElement::GetList(
        [], ['IBLOCK_ID' => EPORTA_REVIEWS_IBLOCK_ID, 'ID' => $id], false, false,
        eportaReviewsFields()
    )->Fetch();
    return $el ? eportaReviewsMap($el) : null;
}

// Средняя оценка + количество активных отзывов — заменяет прежнюю захардкоженную строку
// ".social-proof" (убрана по заявке заказчика 24.09.2026). Возвращает null, если отзывов нет
// вовсе (плашку в этом случае показывать не с чем).
function eportaReviewsAggregate(): ?array {
    if (EPORTA_REVIEWS_IBLOCK_ID <= 0) {
        return null;
    }
    $res = CIBlockElement::GetList(
        [], ['IBLOCK_ID' => EPORTA_REVIEWS_IBLOCK_ID, 'ACTIVE' => 'Y'], false, false,
        ['ID', 'PROPERTY_RATING']
    );
    $count = 0;
    $sum = 0;
    while ($el = $res->Fetch()) {
        $count++;
        $sum += max(1, min(5, (int)($el['PROPERTY_RATING_VALUE'] ?? 5)));
    }
    if ($count === 0) {
        return null;
    }
    return ['count' => $count, 'average' => round($sum / $count, 1)];
}
