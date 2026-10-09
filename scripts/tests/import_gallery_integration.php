<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Интеграционная проверка галереи («Галерея» -> MORE_PHOTO) веб-импортёра на боевом окружении.
// Создаёт ВРЕМЕННЫЙ товар с артикулом ZZ-GALLERY-TEST (без коллекции), несколько раз переимпортирует его
// с разными списками фото, пишет снимок остальных полей и УДАЛЯЕТ товар. Запуск:
//   /opt/php83/bin/php scripts/tests/import_gallery_integration.php [путь_к_снимку.json]
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
// EPORTA_IMPORT_LIB — путь к проверяемой копии lib.php (рядом должен лежать webp_convert.php): так правку
// можно прогнать до выкатки. По умолчанию — боевой файл.
require(getenv('EPORTA_IMPORT_LIB') ?: $_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_import/lib.php');

const ARTICLE = 'ZZ-GALLERY-TEST';
$base = 'https://eporta.ru/upload/iblock/';
$img = [
    'A' => $base . '279/lohenp6nnlnz8irtjxpzrzsz6pyskvra.jpg',
    'B' => $base . '3b1/7n2yg3byaignmh8vzrzqq07mzbrshgmu.jpg',
    'C' => $base . '44e/eknsjq8pegmhjlxqjwd329j5vkorkxcz.jpg',
    'D' => $base . '5c0/4q0vgilsz7okehtk8fo1s46pa3bt6dix.jpg',
    'E' => $base . '84d/vtg5c71tgfo59y66aba9iv212k13z5mc.jpg',
];
$failures = 0;
function check(string $name, bool $cond, string $info = ''): void {
    global $failures;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond || $info === '' ? '' : " — $info") . "\n";
    if (!$cond) $failures++;
}
function findTest(): ?int {
    $r = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, 'PROPERTY_CML2_ARTICLE' => ARTICLE], false, false, ['ID'])->Fetch();
    return $r ? (int)$r['ID'] : null;
}
function cleanup(): void {
    while ($id = findTest()) {
        CIBlockElement::Delete($id);
    }
}
// Имена файлов галереи (по порядку) — различают фото по исходному имени из URL.
function gallery(int $id): array {
    $names = [];
    $rs = CIBlockElement::GetProperty(19, $id, ['ID' => 'ASC'], ['CODE' => 'MORE_PHOTO']);
    while ($p = $rs->Fetch()) {
        if (!empty($p['VALUE'])) {
            $f = CFile::GetFileArray((int)$p['VALUE']);
            $names[] = $f ? $f['ORIGINAL_NAME'] : '?';
        }
    }
    return $names;
}
function galleryFileIds(int $id): array {
    $ids = [];
    $rs = CIBlockElement::GetProperty(19, $id, ['ID' => 'ASC'], ['CODE' => 'MORE_PHOTO']);
    while ($p = $rs->Fetch()) {
        if (!empty($p['VALUE'])) $ids[] = (int)$p['VALUE'];
    }
    return $ids;
}
function product(array $over): array {
    return array_merge([
        'article' => ARTICLE, 'name' => 'Тест галереи', 'model' => 'Тест галереи', 'brand' => 'Eporta',
        'category' => 'Межкомнатные двери', 'price' => 10000.0, 'discount' => 10.0, 'rating' => 5,
    ], $over);
}
function run(array $p): string {
    $r = eportaImportOneProduct($p);
    return $r['status'] . ($r['status'] === 'error' ? ': ' . $r['message'] : '');
}
// Снимок всего, кроме галереи и id файлов: поля элемента, свойства, цена.
function snapshot(int $id): array {
    $el = CIBlockElement::GetByID($id)->GetNextElement();
    $f = $el->GetFields();
    $out = ['fields' => []];
    foreach (['NAME', 'CODE', 'ACTIVE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'IBLOCK_SECTION_ID'] as $k) {
        $out['fields'][$k] = $f[$k] ?? null;
    }
    $out['has_detail_picture'] = !empty($f['DETAIL_PICTURE']);
    $out['has_preview_picture'] = !empty($f['PREVIEW_PICTURE']);
    $out['props'] = [];
    foreach ($el->GetProperties() as $code => $pr) {
        if ($code === 'MORE_PHOTO') continue;
        $out['props'][$code] = $pr['PROPERTY_TYPE'] === 'F' ? (empty($pr['VALUE']) ? '' : 'file') : $pr['VALUE'];
    }
    $price = CPrice::GetList([], ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1])->Fetch();
    $out['price'] = $price ? (float)$price['PRICE'] : null;
    return $out;
}

