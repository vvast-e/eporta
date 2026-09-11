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
    if ($el && $el['DETAIL_PICTURE']) {
        $slot['preview'] = CFile::GetPath($el['DETAIL_PICTURE']);
    } elseif ($slot['fallback']) {
        $slot['preview'] = $assetsWebBase . $slot['fallback'];
    } else {
        // Новая коллекция без исторической картинки-заглушки — превью пустое, плитка на
        // нейтральной подложке, пока картинка не залита.
        $slot['preview'] = '';
    }
    $slot['is_custom'] = (bool)$el;
    $slot['overlay'] = $el ? (bool)($el['OVERLAY_ENABLED'] ?? true) : true;
}
unset($slot);

$catSlots = array_filter($slots, fn($c) => str_starts_with($c, 'cat_'), ARRAY_FILTER_USE_KEY);
$collSlots = array_filter($slots, fn($c) => str_starts_with($c, 'coll_'), ARRAY_FILTER_USE_KEY);

// Слайды главной карусели (заголовок/подзаголовок/ссылка/кнопка/затемнение) — картинка у них
// по-прежнему заливается только через штатную админку Bitrix (тот же приём, что и раньше,
// здесь редактируются только текстовые поля).
$carouselSlides = eportaBannersCarouselSlides();
$carouselGroupLabels = [
    'main' => 'Основной (крупный слева)',
    'side1' => 'Верхний правый (маленький)',
    'side2' => 'Нижний правый (маленький)',
];
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
    .slot-card .overlay-toggle { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #444; margin-top: 8px; cursor: pointer; }
    .slot-card .overlay-toggle input { margin: 0; }

    .slide-group { margin-bottom: 28px; }
    .slide-group h3 { font-size: 14px; margin: 0 0 10px; color: #444; }
    .slide-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
    .slide-card { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff; display: flex; gap: 10px; padding: 10px; }
    .slide-card .thumb { flex: none; width: 90px; height: 60px; border-radius: 5px; overflow: hidden; background: #eee; }
    .slide-card .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .slide-card .fields { flex: 1; min-width: 0; }
    .slide-card label { display: block; font-size: 11px; color: #888; margin: 6px 0 2px; }
    .slide-card label:first-child { margin-top: 0; }
    .slide-card input[type=text] { width: 100%; box-sizing: border-box; font-size: 12.5px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; }
    .slide-card .row-check { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #444; margin-top: 8px; }
    .slide-card .row-check input { margin: 0; }
    .slide-card button { margin-top: 10px; background: #2b6cb0; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; width: 100%; }
    .slide-card button:disabled { background: #999; cursor: default; }
    .slide-card .status { font-size: 12px; margin-top: 6px; min-height: 16px; }
    .slide-card .status.ok { color: #2f9e44; }
    .slide-card .status.err { color: #c0392b; }
    .empty-hint { color: #888; font-size: 13px; }
</style>
</head>
<body>
<h1>Баннеры главной страницы</h1>
<p class="hint">
    Замена картинок для плиток блоков «Каталог по категориям» и «Коллекции фабрики» на главной.
    Пока картинка не залита — используется текущая (фабричная) картинка шаблона. Формат JPG/PNG,
    до 8 МБ. Плитки категорий выводятся через object-fit:cover (обрезка по контейнеру) — важнее
    пропорция. Плитки коллекций — через object-fit:contain (квадрат, без обрезки, лишнее место
    закрывается фоном) — любые пропорции впишутся, но лучше квадратное фото. Затенение поверх
    фото можно выключить галочкой под превью — полезно для светлых фото, где градиент не нужен.
</p>

<h2>Каталог по категориям</h2>
<div class="slot-grid" id="grid-cat"></div>

<h2>Коллекции фабрики</h2>
<div class="slot-grid" id="grid-coll"></div>

<h2>Слайды главной карусели</h2>
<p class="hint">
    Заголовок, подзаголовок, ссылка и кнопка каждого слайда. Затемнение — то же затемнение, что
    и у плиток выше. Картинка слайда и добавление/удаление/порядок слайдов — по-прежнему через
    штатную админку Bitrix (раздел «Слайдер», инфоблок 27); здесь редактируются только эти поля.
</p>
<?php foreach ($carouselGroupLabels as $groupCode => $groupLabel): ?>
<div class="slide-group">
    <h3><?= htmlspecialcharsbx($groupLabel) ?></h3>
    <?php if (empty($carouselSlides[$groupCode])): ?>
    <p class="empty-hint">Нет слайдов в этом месте.</p>
    <?php else: ?>
    <div class="slide-grid" id="slide-grid-<?= htmlspecialcharsbx($groupCode) ?>"></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    const SLOTS = {
        cat: <?= json_encode(array_map(fn($c, $s) => ['code' => $c] + $s, array_keys($catSlots), $catSlots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        coll: <?= json_encode(array_map(fn($c, $s) => ['code' => $c] + $s, array_keys($collSlots), $collSlots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    const SLIDE_GROUPS = <?= json_encode($carouselSlides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function renderGrid(containerId, slots) {
        const container = document.getElementById(containerId);
        slots.forEach(function (slot) {
            const card = document.createElement('div');
            card.className = 'slot-card';
            card.innerHTML =
                '<div class="thumb">' +
                    '<span class="badge ' + (slot.is_custom ? 'custom' : 'fallback') + '">' + (slot.is_custom ? 'Загружено' : 'По умолчанию') + '</span>' +
                    (slot.preview ? '<img src="' + slot.preview + '" alt="">' : '') +
                '</div>' +
                '<div class="body">' +
                    '<div class="label">' + slot.label + '</div>' +
                    '<div class="size-hint">' + (slot.size || '') + '</div>' +
                    '<input type="file" accept=".jpg,.jpeg,.png">' +
                    '<button type="button">Заменить</button>' +
                    '<label class="overlay-toggle"><input type="checkbox" ' + (slot.overlay ? 'checked' : '') + '> Затенение поверх фото</label>' +
                    '<div class="status"></div>' +
                '</div>';

            const fileInput = card.querySelector('input[type=file]');
            const btn = card.querySelector('button');
            const statusEl = card.querySelector('.status');
            const badge = card.querySelector('.badge');
            const overlayCheckbox = card.querySelector('.overlay-toggle input');

            overlayCheckbox.addEventListener('change', async function () {
                overlayCheckbox.disabled = true;
                statusEl.textContent = '';
                statusEl.className = 'status';
                const fd = new FormData();
                fd.append('action', 'set_overlay');
                fd.append('sessid', SESSID);
                fd.append('slot', slot.code);
                fd.append('overlay', overlayCheckbox.checked ? 'Y' : 'N');
                try {
                    const r = await fetch('ajax.php', { method: 'POST', body: fd });
                    const resp = await r.json();
                    if (!resp.ok) {
                        statusEl.textContent = resp.error || 'Ошибка';
                        statusEl.classList.add('err');
                        overlayCheckbox.checked = !overlayCheckbox.checked;
                    } else {
                        statusEl.textContent = 'Сохранено';
                        statusEl.classList.add('ok');
                    }
                } catch (e) {
                    statusEl.textContent = 'Ошибка сети: ' + e.message;
                    statusEl.classList.add('err');
                    overlayCheckbox.checked = !overlayCheckbox.checked;
                }
                overlayCheckbox.disabled = false;
            });

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
                        let imgEl = card.querySelector('img');
                        if (!imgEl) {
                            imgEl = document.createElement('img');
                            card.querySelector('.thumb').appendChild(imgEl);
                        }
                        imgEl.src = resp.image + '?t=' + Date.now();
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

    function renderSlideGroup(groupCode, slides) {
        const container = document.getElementById('slide-grid-' + groupCode);
        if (!container) return;
        slides.forEach(function (slide) {
            const card = document.createElement('div');
            card.className = 'slide-card';
            const hasCta = !!slide.CTA_TEXT;
            card.innerHTML =
                '<div class="thumb">' + (slide.preview ? '<img src="' + slide.preview + '" alt="">' : '') + '</div>' +
                '<div class="fields">' +
                    '<label>Заголовок</label>' +
                    '<input type="text" class="f-name" value="' + escapeAttr(slide.NAME) + '">' +
                    '<label>Подзаголовок</label>' +
                    '<input type="text" class="f-subtitle" value="' + escapeAttr(slide.SUBTITLE) + '">' +
                    '<label>Ссылка</label>' +
                    '<input type="text" class="f-link" value="' + escapeAttr(slide.LINK) + '" placeholder="/catalog/">' +
                    '<label class="row-check"><input type="checkbox" class="f-show-cta" ' + (hasCta ? 'checked' : '') + '> Показывать кнопку</label>' +
                    '<label>Текст кнопки</label>' +
                    '<input type="text" class="f-cta-text" value="' + escapeAttr(slide.CTA_TEXT) + '" placeholder="Подробнее →"' + (hasCta ? '' : ' disabled') + '>' +
                    '<label class="row-check"><input type="checkbox" class="f-overlay" ' + (slide.OVERLAY_ENABLED ? 'checked' : '') + '> Затенение поверх фото</label>' +
                    '<button type="button">Сохранить</button>' +
                    '<div class="status"></div>' +
                '</div>';

            const showCtaCheckbox = card.querySelector('.f-show-cta');
            const ctaTextInput = card.querySelector('.f-cta-text');
            showCtaCheckbox.addEventListener('change', function () {
                ctaTextInput.disabled = !showCtaCheckbox.checked;
            });

            const btn = card.querySelector('button');
            const statusEl = card.querySelector('.status');
            btn.addEventListener('click', async function () {
                statusEl.textContent = '';
                statusEl.className = 'status';
                const name = card.querySelector('.f-name').value.trim();
                if (!name) {
                    statusEl.textContent = 'Заголовок не может быть пустым';
                    statusEl.classList.add('err');
                    return;
                }
                btn.disabled = true;
                statusEl.textContent = 'Сохранение...';

                const fd = new FormData();
                fd.append('action', 'save_meta');
                fd.append('sessid', SESSID);
                fd.append('element_id', slide.ID);
                fd.append('name', name);
                fd.append('subtitle', card.querySelector('.f-subtitle').value.trim());
                fd.append('link', card.querySelector('.f-link').value.trim());
                fd.append('show_cta', showCtaCheckbox.checked ? 'Y' : 'N');
                fd.append('cta_text', ctaTextInput.value.trim());
                fd.append('overlay', card.querySelector('.f-overlay').checked ? 'Y' : 'N');

                try {
                    const r = await fetch('ajax.php', { method: 'POST', body: fd });
                    const resp = await r.json();
                    if (!resp.ok) {
                        statusEl.textContent = resp.error || 'Ошибка';
                        statusEl.classList.add('err');
                    } else {
                        statusEl.textContent = 'Сохранено';
                        statusEl.classList.add('ok');
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

    function escapeAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    Object.keys(SLIDE_GROUPS).forEach(function (groupCode) {
        renderSlideGroup(groupCode, SLIDE_GROUPS[groupCode]);
    });
})();
</script>
</body>
</html>
