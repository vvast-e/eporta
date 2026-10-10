<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Интеграционная проверка витрины фурнитуры на боевом окружении: создаёт ВРЕМЕННЫЕ товары ZZ-HWS-*
// (Фурнитура, без коллекции), запрашивает /catalog/?category=hardware и карточку по HTTP (кука dev_preview),
// проверяет разметку и УДАЛЯЕТ товары. Запуск:
//   /opt/php83/bin/php scripts/tests/hardware_storefront_integration.php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_import/lib.php');

$failures = 0;
function check(string $name, bool $cond, string $info = ''): void {
    global $failures;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond || $info === '' ? '' : " — $info") . "\n";
    if (!$cond) $failures++;
}
function cleanup(): void {
    $r = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, '%PROPERTY_CML2_ARTICLE' => 'ZZ-HWS-'], false, false, ['ID']);
    while ($x = $r->Fetch()) CIBlockElement::Delete($x['ID']);
}
function get(string $path): array {
    $ch = curl_init('https://eporta.ru' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_COOKIE => 'dev_preview=x7Qm2pR9vL']);
    $html = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $html];
}
function cards(string $html): int {
    return preg_match_all('/<div class="product-card[ "]/', $html);
}

cleanup();
try {
    $n1 = 'Ручка Armadillo LD26 Libra SN/CP-3, матовый никель/хром';
    $base = ['category' => 'Фурнитура', 'price' => 2860.0, 'discount' => 0, 'rating' => 5];
    $rows = [
        ['article' => 'ZZ-HWS-1', 'model' => 'Libra', 'name' => $n1, 'series' => 'Legend', 'brand' => 'ARMADILLO', 'material' => ['ZAMAK'], 'coating' => 'Гальваника', 'coating_color' => 'Матовый никель/хром'],
        ['article' => 'ZZ-HWS-2', 'model' => 'Libra', 'name' => 'Ручка Armadillo LD26 Libra BL-19, чёрный', 'series' => 'Legend', 'brand' => 'ARMADILLO', 'material' => ['ZAMAK'], 'coating' => 'Гальваника', 'coating_color' => 'Чёрный'],
        ['article' => 'ZZ-HWS-3', 'model' => 'Aqua', 'name' => 'Петля Fuaro Aqua универсальная', 'series' => 'Prime', 'brand' => 'FUARO', 'material' => ['Латунь'], 'coating' => 'Хром', 'coating_color' => 'Хром'],
        ['article' => 'ZZ-HWS-4', 'model' => '', 'name' => 'Защёлка Fuaro без модели', 'series' => '', 'brand' => 'FUARO', 'material' => [], 'coating' => '', 'coating_color' => ''],
    ];
    foreach ($rows as $r) {
        $res = eportaImportOneProduct($r + $base);
        check('импорт ' . $r['article'], $res['status'] === 'created', $res['status'] . ' ' . $res['message']);
    }

    [$code, $html] = get('/catalog/?category=hardware');
    check('раздел фурнитуры отвечает 200', $code === 200, (string)$code);
    check('схлопывание: 3 карточки из 4 товаров (Libra x2 → 1)', cards($html) === 3, 'карточек: ' . cards($html));
    // Представитель модели Libra — лучший по сортировке (при равном рейтинге — новее), поэтому любой из двух.
    check('полное название с запятой в карточке', str_contains($html, $n1) || str_contains($html, 'Ручка Armadillo LD26 Libra BL-19, чёрный'));
    check('карточка: класс name--full', str_contains($html, 'name--full'));
    foreach (['Бренд', 'Серия', 'Покрытие', 'Цвет', 'Материал'] as $label) {
        check("фильтр «{$label}» в сайдбаре", str_contains($html, '<!-- ' . $label . ' -->'));
    }
    check('нет фильтра «Стиль»', !str_contains($html, '<!-- Стиль -->'));
    check('canonical раздела фурнитуры', str_contains($html, 'rel="canonical" href="https://eporta.ru/catalog/?category=hardware"'));
    check('нет дверных SEO-ссылок', !str_contains($html, 'Популярные разделы каталога'));

    [$code, $html] = get('/catalog/?category=hardware&brand%5B%5D=FUARO');
    check('фильтр бренда: 2 карточки', cards($html) === 2, 'карточек: ' . cards($html));
    check('фильтр бренда: чип FUARO', str_contains($html, 'FUARO <span style="color:#a39e95">'));

    $legend = CIBlockPropertyEnum::GetList([], ['CODE' => 'SERIES', 'VALUE' => 'Legend'])->Fetch();
    [$code, $html] = get('/catalog/?category=hardware&series%5B%5D=' . (int)$legend['ID']);
    check('фильтр серии Legend: 1 карточка (модель Libra схлопнута)', cards($html) === 1, 'карточек: ' . cards($html));

    [$code, $html] = get('/catalog/?category=hardware&material%5B%5D=' . rawurlencode('Латунь'));
    check('фильтр материала Латунь: 1 карточка', cards($html) === 1, 'карточек: ' . cards($html));

    [$code, $html] = get('/catalog/?category=hardware&brand%5B%5D=NOSUCH');
    check('несуществующий бренд игнорируется (3 карточки)', cards($html) === 3, 'карточек: ' . cards($html));

    [$code, $html] = get('/catalog/?category=mkd');
    check('двери: фурнитуры в выдаче нет', !str_contains($html, 'Libra') && !str_contains($html, 'Fuaro'));
    check('двери: фильтр «Стиль» на месте', str_contains($html, '<!-- Стиль -->'));

    $el = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, 'PROPERTY_CML2_ARTICLE' => 'ZZ-HWS-1'], false, false, ['ID', 'CODE'])->Fetch();
    [$code, $html] = get('/catalog/' . $el['CODE'] . '.html');
    check('карточка товара отвечает 200', $code === 200, (string)$code);
    check('карточка: заголовок — полное название', str_contains($html, $n1));
    check('карточка: Бренд и Серия в характеристиках', str_contains($html, '>Бренд<') && str_contains($html, '>Серия<') && str_contains($html, 'Legend'));
} finally {
    cleanup();
}
$left = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, '%PROPERTY_CML2_ARTICLE' => 'ZZ-HWS-'], false, false, ['ID'])->SelectedRowsCount();
check('временные товары удалены', $left === 0, "осталось $left");
echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
