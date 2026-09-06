<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaShowcaseUserHasAccess()) {
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
$collections = eportaShowcaseGetCollections();

$selectedSectionId = (int)($_GET['section'] ?? 0);
if (!$selectedSectionId && $collections) {
    $selectedSectionId = (int)$collections[0]['ID'];
}

$models = $selectedSectionId ? eportaShowcaseGetModels($selectedSectionId) : [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Витрина моделей (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 1100px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; }
    h2 { font-size: 15px; margin: 28px 0 10px; }
    .hint { color: #666; font-size: 13px; margin-bottom: 20px; }
    select { font-size: 14px; padding: 6px 10px; margin-bottom: 20px; }
    .model-block { border: 1px solid #ddd; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; background: #fff; }
    .model-name { font-weight: 700; font-size: 14px; margin-bottom: 10px; }
    .variant-row { display: flex; flex-wrap: wrap; gap: 12px; }
    .variant-card { width: 110px; text-align: center; cursor: pointer; border: 2px solid transparent; border-radius: 8px; padding: 6px; }
    .variant-card.active { border-color: #2f9e44; background: #f1fbf3; }
    .variant-card img { width: 100%; height: 90px; object-fit: contain; background: #f6f4ef; border-radius: 4px; display: block; }
    .variant-card .noimg { width: 100%; height: 90px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #999; }
    .variant-card .color { font-size: 11px; margin-top: 5px; color: #444; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .variant-card .star { font-size: 11px; color: #2f9e44; font-weight: 700; margin-top: 2px; min-height: 14px; }
    .variant-card .show-toggle { display: flex; align-items: center; gap: 4px; justify-content: center; margin-top: 6px; font-size: 10.5px; color: #666; cursor: pointer; }
    .variant-card.hidden-from-list { opacity: .5; }
    .status { font-size: 12px; margin-top: 8px; }
    .status.ok { color: #2f9e44; }
    .status.err { color: #c0392b; }
</style>
</head>
<body>
<h1>Витрина моделей</h1>
<p class="hint">
    Клик по фото делает вариант витринным — он показывается на карточке модели в блоке «Модели
    коллекции» на странице коллекции (остальные цвета модели по-прежнему доступны на карточке
    товара). Если для модели вариант не выбран, показывается самый популярный по рейтингу.<br>
    Галочка «в списке» — показывать ли этот конкретный вариант (цвет + остекление) в блоке
    «Все товары коллекции» и в общем каталоге. Снятая галочка не удаляет и не блокирует товар —
    он остаётся доступен по прямой ссылке и как цвет для переключения на карточке другого
    варианта той же модели, просто не попадает в общий список/плитку.
</p>

<form method="get" onchange="this.submit()">
    <select name="section">
        <?php foreach ($collections as $coll): ?>
        <option value="<?= (int)$coll['ID'] ?>" <?= $selectedSectionId === (int)$coll['ID'] ? 'selected' : '' ?>><?= htmlspecialcharsbx($coll['NAME']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (!$models): ?>
<p>В этой коллекции нет товаров.</p>
<?php endif; ?>

<?php foreach ($models as $modelKey => $model): ?>
<div class="model-block">
    <div class="model-name"><?= htmlspecialcharsbx($model['name']) ?></div>
    <div class="variant-row" data-all-ids="<?= htmlspecialcharsbx(implode(',', array_column($model['variants'], 'id'))) ?>">
        <?php foreach ($model['variants'] as $variant): ?>
        <div class="variant-card <?= $variant['is_showcase'] ? 'active' : '' ?> <?= $variant['show_in_list'] ? '' : 'hidden-from-list' ?>" data-id="<?= $variant['id'] ?>">
            <div class="variant-photo">
                <?php if ($variant['photo']): ?>
                <img src="<?= htmlspecialcharsbx($variant['photo']) ?>" alt="">
                <?php else: ?>
                <div class="noimg">Нет фото</div>
                <?php endif; ?>
            </div>
            <div class="color" title="<?= htmlspecialcharsbx($variant['color']) ?>"><?= htmlspecialcharsbx($variant['color'] ?: '—') ?><?= $variant['glazing'] !== '' ? ', ' . htmlspecialcharsbx($variant['glazing']) : '' ?></div>
            <div class="star"><?= $variant['is_showcase'] ? '★ витрина' : '' ?></div>
            <label class="show-toggle" onclick="event.stopPropagation()">
                <input type="checkbox" class="show-in-list-checkbox" <?= $variant['show_in_list'] ? 'checked' : '' ?>>
                в списке
            </label>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="status"></div>
</div>
<?php endforeach; ?>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    document.querySelectorAll('.model-block').forEach(function (block) {
        const row = block.querySelector('.variant-row');
        const allIds = row.dataset.allIds;
        const statusEl = block.querySelector('.status');
        row.querySelectorAll('.variant-card').forEach(function (card) {
            card.addEventListener('click', async function () {
                const id = card.dataset.id;
                statusEl.textContent = 'Сохранение...';
                statusEl.className = 'status';
                const fd = new FormData();
                fd.append('action', 'set_showcase');
                fd.append('sessid', SESSID);
                fd.append('id', id);
                fd.append('all_ids', allIds);
                try {
                    const r = await fetch('ajax.php', { method: 'POST', body: fd });
                    const resp = await r.json();
                    if (!resp.ok) {
                        statusEl.textContent = resp.error || 'Ошибка';
                        statusEl.classList.add('err');
                        return;
                    }
                    row.querySelectorAll('.variant-card').forEach(function (c) {
                        c.classList.remove('active');
                        c.querySelector('.star').textContent = '';
                    });
                    card.classList.add('active');
                    card.querySelector('.star').textContent = '★ витрина';
                    statusEl.textContent = 'Готово';
                    statusEl.classList.add('ok');
                } catch (e) {
                    statusEl.textContent = 'Ошибка сети: ' + e.message;
                    statusEl.classList.add('err');
                }
            });

            // Галочка "в списке" — отдельное независимое действие (event.stopPropagation() в
            // разметке уже не даёт клику по ней всплыть до обработчика set_showcase выше).
            const checkbox = card.querySelector('.show-in-list-checkbox');
            checkbox.addEventListener('change', async function () {
                const id = card.dataset.id;
                const show = checkbox.checked;
                statusEl.textContent = 'Сохранение...';
                statusEl.className = 'status';
                const fd = new FormData();
                fd.append('action', 'set_show_in_list');
                fd.append('sessid', SESSID);
                fd.append('id', id);
                fd.append('show', show ? '1' : '0');
                try {
                    const r = await fetch('ajax.php', { method: 'POST', body: fd });
                    const resp = await r.json();
                    if (!resp.ok) {
                        checkbox.checked = !show;
                        statusEl.textContent = resp.error || 'Ошибка';
                        statusEl.classList.add('err');
                        return;
                    }
                    card.classList.toggle('hidden-from-list', !show);
                    statusEl.textContent = 'Готово';
                    statusEl.classList.add('ok');
                } catch (e) {
                    checkbox.checked = !show;
                    statusEl.textContent = 'Ошибка сети: ' + e.message;
                    statusEl.classList.add('err');
                }
            });
        });
    });
})();
</script>
</body>
</html>
