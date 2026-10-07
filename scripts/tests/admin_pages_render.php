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

ob_start();
if ($page === 'quick') {
    require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_quick_queries/index.php');
} else {
    require($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_collections/index.php');
}
$html = ob_get_clean();
file_put_contents("$outDir/admin_$page.html", $html);
echo "page=$page bytes=" . strlen($html) . "\n";
