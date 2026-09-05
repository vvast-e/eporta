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
    return [
        'id' => (int)$coll['ID'],
        'name' => $coll['NAME'],
        'code' => $coll['CODE'],
        'description' => (string)$coll['DESCRIPTION'],
        'sort' => (int)$coll['SORT'],
        'active' => $coll['ACTIVE'] === 'Y',
        'cnt' => $counts[$coll['ID']] ?? 0,
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
    .row-actions { display: flex; gap: 8px; align-items: center; }
    button { background: #2b6cb0; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; }
    button:disabled { background: #999; cursor: default; }
    .status { font-size: 12px; margin-top: 4px; min-height: 16px; }
    .status.ok { color: #2f9e44; }
    .status.err { color: #c0392b; }
    .add-form { border: 1px dashed #ccc; border-radius: 8px; padding: 16px; margin-top: 10px; }
    .add-form label { display: block; font-size: 12px; color: #666; margin-bottom: 4px; }
    .add-form input, .add-form textarea { width: 100%; box-sizing: border-box; font-size: 13px; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
</style>
</head>
<body>
<h1>Коллекции фабрики</h1>
<p class="hint">
    Название, описание (подзаголовок на плитке) и порядок показа коллекций на главной и на
    <a href="/collection/" target="_blank">/collection/</a>. Фото плитки заливается отдельно —
    <a href="/local/admin_tools/eporta_banners/#grid-coll" target="_blank">в админке баннеров →</a>.
    Новая коллекция сразу получает свой слот там же.
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
            <th style="width:100px"></th>
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
            '<td class="row-actions"><button type="button" class="f-save">Сохранить</button></td>';

        const status = document.createElement('div');
        status.className = 'status';
        tr.lastElementChild.appendChild(status);

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

        return tr;
    }

    COLLECTIONS.forEach(function (coll, index) {
        tbody.appendChild(renderRow(coll, index));
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
