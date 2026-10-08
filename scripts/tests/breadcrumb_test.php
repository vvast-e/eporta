<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Проверки чистой логики local/lib/eporta_breadcrumb.php. Запуск: php scripts/tests/breadcrumb_test.php
define('B_PROLOG_INCLUDED', true);
require(__DIR__ . '/../../local/lib/eporta_breadcrumb.php');

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

check('пусто', eportaBreadcrumbHtml([]), '');
check('одно звено — текст', eportaBreadcrumbHtml([['Корзина', '/personal/cart/']]),
    '<nav class="breadcrumb" aria-label="Хлебные крошки"><span aria-current="page">Корзина</span></nav>');
check('цепочка: звенья ссылки, последнее текст',
    eportaBreadcrumbHtml([['Главная', '/'], ['Каталог', '/catalog/'], ['Дверь']]),
    '<nav class="breadcrumb" aria-label="Хлебные крошки"><a href="/">Главная</a> · <a href="/catalog/">Каталог</a> · <span aria-current="page">Дверь</span></nav>');
check('звено без ссылки в середине — текст',
    eportaBreadcrumbHtml([['Главная', '/'], ['Раздел'], ['Стр', '/x/']]),
    '<nav class="breadcrumb" aria-label="Хлебные крошки"><a href="/">Главная</a> · <span>Раздел</span> · <span aria-current="page">Стр</span></nav>');
check('экранирование подписи и ссылки',
    eportaBreadcrumbHtml([['<b>"x"</b>', '/a?b=1&c=2"'], ['Y']]),
    '<nav class="breadcrumb" aria-label="Хлебные крошки"><a href="/a?b=1&amp;c=2&quot;">&lt;b&gt;&quot;x&quot;&lt;/b&gt;</a> · <span aria-current="page">Y</span></nav>');
check('пустая подпись пропускается',
    eportaBreadcrumbHtml([['Главная', '/'], ['', '/z/'], ['Конец']]),
    '<nav class="breadcrumb" aria-label="Хлебные крошки"><a href="/">Главная</a> · <span aria-current="page">Конец</span></nav>');
check('модификатор', strpos(eportaBreadcrumbHtml([['A']], 'breadcrumb--lk'), 'class="breadcrumb breadcrumb--lk"') !== false, true);

echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
