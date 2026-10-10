<?php
// Только консоль (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Интеграционная проверка типов фурнитуры (этап 3) на боевом окружении: создаёт ВРЕМЕННЫЕ товары ZZ-HWK-*
// с «Коллекцией» = тип изделия, проверяет раздел товара, страницы категории/типа (HTTP, кука dev_preview),
// крошки карточки, неизменность списка коллекций дверей — и УДАЛЯЕТ товары. Разделы типов должны быть созданы
// (scripts/add_hardware_kinds_sections.php). Запуск:
//   /opt/php83/bin/php scripts/tests/hardware_kinds_integration.php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_import/lib.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/eporta_collections.php');

$failures = 0;
function check(string $name, bool $cond, string $info = ''): void {
    global $failures;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond || $info === '' ? '' : " — $info") . "\n";
    if (!$cond) $failures++;
}
function cleanup(): void {
    $r = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, '%PROPERTY_CML2_ARTICLE' => 'ZZ-HWK-'], false, false, ['ID']);
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

$collectionsBefore = array_column(eportaCollections(true), 'ID');
check('разделы типов созданы (11 штук)', count(eportaHardwareKinds()) === 11, 'типов: ' . count(eportaHardwareKinds()));
check('список коллекций дверей без типов фурнитуры', count(array_intersect($collectionsBefore, array_column(eportaHardwareKinds(), 'ID'))) === 0);

