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
