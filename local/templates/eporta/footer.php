<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

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

</body>
</html>
