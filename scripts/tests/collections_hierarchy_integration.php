<?php
// Интеграционная проверка иерархии коллекций на боевом окружении (нужен Bitrix, запускать на
// сервере: /opt/php83/bin/php scripts/tests/collections_hierarchy_integration.php). Временно
// привязывает подколлекции dorsum-* к dorsum и переносит одну коллекцию в блок 2, проверяет
// страницы по HTTP (кука dev_preview), затем ВОЗВРАЩАЕТ UF_PARENT_COLLECTION/UF_HOME_BLOCK всех
// коллекций к исходным значениям.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_collections/lib.php');

$failures = 0;
function check(string $name, bool $cond, string $info = ''): void {
    global $failures;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond || $info === '' ? '' : " — $info") . "\n";
    if (!$cond) $failures++;
}
function fetchPage(string $path): string {
    $ctx = stream_context_create([
        'http' => ['header' => "Cookie: dev_preview=x7Qm2pR9vL\r\n", 'timeout' => 60],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    return (string)@file_get_contents('https://eporta.ru' . $path, false, $ctx);
}
// Фрагмент главной с блоками коллекций (от .eporta-coll-split до блока запросов/конца секции).
function homeCollSection(string $html): string {
    $start = strpos($html, 'class="eporta-coll-split');
    if ($start === false) return '';
    $end = strpos($html, '</script>', $start);
    return substr($html, $start, $end === false ? 20000 : $end - $start);
}

$all = eportaCollections(true);
$original = [];
foreach ($all as $c) {
    $original[(int)$c['ID']] = ['parent' => (int)($c['UF_PARENT_COLLECTION'] ?? 0), 'block' => (int)($c['UF_HOME_BLOCK'] ?? 0)];
}
$byCode = [];
foreach ($all as $c) $byCode[$c['CODE']] = $c;

try {
    check('есть коллекции dorsum, dorsum-f и dorsum-eco', isset($byCode['dorsum']) && isset($byCode['dorsum-f']) && isset($byCode['dorsum-eco']), implode(',', array_keys($byCode)));
    $parent = $byCode['dorsum'];
    $kids = [$byCode['dorsum-f'], $byCode['dorsum-eco']];
    $other = null;
    foreach ($all as $c) {
        if (strpos($c['CODE'], 'dorsum') !== 0 && $c['ACTIVE'] === 'Y') { $other = $c; break; }
    }
    check('есть коллекция вне семейства dorsum', $other !== null);

    // Страницы ДО привязки: полоски нет
    check('до привязки: на странице dorsum нет полоски', strpos(fetchPage('/catalog/collections/dorsum/'), 'eporta-subcoll-nav') === false);

    foreach ($kids as $kid) {
        $err = null;
        check('привязка ' . $kid['CODE'] . ' к dorsum', eportaCollectionsSetParent((int)$kid['ID'], (int)$parent['ID'], $err), (string)$err);
    }
    $err = null;
    check('сервер отвергает самопривязку', eportaCollectionsSetParent((int)$parent['ID'], (int)$parent['ID'], $err) === false && $err !== null);

    // Страница родителя
    $page = fetchPage('/catalog/collections/dorsum/');
    check('страница dorsum: есть полоска', strpos($page, 'eporta-subcoll-nav') !== false);
    check('страница dorsum: текущая — Dorsum (aria-current)', (bool)preg_match('~aria-current="page">' . preg_quote($parent['NAME'], '~') . '<~u', $page));
    foreach ($kids as $kid) {
        check('страница dorsum: ссылка на ' . $kid['CODE'], strpos($page, 'href="/catalog/collections/' . $kid['CODE'] . '/"') !== false);
    }
    check('полоска между баннером и блоком моделей', strpos($page, 'eporta-subcoll-nav') > strpos($page, 'Коллекция фабрики EPORTA') && (strpos($page, 'Модели коллекции') === false || strpos($page, 'eporta-subcoll-nav') < strpos($page, 'Модели коллекции')));

    // Страница подколлекции
    $childPage = fetchPage('/catalog/collections/' . $kids[0]['CODE'] . '/');
    check('страница подколлекции: есть полоска', strpos($childPage, 'eporta-subcoll-nav') !== false);
    check('страница подколлекции: текущая — она сама', (bool)preg_match('~aria-current="page">' . preg_quote($kids[0]['NAME'], '~') . '<~u', $childPage));
    check('страница подколлекции: есть ссылка на родителя', strpos($childPage, 'href="/catalog/collections/dorsum/"') !== false);
    check('страница подколлекции: есть ссылка на сестру', strpos($childPage, 'href="/catalog/collections/' . $kids[1]['CODE'] . '/"') !== false);
    check('подколлекция остаётся открытой по своему адресу (200, есть H1)', strpos($childPage, '<h1') !== false);

    // Коллекция вне семейства — без полоски
    check('коллекция вне семейства: полоски нет', strpos(fetchPage('/catalog/collections/' . $other['CODE'] . '/'), 'eporta-subcoll-nav') === false);

    // Главная: подколлекции скрыты, родитель на месте
    $home = homeCollSection(fetchPage('/'));
    check('главная: секция коллекций найдена', $home !== '');
    check('главная: родитель dorsum показан', strpos($home, 'href="/catalog/collections/dorsum/"') !== false);
    foreach ($kids as $kid) {
        check('главная: подколлекция ' . $kid['CODE'] . ' скрыта', strpos($home, 'href="/catalog/collections/' . $kid['CODE'] . '/"') === false);
    }
    // Хаб /collection/: подколлекции видны
    $hub = fetchPage('/collection/');
    foreach ($kids as $kid) {
        check('хаб: подколлекция ' . $kid['CODE'] . ' видна', strpos($hub, 'href="/catalog/collections/' . $kid['CODE'] . '/"') !== false);
    }

    // Блоки главной: пока в блоке 2 пусто — 6 моков
    check('главная: без коллекций в блоке 2 — 6 моков', substr_count($home, 'eporta-coll-tile--mock eporta-coll-tile--mock') === 6);
    $err = null;
    check('перенос коллекции в блок 2', eportaCollectionsSetHomeBlock((int)$other['ID'], 2, $err), (string)$err);
    $home2 = homeCollSection(fetchPage('/'));
    $pane2 = strpos($home2, 'eporta-coll-pane--2') !== false ? substr($home2, strpos($home2, 'eporta-coll-pane--2')) : '';
    check('блок 2: реальная коллекция показана справа', strpos($pane2, 'href="/catalog/collections/' . $other['CODE'] . '/"') !== false);
    check('блок 2: моки пропали', strpos($home2, 'eporta-coll-tile--mock') === false);
    $pane1 = substr($home2, 0, strpos($home2, 'eporta-coll-pane--2') ?: 0);
    check('блок 1: перенесённой коллекции больше нет слева', strpos($pane1, 'href="/catalog/collections/' . $other['CODE'] . '/"') === false);
} finally {
    $sec = new CIBlockSection;
    foreach ($original as $id => $orig) {
        $sec->Update($id, ['UF_PARENT_COLLECTION' => $orig['parent'], 'UF_HOME_BLOCK' => $orig['block']]);
    }
}

// Возврат к исходному состоянию — проверяем напрямую по БД
$restoredOk = true;
foreach ($original as $id => $orig) {
    $row = CIBlockSection::GetList([], ['IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID, 'ID' => $id], false, ['ID', 'UF_PARENT_COLLECTION', 'UF_HOME_BLOCK'])->Fetch();
    if ((int)$row['UF_PARENT_COLLECTION'] !== $orig['parent'] || (int)$row['UF_HOME_BLOCK'] !== $orig['block']) {
        $restoredOk = false;
    }
}
check('данные коллекций возвращены в исходное состояние', $restoredOk);
echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки прошли\n";
exit($failures ? 1 : 0);
