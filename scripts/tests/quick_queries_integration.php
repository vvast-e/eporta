<?php
// Интеграционная проверка «Популярных запросов» на боевом окружении (нужен Bitrix, запускать на
// сервере: /opt/php83/bin/php scripts/tests/quick_queries_integration.php). Сохраняет ТЕСТОВЫЙ
// запрос, открывает каталог по его ссылке (с кукой dev_preview) и проверяет, что закреплённая дверь
// идёт первой, а без pq — нет; затем ВОЗВРАЩАЕТ настройки в исходное состояние.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('iblock');
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_quick_queries/lib.php');

$failures = 0;
function check(string $name, bool $cond, string $info = ''): void {
    global $failures;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond || $info === '' ? '' : " — $info") . "\n";
    if (!$cond) $failures++;
}
function fetchPage(string $path): string {
    $ctx = stream_context_create([
        'http' => ['header' => "Cookie: dev_preview=x7Qm2pR9vL\r\n", 'timeout' => 60],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    return (string)@file_get_contents('https://eporta.ru' . $path, false, $ctx);
}
// Коды товаров в порядке появления карточек в сетке каталога.
function cardCodes(string $html): array {
    preg_match_all('~href="/catalog/(?:[^"/]+/)*([^"/]+)\.html"~', $html, $m);
    return array_values(array_unique($m[1]));
}

$original = \COption::GetOptionString(EPORTA_QUICK_QUERIES_OPTION_MODULE, EPORTA_QUICK_QUERIES_OPTION_NAME, '');
try {
    // Закрепляем один из самых "непопулярных" (по RATING ASC) активных товаров — в обычной выдаче он не первый.
    // Случайный из 150 самых "непопулярных" — иначе повторный прогон попадает в кэш компонента
    // (ключ кэша зависит от набора ID страницы) от предыдущего прогона.
    $res = \CIBlockElement::GetList(['PROPERTY_RATING' => 'ASC', 'ID' => 'ASC'], ['IBLOCK_ID' => 19, 'ACTIVE' => 'Y'], false, ['nTopCount' => random_int(1, 150)], ['ID', 'CODE', 'NAME']);
    $row = null;
    while ($candidate = $res->Fetch()) {
        $row = $candidate;
    }
    check('есть товар для закрепления', (bool)$row);
    $pinnedId = (int)$row['ID'];
    $pinnedCode = $row['CODE'];

    $options = eportaQuickQueriesFilterOptions();
    check('варианты фильтров загружены (стиль/цвет/размер/категория)', $options['style'] && $options['color'] && $options['size'] && $options['category']);

    $list = eportaQuickQueriesSanitizeAndSave([[
        'id' => 1, 'label' => 'ТЕСТ-запрос',
        'filters' => ['style' => [array_key_first($options['style']), 999999], 'sale' => false],
        'pinned' => [$pinnedId, 99999999],
    ]], $options);
    check('сохранён один запрос', count($list) === 1);
    check('несуществующая дверь отброшена, настоящая осталась', $list[0]['pinned'] === [$pinnedId]);
    check('несуществующий стиль отброшен', count($list[0]['filters']['style']) === 1);
    check('чтение из COption совпадает с сохранённым', eportaQuickQueriesGet() === $list);

    $url = eportaQuickQueryUrl($list[0]);
    check('в ссылке есть pq', strpos($url, 'pq=1') !== false, $url);

    $withPq = cardCodes(fetchPage($url));
    check('каталог по ссылке отдаёт карточки', count($withPq) > 0);
    check('закреплённая дверь ПЕРВАЯ в выдаче', ($withPq[0] ?? '') === $pinnedCode, 'первая: ' . ($withPq[0] ?? 'нет') . ', ожидалась: ' . $pinnedCode);

    // Чип запроса: клик по нему и по любому другому чипу снимает pq (закреплённые двери исчезают).
    $pageWithPq = fetchPage($url);
    check('чип запроса на странице', (bool)preg_match('~<a href="([^"]*)"[^>]*>ТЕСТ-запрос <span~u', $pageWithPq, $chip), 'чип не найден');
    check('ссылка чипа не содержит pq', isset($chip[1]) && strpos(html_entity_decode($chip[1]), 'pq=') === false, $chip[1] ?? '');
    {
        // Второй чип (стиль) — его ссылка тоже без pq: ищем все ссылки чипов в блоке чипов.
        preg_match('~eporta-catalog-chips(.*?)</div>~s', $pageWithPq, $chipsBlock);
        preg_match_all('~<a href="([^"]*)"~', $chipsBlock[1] ?? '', $chipLinks);
        $badLinks = array_filter($chipLinks[1], function ($href) { return strpos(html_entity_decode($href), 'pq=') !== false; });
        check('ни одна ссылка снятия чипа не содержит pq', $chipLinks[1] && !$badLinks, implode(',', $badLinks));
    }

    $withoutPq = cardCodes(fetchPage(str_replace('&pq=1', '', str_replace('pq=1&', '', $url))));
    check('без pq закреплённая дверь не первая', ($withoutPq[0] ?? '') !== $pinnedCode);

    $home = fetchPage('/');
    check('на главной есть кнопка запроса со ссылкой', strpos($home, 'data-quick-queries') !== false && strpos($home, 'ТЕСТ-запрос') !== false && strpos($home, htmlspecialchars($url, ENT_QUOTES)) !== false);

    // Пустой список = админ всё удалил — блок на главной скрыт.
    eportaQuickQueriesSave([]);
    check('пустой список скрывает блок на главной', strpos(fetchPage('/'), 'data-quick-queries') === false);
} finally {
    if ($original === '') {
        \COption::RemoveOption(EPORTA_QUICK_QUERIES_OPTION_MODULE, EPORTA_QUICK_QUERIES_OPTION_NAME);
    } else {
        \COption::SetOptionString(EPORTA_QUICK_QUERIES_OPTION_MODULE, EPORTA_QUICK_QUERIES_OPTION_NAME, $original);
    }
}
check('настройки возвращены в исходное состояние', \COption::GetOptionString(EPORTA_QUICK_QUERIES_OPTION_MODULE, EPORTA_QUICK_QUERIES_OPTION_NAME, '') === $original);
echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки прошли\n";
exit($failures ? 1 : 0);
