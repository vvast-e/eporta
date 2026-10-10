<?php
// Общая шапка раздела «Управление витриной»: вкладки со ссылками на существующие страницы
// local/admin_tools/*. Только навигация — без проверок доступа и обращений к данным (каждая страница
// сама проверяет права и CSRF). Подключается одной строкой сразу после тега body:
// require_once из DOCUMENT_ROOT + '/local/admin_tools/eporta_storefront/nav.php', затем eportaStorefrontNav('<ключ вкладки>').
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// Единый список вкладок: ключ => [подпись, адрес, описание для страницы-входа].
function eportaStorefrontTabs(): array {
    return [
        'main' => [
            'collections' => ['Коллекции', '/local/admin_tools/eporta_collections/', 'Названия, порядок, подколлекции, блок на главной, баннер страницы, модели и цвета'],
            'hardware_kinds' => ['Типы фурнитуры', '/local/admin_tools/eporta_hardware_kinds/', 'Типы изделий раздела «Фурнитура»: названия, порядок, видимость, новый тип'],
            'banners' => ['Баннеры', '/local/admin_tools/eporta_banners/', 'Слайдер главной и плитки категорий и коллекций'],
            'home_tabs' => ['Табы главной', '/local/admin_tools/eporta_home_tabs/', 'Какие товары показываются в табах на главной'],
            'quick' => ['Популярные запросы', '/local/admin_tools/eporta_quick_queries/', 'Быстрые ссылки в каталог с фильтрами на главной и в каталоге'],
            'import' => ['Импорт', '/local/admin_tools/eporta_import/', 'Загрузка товаров из таблицы 1С'],
        ],
        'content' => [
            'promo' => ['Акции', '/local/admin_tools/eporta_promo/', 'Раздел /promo/'],
            'works' => ['Работы', '/local/admin_tools/eporta_works/', 'Блок «Наши работы» на главной'],
            'reviews' => ['Отзывы', '/local/admin_tools/eporta_reviews/', 'Блок «Отзывы» на главной'],
            'articles' => ['Статьи', '/local/admin_tools/eporta_articles/', 'Раздел /articles/'],
        ],
    ];
}

function eportaStorefrontNav(string $active = ''): void {
    $tabs = eportaStorefrontTabs();
    $inContent = isset($tabs['content'][$active]);
    $link = function (string $key, array $tab) use ($active): string {
        $current = $key === $active;
        return '<a class="sf-tab' . ($current ? ' is-active' : '') . '" href="' . htmlspecialchars($tab[1], ENT_QUOTES) . '"'
            . ($current ? ' aria-current="page"' : '') . '>' . htmlspecialchars($tab[0], ENT_QUOTES) . '</a>';
    };
    ?>
<link rel="stylesheet" href="/local/admin_tools/eporta_storefront/admin.css?v=<?= (int)@filemtime(__DIR__ . '/admin.css') ?>">
<nav class="sf-nav" aria-label="Управление витриной">
    <div class="sf-nav-head"><a class="sf-nav-title" href="/local/admin_tools/eporta_storefront/">Управление витриной</a></div>
    <div class="sf-row">
        <span class="sf-group<?= $inContent ? '' : ' is-active' ?>">Витрина</span>
        <?php foreach ($tabs['main'] as $key => $tab) { echo $link($key, $tab); } ?>
    </div>
    <div class="sf-row">
        <span class="sf-group<?= $inContent ? ' is-active' : '' ?>">Контент</span>
        <?php foreach ($tabs['content'] as $key => $tab) { echo $link($key, $tab); } ?>
    </div>
</nav>
    <?php
}
