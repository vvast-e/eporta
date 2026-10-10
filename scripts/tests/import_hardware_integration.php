<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Интеграционная проверка импорта фурнитуры на боевом окружении. Создаёт ВРЕМЕННЫЕ товары
// ZZ-HW-TEST (Фурнитура) и ZZ-HW-DOOR (дверь-контроль) без коллекции, переимпортирует и УДАЛЯЕТ их.
// Требует свойство SERIES (scripts/add_iblock19_series.php). Запуск:
//   /opt/php83/bin/php scripts/tests/import_hardware_integration.php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
require(getenv('EPORTA_IMPORT_LIB') ?: $_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_import/lib.php');

const HW = 'ZZ-HW-TEST';
const DOOR = 'ZZ-HW-DOOR';
$photo = 'eporta.ru/upload/iblock/279/lohenp6nnlnz8irtjxpzrzsz6pyskvra.jpg'; // без схемы — как у поставщика
$full = 'Ручка Armadillo (Армадилло) раздельная LD26 Libra SN/CP-3, матовый никель/хром';
$failures = 0;
function check(string $name, bool $cond, string $info = ''): void {
    global $failures;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond || $info === '' ? '' : " — $info") . "\n";
    if (!$cond) $failures++;
}
function find(string $a): ?int {
    $r = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, 'PROPERTY_CML2_ARTICLE' => $a], false, false, ['ID'])->Fetch();
    return $r ? (int)$r['ID'] : null;
}
function cleanup(): void {
    foreach ([HW, DOOR] as $a) while ($id = find($a)) CIBlockElement::Delete($id);
}
function run(array $p): string {
    $r = eportaImportOneProduct($p);
    return $r['status'] . ($r['message'] !== '' && $r['status'] === 'error' ? ': ' . $r['message'] : '');
}
function info(int $id): array {
    $el = CIBlockElement::GetByID($id)->GetNextElement();
    $f = $el->GetFields();
    $pr = $el->GetProperties();
    return ['name' => $f['NAME'], 'detail_picture' => !empty($f['DETAIL_PICTURE']),
        'model' => $pr['MODEL']['VALUE'], 'series' => $pr['SERIES']['VALUE'], 'category' => $pr['CATEGORY']['VALUE']];
}

check('свойство SERIES существует', (bool)CIBlockProperty::GetList([], ['IBLOCK_ID' => 19, 'CODE' => 'SERIES'])->Fetch());
cleanup();
try {
    $hw = ['article' => HW, 'category' => 'Фурнитура', 'series' => 'Legend', 'model' => 'Libra', 'name' => $full,
        'brand' => 'ARMADILLO', 'price' => 2860.0, 'discount' => 25.0, 'rating' => 0, 'photo_big' => $photo];

    echo "-- H1: создание фурнитуры\n";
    echo run($hw) . "\n";
    $id = find(HW);
    check('H1 товар создан', $id !== null);
    if ($id) {
        $i = info($id);
        check('H1 NAME = «Название» как есть (с запятой)', $i['name'] === $full, $i['name']);
        check('H1 MODEL = Libra (не полное название)', $i['model'] === 'Libra', (string)$i['model']);
        check('H1 SERIES = Legend', $i['series'] === 'Legend', (string)$i['series']);
        check('H1 фото без схемы скачалось', $i['detail_picture']);
    }

    echo "-- H2: переимпорт, пустое «Название» -> Модель\n";
    echo run(['name' => ''] + $hw) . "\n";
    check('H2 NAME = Libra', info($id)['name'] === 'Libra', info($id)['name']);

    echo "-- H3: возврат полного названия, серия не дублируется\n";
    echo run($hw) . "\n";
    check('H3 NAME снова полное', info($id)['name'] === $full);
    $enums = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => 19, 'CODE' => 'SERIES', 'VALUE' => 'Legend']);
    $n = 0; while ($enums->Fetch()) $n++;
    check('H3 значение серии Legend одно', $n === 1, "значений: $n");

    echo "-- D1: контроль — дверь собирается по-старому\n";
    echo run(['article' => DOOR, 'category' => 'Межкомнатные двери', 'collection' => '', 'model' => 'Тест 1', 'name' => '',
        'coating' => 'экошпон', 'coating_color' => 'дуб серый', 'price' => 10000.0, 'discount' => 0, 'rating' => 5]) . "\n";
    $did = find(DOOR);
    check('D1 имя двери = «Тест 1, экошпон дуб серый»', $did && info($did)['name'] === 'Тест 1, экошпон дуб серый', $did ? info($did)['name'] : 'нет товара');
} finally {
    cleanup();
}
check('временные товары удалены', find(HW) === null && find(DOOR) === null);
echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
