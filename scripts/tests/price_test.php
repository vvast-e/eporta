<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Проверки чистой логики local/lib/eporta_price.php. Запуск: php scripts/tests/price_test.php
define('B_PROLOG_INCLUDED', true);
require(__DIR__ . '/../../local/lib/eporta_price.php');

$failures = 0;
function check(string $name, $actual, $expected): void {
    global $failures;
    if ($actual === $expected) {
        echo "ok   $name\n";
        return;
    }
    $failures++;
    echo "FAIL $name\n  ожидалось: " . json_encode($expected) . "\n  получено:  " . json_encode($actual) . "\n";
}

check('финальная: 18220 −25% = 13665', eportaPriceFinal(18220, 25), 13665.0);
check('финальная: без скидки — как есть', eportaPriceFinal(18220, 0), 18220.0);
check('финальная: скидка >100 ограничена', eportaPriceFinal(1000, 150), 0.0);
check('финальная: отрицательная скидка = 0', eportaPriceFinal(1000, -5), 1000.0);
check('старая: явная исходная цена', eportaPriceOld(13665, 25, 18220), 18220.0);
check('старая: исходная не задана — расчёт от скидки', eportaPriceOld(13665, 25), 18220.0);
check('старая: исходная <= текущей игнорируется', eportaPriceOld(13665, 25, 100), 18220.0);
check('старая: нет скидки — нет зачёркнутой', eportaPriceOld(13665, 0, 18220), 0.0);
check('старая: нет цены — 0', eportaPriceOld(0, 25, 18220), 0.0);
check('старая: скидка 100% — 0 (деление на ноль)', eportaPriceOld(0, 100, 5000), 0.0);
// Круговой путь: исходная → финальная → «старая» без явной цены может разойтись на ≤1 ₽, явная — нет.
$orig = 17999.0;
$final = eportaPriceFinal($orig, 15);
check('явная исходная не искажается округлением', eportaPriceOld($final, 15, $orig), $orig);

echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
