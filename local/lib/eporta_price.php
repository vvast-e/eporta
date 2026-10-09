<?php
// Цены со скидкой. «Цена» в выгрузке 1С — исходная цена ДО скидки, «Скидка» — процент. Исходная цена
// хранится явно в свойстве ORIGINAL_PRICE (IBLOCK 19), цена со скидкой считается от неё и кладётся в
// BASE (её списывает корзина). Функции чистые (без БД), покрыты scripts/tests/price_test.php.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// Цена со скидкой от исходной: процент ограничивается 0..100, результат — целые рубли.
function eportaPriceFinal(float $original, float $discountPercent): float
{
    $discountPercent = max(0.0, min(100.0, $discountPercent));
    return $discountPercent > 0 ? round($original * (1 - $discountPercent / 100)) : $original;
}

// Зачёркнутая цена для витрины: явная исходная цена, если она задана и больше текущей; иначе
// (товары, загруженные до появления ORIGINAL_PRICE) восстанавливается из текущей цены и процента.
function eportaPriceOld(float $current, float $discountPercent, float $original = 0.0): float
{
    if ($current <= 0 || $discountPercent <= 0 || $discountPercent >= 100) {
        return 0.0;
    }
    if ($original > $current) {
        return $original;
    }
    return round($current / (1 - $discountPercent / 100));
}
