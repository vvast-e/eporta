<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Page\Asset;

// Dev-превью: если пришли по ?dev_preview=<токен>, закрепляем токен в cookie на
// сессию браузера, чтобы шаблон eporta не слетал на dresscode при переходе по
// обычным ссылкам без параметра (см. условие показа шаблона в b_site_template,
// SITE_ID=s2 — теперь принимает токен и из $_REQUEST, и из $_COOKIE).
if (($_REQUEST["dev_preview"] ?? "") === "x7Qm2pR9vL" && empty($_COOKIE["dev_preview"])) {
	setcookie("dev_preview", "x7Qm2pR9vL", 0, "/");
}

$asset = Asset::getInstance();
$asset->addCss(SITE_TEMPLATE_PATH."/template_styles.css");
$asset->addString('<link rel="preconnect" href="https://fonts.googleapis.com">');
$asset->addString('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>');
$asset->addString('<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">');
$asset->addJs(SITE_TEMPLATE_PATH."/assets/app.js");

// Телефон/почта: параметры шаблона (.parameters.php), с фолбэком на dw.deluxe
$templatePhone = $arParams["TEMPLATE_TELEPHONE_1"] ?? "+7 (495) 120-11-38";
$templateEmail = $arParams["TEMPLATE_EMAIL_1"] ?? "info@eporta.ru";
if (empty($arParams["TEMPLATE_TELEPHONE_1"]) && \Bitrix\Main\Loader::includeModule("dw.deluxe")) {
	$arTemplateSettings = \DwSettings::getInstance()->getCurrentSettings();
	if (!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"])) {
		$templatePhone = $arTemplateSettings["TEMPLATE_TELEPHONE_1"];
	}
	if (!empty($arTemplateSettings["TEMPLATE_EMAIL_1"])) {
		$templateEmail = $arTemplateSettings["TEMPLATE_EMAIL_1"];
	}
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<?php $APPLICATION->ShowHead(); ?>
<title><?php $APPLICATION->ShowTitle(); ?></title>
</head>
<body>
<?php $APPLICATION->ShowPanel(); ?>

<!-- Тикер -->
<div class="trust-bar">
	<span>Бесплатный замер по Москве</span><span class="sep">•</span>
	<span>Доставка по РФ от 1 дня</span><span class="sep">•</span>
	<span>Рассрочка 0% до 12 мес.</span><span class="sep">•</span>
	<span>Гарантия 2 года</span>
</div>

<!-- Шапка -->
<div class="site-header">
	<a href="/" class="logo-wrap">
		<div class="logo-icon"><div class="logo-icon-inner"><span></span><span></span></div></div>
		<div class="logo-text"><strong>EPORTA</strong><small>ФАБРИКА ДВЕРЕЙ</small></div>
	</a>
	<div class="header-search"><span class="ico">⌕</span> Поиск двери по названию, стилю, цвету…</div>
	<div class="header-phone">
		<strong><?= htmlspecialcharsbx($templatePhone) ?></strong>
		<a href="#">Заказать звонок</a>
	</div>
	<div class="header-actions">
		<a href="/auth/" class="btn-account"></a>
		<a href="/cart/" class="cart-btn"><span class="icon"></span><span class="badge">0</span></a>
	</div>
</div>

<!-- Навигация -->
<div class="cat-nav">
	<a href="/catalog/" class="active">Каталог</a>
	<a href="/collection/">Коллекции</a>
	<span class="nav-item nav-sale"><span class="dot-red"></span>Распродажа</span>
	<span class="nav-item nav-new">Новинки<span class="badge-new">NEW</span></span>
	<span class="spacer"></span>
	<span class="sale-badge"><span class="dot"></span>Акция: рассрочка 0%</span>
</div>
