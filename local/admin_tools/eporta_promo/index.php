<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaPromoUserHasAccess()) {
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
$promoItems = eportaPromoList();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Акции (eporta.ru)</title>
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
    .wysiwyg-toolbar { display: flex; gap: 4px; margin-bottom: 6px; flex-wrap: wrap; }
    .wysiwyg-toolbar button { padding: 5px 10px; border: 1px solid #ccc; background: #fafafa; border-radius: 4px; cursor: pointer; font-size: 13px; }
    .wysiwyg-toolbar button:hover { background: #eee; }
    .wysiwyg-toolbar .sep { width: 1px; background: #ddd; margin: 2px 4px; }
    .wysiwyg-editor { border: 1px solid #ccc; border-radius: 5px; min-height: 260px; padding: 10px 12px; font-size: 14px; line-height: 1.6; }
    .wysiwyg-editor:focus { outline: 2px solid #2b6cb0; outline-offset: -1px; }
    .form-actions { display: flex; gap: 10px; align-items: center; }
    .form-status { font-size: 13px; }
    .form-status.err { color: #c0392b; }
    .form-status.ok { color: #2f9e44; }

    /* ===== Содержимое акции — общее для редактора (.wysiwyg-editor) и предпросмотра
       (.article-preview-body): такие же правила (кроме размера базового шрифта) продублированы
       на публичной странице /promo/ через переиспользуемый класс .article-content
       (template_styles.css, тот же, что и у /articles/), чтобы результат кнопок форматирования
       выглядел тут так же, как в предпросмотре админки. ===== */
    .wysiwyg-editor p, .article-preview-body p { margin: 0 0 14px; }
    .wysiwyg-editor h2, .article-preview-body h2 { margin: 20px 0 10px; font-size: 1.35em; }
    .wysiwyg-editor h3, .article-preview-body h3 { margin: 16px 0 8px; font-size: 1.15em; }
    .wysiwyg-editor ul, .wysiwyg-editor ol, .article-preview-body ul, .article-preview-body ol { margin: 0 0 14px; padding-left: 22px; }
    .wysiwyg-editor li, .article-preview-body li { margin-bottom: 4px; }
    .wysiwyg-editor img, .article-preview-body img { max-width: 100%; border-radius: 8px; cursor: pointer; }
    .wysiwyg-editor img.eporta-img-selected { outline: 3px solid #2b6cb0; outline-offset: 2px; cursor: default; }
    .img-align-left { float: left; margin: 4px 16px 12px 0; max-width: 48%; }
    .img-align-center { display: block; margin: 14px auto; }
    .img-align-full { display: block; width: 100%; margin: 14px 0; }
    .wysiwyg-editor::after, .article-preview-body::after { content: ""; display: table; clear: both; }

    /* Предпросмотр */
    .preview-overlay { display: none; position: fixed; inset: 0; background: rgba(20,17,12,.6); z-index: 1000; align-items: flex-start; justify-content: center; padding: 40px 20px; overflow-y: auto; }
    .preview-overlay.open { display: flex; }
    .preview-modal { background: #fff; border-radius: 10px; max-width: 760px; width: 100%; padding: 0 0 40px; position: relative; }
    .preview-modal-header { display: flex; justify-content: flex-end; padding: 14px 14px 0; }
    .preview-modal-header button { background: none; border: none; font-size: 22px; cursor: pointer; color: #888; line-height: 1; }
    .article-preview-body { padding: 0 40px; font-size: 15px; line-height: 1.7; color: #3a3631; }
    .article-preview-body img { border-radius: 10px; }
    .article-preview-title { padding: 0 40px 16px; font-size: 26px; font-weight: 800; }
    .article-preview-photo { width: 100%; max-height: 360px; object-fit: cover; margin-bottom: 20px; }
</style>
</head>
<body>
<h1>Акции раздела /promo/</h1>
<p class="hint">Список акций, доступных на сайте. Публикация видна сразу после сохранения с включённым «Активна».</p>

<button type="button" class="btn" id="btnAdd">+ Новая акция</button>

<table id="promoTable">
    <thead><tr><th></th><th>Заголовок</th><th>Статус</th><th></th></tr></thead>
    <tbody id="promoTbody"></tbody>
</table>

<div id="formPanel">
    <h2 id="formTitle" style="font-size:16px;margin-top:0">Новая акция</h2>
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
        <label>Краткое описание (для списка акций)</label>
        <textarea id="fPreviewText" rows="3"></textarea>
    </div>
    <div class="field">
        <label>Текст акции</label>
        <div class="wysiwyg-toolbar">
            <button type="button" data-cmd="bold" title="Жирный"><b>Ж</b></button>
            <button type="button" data-cmd="italic" title="Курсив"><i>К</i></button>
            <span class="sep"></span>
            <button type="button" data-cmd="formatBlock" data-arg="<h2>" title="Заголовок">H2</button>
            <button type="button" data-cmd="formatBlock" data-arg="<h3>" title="Подзаголовок">H3</button>
            <button type="button" data-cmd="formatBlock" data-arg="<p>" title="Обычный текстовый абзац">Абзац</button>
            <span class="sep"></span>
            <button type="button" data-cmd="insertUnorderedList" title="Маркированный список">• Список</button>
            <button type="button" data-cmd="insertOrderedList" title="Нумерованный список">1. Список</button>
            <span class="sep"></span>
            <button type="button" data-cmd="createLink" title="Вставить ссылку">Ссылка</button>
            <button type="button" id="btnInsertImage" title="Вставить картинку в текст">🖼 Фото</button>
            <span class="sep"></span>
            <button type="button" data-align="left" title="Картинка слева, текст обтекает справа">⬅ Фото слева</button>
            <button type="button" data-align="center" title="Картинка по центру, отдельным блоком">⬛ По центру</button>
            <button type="button" data-align="full" title="Картинка на всю ширину текста">↔ Во всю ширину</button>
        </div>
        <div class="wysiwyg-editor" id="fDetailEditor" contenteditable="true"></div>
        <input type="file" id="fInlineImageInput" accept=".jpg,.jpeg,.png,.webp" style="display:none">
        <span id="fInlineImageStatus" class="form-status"></span>
    </div>
    <div class="form-actions">
        <button type="button" class="btn" id="btnSave">Сохранить</button>
        <button type="button" class="btn secondary" id="btnPreview">Предпросмотр</button>
        <button type="button" class="btn secondary" id="btnCancel">Отмена</button>
        <span id="formStatus" class="form-status"></span>
    </div>
</div>

<div class="preview-overlay" id="previewOverlay">
    <div class="preview-modal">
        <div class="preview-modal-header"><button type="button" id="btnClosePreview">×</button></div>
        <div id="previewPhotoWrap"></div>
        <div class="article-preview-title" id="previewTitle"></div>
        <div class="article-preview-body" id="previewBody"></div>
    </div>
</div>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    let PROMO_ITEMS = <?= json_encode($promoItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const tbody = document.getElementById('promoTbody');
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
    const fInlineImageInput = document.getElementById('fInlineImageInput');
    const fInlineImageStatus = document.getElementById('fInlineImageStatus');
    const previewOverlay = document.getElementById('previewOverlay');
    const previewTitle = document.getElementById('previewTitle');
    const previewBody = document.getElementById('previewBody');
    const previewPhotoWrap = document.getElementById('previewPhotoWrap');

    // Картинка, выделенная кликом внутри текста акции — к ней применяются кнопки выравнивания
    // (⬅/⬛/↔). Подсвечивается рамкой (.eporta-img-selected), пока не выбрана другая или не
    // снят фокус кликом по остальному тексту.
    let selectedImg = null;
    function selectImg(img) {
        if (selectedImg) selectedImg.classList.remove('eporta-img-selected');
        selectedImg = img;
        if (selectedImg) selectedImg.classList.add('eporta-img-selected');
    }
    fDetailEditor.addEventListener('click', function (e) {
        if (e.target.tagName === 'IMG') {
            selectImg(e.target);
        } else {
            selectImg(null);
        }
    });

    function renderTable() {
        tbody.innerHTML = '';
        PROMO_ITEMS.forEach(function (a) {
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
            btn.addEventListener('click', function () { deletePromo(btn.dataset.del); });
        });
    }

    function openForm(id) {
        formStatus.textContent = '';
        fPictureStatus.textContent = '';
        fInlineImageStatus.textContent = '';
        selectImg(null);
        savedRange = null;
        if (id) {
            const a = PROMO_ITEMS.find(function (x) { return String(x.ID) === String(id); });
            if (!a) return;
            formTitle.textContent = 'Редактирование акции';
            fElementId.value = a.ID;
            fName.value = a.NAME;
            fCode.value = a.CODE;
            fActive.checked = a.ACTIVE === 'Y';
            fPreviewText.value = a.PREVIEW_TEXT || '';
            fDetailEditor.innerHTML = a.DETAIL_TEXT || '';
            fPicturePreview.innerHTML = a.PREVIEW_PICTURE_SRC ? '<img src="' + a.PREVIEW_PICTURE_SRC + '" style="max-width:200px;border-radius:6px">' : '<span style="color:#999;font-size:13px">Картинки нет</span>';
        } else {
            formTitle.textContent = 'Новая акция';
            fElementId.value = '0';
            fName.value = '';
            fCode.value = '';
            fActive.checked = true;
            fPreviewText.value = '';
            fDetailEditor.innerHTML = '';
            fPicturePreview.innerHTML = '<span style="color:#999;font-size:13px">Сначала сохраните акцию, потом добавьте картинку</span>';
        }
        formPanel.classList.add('open');
        formPanel.scrollIntoView({ behavior: 'smooth' });
    }

    function closeForm() {
        formPanel.classList.remove('open');
    }

    document.getElementById('btnAdd').addEventListener('click', function () { openForm(null); });
    document.getElementById('btnCancel').addEventListener('click', closeForm);

    // Кнопки форматирования (жирный/курсив/заголовки/абзац/списки/ссылка) — execCommand с
    // тегом в угловых скобках (<p>, <h2>) для формата, надёжнее без скобок в разных браузерах
    // (историческая особенность Firefox, Chrome понимает оба варианта).
    document.querySelectorAll('.wysiwyg-toolbar button[data-cmd]').forEach(function (btn) {
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

    // Выравнивание/обтекание выделенной картинки (⬅ слева / ⬛ по центру / ↔ во всю ширину) —
    // применяется к последней картинке, по которой кликнули внутри редактора (selectedImg).
    document.querySelectorAll('.wysiwyg-toolbar button[data-align]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!selectedImg) {
                alert('Сначала кликните по картинке в тексте, чтобы её выделить');
                return;
            }
            selectedImg.classList.remove('img-align-left', 'img-align-center', 'img-align-full');
            selectedImg.classList.add('img-align-' + btn.dataset.align);
        });
    });

    // Вставка картинки в произвольное место текста: сохраняем позицию курсора ДО открытия
    // диалога выбора файла (иначе фокус/выделение в contenteditable теряется, пока открыт
    // системный файловый диалог и идёт асинхронная загрузка), после успешной загрузки
    // восстанавливаем ту же позицию и вставляем <img> через insertHTML.
    let savedRange = null;
    document.getElementById('btnInsertImage').addEventListener('click', function () {
        fDetailEditor.focus();
        const sel = window.getSelection();
        savedRange = sel.rangeCount ? sel.getRangeAt(0) : null;
        fInlineImageInput.value = '';
        fInlineImageInput.click();
    });
    fInlineImageInput.addEventListener('change', async function () {
        if (!fInlineImageInput.files.length) return;
        fInlineImageStatus.textContent = 'Загрузка...';
        fInlineImageStatus.className = 'form-status';

        const fd = new FormData();
        fd.append('action', 'upload_inline');
        fd.append('sessid', SESSID);
        fd.append('image', fInlineImageInput.files[0]);

        try {
            const r = await fetch('ajax.php', { method: 'POST', body: fd });
            const resp = await r.json();
            if (!resp.ok) {
                fInlineImageStatus.textContent = resp.error || 'Ошибка';
                fInlineImageStatus.className = 'form-status err';
                return;
            }
            fDetailEditor.focus();
            const sel = window.getSelection();
            sel.removeAllRanges();
            if (savedRange) {
                sel.addRange(savedRange);
            } else {
                // Курсор никогда не был в редакторе (например, кликнули "Фото" сразу после
                // открытия формы) — вставляем в конец текста, а не теряем картинку молча.
                const r2 = document.createRange();
                r2.selectNodeContents(fDetailEditor);
                r2.collapse(false);
                sel.addRange(r2);
            }
            document.execCommand('insertHTML', false, '<img src="' + resp.image + '" class="img-align-full">');
            fInlineImageStatus.textContent = 'Готово';
            fInlineImageStatus.className = 'form-status ok';
        } catch (e) {
            fInlineImageStatus.textContent = 'Ошибка сети: ' + e.message;
            fInlineImageStatus.className = 'form-status err';
        }
    });

    // Предпросмотр — рендерит несохранённое текущее состояние формы в том же оформлении,
    // что и настоящая страница акции (.article-preview-body повторяет правила .article-content
    // из template_styles.css для абзацев/списков/выравнивания фото).
    document.getElementById('btnPreview').addEventListener('click', function () {
        previewTitle.textContent = fName.value.trim() || '(без названия)';
        previewBody.innerHTML = fDetailEditor.innerHTML || '<p style="color:#999">Текст акции пока пуст.</p>';
        const previewImg = fPicturePreview.querySelector('img');
        previewPhotoWrap.innerHTML = previewImg ? '<img class="article-preview-photo" src="' + previewImg.src + '">' : '';
        previewOverlay.classList.add('open');
    });
    document.getElementById('btnClosePreview').addEventListener('click', function () {
        previewOverlay.classList.remove('open');
    });
    previewOverlay.addEventListener('click', function (e) {
        if (e.target === previewOverlay) previewOverlay.classList.remove('open');
    });

    async function refreshList() {
        const fd = new FormData();
        fd.append('action', 'list');
        fd.append('sessid', SESSID);
        const r = await fetch('ajax.php', { method: 'POST', body: fd });
        const resp = await r.json();
        if (resp.ok) {
            PROMO_ITEMS = resp.items;
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
            fPictureStatus.textContent = 'Сначала сохраните акцию';
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

    async function deletePromo(id) {
        if (!confirm('Удалить акцию безвозвратно?')) return;
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
