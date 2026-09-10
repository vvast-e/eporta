<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/eporta_home_tabs_common.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaHomeTabsUserHasAccess()) {
    ?>
    <!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Нет доступа</title></head>
    <body style="font-family: sans-serif; padding: 40px;">
        <h1>Нет доступа</h1>
        <p>У вашей учётной записи нет прав на запись в каталог (IBLOCK 19). Обратитесь к администратору сайта.</p>
    </body></html>
    <?php
    exit;
}

$sessid = bitrix_sessid();
$config = eportaHomeTabsGetConfig();

// Подмешиваем карточки уже закреплённых товаров (фото/название/артикул), чтобы страница сразу
// открывалась с заполненными списками, а не только ID.
$allPinnedIds = [];
foreach ($config as $tab) {
    $allPinnedIds = array_merge($allPinnedIds, $tab['pinned']);
}
$productsInfo = eportaHomeTabsGetProductsInfo($allPinnedIds);

$tabLabels = [
    'hit' => 'Хиты',
    'sale' => 'Распродажа',
    'new' => 'Новинки',
];
$tabHints = [
    'hit' => 'Автоотбор: товары с рейтингом от 4.8 (тот же порог, что даёт плашку «ХИТ» на карточке).',
    'sale' => 'Автоотбор: товары со свойством «Распродажа» = Да (то же поле, что и фильтр /catalog/?sale=1).',
    'new' => 'Автоотбор: товары с рейтингом 0 (тот же порог, что даёт плашку «Новинка»).',
];