cleanup();
try {
    $base = ['category' => 'Фурнитура', 'price' => 1500.0, 'discount' => 0, 'rating' => 5, 'brand' => 'ZZBRAND', 'series' => 'Legend', 'coating' => 'Гальваника', 'material' => ['ZAMAK']];
    $rows = [
        ['article' => 'ZZ-HWK-1', 'model' => 'Libra', 'name' => 'Ручка ZZ Libra хром', 'collection' => 'Ручки', 'coating_color' => 'Хром'],
        ['article' => 'ZZ-HWK-2', 'model' => 'Libra', 'name' => 'Ручка ZZ Libra чёрная', 'collection' => 'Ручки', 'coating_color' => 'Чёрный'],
        ['article' => 'ZZ-HWK-3', 'model' => 'Aqua', 'name' => 'Ручка ZZ Aqua', 'collection' => 'Ручки', 'coating_color' => 'Хром'],
        ['article' => 'ZZ-HWK-4', 'model' => 'Mono', 'name' => 'Петля ZZ Mono', 'collection' => ' петли ', 'coating_color' => 'Хром'],
    ];
    foreach ($rows as $r) {
        $res = eportaImportOneProduct($r + $base);
        check('импорт ' . $r['article'], $res['status'] === 'created', $res['status'] . ' ' . $res['message']);
    }
    $bad = eportaImportOneProduct(['article' => 'ZZ-HWK-5', 'model' => 'X', 'name' => 'ZZ без типа', 'collection' => 'Несуществующий'] + $base);
    check('неизвестный тип: ошибка «Не найден тип фурнитуры»', $bad['status'] === 'error' && str_contains($bad['message'], 'Не найден тип фурнитуры'), $bad['message']);
    $door = eportaImportOneProduct(['article' => 'ZZ-HWK-6', 'model' => 'X', 'name' => 'ZZ дверь', 'collection' => 'Ручки', 'category' => 'Межкомнатные двери', 'price' => 1000.0, 'discount' => 0, 'rating' => 1]);
    check('дверь с коллекцией «Ручки»: ошибка «Не найдена коллекция» (типы дверям недоступны)', $door['status'] === 'error' && str_contains($door['message'], 'Не найдена коллекция'), $door['message']);

    $kindId = function (string $code): int {
        $k = eportaHardwareFindKind(eportaHardwareKinds(), $code);
        return $k ? (int)$k['ID'] : 0;
    };
    $el = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, 'PROPERTY_CML2_ARTICLE' => 'ZZ-HWK-1'], false, false, ['ID', 'CODE', 'IBLOCK_SECTION_ID'])->Fetch();
    check('товар лежит в разделе «Ручки»', (int)$el['IBLOCK_SECTION_ID'] === $kindId('ruchki'));
    $el4 = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, 'PROPERTY_CML2_ARTICLE' => 'ZZ-HWK-4'], false, false, ['ID', 'IBLOCK_SECTION_ID'])->Fetch();
    check('«петли» с пробелами/регистром — в разделе «Петли»', (int)$el4['IBLOCK_SECTION_ID'] === $kindId('petli'));

    [$code, $html] = get('/catalog/?category=hardware');
    check('категория отвечает 200', $code === 200, (string)$code);
    check('полоска типов есть', str_contains($html, 'class="eporta-hwkind-nav"'));
    check('кнопка «Ручки» со счётчиком моделей = 2', (bool)preg_match('~type=ruchki"[^>]*>Ручки <span class="eporta-hwkind-cnt">(\d+)</span>~u', $html, $m) && (int)$m[1] === 2, $m[1] ?? 'нет кнопки');
    check('кнопка «Петли» есть', str_contains($html, 'type=petli"'));
    check('кнопки без товаров («Пороги») скрыты', !str_contains($html, 'type=porogi"'));
    check('кнопка «Скрыть»', str_contains($html, 'id="eportaHwKindToggle"'));
    check('крошки категории: Фурнитура, не «Межкомнатные»', str_contains($html, '<span aria-current="page">Фурнитура</span>') && !str_contains($html, '>Межкомнатные<'));
    check('canonical категории', str_contains($html, 'rel="canonical" href="https://eporta.ru/catalog/?category=hardware"'));

    [$code, $html] = get('/catalog/?category=hardware&type=ruchki&brand%5B%5D=ZZBRAND');
    check('страница типа: 2 карточки (Libra x2 → 1, Aqua)', cards($html) === 2, 'карточек: ' . cards($html));
    check('тип: H1 «Ручки»', str_contains($html, '>Ручки</h1>'));
    check('тип: без слова «Коллекция»', !str_contains($html, 'Коллекция'));
    check('тип: петля не попала', !str_contains($html, 'Петля ZZ Mono'));
    check('тип: крошки Фурнитура › Ручки', str_contains($html, 'category=hardware">Фурнитура</a>') && str_contains($html, '<span aria-current="page">Ручки</span>'));
    check('тип: активная кнопка', (bool)preg_match('~eporta-subcoll-btn--active"[^>]*aria-current="page">Ручки~u', $html));
    check('тип: canonical на себя', str_contains($html, 'rel="canonical" href="https://eporta.ru/catalog/?category=hardware&amp;type=ruchki"') || str_contains($html, 'rel="canonical" href="https://eporta.ru/catalog/?category=hardware&type=ruchki"'));
    foreach (['Бренд', 'Серия', 'Покрытие', 'Цвет', 'Материал'] as $label) {
        check("тип: фильтр «{$label}»", str_contains($html, '<!-- ' . $label . ' -->'));
    }

    [$code, $html] = get('/catalog/?category=hardware&type=nosuch');
    check('неизвестный type игнорируется (200, canonical категории)', $code === 200 && str_contains($html, 'rel="canonical" href="https://eporta.ru/catalog/?category=hardware"'));

    [$code, $html] = get('/catalog/' . $el['CODE'] . '.html');
    check('карточка 200', $code === 200, (string)$code);
    check('карточка: крошки Фурнитура › Ручки › товар', str_contains($html, 'category=hardware">Фурнитура</a>') && str_contains($html, 'type=ruchki">Ручки</a>') && str_contains($html, '<span aria-current="page">Ручка ZZ Libra хром</span>'));

    [$code, $html] = get('/catalog/collections/ruchki/');
    check('/catalog/collections/ruchki/ — 404 (тип не коллекция)', $code === 404, (string)$code);
    [$code, $html] = get('/collection/');
    check('хаб коллекций не содержит типов', !str_contains($html, 'Ручки') && !str_contains($html, 'Петли'));
} finally {
    cleanup();
}
$left = CIBlockElement::GetList([], ['IBLOCK_ID' => 19, '%PROPERTY_CML2_ARTICLE' => 'ZZ-HWK-'], false, false, ['ID'])->SelectedRowsCount();
check('временные товары удалены', $left === 0, "осталось $left");
check('список коллекций дверей не изменился', array_column(eportaCollections(true), 'ID') === $collectionsBefore);
echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки пройдены\n";
exit($failures ? 1 : 0);
