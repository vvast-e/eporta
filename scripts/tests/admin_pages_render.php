<?php
// Только консоль: скрипт подставляет права/меняет данные и не должен выполняться по HTTP (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Рендер страниц админок «Коллекции» и «Популярные запросы» в CLI на сервере (без входа в админку:
// после загрузки ядра подставляется объект пользователя с правами) — результат кладётся в HTML-файлы
// для проверки в браузере. Запуск: /opt/php83/bin/php scripts/tests/admin_pages_render.php <папка-вывода>
// Ничего не пишет в БД и не трогает реальных пользователей.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
$_SERVER['REQUEST_URI'] = '/local/admin_tools/';
$_SERVER['HTTP_HOST'] = 'eporta.ru';
$outDir = rtrim($argv[1] ?? '/tmp/ehtest/out', '/');
@mkdir($outDir, 0777, true);
$page = $argv[2] ?? 'collections';
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

global $USER;
// Наследник реального CUser (остальные методы нужны ядру при завершении страницы) с правами админа.
$USER = new class extends CUser {
    public function IsAuthorized() { return true; }
    public function IsAdmin() { return true; }
};

// Страницы админок: ключ -> путь относительно local/admin_tools/ (collections/quick — как раньше).
$pages = [
    'collections' => 'eporta_collections/index.php',
    'quick' => 'eporta_quick_queries/index.php',
    'banners' => 'eporta_banners/index.php',
    'home_tabs' => 'eporta_home_tabs/index.php',
    'import' => 'eporta_import/index.php',
    'promo' => 'eporta_promo/index.php',
    'works' => 'eporta_works/index.php',
    'reviews' => 'eporta_reviews/index.php',
    'articles' => 'eporta_articles/index.php',
    'collection_banner' => 'eporta_collections/banner.php',
    'storefront' => 'eporta_storefront/index.php',
];
if ($page === 'collection_banner') {
    $_GET['id'] = $_REQUEST['id'] = $argv[3] ?? 194;
}
ob_start();
require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/' . ($pages[$page] ?? $pages['collections']));
$html = ob_get_clean();
file_put_contents("$outDir/admin_$page.html", $html);
echo "page=$page bytes=" . strlen($html) . "\n";
