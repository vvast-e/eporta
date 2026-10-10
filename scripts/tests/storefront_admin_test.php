<?php
// Только консоль: статические проверки файлов репозитория, в веб-доступе не нужны.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Проверки раздела «Управление витриной» без Bitrix: вкладки и шапка, подключение шапки на страницах,
// сохранность прав/CSRF/action-ов у страниц (заморожены — менять их эта задача не должна), страница-вход.
// Запуск: php scripts/tests/storefront_admin_test.php  (код возврата 0 = всё прошло)
define('B_PROLOG_INCLUDED', true);
$root = realpath(__DIR__ . '/../../local/admin_tools');
require($root . '/eporta_storefront/nav.php');

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
function navHtml(string $key): string {
    ob_start();
    eportaStorefrontNav($key);
    return ob_get_clean();
}

// Вкладка => [папка, страница, action-ы ajax.php]. Список action-ов заморожен: переименование/удаление
// ломает JS страниц, поэтому тест упадёт при любом изменении.
$pages = [
    'collections' => ['eporta_collections', ['create', 'update', 'set_parent', 'upload_banner', 'update_banner_meta', 'get_models', 'set_showcase', 'set_show_in_list']],
    'hardware_kinds' => ['eporta_hardware_kinds', ['create', 'update']],
    'banners' => ['eporta_banners', ['save_slot_meta', 'save_meta', 'set_overlay']],
    'home_tabs' => ['eporta_home_tabs', ['search', 'save']],
    'quick' => ['eporta_quick_queries', ['search', 'save']],
    'import' => ['eporta_import', ['upload', 'batch']],
    'promo' => ['eporta_promo', ['list', 'save', 'upload', 'upload_inline', 'delete']],
    'works' => ['eporta_works', ['list', 'save', 'upload', 'delete']],
    'reviews' => ['eporta_reviews', ['list', 'save', 'delete']],
    'articles' => ['eporta_articles', ['list', 'save', 'upload', 'upload_inline', 'delete']],
];

// --- вкладки и шапка ---
$tabs = eportaStorefrontTabs();
$all = array_merge($tabs['main'], $tabs['content']);
check('вкладки: набор ключей', array_keys($all), array_keys($pages));
check('вкладки: группа «Контент»', array_keys($tabs['content']), ['promo', 'works', 'reviews', 'articles']);
foreach ($pages as $key => [$dir]) {
    check("вкладка $key: адрес прежний", $all[$key][1], "/local/admin_tools/$dir/");
    check("вкладка $key: страница существует", is_file("$root/$dir/index.php"), true);
    $html = navHtml($key);
    check("шапка $key: одна активная вкладка", substr_count($html, 'aria-current="page"'), 1);
    check("шапка $key: активна своя ссылка", (bool)preg_match('~href="/local/admin_tools/' . $dir . '/" aria-current="page"~', $html), true);
    check("шапка $key: ссылок на вкладки", substr_count($html, 'class="sf-tab'), count($all));
    check("шапка $key: без скриптов", strpos($html, '<script'), false);
}
check('общий файл стилей на месте', is_file("$root/eporta_storefront/admin.css"), true);
check('шапка без активной вкладки', substr_count(navHtml(''), 'aria-current'), 0);
check('шапка: неизвестный ключ не ломает вывод', substr_count(navHtml('zzz'), 'aria-current'), 0);
check('шапка: группа Контент подсвечена для promo', (bool)preg_match('~sf-group is-active">Контент~', navHtml('promo')), true);
check('шапка: группа Витрина подсвечена для collections', (bool)preg_match('~sf-group is-active">Витрина~', navHtml('collections')), true);

// --- подключение шапки: ровно одна строка на странице, ключ совпадает со страницей ---
$includes = [];
foreach ($pages as $key => [$dir]) {
    $includes["$dir/index.php"] = $key;
}
$includes['eporta_collections/banner.php'] = 'collections';
foreach ($includes as $file => $key) {
    $src = file_get_contents("$root/$file");
    check("$file: шапка подключена один раз", substr_count($src, 'eportaStorefrontNav('), 1);
    check("$file: ключ вкладки $key", strpos($src, "eportaStorefrontNav('$key')") !== false, true);
    check("$file: шапка сразу после body", (bool)preg_match("~<body>\r?\n<\?php require_once \\\$_SERVER\['DOCUMENT_ROOT'\] \. '/local/admin_tools/eporta_storefront/nav\.php'; eportaStorefrontNav~", $src), true);
}

// --- права и CSRF у каждой вкладки ---
foreach ($pages as $key => [$dir, $actions]) {
    $index = file_get_contents("$root/$dir/index.php");
    $ajax = file_get_contents("$root/$dir/ajax.php");
    $posIsAuth = strpos($index, '->IsAuthorized()');
    $posAccess = strpos($index, 'UserHasAccess()');
    check("$key: страница проверяет вход и права до вывода", $posIsAuth !== false && $posAccess !== false && $posIsAuth < $posAccess, true);
    $posCsrf = strpos($ajax, "!check_bitrix_sessid()");
    $posFirstAction = strpos($ajax, '$action ===');
    check("$key: ajax — POST+sessid раньше любого action", $posCsrf !== false && $posFirstAction !== false && $posCsrf < $posFirstAction, true);
    check("$key: ajax — проверка прав", (bool)preg_match('~UserHasAccess\(\)~', $ajax), true);
    preg_match_all('~\$action === \'([a-z_]+)\'~', $ajax, $m);
    check("$key: список action-ов не менялся", $m[1], $actions);
}
$bannerPage = file_get_contents("$root/eporta_collections/banner.php");
check('collection banner.php: права проверяются', strpos($bannerPage, 'eportaCollectionsUserHasAccess()') !== false, true);

// --- страница-вход: только навигация ---
$hub = file_get_contents("$root/eporta_storefront/index.php");
check('вход: требует авторизацию', strpos($hub, '->IsAuthorized()') !== false, true);
check('вход: не пишет и не читает данные', (bool)preg_match('~\$_POST|CIBlock|CUser::|->Update\(|->Add\(|->Delete\(|COption~', $hub), false);
check('вход: нет собственного ajax', is_file("$root/eporta_storefront/ajax.php"), false);

echo $failures ? "\nПровалено: $failures\n" : "\nВсё прошло\n";
exit($failures ? 1 : 0);