$initialState = [];
foreach (eportaHomeTabsKeys() as $tabKey) {
    $pinnedCards = [];
    foreach ($config[$tabKey]['pinned'] as $pid) {
        if (isset($productsInfo[$pid])) {
            $pinnedCards[] = $productsInfo[$pid];
        }
    }
    $initialState[$tabKey] = [
        'title' => $config[$tabKey]['title'],
        'limit' => $config[$tabKey]['limit'],
        'pinned' => $pinnedCards,
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Табы главной (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 24px; }
    .tab-card { border: 1px solid #ddd; border-radius: 8px; padding: 18px 20px; margin-bottom: 20px; background: #fff; }
    .tab-card h2 { font-size: 16px; margin: 0 0 6px; }
    .tab-hint { color: #888; font-size: 12px; margin-bottom: 14px; }
    .tab-fields { display: flex; gap: 16px; margin-bottom: 14px; }
    .tab-fields label { font-size: 12px; color: #555; display: flex; flex-direction: column; gap: 4px; }
    .tab-fields input[type=text] { width: 220px; font-size: 13px; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; }
    .tab-fields input[type=number] { width: 80px; font-size: 13px; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; }
    .pinned-list { list-style: none; margin: 0 0 12px; padding: 0; display: flex; flex-direction: column; gap: 6px; }
    .pinned-item { display: flex; align-items: center; gap: 10px; border: 1px solid #eee; border-radius: 6px; padding: 6px 8px; background: #fafafa; cursor: grab; }
    .pinned-item.dragging { opacity: .4; }
    .pinned-item .handle { color: #aaa; font-size: 14px; user-select: none; }
    .pinned-item img { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; background: #eee; flex: none; }
    .pinned-item .noimg { width: 36px; height: 36px; border-radius: 4px; background: #eee; flex: none; }
    .pinned-item .name { font-size: 13px; flex: 1; }
    .pinned-item .article { font-size: 11px; color: #999; }
    .pinned-item .remove { border: none; background: none; color: #c0392b; cursor: pointer; font-size: 16px; line-height: 1; padding: 2px 4px; }
    .pinned-empty { font-size: 12px; color: #999; padding: 6px 0; }
    .search-box { position: relative; }
    .search-box input[type=text] { width: 100%; box-sizing: border-box; font-size: 13px; padding: 7px 9px; border: 1px solid #ddd; border-radius: 4px; }
    .search-results { position: absolute; z-index: 5; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px; max-height: 260px; overflow-y: auto; display: none; }
    .search-results .res-item { display: flex; align-items: center; gap: 10px; padding: 7px 9px; cursor: pointer; }
    .search-results .res-item:hover { background: #f2f6fb; }
    .search-results .res-item img { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; background: #eee; flex: none; }
    .search-results .res-item .noimg { width: 32px; height: 32px; border-radius: 4px; background: #eee; flex: none; }
    .search-results .res-empty { padding: 8px 9px; font-size: 12px; color: #999; }
    .save-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #ddd; padding: 14px 0; display: flex; align-items: center; gap: 14px; }
    .save-bar button { background: #2b6cb0; color: #fff; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .save-bar button:disabled { background: #999; cursor: default; }
    .save-status { font-size: 13px; }
    .save-status.ok { color: #2f9e44; }
    .save-status.err { color: #c0392b; }
</style>
</head>
<body>
<h1>Табы главной страницы</h1>
<p class="hint">
    Настройка переключателей «Хиты / Распродажа / Новинки» на главной (блок под баннерами коллекций).
    Каждый таб: заголовок, количество товаров и закреплённые товары (показываются первыми, в заданном
    порядке — перетаскивайте за ⠿, чтобы поменять местами). Пустые места до лимита заполняются
    автоматически по критерию таба.
</p>

<div id="tabs-root"></div>

<div class="save-bar">
    <button type="button" id="save-btn">Сохранить</button>
    <span class="save-status" id="save-status"></span>
</div>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    const TAB_KEYS = <?= json_encode(eportaHomeTabsKeys()) ?>;
    const TAB_LABELS = <?= json_encode($tabLabels, JSON_UNESCAPED_UNICODE) ?>;
    const TAB_HINTS = <?= json_encode($tabHints, JSON_UNESCAPED_UNICODE) ?>;
    const state = <?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const root = document.getElementById('tabs-root');
    const saveBtn = document.getElementById('save-btn');
    const saveStatus = document.getElementById('save-status');

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderPinned(tabKey) {
        const listEl = document.querySelector('.pinned-list[data-tab="' + tabKey + '"]');
        const items = state[tabKey].pinned;
        listEl.innerHTML = '';
        if (!items.length) {
            listEl.innerHTML = '<div class="pinned-empty">Закреплённых товаров нет — таб полностью на автоотборе.</div>';
            return;
        }
        items.forEach(function (item, idx) {
            const li = document.createElement('li');
            li.className = 'pinned-item';
            li.draggable = true;
            li.dataset.idx = idx;
            li.innerHTML =
                '<span class="handle">⠿</span>' +
                (item.photo ? '<img src="' + esc(item.photo) + '" alt="">' : '<div class="noimg"></div>') +
                '<span class="name">' + esc(item.name) + '</span>' +
                (item.article ? '<span class="article">' + esc(item.article) + '</span>' : '') +
                '<button type="button" class="remove" title="Убрать">×</button>';
            li.querySelector('.remove').addEventListener('click', function () {
                state[tabKey].pinned.splice(idx, 1);
                renderPinned(tabKey);
            });
            li.addEventListener('dragstart', function () {
                li.classList.add('dragging');
                li.dataset.dragIdx = idx;
            });
            li.addEventListener('dragend', function () {
                li.classList.remove('dragging');
            });
            li.addEventListener('dragover', function (e) {
                e.preventDefault();
            });
            li.addEventListener('drop', function (e) {
                e.preventDefault();
                const from = listEl.querySelector('.dragging');
                if (!from) return;
                const fromIdx = parseInt(from.dataset.idx, 10);
                const toIdx = idx;
                if (fromIdx === toIdx) return;
                const arr = state[tabKey].pinned;
                const [moved] = arr.splice(fromIdx, 1);
                arr.splice(toIdx, 0, moved);
                renderPinned(tabKey);
            });
            listEl.appendChild(li);
        });
    }

    function addPinned(tabKey, item) {
        if (state[tabKey].pinned.some(function (p) { return p.id === item.id; })) {
            return;
        }
        state[tabKey].pinned.push(item);
        renderPinned(tabKey);
    }

    TAB_KEYS.forEach(function (tabKey) {
        const card = document.createElement('div');
        card.className = 'tab-card';
        card.innerHTML =
            '<h2>' + esc(TAB_LABELS[tabKey]) + '</h2>' +
            '<div class="tab-hint">' + esc(TAB_HINTS[tabKey]) + '</div>' +
            '<div class="tab-fields">' +
                '<label>Заголовок таба<input type="text" data-field="title" data-tab="' + tabKey + '" value="' + esc(state[tabKey].title) + '"></label>' +
                '<label>Кол-во товаров<input type="number" min="1" max="10" data-field="limit" data-tab="' + tabKey + '" value="' + esc(state[tabKey].limit) + '"></label>' +
            '</div>' +
            '<ul class="pinned-list" data-tab="' + tabKey + '"></ul>' +
            '<div class="search-box">' +
                '<input type="text" placeholder="Поиск по названию или артикулу — добавить закреплённый товар" data-search="' + tabKey + '">' +
                '<div class="search-results" data-results="' + tabKey + '"></div>' +
            '</div>';
        root.appendChild(card);

        card.querySelector('input[data-field="title"]').addEventListener('input', function (e) {
            state[tabKey].title = e.target.value;
        });
        card.querySelector('input[data-field="limit"]').addEventListener('input', function (e) {
            state[tabKey].limit = parseInt(e.target.value, 10) || state[tabKey].limit;
        });

        const searchInput = card.querySelector('input[data-search="' + tabKey + '"]');
        const resultsEl = card.querySelector('[data-results="' + tabKey + '"]');
        let searchTimer = null;

        searchInput.addEventListener('input', function () {
            const q = searchInput.value.trim();
            clearTimeout(searchTimer);
            if (!q) {
                resultsEl.style.display = 'none';
                resultsEl.innerHTML = '';
                return;
            }
            searchTimer = setTimeout(async function () {
                const fd = new FormData();
                fd.append('action', 'search');
                fd.append('sessid', SESSID);
                fd.append('q', q);
                try {
                    const r = await fetch('ajax.php', { method: 'POST', body: fd });
                    const resp = await r.json();
                    resultsEl.innerHTML = '';
                    if (!resp.ok || !resp.items.length) {
                        resultsEl.innerHTML = '<div class="res-empty">Ничего не найдено</div>';
                    } else {
                        resp.items.forEach(function (item) {
                            const row = document.createElement('div');
                            row.className = 'res-item';
                            row.innerHTML =
                                (item.photo ? '<img src="' + esc(item.photo) + '" alt="">' : '<div class="noimg"></div>') +
                                '<span>' + esc(item.name) + (item.article ? ' <span style="color:#999">(' + esc(item.article) + ')</span>' : '') + '</span>';
                            row.addEventListener('click', function () {
                                addPinned(tabKey, item);
                                searchInput.value = '';
                                resultsEl.style.display = 'none';
                                resultsEl.innerHTML = '';
                            });
                            resultsEl.appendChild(row);
                        });
                    }
                    resultsEl.style.display = 'block';
                } catch (e) {
                    resultsEl.innerHTML = '<div class="res-empty">Ошибка сети: ' + esc(e.message) + '</div>';
                    resultsEl.style.display = 'block';
                }
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!card.contains(e.target)) {
                resultsEl.style.display = 'none';
            }
        });

        renderPinned(tabKey);
    });

    saveBtn.addEventListener('click', async function () {
        saveBtn.disabled = true;
        saveStatus.textContent = 'Сохранение...';
        saveStatus.className = 'save-status';

        const payload = {};
        TAB_KEYS.forEach(function (tabKey) {
            payload[tabKey] = {
                title: state[tabKey].title,
                limit: state[tabKey].limit,
                pinned: state[tabKey].pinned.map(function (p) { return p.id; })
            };
        });

        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('sessid', SESSID);
        fd.append('config', JSON.stringify(payload));

        try {
            const r = await fetch('ajax.php', { method: 'POST', body: fd });
            const resp = await r.json();
            if (!resp.ok) {
                saveStatus.textContent = resp.error || 'Ошибка';
                saveStatus.classList.add('err');
            } else {
                saveStatus.textContent = 'Сохранено';
                saveStatus.classList.add('ok');
            }
        } catch (e) {
            saveStatus.textContent = 'Ошибка сети: ' + e.message;
            saveStatus.classList.add('err');
        }
        saveBtn.disabled = false;
    });
})();
</script>
</body>
</html>
