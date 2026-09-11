<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Debug-переключатель "старое/новое превью карточки" (задача 11.09.2026, временный
// инструмент для сравнения на глаз — НЕ трогает данные, только рендер сетки каталога).
// Флаг — обычная кука, не привязан к dev_preview (тот определяет, виден ли вообще сайт).
// Кнопка сайтовая (плавающая, поверх всего), а не админский тумблер — заказчику проще
// щёлкнуть прямо на живой странице, чем лезть в отдельный интерфейс. Уводить в постоянный
// код не нужно, при желании удалить — этот блок и чтение куки в catalog.section/.default/
// template.php ($eportaDebugHqPreview).
$eportaIsEportaTplForDebug = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
$eportaDebugHqPreviewOn = ($_COOKIE["eporta_debug_hq_photo"] ?? "") === "1";

$templatePhone = $arParams["TEMPLATE_TELEPHONE_1"] ?? "+7 (495) 120-11-38";
if (empty($arParams["TEMPLATE_TELEPHONE_1"]) && \Bitrix\Main\Loader::includeModule("dw.deluxe")) {
	$arTemplateSettings = \DwSettings::getInstance()->getCurrentSettings();
	if (!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"])) {
		$templatePhone = $arTemplateSettings["TEMPLATE_TELEPHONE_1"];
	}
}
?>

<!-- Подвал -->
<div class="site-footer">
	<div class="f-logo">EPORTA</div>
	<nav>
		<a href="/catalog/">Каталог</a>
		<a href="/dostavka/">Доставка</a>
		<a href="/oplata/">Оплата</a>
		<a href="/garantiya/">Гарантия</a>
		<a href="/about/contacts/">Контакты</a>
	</nav>
	<div class="f-phone"><?= htmlspecialcharsbx($templatePhone) ?></div>
</div>

<?php if ($eportaIsEportaTplForDebug): ?>
<!-- Debug: сравнение превью карточки "как сейчас" (Фото-Preview от поставщика) и "из Фото-Big"
     (наш ресайз до 480px из детального фото — острее у мелких/некачественных превью, задача
     сравнения качества фото карточек, 11.09.2026). Затрагивает ТОЛЬКО сетку каталога
     (bitrix:catalog.section/.default/template.php, PREVIEW_PICTURE vs DETAIL_PICTURE) —
     картинка выбирается на лету по куке, без записи в базу. Временный инструмент, снести
     вместе с чтением куки там и в catalog/index.php ($eportaDebugHqPreview), когда решение
     по факту принято. -->
<button type="button" id="eportaDebugHqToggle" style="position:fixed;top:50%;right:0;transform:translateY(-50%);z-index:99999;writing-mode:vertical-rl;text-orientation:mixed;background:<?= $eportaDebugHqPreviewOn ? "#1f8a4c" : "#1b1a17" ?>;color:#fff;border:none;border-radius:10px 0 0 10px;padding:14px 8px;font:700 12px 'Manrope',sans-serif;letter-spacing:.03em;cursor:pointer;box-shadow:-2px 0 12px rgba(0,0,0,.25)">
	<?= $eportaDebugHqPreviewOn ? "ФОТО: HQ (Big) — вкл" : "ФОТО: обычное — HQ выкл" ?>
</button>
<script>
document.getElementById('eportaDebugHqToggle').addEventListener('click', function () {
	var on = <?= $eportaDebugHqPreviewOn ? "true" : "false" ?>;
	var name = 'eporta_debug_hq_photo';
	if (on) {
		document.cookie = name + '=; path=/; max-age=0';
	} else {
		document.cookie = name + '=1; path=/; max-age=' + (60 * 60 * 24 * 7);
	}
	location.reload();
});
</script>
<?php endif; ?>

</body>
</html>
