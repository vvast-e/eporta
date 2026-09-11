<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaCollectionsUserHasAccess()) {
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
// Все коллекции, включая скрытые — иначе выключенную нельзя было бы включить обратно (она
// пропала бы из этого списка вместе с формой).
$collections = eportaCollections(true);
$counts = eportaCollectionsElementCounts(true);

$collectionsForJs = array_map(function ($coll) use ($counts) {
    // Баннер страницы САМОЙ коллекции (DETAIL_PICTURE ?: PICTURE секции) — тот же приоритет
    // полей, что и на публичной странице /catalog/collections/<code>/ (catalog/index.php).
    // Не путать с плиткой на главной/на /collection/ — та отдельный слот IBLOCK 27, см. hint выше.
    $bannerFile = $coll['DETAIL_PICTURE'] ?: $coll['PICTURE'];
    $bannerPath = $bannerFile ? CFile::GetPath($bannerFile) : '';
    return [
        'id' => (int)$coll['ID'],
        'name' => $coll['NAME'],
        'code' => $coll['CODE'],
        'description' => (string)$coll['DESCRIPTION'],
        'sort' => (int)$coll['SORT'],
        'active' => $coll['ACTIVE'] === 'Y',
        'cnt' => $counts[$coll['ID']] ?? 0,
        'banner' => $bannerPath ?: '',
        // Пусто/поле ещё не заведено — затемнение включено (тот же дефолт, что и в catalog/index.php).
        'banner_overlay' => ($coll['UF_BANNER_OVERLAY'] ?? '') !== 'N',
        'banner_cta_text' => (string)($coll['UF_BANNER_CTA_TEXT'] ?? ''),
        'banner_cta_link' => (string)($coll['UF_BANNER_CTA_LINK'] ?? ''),
    ];
}, $collections);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Коллекции фабрики (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    h2 { font-size: 16px; margin: 32px 0 14px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; }
    .hint a { color: #2b6cb0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
    th { font-size: 12px; color: #888; font-weight: 600; }
    td input[type=text], td textarea { width: 100%; box-sizing: border-box; font-size: 13px; padding: 5px 7px; border: 1px solid #ddd; border-radius: 4px; }
    td input[type=number] { width: 70px; font-size: 13px; padding: 5px 7px; border: 1px solid #ddd; border-radius: 4px; }
    td textarea { resize: vertical; min-height: 34px; }
    .code-cell { font-family: monospace; font-size: 12px; color: #888; }
    .cnt-cell { font-size: 12px; color: #888; white-space: nowrap; }
    .row-actions { display: flex; flex-wrap: wrap; gap: 6px 8px; align-items: center; }
    .row-actions .status { flex-basis: 100%; }
    button { background: #2b6cb0; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; }
    button:disabled { background: #999; cursor: default; }
    .status { font-size: 12px; margin-top: 4px; min-height: 16px; }
    .status.ok { color: #2f9e44; }
    .status.err { color: #c0392b; }
    .add-form { border: 1px dashed #ccc; border-radius: 8px; padding: 16px; margin-top: 10px; }
    .add-form label { display: block; font-size: 12px; color: #666; margin-bottom: 4px; }
    .add-form input, .add-form textarea { width: 100%; box-sizing: border-box; font-size: 13px; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
    .banner-cell { display: flex; align-items: center; gap: 8px; }
    .banner-thumb { width: 64px; height: 40px; object-fit: cover; border-radius: 4px; background: #f1f1f1; border: 1px solid #e2e2e2; flex: none; }
    .banner-thumb.is-empty { display: flex; align-items: center; justify-content: center; font-size: 10px; color: #aaa; }
    .banner-upload-btn { font-size: 12px; color: #2b6cb0; cursor: pointer; white-space: nowrap; }
    .banner-upload-btn:hover { text-decoration: underline; }
    .banner-meta-link { font-size: 12.5px; color: #2b6cb0; white-space: nowrap; }
    .banner-meta-link:hover { text-decoration: underline; }
    .banner-meta-summary { font-size: 11.5px; color: #888; margin-top: 4px; }

    /* Модели и варианты коллекции (задача 06.09.2026: перенесено из отдельной страницы
       local/admin_tools/eporta_showcase/ — всё управление коллекцией в одном месте). */
    .f-models-toggle { background: #fff; color: #2b6cb0; border: 1px solid #cfe0f0; }
    .f-models-toggle:hover { background: #f2f7fc; }
    .models-row > td { padding: 0; border-bottom: 1px solid #eee; }
    .models-panel { background: #f9fafb; padding: 16px 18px; }
    .models-panel .loading { color: #888; font-size: 13px; }
    .models-panel .hint { color: #666; font-size: 12.5px; margin: 0 0 14px; }
    .model-block { border: 1px solid #ddd; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px; background: #fff; }
    .model-block:last-child { margin-bottom: 0; }
    .model-name { font-weight: 700; font-size: 13px; margin-bottom: 10px; }
    .variant-row { display: flex; flex-wrap: wrap; gap: 12px; }
    .variant-card { width: 104px; text-align: center; cursor: pointer; border: 2px solid transparent; border-radius: 8px; padding: 6px; }
    .variant-card.active { border-color: #2f9e44; background: #f1fbf3; }
    .variant-card.hidden-from-list { opacity: .5; }
    .variant-card img { width: 100%; height: 84px; object-fit: contain; background: #f6f4ef; border-radius: 4px; display: block; }
    .variant-card .noimg { width: 100%; height: 84px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #999; }
    .variant-card .color { font-size: 10.5px; margin-top: 5px; color: #444; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .variant-card .star { font-size: 10.5px; color: #2f9e44; font-weight: 700; margin-top: 2px; min-height: 13px; }
    .variant-card .show-toggle { display: flex; align-items: center; gap: 4px; justify-content: center; margin-top: 6px; font-size: 10px; color: #666; cursor: pointer; }
    .models-status { font-size: 12px; margin-top: 8px; min-height: 16px; }
    .models-status.ok { color: #2f9e44; }
    .models-status.err { color: #c0392b; }
</style>
</head>
<body>
<h1>Коллекции фабрики</h1>
<p class="hint">
    Название, описание (подзаголовок на плитке) и порядок показа коллекций на главной и на
    <a href="/collection/" target="_blank">/collection/</a>. Фото плитки заливается отдельно —
    <a href="/local/admin_tools/eporta_banners/#grid-coll" target="_blank">в админке баннеров →</a>.
    Новая коллекция сразу получает свой слот там же. Столбец «Баннер страницы» ниже — другое фото:
    заглавная картинка на самой странице коллекции (<code>/catalog/collections/&lt;код&gt;/</code>),
    не плитка на главной и не на хабе всех коллекций.<br>
    Кнопка «Модели/цвета» у каждой коллекции открывает выбор витринного варианта (какой цвет
    показан на карточке модели в блоке «Модели коллекции») и видимость каждого варианта в блоке
    «Все товары коллекции»/каталоге.<br>
    Ссылка «Настроить кнопку/затемнение →» в столбце «Кнопка/затемнение баннера» открывает
    отдельную страницу с необязательной кнопкой на баннере страницы коллекции (пустой текст =
    кнопки нет) и включением/выключением тёмного градиента поверх фото.
</p>

<table id="collections-table">
    <thead>
        <tr>
            <th style="width:26px">№</th>
            <th>Название</th>
            <th>Описание (подзаголовок)</th>
            <th style="width:80px">Порядок</th>
            <th style="width:60px">Активна</th>
            <th style="width:90px">Моделей</th>
            <th style="width:130px">Баннер страницы</th>
            <th style="width:200px">Кнопка/затемнение баннера</th>
            <th style="width:170px"></th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<h2>Добавить коллекцию</h2>
<div class="add-form">
    <label>Название</label>
    <input type="text" id="new-name" placeholder="Например, Vetus-Loft">
    <label>Описание (подзаголовок на плитке, необязательно)</label>
    <textarea id="new-description" rows="2"></textarea>
    <button type="button" id="new-submit">Добавить</button>
    <div class="status" id="new-status"></div>
</div>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    const COLLECTIONS = <?= json_encode($collectionsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const tbody = document.querySelector('#collections-table tbody');

    function bannerThumbHtml(url) {
        return url
            ? '<img class="banner-thumb" src="' + url.replace(/"/g, '&quot;') + '">'
            : '<div class="banner-thumb is-empty">нет фото</div>';
    }

    function renderRow(coll, index) {
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + (index + 1) + '</td>' +
            '<td><input type="text" class="f-name" value="' + coll.name.replace(/"/g, '&quot;') + '">' +
                '<div class="code-cell">/catalog/collections/' + coll.code + '/</div></td>' +
            '<td><textarea class="f-description" rows="2">' + coll.description.replace(/</g, '&lt;') + '</textarea></td>' +
            '<td><input type="number" class="f-sort" value="' + coll.sort + '" step="100"></td>' +
            '<td style="text-align:center"><input type="checkbox" class="f-active"' + (coll.active ? ' checked' : '') + '></td>' +
            '<td class="cnt-cell">' + coll.cnt + '</td>' +
            '<td><div class="banner-cell">' + bannerThumbHtml(coll.banner) +
                '<label class="banner-upload-btn">Изменить<input type="file" class="f-banner" accept="image/jpeg,image/png" hidden></label>' +
                '</div><div class="status banner-status"></div></td>' +
            '<td class="banner-meta-cell">' +
                '<a class="banner-meta-link" href="banner.php?id=' + coll.id + '">Настроить кнопку/затемнение →</a>' +
                '<div class="banner-meta-summary">' + (coll.banner_cta_text ? 'Кнопка: «' + coll.banner_cta_text.replace(/</g, '&lt;') + '»' : 'Без кнопки') +
                    (coll.banner_overlay ? '' : ', без затемнения') + '</div></td>' +
            '<td class="row-actions"><button type="button" class="f-save">Сохранить</button>' +
                '<button type="button" class="f-models-toggle">Модели/цвета</button></td>';

        const status = document.createElement('div');
        status.className = 'status';
        tr.lastElementChild.appendChild(status);

        const bannerStatus = tr.querySelector('.banner-status');
        const bannerThumbWrap = tr.querySelector('.banner-cell');
        tr.querySelector('.f-banner').addEventListener('change', async function () {
            const fileInput = this;
            const file = fileInput.files && fileInput.files[0];
            if (!file) return;
            bannerStatus.textContent = 'Загрузка...';
            bannerStatus.className = 'status banner-status';
            const fd = new FormData();
            fd.append('action', 'upload_banner');
            fd.append('sessid', SESSID);
            fd.append('id', coll.id);
            fd.append('banner', file);
            try {
                const r = await fetch('ajax.php', { method: 'POST', body: fd });
                const resp = await r.json();
                if (!resp.ok) {
                    bannerStatus.textContent = resp.error || 'Ошибка';
                    bannerStatus.classList.add('err');
                } else {
                    coll.banner = resp.image || '';
                    bannerThumbWrap.querySelector('.banner-thumb').outerHTML = bannerThumbHtml(coll.banner);
                    bannerStatus.textContent = 'Сохранено';
                    bannerStatus.classList.add('ok');
                }
            } catch (e) {
                bannerStatus.textContent = 'Ошибка сети: ' + e.message;
                bannerStatus.classList.add('err');
            }
            fileInput.value = '';
        });

        tr.querySelector('.f-save').addEventListener('click', async function () {
            const btn = tr.querySelector('.f-save');
            btn.disabled = true;
            status.textContent = '';
            status.className = 'status';
            const fd = new FormData();
            fd.append('action', 'update');
            fd.append('sessid', SESSID);
            fd.append('id', coll.id);
            fd.append('name', tr.querySelector('.f-name').value);
            fd.append('description', tr.querySelector('.f-description').value);
            fd.append('sort', tr.querySelector('.f-sort').value);
            fd.append('active', tr.querySelector('.f-active').checked ? 'Y' : 'N');
            try {
                const r = await fetch('ajax.php', { method: 'POST', body: fd });
                const resp = await r.json();
                if (!resp.ok) {
                    status.textContent = resp.error || 'Ошибка';
                    status.classList.add('err');
                } else {
                    status.textContent = 'Сохранено';
                    status.classList.add('ok');
                }
            } catch (e) {
                status.textContent = 'Ошибка сети: ' + e.message;
                status.classList.add('err');
            }
            btn.disabled = false;
        });

        // Разворачиваемая панель "Модели/цвета" (перенесено из local/admin_tools/eporta_showcase/,
        // задача 06.09.2026) — своя строка под основной, во всю ширину таблицы, контент грузится
        // по первому раскрытию (ajax get_models), а не сразу для всех коллекций на странице.
        const modelsRow = document.createElement('tr');
        modelsRow.className = 'models-row';
        modelsRow.style.display = 'none';
        const modelsCell = document.createElement('td');
        modelsCell.colSpan = 9;
        const modelsPanel = document.createElement('div');
        modelsPanel.className = 'models-panel';
        modelsCell.appendChild(modelsPanel);
        modelsRow.appendChild(modelsCell);

        let modelsLoaded = false;
        tr.querySelector('.f-models-toggle').addEventListener('click', async function () {
            const open = modelsRow.style.display !== 'none';
            if (open) {
                modelsRow.style.display = 'none';
                return;
            }
            modelsRow.style.display = '';
            if (modelsLoaded) return;
            modelsLoaded = true;
            modelsPanel.innerHTML = '<div class="loading">Загрузка…</div>';
            try {
                const fd = new FormData();
                fd.append('action', 'get_models');
                fd.append('sessid', SESSID);
                fd.append('section_id', coll.id);
                const r = await fetch('ajax.php', { method: 'POST', body: fd });
                const resp = await r.json();
                if (!resp.ok) {
                    modelsPanel.innerHTML = '<div class="models-status err">' + (resp.error || 'Ошибка загрузки') + '</div>';
                    modelsLoaded = false;
                    return;
                }
                renderModelsPanel(modelsPanel, resp.models);
            } catch (e) {
                modelsPanel.innerHTML = '<div class="models-status err">Ошибка сети: ' + e.message + '</div>';
                modelsLoaded = false;
            }
        });

        return [tr, modelsRow];
    }

    // Разметка и обработчики блока "Модели/цвета" одной коллекции — тот же UX, что раньше был
    // на отдельной странице local/admin_tools/eporta_showcase/: клик по фото делает вариант
    // витринным (эксклюзивно в рамках модели), чекбокс "в списке" переключает видимость варианта
    // в блоке "Все товары коллекции"/каталоге независимо для каждого варианта.
    function renderModelsPanel(panel, models) {
        const modelKeys = Object.keys(models);
        if (!modelKeys.length) {
            panel.innerHTML = '<p class="hint" style="margin:0">В этой коллекции нет товаров.</p>';
            return;
        }
        panel.innerHTML =
            '<p class="hint">' +
                'Кликните на фото цвета, чтобы сделать его витринным — именно этот цвет будет ' +
                'показан на карточке модели в блоке «Модели коллекции» на странице коллекции ' +
                '(зелёная рамка и подпись «★ витрина» — текущий выбор). Галочка «в списке» — ' +
                'показывать этот цвет в блоке «Все товары коллекции»/каталоге или только по прямой ' +
                'ссылке и при переключении цвета на карточке товара.' +
            '</p>';
        modelKeys.forEach(function (modelKey) {
            const model = models[modelKey];
            const block = document.createElement('div');
            block.className = 'model-block';
            const allIds = model.variants.map(function (v) { return v.id; }).join(',');
            block.innerHTML =
                '<div class="model-name">' + model.name.replace(/</g, '&lt;') + '</div>' +
                '<div class="variant-row" data-all-ids="' + allIds + '"></div>' +
                '<div class="models-status"></div>';
            const row = block.querySelector('.variant-row');
            const status = block.querySelector('.models-status');

            model.variants.forEach(function (variant) {
                const card = document.createElement('div');
                card.className = 'variant-card' + (variant.is_showcase ? ' active' : '') + (variant.show_in_list ? '' : ' hidden-from-list');
                card.dataset.id = variant.id;
                const colorLabel = (variant.color || '—') + (variant.glazing ? ', ' + variant.glazing : '');
                card.innerHTML =
                    (variant.photo
                        ? '<img src="' + variant.photo.replace(/"/g, '&quot;') + '" alt="">'
                        : '<div class="noimg">Нет фото</div>') +
                    '<div class="color" title="' + colorLabel.replace(/"/g, '&quot;') + '">' + colorLabel.replace(/</g, '&lt;') + '</div>' +
                    '<div class="star">' + (variant.is_showcase ? '★ витрина' : '') + '</div>' +
                    '<label class="show-toggle"><input type="checkbox" class="show-in-list-checkbox"' + (variant.show_in_list ? ' checked' : '') + '> в списке</label>';

                card.addEventListener('click', async function (e) {
                    if (e.target.closest('.show-toggle')) return;
                    status.textContent = 'Сохранение...';
                    status.className = 'models-status';
                    const fd = new FormData();
                    fd.append('action', 'set_showcase');
                    fd.append('sessid', SESSID);
                    fd.append('id', variant.id);
                    fd.append('all_ids', allIds);
                    try {
                        const r = await fetch('ajax.php', { method: 'POST', body: fd });
                        const resp = await r.json();
                        if (!resp.ok) {
                            status.textContent = resp.error || 'Ошибка';
                            status.classList.add('err');
                            return;
                        }
                        row.querySelectorAll('.variant-card').forEach(function (c) {
                            c.classList.remove('active');
                            c.querySelector('.star').textContent = '';
                        });
                        card.classList.add('active');
                        card.querySelector('.star').textContent = '★ витрина';
                        status.textContent = 'Готово';
                        status.classList.add('ok');
                    } catch (err) {
                        status.textContent = 'Ошибка сети: ' + err.message;
                        status.classList.add('err');
                    }
                });

                const checkbox = card.querySelector('.show-in-list-checkbox');
                checkbox.addEventListener('click', function (e) { e.stopPropagation(); });
                checkbox.addEventListener('change', async function () {
                    const show = checkbox.checked;
                    status.textContent = 'Сохранение...';
                    status.className = 'models-status';
                    const fd = new FormData();
                    fd.append('action', 'set_show_in_list');
                    fd.append('sessid', SESSID);
                    fd.append('id', variant.id);
                    fd.append('show', show ? '1' : '0');
                    try {
                        const r = await fetch('ajax.php', { method: 'POST', body: fd });
                        const resp = await r.json();
                        if (!resp.ok) {
                            checkbox.checked = !show;
                            status.textContent = resp.error || 'Ошибка';
                            status.classList.add('err');
                            return;
                        }
                        card.classList.toggle('hidden-from-list', !show);
                        status.textContent = 'Готово';
                        status.classList.add('ok');
                    } catch (err) {
                        checkbox.checked = !show;
                        status.textContent = 'Ошибка сети: ' + err.message;
                        status.classList.add('err');
                    }
                });

                row.appendChild(card);
            });

            panel.appendChild(block);
        });
    }

    COLLECTIONS.forEach(function (coll, index) {
        const rows = renderRow(coll, index);
        tbody.appendChild(rows[0]);
        tbody.appendChild(rows[1]);
    });

    document.getElementById('new-submit').addEventListener('click', async function () {
        const btn = document.getElementById('new-submit');
        const status = document.getElementById('new-status');
        const nameInput = document.getElementById('new-name');
        const descInput = document.getElementById('new-description');
        status.textContent = '';
        status.className = 'status';
        if (!nameInput.value.trim()) {
            status.textContent = 'Укажите название';
            status.classList.add('err');
            return;
        }
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('sessid', SESSID);
        fd.append('name', nameInput.value);
        fd.append('description', descInput.value);
        try {
            const r = await fetch('ajax.php', { method: 'POST', body: fd });
            const resp = await r.json();
            if (!resp.ok) {
                status.textContent = resp.error || 'Ошибка';
                status.classList.add('err');
            } else {
                status.textContent = 'Коллекция добавлена, страница обновится...';
                status.classList.add('ok');
                setTimeout(function () { location.reload(); }, 700);
            }
        } catch (e) {
            status.textContent = 'Ошибка сети: ' + e.message;
            status.classList.add('err');
        }
        btn.disabled = false;
    });
})();
</script>
</body>
</html>
