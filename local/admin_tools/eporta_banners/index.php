<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaBannersUserHasAccess()) {
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
$slots = eportaBannersSlots();
$slotElements = eportaBannersGetSlotElements();

// Превью — либо уже залитая картинка, либо текущий файл-фолбэк из шаблона (тот же, что
// сейчас используется в index.php главной), чтобы страница сразу показывала реальную
// текущую картинку плитки, а не пустое место.
$assetsWebBase = '/local/templates/eporta/assets/img/';
foreach ($slots as $code => &$slot) {
    $el = $slotElements[$code] ?? null;
    $slot['preview'] = ($el && $el['DETAIL_PICTURE']) ? CFile::GetPath($el['DETAIL_PICTURE']) : $assetsWebBase . $slot['fallback'];
    $slot['is_custom'] = (bool)$el;
}
unset($slot);

$catSlots = array_filter($slots, fn($c) => str_starts_with($c, 'cat_'), ARRAY_FILTER_USE_KEY);
$collSlots = array_filter($slots, fn($c) => str_starts_with($c, 'coll_'), ARRAY_FILTER_USE_KEY);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Баннеры главной (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 1100px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    h2 { font-size: 16px; margin: 32px 0 14px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; }
    .slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
    .slot-card { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff; }
    .slot-card .thumb { position: relative; width: 100%; aspect-ratio: 16/10; background: #eee; overflow: hidden; }
    .slot-card .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .slot-card .badge { position: absolute; top: 6px; left: 6px; font-size: 11px; padding: 2px 7px; border-radius: 10px; color: #fff; }
    .slot-card .badge.custom { background: #2f9e44; }
    .slot-card .badge.fallback { background: #868e96; }
    .slot-card .body { padding: 10px 12px 12px; }
    .slot-card .label { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
    .slot-card .size-hint { font-size: 11px; color: #888; margin-bottom: 8px; }
    .slot-card input[type=file] { font-size: 11px; width: 100%; margin-bottom: 8px; }
    .slot-card button { background: #2b6cb0; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; width: 100%; }
    .slot-card button:disabled { background: #999; cursor: default; }
    .slot-card .status { font-size: 12px; margin-top: 6px; min-height: 16px; }
    .slot-card .status.ok { color: #2f9e44; }
    .slot-card .status.err { color: #c0392b; }
</style>
</head>
<body>
<h1>Баннеры главной страницы</h1>
<p class="hint">
    Замена картинок для плиток блоков «Каталог по категориям» и «Коллекции фабрики» на главной.
    Пока картинка не залита — используется текущая (фабричная) картинка шаблона. Формат JPG/PNG,
    до 8 МБ. Вывод везде через object-fit:cover (обрезка по контейнеру), поэтому важнее соблюсти
    пропорцию, чем точный размер в пикселях — рекомендация под каждой плиткой ниже.
</p>

<h2>Каталог по категориям</h2>
<div class="slot-grid" id="grid-cat"></div>

<h2>Коллекции фабрики</h2>
<div class="slot-grid" id="grid-coll"></div>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    const SLOTS = {
        cat: <?= json_encode(array_map(fn($c, $s) => ['code' => $c] + $s, array_keys($catSlots), $catSlots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        coll: <?= json_encode(array_map(fn($c, $s) => ['code' => $c] + $s, array_keys($collSlots), $collSlots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };

    function renderGrid(containerId, slots) {
        const container = document.getElementById(containerId);
        slots.forEach(function (slot) {
            const card = document.createElement('div');
            card.className = 'slot-card';
            card.innerHTML =
                '<div class="thumb">' +
                    '<span class="badge ' + (slot.is_custom ? 'custom' : 'fallback') + '">' + (slot.is_custom ? 'Загружено' : 'По умолчанию') + '</span>' +
                    '<img src="' + slot.preview + '" alt="">' +
                '</div>' +
                '<div class="body">' +
                    '<div class="label">' + slot.label + '</div>' +
                    '<div class="size-hint">' + (slot.size || '') + '</div>' +
                    '<input type="file" accept=".jpg,.jpeg,.png">' +
                    '<button type="button">Заменить</button>' +
                    '<div class="status"></div>' +
                '</div>';

            const img = card.querySelector('img');
            const fileInput = card.querySelector('input[type=file]');
            const btn = card.querySelector('button');
            const statusEl = card.querySelector('.status');
            const badge = card.querySelector('.badge');

            btn.addEventListener('click', async function () {
                statusEl.textContent = '';
                statusEl.className = 'status';
                if (!fileInput.files.length) {
                    statusEl.textContent = 'Выберите файл';
                    statusEl.classList.add('err');
                    return;
                }
                btn.disabled = true;
                statusEl.textContent = 'Загрузка...';

                const fd = new FormData();
                fd.append('action', 'upload');
                fd.append('sessid', SESSID);
                fd.append('slot', slot.code);
                fd.append('image', fileInput.files[0]);

                try {
                    const r = await fetch('ajax.php', { method: 'POST', body: fd });
                    const resp = await r.json();
                    if (!resp.ok) {
                        statusEl.textContent = resp.error || 'Ошибка';
                        statusEl.classList.add('err');
                    } else {
                        img.src = resp.image + '?t=' + Date.now();
                        badge.textContent = 'Загружено';
                        badge.className = 'badge custom';
                        statusEl.textContent = 'Готово';
                        statusEl.classList.add('ok');
                        fileInput.value = '';
                    }
                } catch (e) {
                    statusEl.textContent = 'Ошибка сети: ' + e.message;
                    statusEl.classList.add('err');
                }
                btn.disabled = false;
            });

            container.appendChild(card);
        });
    }

    renderGrid('grid-cat', SLOTS.cat);
    renderGrid('grid-coll', SLOTS.coll);
})();
</script>
</body>
</html>
