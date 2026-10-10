<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Проверки чистой логики импорта фурнитуры (local/admin_tools/eporta_import/lib.php) — Bitrix не нужен.
// Запуск: php -d short_open_tag=1 scripts/tests/import_hardware_test.php  (0 = всё прошло)
define('B_PROLOG_INCLUDED', true);
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_import/lib.php');

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

$full = 'Ручка Armadillo (Армадилло) раздельная LD26 Libra SN/CP-3 матовый никель/хром';
$hw = ['category' => 'Фурнитура', 'collection' => 'Ручки', 'model' => 'Libra', 'name' => $full, 'coating' => 'Гальваника', 'coating_color' => 'Матовый никель/хром'];

check('фурнитура: категория «Фурнитура»', eportaImportIsHardware($hw), true);
check('фурнитура: алиас «ФУР», любой регистр', eportaImportIsHardware(['category' => 'фур']), true);
check('не фурнитура: МКД', eportaImportIsHardware(['category' => 'МКД']), false);
check('не фурнитура: пустая категория', eportaImportIsHardware([]), false);

check('имя фурнитуры = «Название» как есть', eportaImportComposeName($hw, '13739'), $full);
check('имя с запятой не режется и не дополняется', eportaImportComposeName(['category' => 'Фурнитура', 'name' => 'Петля, универсальная'] + $hw, '1'), 'Петля, универсальная');
check('имя: пустое «Название» → Модель', eportaImportComposeName(['name' => ''] + $hw, '13739'), 'Libra');
check('имя: ничего нет → артикул', eportaImportComposeName(['category' => 'Фурнитура'], '13739'), '13739');
// Двери не затронуты
$door = ['category' => 'Межкомнатные двери', 'collection' => 'Vilis', 'model' => 'Vilis 2', 'name' => '', 'coating' => 'экошпон', 'coating_color' => 'дуб серый'];
check('двери: прежняя сборка имени', eportaImportComposeName($door, '1'), 'Vilis 2, экошпон дуб серый');

check('url без схемы → https', eportaImportNormalizeImageUrl('www.tlock.ru/photo_bank/13739_01.jpg'), 'https://www.tlock.ru/photo_bank/13739_01.jpg');
check('url //host → https', eportaImportNormalizeImageUrl('//cdn.x.ru/a.jpg'), 'https://cdn.x.ru/a.jpg');
check('url с https не меняется', eportaImportNormalizeImageUrl('https://a.ru/b.jpg'), 'https://a.ru/b.jpg');
check('url http не меняется', eportaImportNormalizeImageUrl('http://a.ru/b.jpg'), 'http://a.ru/b.jpg');
check('url file:// не «чинится»', eportaImportNormalizeImageUrl('file:///etc/passwd'), 'file:///etc/passwd');
check('url ftp:// не «чинится»', eportaImportNormalizeImageUrl('ftp://a.ru/b.jpg'), 'ftp://a.ru/b.jpg');
check('url localhost без точки не «чинится»', eportaImportNormalizeImageUrl('localhost/a.jpg'), 'localhost/a.jpg');
check('путь без хоста не меняется', eportaImportNormalizeImageUrl('/etc/passwd'), '/etc/passwd');

$parsed = eportaImportParseGrid([
    ['Артикул', 'Серия', 'Модель', 'Название', 'Категория'],
    ['13739', 'Legend', 'Libra', $full, 'Фурнитура'],
]);
check('парсер: «Серия» распознана', $parsed['products'][0]['series'] ?? null, 'Legend');
check('парсер: «Серия» не в неизвестных', in_array('Серия', $parsed['unmapped'], true), false);

echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
