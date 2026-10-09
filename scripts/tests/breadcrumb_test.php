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

$cols = [
    ['ID' => '1', 'NAME' => 'Dorsum', 'CODE' => 'dorsum'],
    ['ID' => '2', 'NAME' => 'Dorsum-F', 'CODE' => 'dorsum-f', 'UF_PARENT_COLLECTION' => '1'],
    ['ID' => '3', 'NAME' => 'Loop', 'CODE' => 'loop', 'UF_PARENT_COLLECTION' => '4'],
    ['ID' => '4', 'NAME' => 'Loop2', 'CODE' => 'loop2', 'UF_PARENT_COLLECTION' => '3'],
];
check('цепочка коллекции верхнего уровня', eportaBreadcrumbCollectionChain($cols, 1), [['Dorsum', '/catalog/collections/dorsum/']]);
check('цепочка подколлекции: родитель, затем она', eportaBreadcrumbCollectionChain($cols, 2),
    [['Dorsum', '/catalog/collections/dorsum/'], ['Dorsum-F', '/catalog/collections/dorsum-f/']]);
check('цикл родителей не зацикливает', count(eportaBreadcrumbCollectionChain($cols, 3)), 2);
check('неизвестная коллекция — пусто', eportaBreadcrumbCollectionChain($cols, 99), []);
check('автокрошки: верхний уровень', eportaAutoBreadcrumbItems('/zamer/', 'Вызвать замерщика'), [['Главная', '/'], ['Вызвать замерщика']]);
check('автокрошки: вложенная about', eportaAutoBreadcrumbItems('/about/contacts/', 'Контакты'), [['Главная', '/'], ['О магазине', '/about/'], ['Контакты']]);
check('автокрошки: корень about без родителя', eportaAutoBreadcrumbItems('/about/', 'О магазине'), [['Главная', '/'], ['О магазине']]);

echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
