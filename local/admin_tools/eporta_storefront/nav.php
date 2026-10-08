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
<style>
    .sf-nav { margin: 0 0 22px; border-bottom: 1px solid #e2e2e2; font-size: 14px; }
    .sf-nav-head { display: flex; align-items: baseline; gap: 12px; margin-bottom: 8px; }
    .sf-nav-title { font-weight: 700; font-size: 15px; color: #222; text-decoration: none; }
    .sf-nav-title:hover { color: #2b6cb0; }
    .sf-row { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 4px; }
    .sf-row + .sf-row { margin-top: 2px; }
    .sf-group { color: #888; font-size: 12px; margin-right: 6px; min-width: 62px; }
    .sf-group.is-active { color: #222; font-weight: 600; }
    .sf-tab { display: inline-block; padding: 7px 12px; color: #2b6cb0; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; }
    .sf-tab:hover { background: #f2f7fc; }
    .sf-tab:focus-visible { outline: 2px solid #2b6cb0; outline-offset: -2px; }
    .sf-tab.is-active { color: #222; font-weight: 600; border-bottom-color: #2b6cb0; }
</style>
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
