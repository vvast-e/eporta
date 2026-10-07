<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaQuickQueriesUserHasAccess()) {
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
$options = eportaQuickQueriesFilterOptions();
$saved = eportaQuickQueriesGet();
$neverSaved = $saved === null;

// Пока настройки ни разу не сохранялись, на главной показан прежний набор из шести кнопок — их
// подставляем в форму как стартовые (без фильтров), чтобы админ мог сразу их доработать.
if ($neverSaved) {
    $saved = eportaQuickQueriesNormalize(array_map(function ($label) {
        return ['label' => $label, 'filters' => []];
    }, ['Белые двери', 'Современные двери', 'Классические двери', 'Двери с терморазрывом', 'Двери экошпон', 'Ульяновские двери']));
}

$allPinned = [];
foreach ($saved as $query) {
    $allPinned = array_merge($allPinned, $query['pinned']);
}
$productsInfo = eportaHomeTabsGetProductsInfo($allPinned);
$initial = [];
foreach ($saved as $query) {
    $query['url'] = eportaQuickQueryUrl($query);
    $query['pinned'] = array_values(array_filter(array_map(function ($id) use ($productsInfo) {
        return $productsInfo[$id] ?? null;
    }, $query['pinned'])));
    $initial[] = $query;
}
$optionsForJs = [];
foreach ($options as $group => $values) {
    $optionsForJs[$group] = [];
    foreach ($values as $value => $label) {
        $optionsForJs[$group][] = ['value' => (string)$value, 'label' => (string)$label];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Популярные запросы (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; line-height: 1.5; }
    .hint a { color: #2b6cb0; }
    .q-card { border: 1px solid #ddd; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; background: #fff; }
    .q-head { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
    .q-head .num { width: 22px; color: #888; font-size: 13px; }
    .q-head input[type=text] { flex: 1; font-size: 14px; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; }
    button { font-size: 13px; padding: 5px 10px; border: 1px solid #bbb; border-radius: 4px; background: #f6f6f6; cursor: pointer; }
    button:disabled { opacity: .4; cursor: default; }
    button.danger { color: #c0392b; }
    button.primary { background: #2f9e44; border-color: #2f9e44; color: #fff; font-size: 14px; padding: 8px 18px; }
    .q-section { margin-top: 10px; }
    .q-section > summary { cursor: pointer; font-size: 13px; font-weight: 600; color: #444; }
    .chk-list { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 8px; }
    .chk-list label { font-size: 12.5px; white-space: nowrap; }
    .row { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; margin-top: 10px; font-size: 13px; }
    .row input[type=number] { width: 90px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; }
    .row select { padding: 4px 6px; }
    .pin-list { margin-top: 8px; }
    .pin-item { display: flex; gap: 8px; align-items: center; padding: 4px 0; border-bottom: 1px solid #f0f0f0; font-size: 12.5px; }
    .pin-item img { width: 36px; height: 44px; object-fit: contain; background: #f6f4ef; border-radius: 3px; }
    .pin-item .nm { flex: 1; }
    .search-box input { width: 100%; box-sizing: border-box; font-size: 13px; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; margin-top: 8px; }
    .search-res { border: 1px solid #eee; border-radius: 4px; margin-top: 4px; max-height: 220px; overflow: auto; }
    .search-res .pin-item { padding: 4px 8px; cursor: pointer; }
    .search-res .pin-item:hover { background: #f1fbf3; }
    .url { font-family: monospace; font-size: 11.5px; color: #888; margin-top: 10px; word-break: break-all; }
    .status { font-size: 13px; min-height: 18px; margin: 10px 0; }
    .status.ok { color: #2f9e44; } .status.err { color: #c0392b; }
    .bar { display: flex; gap: 10px; align-items: center; margin-top: 18px; }
</style>
</head>
<body>
<h1>Популярные запросы на главной</h1>
<p class="hint">
    Кнопки под блоком коллекций на главной. У каждой: <b>текст</b>, <b>фильтры каталога</b> (какие значения
    выставятся при переходе) и <b>конкретные двери</b>. Двери, выбранные вручную, показываются в каталоге
    <b>первыми</b> (в том порядке, как здесь), затем идёт результат фильтра. Если пользователь меняет фильтр
    в каталоге, закреплённые двери убираются. Порядок кнопок — стрелками ↑↓, количество любое.
    Изменения применяются кнопкой «Сохранить». Коллекции — в
    <a href="/local/admin_tools/eporta_collections/">админке коллекций</a>.
    <?php if ($neverSaved): ?><br><b>Сейчас на главной показан прежний набор из шести кнопок без фильтров — они подставлены ниже. После первого сохранения главная начнёт брать кнопки отсюда.</b><?php endif; ?>
</p>

<div id="list"></div>
<div class="bar">
    <button type="button" id="add">+ Добавить запрос</button>
    <button type="button" class="primary" id="save">Сохранить</button>
</div>
<div class="status" id="status"></div>

<script>
(function () {
    var SESSID = <?= json_encode($sessid) ?>;
    var OPTIONS = <?= json_encode($optionsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var state = <?= json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var GROUPS = [['style', 'Стиль'], ['coating', 'Покрытие'], ['color', 'Цвет'], ['size', 'Размер']];
    var listEl = document.getElementById('list');
    var statusEl = document.getElementById('status');

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function emptyFilters() {
        return { style: [], coating: [], color: [], size: [], price_min: 0, price_max: 0, category: '', sale: false, 'new': false };
    }
    function setStatus(text, cls) {
        statusEl.textContent = text;
        statusEl.className = 'status' + (cls ? ' ' + cls : '');
    }

    function render() {
        listEl.innerHTML = '';
        state.forEach(function (q, i) {
            var card = document.createElement('div');
            card.className = 'q-card';
            var html = '<div class="q-head"><span class="num">' + (i + 1) + '</span>' +
                '<input type="text" class="f-label" maxlength="60" placeholder="Текст кнопки" value="' + esc(q.label) + '">' +
                '<button type="button" class="f-up"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
                '<button type="button" class="f-down"' + (i === state.length - 1 ? ' disabled' : '') + '>↓</button>' +
                '<button type="button" class="f-del danger">Удалить</button></div>';
            GROUPS.forEach(function (g) {
                var key = g[0];
                var picked = (q.filters[key] || []).map(String);
                html += '<details class="q-section"' + (picked.length ? ' open' : '') + '><summary>' + g[1] + (picked.length ? ' (' + picked.length + ')' : '') + '</summary><div class="chk-list">';
                OPTIONS[key].forEach(function (opt) {
                    html += '<label><input type="checkbox" data-group="' + key + '" value="' + esc(opt.value) + '"' + (picked.indexOf(opt.value) !== -1 ? ' checked' : '') + '> ' + esc(opt.label) + '</label>';
                });
                html += '</div></details>';
            });
            html += '<div class="row"><span>Цена от <input type="number" min="0" class="f-pmin" value="' + (q.filters.price_min || '') + '"> до <input type="number" min="0" class="f-pmax" value="' + (q.filters.price_max || '') + '"> ₽</span>' +
                '<span>Категория <select class="f-cat"><option value="">— любая —</option>';
            OPTIONS.category.forEach(function (opt) {
                html += '<option value="' + esc(opt.value) + '"' + (q.filters.category === opt.value ? ' selected' : '') + '>' + esc(opt.label) + '</option>';
            });
            html += '</select></span><label><input type="checkbox" class="f-sale"' + (q.filters.sale ? ' checked' : '') + '> Распродажа</label>' +
                '<label><input type="checkbox" class="f-new"' + (q.filters['new'] ? ' checked' : '') + '> Новинки</label></div>';
            html += '<div class="q-section"><b style="font-size:13px">Закреплённые двери</b> <span style="font-size:12px;color:#888">(показываются первыми)</span><div class="pin-list"></div>' +
                '<div class="search-box"><input type="text" class="f-search" placeholder="Найти дверь по названию или артикулу…"><div class="search-res" style="display:none"></div></div></div>';
            html += '<div class="url">' + (q.url ? 'Ссылка: ' + esc(q.url) : 'Ссылка появится после сохранения') + '</div>';
            card.innerHTML = html;
            listEl.appendChild(card);
            wire(card, q, i);
            renderPinned(card, q);
        });
    }

    function renderPinned(card, q) {
        var wrap = card.querySelector('.pin-list');
        wrap.innerHTML = '';
        q.pinned.forEach(function (p, pi) {
            var row = document.createElement('div');
            row.className = 'pin-item';
            row.innerHTML = (p.photo ? '<img src="' + esc(p.photo) + '">' : '<span style="width:36px"></span>') +
                '<span class="nm">' + esc(p.name) + (p.article ? ' <span style="color:#888">· ' + esc(p.article) + '</span>' : '') + '</span>' +
                '<button type="button" class="p-up"' + (pi === 0 ? ' disabled' : '') + '>↑</button>' +
                '<button type="button" class="p-down"' + (pi === q.pinned.length - 1 ? ' disabled' : '') + '>↓</button>' +
                '<button type="button" class="p-del danger">×</button>';
            row.querySelector('.p-up').onclick = function () { move(q.pinned, pi, -1); renderPinned(card, q); };
            row.querySelector('.p-down').onclick = function () { move(q.pinned, pi, 1); renderPinned(card, q); };
            row.querySelector('.p-del').onclick = function () { q.pinned.splice(pi, 1); renderPinned(card, q); };
            wrap.appendChild(row);
        });
    }

    function move(arr, i, d) {
        var j = i + d;
        if (j < 0 || j >= arr.length) return;
        var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
    }

    // Считывает значения полей карточки в состояние (вызывается перед любой перерисовкой/сохранением).
    function syncAll() {
        var cards = listEl.querySelectorAll('.q-card');
        cards.forEach(function (card, i) {
            var q = state[i];
            q.label = card.querySelector('.f-label').value;
            GROUPS.forEach(function (g) {
                q.filters[g[0]] = Array.prototype.map.call(card.querySelectorAll('input[data-group="' + g[0] + '"]:checked'), function (el) { return el.value; });
            });
            q.filters.price_min = parseInt(card.querySelector('.f-pmin').value, 10) || 0;
            q.filters.price_max = parseInt(card.querySelector('.f-pmax').value, 10) || 0;
            q.filters.category = card.querySelector('.f-cat').value;
            q.filters.sale = card.querySelector('.f-sale').checked;
            q.filters['new'] = card.querySelector('.f-new').checked;
        });
    }

    function wire(card, q, i) {
        card.querySelector('.f-up').onclick = function () { syncAll(); move(state, i, -1); render(); };
        card.querySelector('.f-down').onclick = function () { syncAll(); move(state, i, 1); render(); };
        card.querySelector('.f-del').onclick = function () {
            if (!confirm('Удалить запрос «' + (card.querySelector('.f-label').value || 'без названия') + '»?')) return;
            syncAll(); state.splice(i, 1); render();
        };
        var input = card.querySelector('.f-search');
        var res = card.querySelector('.search-res');
        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var term = input.value.trim();
            if (term.length < 2) { res.style.display = 'none'; return; }
            timer = setTimeout(async function () {
                var fd = new FormData();
                fd.append('action', 'search'); fd.append('sessid', SESSID); fd.append('q', term);
                try {
                    var r = await fetch('ajax.php', { method: 'POST', body: fd });
                    var data = await r.json();
                    res.innerHTML = '';
                    (data.items || []).forEach(function (it) {
                        var row = document.createElement('div');
                        row.className = 'pin-item';
                        row.innerHTML = (it.photo ? '<img src="' + esc(it.photo) + '">' : '<span style="width:36px"></span>') + '<span class="nm">' + esc(it.name) + (it.article ? ' <span style="color:#888">· ' + esc(it.article) + '</span>' : '') + '</span>';
                        row.onclick = function () {
                            if (!q.pinned.some(function (p) { return p.id === it.id; })) q.pinned.push(it);
                            renderPinned(card, q);
                        };
                        res.appendChild(row);
                    });
                    if (!res.children.length) res.innerHTML = '<div style="padding:8px;font-size:12px;color:#888">Ничего не найдено</div>';
                    res.style.display = '';
                } catch (e) { res.style.display = 'none'; }
            }, 300);
        });
    }

    document.getElementById('add').onclick = function () {
        syncAll();
        state.push({ id: 0, label: '', filters: emptyFilters(), pinned: [], url: '' });
        render();
        var inputs = listEl.querySelectorAll('.f-label');
        inputs[inputs.length - 1].focus();
    };

    document.getElementById('save').onclick = async function () {
        syncAll();
        var btn = this;
        btn.disabled = true;
        setStatus('Сохраняю…');
        var payload = state.map(function (q) {
            return { id: q.id, label: q.label, filters: q.filters, pinned: q.pinned.map(function (p) { return p.id; }) };
        });
        var fd = new FormData();
        fd.append('action', 'save'); fd.append('sessid', SESSID); fd.append('config', JSON.stringify(payload));
        try {
            var r = await fetch('ajax.php', { method: 'POST', body: fd });
            var data = await r.json();
            if (!data.ok) {
                setStatus(data.error || 'Ошибка', 'err');
            } else {
                state = data.queries;
                render();
                setStatus('Сохранено. Кнопок: ' + state.length + (state.length ? '' : ' (блок на главной скрыт)') + '. Запросы без текста не сохраняются.', 'ok');
            }
        } catch (e) {
            setStatus('Ошибка сети: ' + e.message, 'err');
        }
        btn.disabled = false;
    };

    render();
})();
</script>
</body>
</html>
