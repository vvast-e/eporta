<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaReviewsUserHasAccess()) {
    ?>
    <!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Нет доступа</title></head>
    <body style="font-family: sans-serif; padding: 40px;">
        <h1>Нет доступа</h1>
        <p>У вашей учётной записи нет прав на запись в каталог (IBLOCK 19). Обратитесь к администратору сайта.</p>
    </body></html>
    <?php
    exit;
}
if (EPORTA_REVIEWS_IBLOCK_ID <= 0) {
    ?>
    <!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Инфоблок не создан</title></head>
    <body style="font-family: sans-serif; padding: 40px;">
        <h1>Инфоблок "Отзывы" ещё не создан</h1>
        <p>Запустите на проде <code>php scripts/create_iblock_reviews.php</code>, затем впишите полученный ID
        как <code>EPORTA_REVIEWS_IBLOCK_ID</code> в <code>local/php_interface/include/eporta_reviews_common.php</code>.</p>
    </body></html>
    <?php
    exit;
}

$sessid = bitrix_sessid();
$reviews = eportaReviewsAdminList();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Отзывы (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
    th { color: #888; font-weight: 600; }
    td.text { max-width: 320px; color: #555; }
    .status-y { color: #2f9e44; font-weight: 600; }
    .status-n { color: #999; }
    .btn { background: #2b6cb0; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; }
    .btn.secondary { background: #6c757d; }
    .btn.danger { background: #c0392b; }
    #formPanel { display: none; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
    #formPanel.open { display: block; }
    .field { margin-bottom: 14px; }
    .field label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
    .field input[type=text], .field input[type=date], .field select, .field textarea { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; font-family: inherit; }
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
<h1>Блок «Отзывы» на главной</h1>
<p class="hint">Отзывы добавляются только здесь — публичной формы «оставить отзыв» на сайте нет.
Публикация видна сразу после сохранения с включённым «Активен». Если активных отзывов нет — блок
на главной не выводится.</p>

<button type="button" class="btn" id="btnAdd">+ Новый отзыв</button>

<table id="reviewsTable">
    <thead><tr><th>Автор</th><th>Оценка</th><th>Текст</th><th>Статус</th><th></th></tr></thead>
    <tbody id="reviewsTbody"></tbody>
</table>

<div id="formPanel">
    <h2 id="formTitle" style="font-size:16px;margin-top:0">Новый отзыв</h2>
    <input type="hidden" id="fElementId" value="0">
    <div class="field-row">
        <div class="field">
            <label>Имя автора</label>
            <input type="text" id="fName">
        </div>
        <div class="field">
            <label>Город</label>
            <input type="text" id="fCity">
        </div>
    </div>
    <div class="field-row">
        <div class="field">
            <label>Оценка</label>
            <select id="fRating">
                <option value="5">5 — ★★★★★</option>
                <option value="4">4 — ★★★★</option>
                <option value="3">3 — ★★★</option>
                <option value="2">2 — ★★</option>
                <option value="1">1 — ★</option>
            </select>
        </div>
        <div class="field">
            <label>Дата</label>
            <input type="date" id="fDate">
        </div>
    </div>
    <div class="field">
        <label><input type="checkbox" id="fActive" checked> Активен (виден на сайте)</label>
    </div>
    <div class="field">
        <label>Текст отзыва</label>
        <textarea id="fText" rows="4"></textarea>
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
    let REVIEWS = <?= json_encode($reviews, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const tbody = document.getElementById('reviewsTbody');
    const formPanel = document.getElementById('formPanel');
    const formTitle = document.getElementById('formTitle');
    const fElementId = document.getElementById('fElementId');
    const fName = document.getElementById('fName');
    const fCity = document.getElementById('fCity');
    const fRating = document.getElementById('fRating');
    const fDate = document.getElementById('fDate');
    const fActive = document.getElementById('fActive');
    const fText = document.getElementById('fText');
    const formStatus = document.getElementById('formStatus');

    function toDateInput(s) {
        // ACTIVE_FROM приходит в формате Bitrix (обычно ДД.ММ.ГГГГ) -> ГГГГ-ММ-ДД для <input type=date>.
        const m = /^(\d{2})\.(\d{2})\.(\d{4})/.exec(s || '');
        return m ? m[3] + '-' + m[2] + '-' + m[1] : '';
    }
    function fromDateInput(s) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
        return m ? m[3] + '.' + m[2] + '.' + m[1] : '';
    }

    function renderTable() {
        tbody.innerHTML = '';
        REVIEWS.forEach(function (r) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + r.NAME.replace(/</g, '&lt;') + (r.CITY ? '<br><span style="color:#999">' + r.CITY.replace(/</g, '&lt;') + '</span>' : '') + '</td>' +
                '<td>' + '★'.repeat(r.RATING) + '</td>' +
                '<td class="text">' + (r.PREVIEW_TEXT || '').replace(/</g, '&lt;').slice(0, 120) + '</td>' +
                '<td class="' + (r.ACTIVE === 'Y' ? 'status-y' : 'status-n') + '">' + (r.ACTIVE === 'Y' ? 'Активен' : 'Скрыт') + '</td>' +
                '<td><button type="button" class="btn secondary" data-edit="' + r.ID + '">Редактировать</button> ' +
                '<button type="button" class="btn danger" data-del="' + r.ID + '">Удалить</button></td>';
            tbody.appendChild(tr);
        });
        tbody.querySelectorAll('[data-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () { openForm(btn.dataset.edit); });
        });
        tbody.querySelectorAll('[data-del]').forEach(function (btn) {
            btn.addEventListener('click', function () { deleteReview(btn.dataset.del); });
        });
    }

    function openForm(id) {
        formStatus.textContent = '';
        if (id) {
            const r = REVIEWS.find(function (x) { return String(x.ID) === String(id); });
            if (!r) return;
            formTitle.textContent = 'Редактирование отзыва';
            fElementId.value = r.ID;
            fName.value = r.NAME;
            fCity.value = r.CITY || '';
            fRating.value = String(r.RATING || 5);
            fDate.value = toDateInput(r.ACTIVE_FROM);
            fActive.checked = r.ACTIVE === 'Y';
            fText.value = r.PREVIEW_TEXT || '';
        } else {
            formTitle.textContent = 'Новый отзыв';
            fElementId.value = '0';
            fName.value = '';
            fCity.value = '';
            fRating.value = '5';
            fDate.value = new Date().toISOString().slice(0, 10);
            fActive.checked = true;
            fText.value = '';
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
            REVIEWS = resp.items;
            renderTable();
        }
    }

    document.getElementById('btnSave').addEventListener('click', async function () {
        const name = fName.value.trim();
        const text = fText.value.trim();
        if (!name) {
            formStatus.textContent = 'Имя автора обязательно';
            formStatus.className = 'form-status err';
            return;
        }
        if (!text) {
            formStatus.textContent = 'Текст отзыва обязателен';
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
        fd.append('RATING', fRating.value);
        fd.append('ACTIVE_FROM', fromDateInput(fDate.value));
        fd.append('PREVIEW_TEXT', text);

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

    async function deleteReview(id) {
        if (!confirm('Удалить отзыв безвозвратно?')) return;
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
