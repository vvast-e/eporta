<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaWorksUserHasAccess()) {
    ?>
    <!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Нет доступа</title></head>
    <body style="font-family: sans-serif; padding: 40px;">
        <h1>Нет доступа</h1>
        <p>У вашей учётной записи нет прав на запись в каталог (IBLOCK 19). Обратитесь к администратору сайта.</p>
    </body></html>
    <?php
    exit;
}
if (EPORTA_WORKS_IBLOCK_ID <= 0) {
    ?>
    <!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Инфоблок не создан</title></head>
    <body style="font-family: sans-serif; padding: 40px;">
        <h1>Инфоблок "Наши работы" ещё не создан</h1>
        <p>Запустите на проде <code>php scripts/create_iblock_works.php</code>, затем впишите полученный ID
        как <code>EPORTA_WORKS_IBLOCK_ID</code> в <code>local/php_interface/include/eporta_works_common.php</code>.</p>
    </body></html>
    <?php
    exit;
}

$sessid = bitrix_sessid();
$works = eportaWorksAdminList();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Наши работы (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
    th { color: #888; font-weight: 600; }
    td.thumb img { width: 64px; height: 48px; object-fit: cover; border-radius: 4px; background: #eee; display: block; }
    td.thumb .noimg { width: 64px; height: 48px; border-radius: 4px; background: #eee; }
    .status-y { color: #2f9e44; font-weight: 600; }
    .status-n { color: #999; }
    .btn { background: #2b6cb0; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; }
    .btn.secondary { background: #6c757d; }
    .btn.danger { background: #c0392b; }
    #formPanel { display: none; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
    #formPanel.open { display: block; }
    .field { margin-bottom: 14px; }
    .field label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
    .field input[type=text], .field input[type=number], .field textarea { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; font-family: inherit; }
    .field textarea { resize: vertical; }
    .field-row { display: flex; gap: 14px; }
    .field-row .field { flex: 1; }
    .form-actions { display: flex; gap: 10px; align-items: center; }
    .form-status { font-size: 13px; }
    .form-status.err { color: #c0392b; }
    .form-status.ok { color: #2f9e44; }
</style>
</head>
<body>
<h1>Блок «Наши работы» на главной</h1>
<p class="hint">Список работ, показываемых в слайдере на главной странице. Публикация видна сразу
после сохранения с включённым «Активна». Если работ нет ни одной активной — блок на главной не выводится.</p>

<button type="button" class="btn" id="btnAdd">+ Новая работа</button>

<table id="worksTable">
    <thead><tr><th></th><th>Заголовок</th><th>Город/объект</th><th>Статус</th><th></th></tr></thead>
    <tbody id="worksTbody"></tbody>
</table>

<div id="formPanel">
    <h2 id="formTitle" style="font-size:16px;margin-top:0">Новая работа</h2>
    <input type="hidden" id="fElementId" value="0">
    <div class="field">
        <label>Заголовок</label>
        <input type="text" id="fName">
    </div>
    <div class="field-row">
        <div class="field">
            <label>Город / объект</label>
            <input type="text" id="fCity" placeholder="Москва, ЖК «Пример»">
        </div>
        <div class="field">
            <label>Коллекция</label>
            <input type="text" id="fCollection" placeholder="Dorsum-F">
        </div>
    </div>
    <div class="field">
        <label><input type="checkbox" id="fActive" checked> Активна (видна на сайте)</label>
    </div>
    <div class="field">
        <label>Фото работы</label>
        <div id="fPicturePreview" style="margin-bottom:8px"></div>
        <input type="file" id="fPictureInput" accept=".jpg,.jpeg,.png">
        <span id="fPictureStatus" class="form-status"></span>
    </div>
    <div class="field">
        <label>Короткое описание (необязательно)</label>
        <textarea id="fPreviewText" rows="2"></textarea>
    </div>
    <div class="field">
        <label>Сортировка (меньше — выше в списке)</label>
        <input type="number" id="fSort" value="500">
    </div>
    <div class="form-actions">
        <button type="button" class="btn" id="btnSave">Сохранить</button>
        <button type="button" class="btn secondary" id="btnCancel">Отмена</button>
        <span id="formStatus" class="form-status"></span>
    </div>
</div>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    let WORKS = <?= json_encode($works, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const tbody = document.getElementById('worksTbody');
    const formPanel = document.getElementById('formPanel');
    const formTitle = document.getElementById('formTitle');
    const fElementId = document.getElementById('fElementId');
    const fName = document.getElementById('fName');
    const fCity = document.getElementById('fCity');
    const fCollection = document.getElementById('fCollection');
    const fActive = document.getElementById('fActive');
    const fPreviewText = document.getElementById('fPreviewText');
    const fSort = document.getElementById('fSort');
    const fPicturePreview = document.getElementById('fPicturePreview');
    const fPictureInput = document.getElementById('fPictureInput');
    const fPictureStatus = document.getElementById('fPictureStatus');
    const formStatus = document.getElementById('formStatus');

    function renderTable() {
        tbody.innerHTML = '';
        WORKS.forEach(function (w) {
            const tr = document.createElement('tr');
            const thumbHtml = w.PREVIEW_PICTURE_SRC
                ? '<img src="' + w.PREVIEW_PICTURE_SRC + '" alt="">'
                : '<div class="noimg"></div>';
            tr.innerHTML =
                '<td class="thumb">' + thumbHtml + '</td>' +
                '<td>' + w.NAME.replace(/</g, '&lt;') + '</td>' +
                '<td>' + (w.CITY || '').replace(/</g, '&lt;') + '</td>' +
                '<td class="' + (w.ACTIVE === 'Y' ? 'status-y' : 'status-n') + '">' + (w.ACTIVE === 'Y' ? 'Активна' : 'Черновик') + '</td>' +
                '<td><button type="button" class="btn secondary" data-edit="' + w.ID + '">Редактировать</button> ' +
                '<button type="button" class="btn danger" data-del="' + w.ID + '">Удалить</button></td>';
            tbody.appendChild(tr);
        });
        tbody.querySelectorAll('[data-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () { openForm(btn.dataset.edit); });
        });
        tbody.querySelectorAll('[data-del]').forEach(function (btn) {
            btn.addEventListener('click', function () { deleteWork(btn.dataset.del); });
        });
    }

    function openForm(id) {
        formStatus.textContent = '';
        fPictureStatus.textContent = '';
        if (id) {
            const w = WORKS.find(function (x) { return String(x.ID) === String(id); });
            if (!w) return;
            formTitle.textContent = 'Редактирование работы';
            fElementId.value = w.ID;
            fName.value = w.NAME;
            fCity.value = w.CITY || '';
            fCollection.value = w.COLLECTION || '';
            fActive.checked = w.ACTIVE === 'Y';
            fPreviewText.value = w.PREVIEW_TEXT || '';
            fSort.value = w.SORT || 500;
            fPicturePreview.innerHTML = w.PREVIEW_PICTURE_SRC ? '<img src="' + w.PREVIEW_PICTURE_SRC + '" style="max-width:200px;border-radius:6px">' : '<span style="color:#999;font-size:13px">Фото нет</span>';
        } else {
            formTitle.textContent = 'Новая работа';
            fElementId.value = '0';
            fName.value = '';
            fCity.value = '';
            fCollection.value = '';
            fActive.checked = true;
            fPreviewText.value = '';
            fSort.value = 500;
            fPicturePreview.innerHTML = '<span style="color:#999;font-size:13px">Сначала сохраните работу, потом добавьте фото</span>';
        }
        formPanel.classList.add('open');
        formPanel.scrollIntoView({ behavior: 'smooth' });
    }

    function closeForm() {
        formPanel.classList.remove('open');
    }

    document.getElementById('btnAdd').addEventListener('click', function () { openForm(null); });
    document.getElementById('btnCancel').addEventListener('click', closeForm);

    async function refreshList() {
        const fd = new FormData();
        fd.append('action', 'list');
        fd.append('sessid', SESSID);
        const r = await fetch('ajax.php', { method: 'POST', body: fd });
        const resp = await r.json();
        if (resp.ok) {
            WORKS = resp.items;
            renderTable();
        }
    }

    document.getElementById('btnSave').addEventListener('click', async function () {
        const name = fName.value.trim();
        if (!name) {
            formStatus.textContent = 'Заголовок обязателен';
            formStatus.className = 'form-status err';
            return;
        }
        formStatus.textContent = 'Сохранение...';
        formStatus.className = 'form-status';

        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('sessid', SESSID);
        fd.append('element_id', fElementId.value);
        fd.append('NAME', name);
        fd.append('ACTIVE', fActive.checked ? 'Y' : 'N');
        fd.append('CITY', fCity.value.trim());
        fd.append('COLLECTION', fCollection.value.trim());
        fd.append('PREVIEW_TEXT', fPreviewText.value);
        fd.append('SORT', fSort.value || '500');

        try {
            const r = await fetch('ajax.php', { method: 'POST', body: fd });
            const resp = await r.json();
            if (!resp.ok) {
                formStatus.textContent = resp.error || 'Ошибка';
                formStatus.className = 'form-status err';
                return;
            }
            fElementId.value = resp.element_id;
            formStatus.textContent = 'Сохранено';
            formStatus.className = 'form-status ok';
            await refreshList();
        } catch (e) {
            formStatus.textContent = 'Ошибка сети: ' + e.message;
            formStatus.className = 'form-status err';
        }
    });

    fPictureInput.addEventListener('change', async function () {
        if (!fPictureInput.files.length) return;
        const elementId = fElementId.value;
        if (!elementId || elementId === '0') {
            fPictureStatus.textContent = 'Сначала сохраните работу';
            fPictureStatus.className = 'form-status err';
            fPictureInput.value = '';
            return;
        }
        fPictureStatus.textContent = 'Загрузка...';
        fPictureStatus.className = 'form-status';

        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('sessid', SESSID);
        fd.append('element_id', elementId);
        fd.append('image', fPictureInput.files[0]);

        try {
            const r = await fetch('ajax.php', { method: 'POST', body: fd });
            const resp = await r.json();
            if (!resp.ok) {
                fPictureStatus.textContent = resp.error || 'Ошибка';
                fPictureStatus.className = 'form-status err';
                return;
            }
            fPicturePreview.innerHTML = '<img src="' + resp.image + '?t=' + Date.now() + '" style="max-width:200px;border-radius:6px">';
            fPictureStatus.textContent = 'Готово';
            fPictureStatus.className = 'form-status ok';
            fPictureInput.value = '';
            await refreshList();
        } catch (e) {
            fPictureStatus.textContent = 'Ошибка сети: ' + e.message;
            fPictureStatus.className = 'form-status err';
        }
    });

    async function deleteWork(id) {
        if (!confirm('Удалить работу безвозвратно?')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('sessid', SESSID);
        fd.append('element_id', id);
        try {
            const r = await fetch('ajax.php', { method: 'POST', body: fd });
            const resp = await r.json();
            if (!resp.ok) {
                alert(resp.error || 'Ошибка удаления');
                return;
            }
            await refreshList();
        } catch (e) {
            alert('Ошибка сети: ' + e.message);
        }
    }

    renderTable();
})();
</script>
</body>
</html>
