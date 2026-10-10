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

// --- Типы фурнитуры (этап 3) ---
$kindDefs = eportaHardwareKindDefs();
check('типы: 11 штук в порядке заказчика', array_column($kindDefs, 'NAME'), ['Ручки', 'Петли', 'Защёлки', 'Фиксаторы', 'Ограничители', 'Накладки', 'Цилиндры', 'Замки', 'Доводчики', 'Задвижки', 'Пороги']);
check('типы: коды уникальны', count(array_unique(array_column($kindDefs, 'CODE'))), 11);
check('типы: коды — латиница/дефис', count(array_filter(array_column($kindDefs, 'CODE'), function ($c) { return preg_match('/^[a-z-]+$/', $c); })), 11);

$rows = [
    ['ID' => 1, 'NAME' => 'Фурнитура', 'IBLOCK_SECTION_ID' => 0],
    ['ID' => 2, 'NAME' => 'Ручки', 'IBLOCK_SECTION_ID' => 1],
    ['ID' => 3, 'NAME' => ' Петли ', 'IBLOCK_SECTION_ID' => '1'],
    ['ID' => 183, 'NAME' => 'Коллекции', 'IBLOCK_SECTION_ID' => 183],
    ['ID' => 184, 'NAME' => 'Dorsum', 'IBLOCK_SECTION_ID' => 183],
    ['ID' => 5, 'NAME' => 'Ручки', 'IBLOCK_SECTION_ID' => 183],
];
check('карта разделов: только дети родителя, имя в нижнем регистре', eportaSectionNameMap($rows, 1), ['ручки' => 2, 'петли' => 3]);
check('карта разделов: дети 183, сам 183 не входит', eportaSectionNameMap($rows, 183), ['dorsum' => 184, 'ручки' => 5]);
check('карта разделов: родитель без детей', eportaSectionNameMap($rows, 99), []);

$kinds = [
    ['ID' => 2, 'NAME' => 'Ручки', 'CODE' => 'ruchki'],
    ['ID' => 3, 'NAME' => 'Петли', 'CODE' => 'petli'],
    ['ID' => 4, 'NAME' => 'Замки', 'CODE' => 'zamki'],
];
$nav = eportaHardwareKindsNav($kinds, [2 => 14, 4 => 3], '');
check('полоска: пустые типы скрыты', array_column($nav, 'CODE'), ['ruchki', 'zamki']);
check('полоска: счётчики', array_column($nav, 'COUNT'), [14, 3]);
check('полоска: ссылка типа', $nav[0]['URL'], '/catalog/?category=hardware&type=ruchki');
check('полоска: активного нет', array_column($nav, 'ACTIVE'), [false, false]);
$navActive = eportaHardwareKindsNav($kinds, [2 => 14, 4 => 3], 'zamki');
check('полоска: активный помечен и ведёт в категорию', [$navActive[1]['ACTIVE'], $navActive[1]['URL'], $navActive[0]['ACTIVE']], [true, '/catalog/?category=hardware', false]);
check('полоска: нет товаров — пусто', eportaHardwareKindsNav($kinds, [], ''), []);
check('поиск типа: найден', eportaHardwareFindKind($kinds, 'petli')['ID'], 3);
check('поиск типа: неизвестный', eportaHardwareFindKind($kinds, 'x'), null);
check('поиск типа: пустой', eportaHardwareFindKind($kinds, ''), null);

check('крошки: категория', eportaHardwareBreadcrumb(null), [['Главная', '/'], ['Каталог', '/catalog/'], ['Фурнитура']]);
check('крошки: тип', eportaHardwareBreadcrumb($kinds[0]), [['Главная', '/'], ['Каталог', '/catalog/'], ['Фурнитура', '/catalog/?category=hardware'], ['Ручки']]);
check('крошки: товар', eportaHardwareBreadcrumb($kinds[0], 'Ручка Libra'), [['Главная', '/'], ['Каталог', '/catalog/'], ['Фурнитура', '/catalog/?category=hardware'], ['Ручки', '/catalog/?category=hardware&type=ruchki'], ['Ручка Libra']]);

$vrows = [
    ['ID' => 1, 'NAME' => 'Фурнитура', 'IBLOCK_SECTION_ID' => 0],
    ['ID' => 2, 'NAME' => 'Ручки', 'IBLOCK_SECTION_ID' => 1],
    ['ID' => 3, 'NAME' => 'Петли', 'IBLOCK_SECTION_ID' => 1],
    ['ID' => 9, 'NAME' => 'Dorsum', 'IBLOCK_SECTION_ID' => 183],
];
check('название типа: свободное', eportaHardwareKindValidateName($vrows, 1, 'Замки'), null);
check('название типа: пустое', eportaHardwareKindValidateName($vrows, 1, '  ') !== null, true);
check('название типа: дубль без учёта регистра', eportaHardwareKindValidateName($vrows, 1, ' ручки ') !== null, true);
check('название типа: своё же при переименовании допустимо', eportaHardwareKindValidateName($vrows, 1, 'Ручки', 2), null);
check('название типа: совпадение с коллекцией дверей не мешает', eportaHardwareKindValidateName($vrows, 1, 'Dorsum'), null);
check('название типа: слишком длинное', eportaHardwareKindValidateName($vrows, 1, str_repeat('а', 101)) !== null, true);

echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
