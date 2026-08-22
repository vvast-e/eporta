<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaArticlesUserHasAccess()) {
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
$articles = eportaArticlesList();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Статьи (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 1000px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
    th { color: #888; font-weight: 600; }
    td.thumb img { width: 48px; height: 36px; object-fit: cover; border-radius: 4px; background: #eee; display: block; }
    td.thumb .noimg { width: 48px; height: 36px; border-radius: 4px; background: #eee; }
    .status-y { color: #2f9e44; font-weight: 600; }
    .status-n { color: #999; }
    .btn { background: #2b6cb0; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; }
    .btn.secondary { background: #6c757d; }
    .btn.danger { background: #c0392b; }
    .btn:disabled { background: #999; cursor: default; }
    #formPanel { display: none; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
    #formPanel.open { display: block; }
    .field { margin-bottom: 14px; }
    .field label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
    .field input[type=text], .field textarea { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; font-family: inherit; }
    .field textarea { resize: vertical; }
    .wysiwyg-toolbar { display: flex; gap: 4px; margin-bottom: 6px; }
    .wysiwyg-toolbar button { padding: 5px 10px; border: 1px solid #ccc; background: #fafafa; border-radius: 4px; cursor: pointer; font-size: 13px; }
    .wysiwyg-toolbar button:hover { background: #eee; }
    .wysiwyg-editor { border: 1px solid #ccc; border-radius: 5px; min-height: 220px; padding: 10px 12px; font-size: 14px; line-height: 1.5; }
    .wysiwyg-editor:focus { outline: 2px solid #2b6cb0; outline-offset: -1px; }
    .form-actions { display: flex; gap: 10px; align-items: center; }
    .form-status { font-size: 13px; }
    .form-status.err { color: #c0392b; }
    .form-status.ok { color: #2f9e44; }
</style>
</head>
<body>
<h1>Статьи раздела /articles/</h1>
<p class="hint">Список статей, доступных на сайте. Публикация видна сразу после сохранения с включённым «Активна».</p>

<button type="button" class="btn" id="btnAdd">+ Новая статья</button>

<table id="articlesTable">
    <thead><tr><th></th><th>Заголовок</th><th>Статус</th><th></th></tr></thead>
    <tbody id="articlesTbody"></tbody>
</table>

<div id="formPanel">
    <h2 id="formTitle" style="font-size:16px;margin-top:0">Новая статья</h2>
    <input type="hidden" id="fElementId" value="0">
    <div class="field">
        <label>Заголовок</label>
        <input type="text" id="fName">
    </div>
    <div class="field">
        <label>URL (латиницей, необязательно — сгенерируется автоматически)</label>
        <input type="text" id="fCode" placeholder="naznachit-avtomaticheski">
    </div>
    <div class="field">
        <label><input type="checkbox" id="fActive" checked> Активна (видна на сайте)</label>
    </div>
    <div class="field">
        <label>Картинка превью</label>
        <div id="fPicturePreview" style="margin-bottom:8px"></div>
        <input type="file" id="fPictureInput" accept=".jpg,.jpeg,.png">
        <span id="fPictureStatus" class="form-status"></span>
    </div>
    <div class="field">
        <label>Краткое описание (для списка статей)</label>
        <textarea id="fPreviewText" rows="3"></textarea>
    </div>
    <div class="field">
        <label>Текст статьи</label>
        <div class="wysiwyg-toolbar">
            <button type="button" data-cmd="bold"><b>Ж</b></button>
            <button type="button" data-cmd="italic"><i>К</i></button>
            <button type="button" data-cmd="formatBlock" data-arg="h2">H2</button>
            <button type="button" data-cmd="formatBlock" data-arg="p">Абзац</button>
            <button type="button" data-cmd="insertUnorderedList">• Список</button>
            <button type="button" data-cmd="createLink">Ссылка</button>
        </div>
        <div class="wysiwyg-editor" id="fDetailEditor" contenteditable="true"></div>
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
    let ARTICLES = <?= json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const tbody = document.getElementById('articlesTbody');
    const formPanel = document.getElementById('formPanel');
    const formTitle = document.getElementById('formTitle');
    const fElementId = document.getElementById('fElementId');
    const fName = document.getElementById('fName');
    const fCode = document.getElementById('fCode');
    const fActive = document.getElementById('fActive');
    const fPreviewText = document.getElementById('fPreviewText');
    const fDetailEditor = document.getElementById('fDetailEditor');
    const fPicturePreview = document.getElementById('fPicturePreview');
    const fPictureInput = document.getElementById('fPictureInput');
    const fPictureStatus = document.getElementById('fPictureStatus');
    const formStatus = document.getElementById('formStatus');

    function renderTable() {
        tbody.innerHTML = '';
        ARTICLES.forEach(function (a) {
            const tr = document.createElement('tr');
            const thumbHtml = a.PREVIEW_PICTURE_SRC
                ? '<img src="' + a.PREVIEW_PICTURE_SRC + '" alt="">'
                : '<div class="noimg"></div>';
            tr.innerHTML =
                '<td class="thumb">' + thumbHtml + '</td>' +
                '<td>' + a.NAME.replace(/</g, '&lt;') + '</td>' +
                '<td class="' + (a.ACTIVE === 'Y' ? 'status-y' : 'status-n') + '">' + (a.ACTIVE === 'Y' ? 'Активна' : 'Черновик') + '</td>' +
                '<td><button type="button" class="btn secondary" data-edit="' + a.ID + '">Редактировать</button> ' +
                '<button type="button" class="btn danger" data-del="' + a.ID + '">Удалить</button></td>';
            tbody.appendChild(tr);
        });
        tbody.querySelectorAll('[data-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () { openForm(btn.dataset.edit); });
        });
        tbody.querySelectorAll('[data-del]').forEach(function (btn) {
            btn.addEventListener('click', function () { deleteArticle(btn.dataset.del); });
        });
    }

    function openForm(id) {
        formStatus.textContent = '';
        fPictureStatus.textContent = '';
        if (id) {
            const a = ARTICLES.find(function (x) { return String(x.ID) === String(id); });
            if (!a) return;
            formTitle.textContent = 'Редактирование статьи';
            fElementId.value = a.ID;
            fName.value = a.NAME;
            fCode.value = a.CODE;
            fActive.checked = a.ACTIVE === 'Y';
            fPreviewText.value = a.PREVIEW_TEXT || '';
            fDetailEditor.innerHTML = a.DETAIL_TEXT || '';
            fPicturePreview.innerHTML = a.PREVIEW_PICTURE_SRC ? '<img src="' + a.PREVIEW_PICTURE_SRC + '" style="max-width:200px;border-radius:6px">' : '<span style="color:#999;font-size:13px">Картинки нет</span>';
        } else {
            formTitle.textContent = 'Новая статья';
            fElementId.value = '0';
            fName.value = '';
            fCode.value = '';
            fActive.checked = true;
            fPreviewText.value = '';
            fDetailEditor.innerHTML = '';
            fPicturePreview.innerHTML = '<span style="color:#999;font-size:13px">Сначала сохраните статью, потом добавьте картинку</span>';
        }
        formPanel.classList.add('open');
        formPanel.scrollIntoView({ behavior: 'smooth' });
    }

    function closeForm() {
        formPanel.classList.remove('open');
    }

    document.getElementById('btnAdd').addEventListener('click', function () { openForm(null); });
    document.getElementById('btnCancel').addEventListener('click', closeForm);

    document.querySelectorAll('.wysiwyg-toolbar button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fDetailEditor.focus();
            const cmd = btn.dataset.cmd;
            if (cmd === 'createLink') {
                const url = prompt('Адрес ссылки (https://...)');
                if (!url) return;
                document.execCommand('createLink', false, url);
                return;
            }
            document.execCommand(cmd, false, btn.dataset.arg || null);
        });
    });

    async function refreshList() {
        const fd = new FormData();
        fd.append('action', 'list');
        fd.append('sessid', SESSID);
        const r = await fetch('ajax.php', { method: 'POST', body: fd });
        const resp = await r.json();
        if (resp.ok) {
            ARTICLES = resp.items;
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
        fd.append('CODE', fCode.value.trim());
        fd.append('ACTIVE', fActive.checked ? 'Y' : 'N');
        fd.append('PREVIEW_TEXT', fPreviewText.value);
        fd.append('DETAIL_TEXT', fDetailEditor.innerHTML);

        try {
            const r = await fetch('ajax.php', { method: 'POST', body: fd });
            const resp = await r.json();
            if (!resp.ok) {
                formStatus.textContent = resp.error || 'Ошибка';
                formStatus.className = 'form-status err';
                return;
            }
            fElementId.value = resp.element_id;
            fCode.value = resp.code;
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
            fPictureStatus.textContent = 'Сначала сохраните статью';
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

    async function deleteArticle(id) {
        if (!confirm('Удалить статью безвозвратно?')) return;
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
