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

// Выводит крошки и помечает, что страница показала свои — тогда автокрошки из header.php
// (eportaAutoBreadcrumb) второй раз не добавляются.
function eportaBreadcrumb(array $items, string $modifier = ''): void
{
    $GLOBALS['EPORTA_BREADCRUMB_RENDERED'] = true;
    echo eportaBreadcrumbHtml($items, $modifier);
}

// Цепочка коллекций от верхнего уровня до $id по UF_PARENT_COLLECTION: [[имя, /catalog/collections/<code>/], ...].
// Чистая функция (список коллекций передаётся), защищена от циклов и от отсутствия родителя в списке.
function eportaBreadcrumbCollectionChain(array $collections, int $id): array
{
    $byId = [];
    foreach ($collections as $c) {
        $byId[(int)$c['ID']] = $c;
    }
    $chain = [];
    $seen = [];
    while ($id > 0 && isset($byId[$id]) && !isset($seen[$id])) {
        $seen[$id] = true;
        $c = $byId[$id];
        array_unshift($chain, [(string)$c['NAME'], '/catalog/collections/' . $c['CODE'] . '/']);
        $id = (int)($c['UF_PARENT_COLLECTION'] ?? 0);
    }
    return $chain;
}

// Автокрошки для страниц, которые своих не вывели: «Главная › [раздел] › заголовок страницы».
// Регистрируется в header.php через $APPLICATION->AddBufferContent и вызывается в конце страницы,
// когда уже известны заголовок и то, вывела ли страница свои крошки.
function eportaAutoBreadcrumb(): string
{
    global $APPLICATION;
    if (!empty($GLOBALS['EPORTA_BREADCRUMB_RENDERED'])) {
        return '';
    }
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($path === '/' || $path === '/index.php' || strpos((string)\CHTTP::GetLastStatus(), '404') === 0) {
        return '';
    }
    $title = trim((string)$APPLICATION->GetTitle());
    return eportaBreadcrumbHtml(eportaAutoBreadcrumbItems($path, $title));
}

// Чистая часть автокрошек: родительский раздел по первому сегменту пути (только для вложенных страниц).
function eportaAutoBreadcrumbItems(string $path, string $title): array
{
    static $parents = [
        'about' => ['О магазине', '/about/'],
        'personal' => ['Личный кабинет', '/personal/'],
    ];
    $segments = array_values(array_filter(explode('/', $path), 'strlen'));
    $items = [['Главная', '/']];
    if (count($segments) > 1 && isset($parents[$segments[0]])) {
        $items[] = $parents[$segments[0]];
    }
    $items[] = [$title];
    return $items;
}
