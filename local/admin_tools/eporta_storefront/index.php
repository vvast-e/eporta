<?php
// Единый вход в раздел «Управление витриной»: только список страниц админок. Права и CSRF проверяет
// каждая страница сама, здесь данных не читается и ничего не меняется.
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/nav.php');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}

$tabs = eportaStorefrontTabs();
$groups = ['main' => 'Витрина', 'content' => 'Контент'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Управление витриной (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    h2 { font-size: 16px; margin: 28px 0 10px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; }
    .sf-list { list-style: none; margin: 0; padding: 0; border-top: 1px solid #eee; }
    .sf-list li { border-bottom: 1px solid #eee; }
    .sf-list a { display: flex; flex-wrap: wrap; gap: 4px 16px; padding: 12px 10px; color: inherit; text-decoration: none; }
    .sf-list a:hover { background: #f2f7fc; }
    .sf-list a:focus-visible { outline: 2px solid #2b6cb0; outline-offset: -2px; }
    .sf-list .name { font-weight: 600; color: #2b6cb0; min-width: 180px; }
    .sf-list .desc { color: #666; font-size: 13px; flex: 1 1 320px; align-self: center; }
</style>
</head>
<body>
<?php eportaStorefrontNav(''); ?>
<h1>Разделы</h1>
<p class="hint">Всё, что настраивается на сайте, в одном месте. Если на нужной странице нет доступа, обратитесь к администратору сайта.</p>
<?php foreach ($groups as $key => $title) { ?>
    <h2><?= htmlspecialchars($title, ENT_QUOTES) ?></h2>
    <ul class="sf-list">
        <?php foreach ($tabs[$key] as $tab) { ?>
            <li><a href="<?= htmlspecialchars($tab[1], ENT_QUOTES) ?>"><span class="name"><?= htmlspecialchars($tab[0], ENT_QUOTES) ?></span><span class="desc"><?= htmlspecialchars($tab[2], ENT_QUOTES) ?></span></a></li>
        <?php } ?>
    </ul>
<?php } ?>
</body>
</html>
