<?php
// Отдельная страница настройки кнопки/затемнения баннера ОДНОЙ коллекции — вынесена из общей
// таблицы local/admin_tools/eporta_collections/index.php (задача 11.09.2026, заказчику неудобно
// было держать эти поля прямо в строке таблицы). Название/описание/порядок/активность и загрузка
// самой картинки баннера по-прежнему редактируются в общей таблице — здесь только
// UF_BANNER_OVERLAY/UF_BANNER_CTA_TEXT/UF_BANNER_CTA_LINK (см. eportaCollectionsUpdateBannerMeta()
// в lib.php).
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

$sectionId = (int)($_GET['id'] ?? 0);
// Ищем только среди реальных коллекций (включая скрытые), а не по произвольному ID секции ИБ 19 —
// та же защита, что и в остальных действиях ajax.php этой админки.
$collection = null;
foreach (eportaCollections(true) as $coll) {
    if ((int)$coll['ID'] === $sectionId) {
        $collection = $coll;
        break;
    }
}
if (!$collection) {
    ?>
    <!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Коллекция не найдена</title></head>
    <body style="font-family: sans-serif; padding: 40px;">
        <h1>Коллекция не найдена</h1>
        <p><a href="index.php">← Назад к списку коллекций</a></p>
    </body></html>
    <?php
    exit;
}

$bannerFile = $collection['DETAIL_PICTURE'] ?: $collection['PICTURE'];
$bannerPath = $bannerFile ? CFile::GetPath($bannerFile) : '';
$overlay = ($collection['UF_BANNER_OVERLAY'] ?? '') !== 'N';
$ctaText = (string)($collection['UF_BANNER_CTA_TEXT'] ?? '');
$ctaLink = (string)($collection['UF_BANNER_CTA_LINK'] ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Баннер коллекции «<?= htmlspecialcharsbx($collection['NAME']) ?>» (eporta.ru)</title>
<style>
    body { font-family: -apple-system, Segoe UI, Arial, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 20px; margin-bottom: 4px; }
    .back-link { display: inline-block; margin-bottom: 20px; font-size: 13px; color: #2b6cb0; }
    .hint { color: #666; font-size: 13px; margin-bottom: 24px; }
    .preview { position: relative; border-radius: 12px; overflow: hidden; min-height: 140px; background: #e5e0d5 center/cover no-repeat; margin-bottom: 24px; }
    .preview .noimg { padding: 50px 0; text-align: center; color: #999; font-size: 13px; }
    .preview .overlay-demo { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(20,17,12,.86) 0%, rgba(20,17,12,.5) 55%, rgba(20,17,12,.08) 100%); }
    .preview .caption { position: relative; padding: 20px 24px; color: #fff; font: 800 22px 'Manrope', sans-serif; }
    .field { margin-bottom: 18px; }
    .field label { display: block; font-size: 13px; color: #555; margin-bottom: 5px; font-weight: 600; }
    .field .desc { font-size: 12px; color: #888; margin-top: 4px; }
    .field input[type=text] { width: 100%; box-sizing: border-box; font-size: 14px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px; }
    .row-check { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #333; }
    .row-check input { margin: 0; }
    button { background: #2b6cb0; color: #fff; border: none; padding: 9px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; margin-top: 10px; }
    button:disabled { background: #999; cursor: default; }
    .status { font-size: 13px; margin-top: 10px; min-height: 18px; }
    .status.ok { color: #2f9e44; }
    .status.err { color: #c0392b; }
</style>
</head>
<body>
<a class="back-link" href="index.php">← Назад к списку коллекций</a>
<h1>Баннер коллекции «<?= htmlspecialcharsbx($collection['NAME']) ?>»</h1>
<p class="hint">
    Кнопка и затемнение на баннере страницы <code>/catalog/collections/<?= htmlspecialcharsbx($collection['CODE']) ?>/</code>.
    Название и подзаголовок баннера берутся из названия/описания коллекции в
    <a href="index.php" target="_blank">общем списке →</a>, картинка баннера грузится тоже там.
</p>

<div class="preview" id="preview" style="<?= $bannerPath ? "background-image:url('".htmlspecialcharsbx($bannerPath)."')" : '' ?>">
    <?php if (!$bannerPath): ?>
    <div class="noimg">Баннер без картинки — фото грузится в общем списке коллекций</div>
    <?php endif; ?>
    <div class="overlay-demo" id="overlay-demo" style="display:<?= $overlay ? 'block' : 'none' ?>"></div>
    <div class="caption"><?= htmlspecialcharsbx($collection['NAME']) ?></div>
</div>

<div class="field">
    <label class="row-check"><input type="checkbox" id="f-overlay" <?= $overlay ? 'checked' : '' ?>> Затемнение поверх фото</label>
    <div class="desc">Тёмный градиент под текстом заголовка — выключите для уже тёмных/контрастных фото.</div>
</div>

<div class="field">
    <label for="f-cta-text">Текст кнопки</label>
    <input type="text" id="f-cta-text" value="<?= htmlspecialcharsbx($ctaText) ?>" placeholder="Пусто — кнопки не будет">
    <div class="desc">Оставьте пустым, чтобы кнопка на баннере не показывалась вовсе.</div>
</div>

<div class="field">
    <label for="f-cta-link">Ссылка кнопки</label>
    <input type="text" id="f-cta-link" value="<?= htmlspecialcharsbx($ctaLink) ?>" placeholder="/catalog/">
    <div class="desc">Относительный путь (/catalog/…) или полный http(s)-адрес.</div>
</div>

<button type="button" id="save-btn">Сохранить</button>
<div class="status" id="status"></div>

<script>
(function () {
    const SESSID = <?= json_encode($sessid) ?>;
    const SECTION_ID = <?= (int)$collection['ID'] ?>;
    const overlayCheckbox = document.getElementById('f-overlay');
    const overlayDemo = document.getElementById('overlay-demo');
    overlayCheckbox.addEventListener('change', function () {
        overlayDemo.style.display = overlayCheckbox.checked ? 'block' : 'none';
    });

    document.getElementById('save-btn').addEventListener('click', async function () {
        const btn = this;
        const status = document.getElementById('status');
        btn.disabled = true;
        status.textContent = '';
        status.className = 'status';

        const fd = new FormData();
        fd.append('action', 'update_banner_meta');
        fd.append('sessid', SESSID);
        fd.append('id', SECTION_ID);
        fd.append('cta_text', document.getElementById('f-cta-text').value.trim());
        fd.append('cta_link', document.getElementById('f-cta-link').value.trim());
        fd.append('overlay', overlayCheckbox.checked ? 'Y' : 'N');

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
})();
</script>
</body>
</html>
