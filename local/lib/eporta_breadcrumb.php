<?php
// Единые хлебные крошки сайта: один маркап и один класс .breadcrumb (template_styles.css) на всех
// страницах. Раньше у каждой страницы был свой вариант (inline-стили, .lk-breadcrumb, .store-breadcrumb,
// невалидный `font:500 13px` без семейства), из-за чего размеры и кликабельность расходились.
// Функция сборки — чистая (без Bitrix), покрыта scripts/tests/breadcrumb_test.php.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// $items — список [подпись, ссылка|null]. Звено со ссылкой кликабельно, последнее (или без ссылки)
// выводится текстом. $modifier — необязательный класс-модификатор отступов (например 'breadcrumb--lk').
function eportaBreadcrumbHtml(array $items, string $modifier = ''): string
{
    $parts = [];
    $last = count($items) - 1;
    foreach (array_values($items) as $i => $item) {
        $label = htmlspecialchars((string)($item[0] ?? ''), ENT_QUOTES, 'UTF-8');
        $url = (string)($item[1] ?? '');
        if ($label === '') {
            continue;
        }
        if ($i < $last && $url !== '') {
            $parts[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        } else {
            $parts[] = '<span' . ($i === $last ? ' aria-current="page"' : '') . '>' . $label . '</span>';
        }
    }
    if (!$parts) {
        return '';
    }
    $class = 'breadcrumb' . ($modifier !== '' ? ' ' . htmlspecialchars($modifier, ENT_QUOTES, 'UTF-8') : '');
    return '<nav class="' . $class . '" aria-label="Хлебные крошки">' . implode(' · ', $parts) . '</nav>';
}

function eportaBreadcrumb(array $items, string $modifier = ''): void
{
    echo eportaBreadcrumbHtml($items, $modifier);
}
