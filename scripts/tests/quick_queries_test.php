<?php
// Только консоль: скрипт подставляет права/меняет данные и не должен выполняться по HTTP (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Проверки чистой логики «Популярных запросов» (local/lib/eporta_quick_queries.php) — Bitrix не нужен.
// Запуск: php scripts/tests/quick_queries_test.php  (код возврата 0 = всё прошло)
define('B_PROLOG_INCLUDED', true);
require(__DIR__ . '/../../local/lib/eporta_quick_queries.php');

$failures = 0;
function check(string $name, $actual, $expected): void {
    global $failures;
    if ($actual === $expected) {
        echo "ok   $name\n";
        return;
    }
    $failures++;
    echo "FAIL $name\n  ожидалось: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  получено:  " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}

// --- URL
$q = ['id' => 7, 'label' => 'x', 'filters' => ['style' => [3, 5], 'coating' => [], 'color' => [9], 'size' => ['900x2000'], 'price_min' => 5000, 'price_max' => 0, 'category' => 'mkd', 'sale' => true, 'new' => false], 'pinned' => []];
check('url: фильтры без закреплённых — без pq', eportaQuickQueryUrl($q), '/catalog/?' . http_build_query(['style' => [3, 5], 'color' => [9], 'size' => ['900x2000'], 'price_min' => 5000, 'category' => 'mkd', 'sale' => 1]));
$q['pinned'] = [11, 12];
check('url: с закреплёнными — добавляется pq', strpos(eportaQuickQueryUrl($q), 'pq=7') !== false, true);
check('url: пустой запрос — просто /catalog/', eportaQuickQueryUrl(['id' => 1, 'label' => 'a', 'filters' => [], 'pinned' => []]), '/catalog/');
check('url: только закреплённые', eportaQuickQueryUrl(['id' => 4, 'label' => 'a', 'filters' => [], 'pinned' => [1]]), '/catalog/?pq=4');

// --- порядок выдачи
check('pinned-first: закреплённые первыми, без дублей', eportaQuickQueryPinnedFirst([1, 2, 3, 4], [3, 9]), [3, 9, 1, 2, 4]);
check('pinned-first: пустые закреплённые', eportaQuickQueryPinnedFirst([1, 2], []), [1, 2]);
check('pinned-first: пустая выдача', eportaQuickQueryPinnedFirst([], [5, 6]), [5, 6]);
check('pinned-first: дубли в закреплённых', eportaQuickQueryPinnedFirst([1, 2], [2, 2, 1]), [2, 1]);
check('pinned-first: порядок закреплённых сохраняется', eportaQuickQueryPinnedFirst([1, 2, 3], [3, 1]), [3, 1, 2]);

// --- нормализация
check('normalize: пустая подпись отбрасывается', eportaQuickQueriesNormalize([['label' => '  ', 'filters' => []]]), []);
check('normalize: не массив пропускается', eportaQuickQueriesNormalize(['мусор', 5, null]), []);
$n = eportaQuickQueriesNormalize([
    ['id' => 3, 'label' => 'Белые', 'filters' => ['style' => ['2', 'abc', 0, -1, '2'], 'size' => ['900x2000', 'bad', '900x2000'], 'price_min' => '9000', 'price_max' => '1000'], 'pinned' => ['5', 5, 'x']],
    ['id' => 3, 'label' => 'Дубль id', 'filters' => []],
    ['label' => 'Без id', 'filters' => []],
]);
check('normalize: style очищен от мусора и дублей', $n[0]['filters']['style'], [2]);
check('normalize: size валиден и без дублей', $n[0]['filters']['size'], ['900x2000']);
check('normalize: перевёрнутая цена сбрасывается', [$n[0]['filters']['price_min'], $n[0]['filters']['price_max']], [0, 0]);
check('normalize: pinned уникальные int', $n[0]['pinned'], [5]);
check('normalize: повторный id переназначается', array_column($n, 'id'), [3, 4, 5]);
check('normalize: подпись обрезается', mb_strlen(eportaQuickQueriesNormalize([['label' => str_repeat('я', 200)]])[0]['label']), EPORTA_QUICK_QUERIES_LABEL_MAX);

// --- allowed-списки (админка)
$allowed = ['style' => [1, 2], 'coating' => [], 'color' => [7], 'size' => ['800x2000'], 'category' => ['mkd']];
$a = eportaQuickQueryNormalizeOne(['label' => 'a', 'filters' => ['style' => [1, 99], 'color' => [8], 'size' => ['800x2000', '900x2000'], 'category' => 'zzz']], $allowed);
check('allowed: чужой style отсекается', $a['filters']['style'], [1]);
check('allowed: чужой color отсекается', $a['filters']['color'], []);
check('allowed: чужой size отсекается', $a['filters']['size'], ['800x2000']);
check('allowed: неизвестная категория сбрасывается', $a['filters']['category'], '');
check('allowed: валидная категория остаётся', eportaQuickQueryNormalizeOne(['label' => 'a', 'filters' => ['category' => 'mkd']], $allowed)['filters']['category'], 'mkd');
check('category: мусорные символы отсекаются', eportaQuickQueryNormalizeOne(['label' => 'a', 'filters' => ['category' => '../x']])['filters']['category'], '');
check('pinned: лимит', count(eportaQuickQueryNormalizeOne(['label' => 'a', 'pinned' => range(1, 500)])['pinned']), EPORTA_QUICK_QUERIES_PINNED_MAX);

echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки прошли\n";
exit($failures ? 1 : 0);
