<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Проверки чистой логики витрины фурнитуры (local/lib/eporta_hardware.php) — Bitrix не нужен.
// Запуск: php -d short_open_tag=1 scripts/tests/hardware_storefront_test.php  (0 = всё прошло)
define('B_PROLOG_INCLUDED', true);
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
require($_SERVER['DOCUMENT_ROOT'] . '/local/lib/eporta_hardware.php');

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

check('категория: Фурнитура', eportaIsHardwareCategory('Фурнитура'), true);
check('категория: ФУР в нижнем регистре с пробелами', eportaIsHardwareCategory(' фур '), true);
check('категория: МКД — не фурнитура', eportaIsHardwareCategory('МКД'), false);
check('категория: пустая', eportaIsHardwareCategory(''), false);

$defs = eportaHardwareFilterDefs();
check('фильтры фурнитуры: ключи и порядок', array_keys($defs), ['brand', 'series', 'coating', 'color', 'material']);
check('фильтры фурнитуры: без «Стиля»', isset($defs['style']), false);
check('фильтры: Бренд и Материал — строковые', [$defs['brand']['TYPE'], $defs['material']['TYPE']], ['string', 'string']);
check('фильтры: строковые — точное сравнение (=)', [$defs['brand']['FILTER_KEY'], $defs['material']['FILTER_KEY']], ['=PROPERTY_BRAND', '=PROPERTY_MATERIAL']);
check('фильтры: Серия/Покрытие/Цвет — списки', [$defs['series']['TYPE'], $defs['coating']['TYPE'], $defs['color']['TYPE']], ['enum', 'enum', 'enum']);
check('фильтры: «Цвет» фурнитуры = COATING_COLOR', $defs['color']['CODE'], 'COATING_COLOR');
foreach ($defs as $k => $d) {
    check("фильтры: $k — есть CODE/LABEL/FILTER_KEY", isset($d['CODE'], $d['LABEL'], $d['FILTER_KEY']), true);
}

$groups = [
    'Libra' => [['ID' => 5, 'SORT' => 9], ['ID' => 3, 'SORT' => 1]],
    '__id_7' => [['ID' => 7, 'SORT' => 4]],
    'Aqua' => [['ID' => 11, 'SORT' => 2], ['ID' => 12, 'SORT' => 2], ['ID' => 13, 'SORT' => 1]],
];
check('схлопывание: по первому товару каждой группы, порядок групп сохранён', eportaCollapseModelGroups($groups), [5, 7, 11]);
check('схлопывание: пусто', eportaCollapseModelGroups([]), []);
check('схлопывание: пустая группа пропускается', eportaCollapseModelGroups(['a' => [], 'b' => [['ID' => 1, 'SORT' => 0]]]), [1]);

echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
