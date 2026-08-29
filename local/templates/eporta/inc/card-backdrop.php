<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Подложка под фото товара в карточке: у светлых дверей (белый, слоновая кость и т.п.) базовый
// бежевый #f6f4ef (template_styles.css) сливается с полотном — почти нет контраста между дверью
// и фоном. Определяем "светлый" товар по значениям свойств MAIN_COLOR ("Оттенок") и
# COATING_COLOR ("Цвет") — см. памятку по неймингу свойств IBLOCK 19 — простым сопоставлением
// по ключевым словам (не по жёсткому списку из БД: новые светлые оттенки в фиде подхватятся
// автоматически, если в названии есть одно из характерных слов).
if (!function_exists("eportaIsLightDoorColor")) {
function eportaIsLightDoorColor(?string $mainColor, ?string $coatingColor = null): bool {
    $needle = mb_strtolower(trim(($mainColor ?? "") . " " . ($coatingColor ?? "")), "UTF-8");
    if ($needle === "") {
        return false;
    }
    static $lightKeywords = [
        "бел",       // белый, белоснежный
        "слонов",    // слоновая кость
        "ivory",
        "жемчуж",    // жемчужный
        "магнол",    // магнолия
        "крем",      // кремовый
        "молоч",     // молочный
        "ясень бел",
    ];
    foreach ($lightKeywords as $kw) {
        if (mb_strpos($needle, $kw, 0, "UTF-8") !== false) {
            return true;
        }
    }
    return false;
}
}

// CSS-подложка под фото товара — контрастнее базового бежевого #f6f4ef специально для светлых
// дверей, чтобы полотно визуально отделялось от фона карточки.
if (!defined("EPORTA_CARD_BACKDROP_LIGHT")) {
    define("EPORTA_CARD_BACKDROP_LIGHT", "#e2ddd0");
}
