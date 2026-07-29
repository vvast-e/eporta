<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaImportUserHasAccess()) {
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Загрузка товаров из 1С (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; }
    .panel { border: 1px solid #ddd; border-radius: 6px; padding: 20px; margin-bottom: 20px; }
    button { background: #2b6cb0; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-size: 14px; }
    button:disabled { background: #999; cursor: default; }
    .progress-outer { background: #eee; border-radius: 4px; height: 18px; overflow: hidden; margin: 12px 0; }
    .progress-inner { background: #2b6cb0; height: 100%; width: 0%; transition: width .2s; }
    table { border-collapse: collapse; width: 100%; font-size: 13px; margin-top: 12px; }
    th, td { border: 1px solid #e2e2e2; padding: 5px 8px; text-align: left; }
    th { background: #f7f7f7; }
    tr.status-error td { background: #fdeded; }
    tr.status-created td { background: #eafaea; }
    tr.status-updated td { background: #eaf2fa; }
    .summary { font-weight: bold; margin-top: 10px; }
    .warn { color: #a15c00; background: #fff6e5; border: 1px solid #f0d9a0; border-radius: 4px; padding: 10px; margin-top: 12px; font-size: 13px; }
    .error { color: #a10000; background: #fdeded; border: 1px solid #f0a0a0; border-radius: 4px; padding: 10px; margin-top: 12px; font-size: 13px; }
</style>
</head>
<body>
<h1>Загрузка товаров из 1С</h1>
<p class="hint">
    Формат файла — как в эталоне (лист «Тест»): Артикул, Модель, Название, Фабрика, Бренд, Категория,
    Коллекция, Цена, Скидка, Покрытие, Цвет, Оттенок, Остекление, Кромка, Стиль, Открывание, Вид двери,
    Конструкция, Материал, Шумоизоляция, Огнестойкость, Гарантия, Размеры, Наличие, Срок поставки,
    Краткое описание, Полное описание, Фото-Big, Фото-Preview, Галеррея, Рейтинг.
    Товар ищется/обновляется по Артикулу — при повторной загрузке того же файла дубликаты не создаются.
</p>

<div class="panel">
    <input type="file" id="xlsx-file" accept=".xlsx">
    <button id="btn-upload">Импортировать</button>
    <div id="upload-error"></div>
</div>

<div class="panel" id="progress-panel" style="display:none;">
    <div id="header-warning"></div>
    <div class="progress-outer"><div class="progress-inner" id="progress-bar"></div></div>
    <div id="progress-text"></div>
    <div class="summary" id="summary"></div>
    <table id="results-table" style="display:none;">
        <thead><tr><th>Артикул</th><th>Статус</th><th>Комментарий</th></tr></thead>
        <tbody id="results-body"></tbody>
    </table>
</div>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    const btn = document.getElementById('btn-upload');
    const fileInput = document.getElementById('xlsx-file');
    const uploadError = document.getElementById('upload-error');
    const progressPanel = document.getElementById('progress-panel');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const summaryEl = document.getElementById('summary');
    const headerWarning = document.getElementById('header-warning');
    const resultsTable = document.getElementById('results-table');
    const resultsBody = document.getElementById('results-body');

    function showError(msg) {
        uploadError.innerHTML = '<div class="error">' + msg + '</div>';
    }

    function statusLabel(status) {
        if (status === 'created') return 'Создан';
        if (status === 'updated') return 'Обновлён';
        return 'Ошибка';
    }

    btn.addEventListener('click', async function () {
        uploadError.innerHTML = '';
        if (!fileInput.files.length) {
            showError('Выберите файл .xlsx');
            return;
        }
        btn.disabled = true;
        progressPanel.style.display = 'block';
        headerWarning.innerHTML = '';
        summaryEl.innerHTML = '';
        resultsBody.innerHTML = '';
        resultsTable.style.display = 'none';
        progressText.textContent = 'Загрузка и разбор файла...';
        progressBar.style.width = '0%';

        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('sessid', SESSID);
        fd.append('xlsx_file', fileInput.files[0]);

        let uploadResp;
        try {
            const r = await fetch('ajax.php', { method: 'POST', body: fd });
            uploadResp = await r.json();
        } catch (e) {
            showError('Ошибка загрузки: ' + e.message);
            btn.disabled = false;
            return;
        }
        if (!uploadResp.ok) {
            showError(uploadResp.error || 'Не удалось разобрать файл');
            btn.disabled = false;
            progressPanel.style.display = 'none';
            return;
        }

        if (uploadResp.unmapped_headers && uploadResp.unmapped_headers.length) {
            headerWarning.innerHTML = '<div class="warn">Неизвестные колонки (проигнорированы): ' +
                uploadResp.unmapped_headers.join(', ') + '</div>';
        }

        const token = uploadResp.token;
        const total = uploadResp.total;
        resultsTable.style.display = '';
        let offset = 0;
        let created = 0, updated = 0, errors = 0;

        async function runBatch() {
            const fd2 = new FormData();
            fd2.append('action', 'batch');
            fd2.append('sessid', SESSID);
            fd2.append('token', token);
            fd2.append('offset', offset);

            let resp;
            try {
                const r = await fetch('ajax.php', { method: 'POST', body: fd2 });
                resp = await r.json();
            } catch (e) {
                showError('Ошибка обработки: ' + e.message);
                btn.disabled = false;
                return;
            }
            if (!resp.ok) {
                showError(resp.error || 'Ошибка обработки партии');
                btn.disabled = false;
                return;
            }

            resp.results.forEach(function (row) {
                if (row.status === 'created') created++;
                else if (row.status === 'updated') updated++;
                else errors++;
                const tr = document.createElement('tr');
                tr.className = 'status-' + row.status;
                tr.innerHTML = '<td>' + row.article + '</td><td>' + statusLabel(row.status) + '</td><td>' + (row.message || '') + '</td>';
                resultsBody.appendChild(tr);
            });

            offset = resp.next_offset;
            const pct = total ? Math.round(offset / total * 100) : 100;
            progressBar.style.width = pct + '%';
            progressText.textContent = 'Обработано ' + offset + ' из ' + total;
            summaryEl.textContent = 'Создано: ' + created + ' · Обновлено: ' + updated + ' · Ошибок: ' + errors;

            if (!resp.done) {
                await runBatch();
            } else {
                progressText.textContent = 'Готово. Обработано ' + total + ' строк.';
                btn.disabled = false;
            }
        }

        await runBatch();
    });
})();
</script>
</body>
</html>