cleanup();
$names = array_map(fn($u) => basename($u), $img);

echo "-- S1: создание, галерея [A,B]\n";
echo run(product(['photo_big' => $img['A'], 'gallery' => [$img['A'], $img['B']]])) . "\n";
$id = findTest();
check('S1 товар создан', $id !== null);
$g1 = gallery($id);
check('S1 в галерее 2 фото', count($g1) === 2, json_encode($g1));
$snap1 = snapshot($id);
$oldFileIds = galleryFileIds($id);

echo "-- S2: переимпорт, галерея [C,D] (ожидается замена A,B на C,D)\n";
echo run(product(['photo_big' => $img['A'], 'gallery' => [$img['C'], $img['D']]])) . "\n";
$g2 = gallery($id);
check('S2 в галерее ровно C,D', $g2 === [$names['C'], $names['D']], json_encode($g2));
$alive = array_filter($oldFileIds, fn($fid) => CFile::GetFileArray($fid));
check('S2 старые файлы A,B удалены из хранилища', !$alive, 'остались file_id: ' . implode(',', $alive));

echo "-- S3: тот же список [C,D] ещё раз\n";
echo run(product(['photo_big' => $img['A'], 'gallery' => [$img['C'], $img['D']]])) . "\n";
$g3 = gallery($id);
check('S3 по-прежнему ровно C,D', $g3 === [$names['C'], $names['D']], json_encode($g3));
$snap3 = snapshot($id);
check('S3 прочие поля не изменились относительно S1', $snap3 === $snap1);
if ($snap3 !== $snap1) {
    echo "  S1: " . json_encode($snap1, JSON_UNESCAPED_UNICODE) . "\n  S3: " . json_encode($snap3, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "-- S4: переимпорт с НЕДОСТУПНЫМ фото в галерее (старая галерея должна сохраниться)\n";
$r4 = eportaImportOneProduct(product(['photo_big' => $img['A'], 'gallery' => ['https://eporta.ru/upload/zz-no-such-file.jpg']]));
echo $r4['status'] . ' ' . $r4['message'] . "\n";
$g4 = gallery($id);
check('S4 прежняя галерея C,D сохранена', $g4 === [$names['C'], $names['D']], json_encode($g4));
check('S4 в отчёте есть предупреждение', strpos($r4['message'], 'не скачались') !== false, $r4['message']);

echo "-- S4b: файл БЕЗ колонки «Галерея» (ключ gallery не задан) — галерея не трогается\n";
echo run(product(['photo_big' => $img['A']])) . "\n";
$g4b = gallery($id);
check('S4b галерея C,D не тронута', $g4b === [$names['C'], $names['D']], json_encode($g4b));

echo "-- S4c: замена на [E] -> ровно E\n";
echo run(product(['photo_big' => $img['A'], 'gallery' => [$img['E']]])) . "\n";
$g4c = gallery($id);
check('S4c ровно E', $g4c === [$names['E']], json_encode($g4c));

echo "-- S5: переимпорт с ПУСТОЙ галереей — очищается\n";
echo run(product(['photo_big' => $img['A'], 'gallery' => []])) . "\n";
$g5 = gallery($id);
check('S5 галерея пуста', $g5 === [], json_encode($g5));
$snap5 = snapshot($id);
check('S5 прочие поля не изменились относительно S1', $snap5 === $snap1);

if (!empty($argv[1])) {
    file_put_contents($argv[1], json_encode(['s1' => $snap1, 's3' => $snap3, 'g' => [$g1, $g2, $g3, $g4, $g4b, $g4c, $g5]], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "снимок: {$argv[1]}\n";
}
cleanup();
check('временный товар удалён', findTest() === null);
echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
