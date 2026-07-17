<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Page\Asset;

global $USER;

// Dev-превью: если пришли по ?dev_preview=<токен>, закрепляем токен в cookie на
// сессию браузера, чтобы шаблон eporta не слетал на dresscode при переходе по
// обычным ссылкам без параметра (см. условие показа шаблона в b_site_template,
// SITE_ID=s2 — теперь принимает токен и из $_REQUEST, и из $_COOKIE).
if (($_REQUEST["dev_preview"] ?? "") === "x7Qm2pR9vL" && empty($_COOKIE["dev_preview"])) {
	setcookie("dev_preview", "x7Qm2pR9vL", 0, "/");
}

$asset = Asset::getInstance();
// Cache-busting через filemtime: nginx отдаёт статику immutable+1y без версионирования
// (конфиг генерируется ISPmanager, ручную правку не сохранить), поэтому версионируем
// на стороне PHP через query-параметр — браузер перекачает файл при изменении даты правки.
$cssPath = SITE_TEMPLATE_PATH."/template_styles.css";
$cssVer = @filemtime($_SERVER["DOCUMENT_ROOT"].$cssPath) ?: time();
$asset->addCss($cssPath."?v=".$cssVer);
$asset->addString('<link rel="preconnect" href="https://fonts.googleapis.com">');
$asset->addString('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>');
$asset->addString('<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">');
$jsPath = SITE_TEMPLATE_PATH."/assets/app.js";
$jsVer = @filemtime($_SERVER["DOCUMENT_ROOT"].$jsPath) ?: time();
$asset->addJs($jsPath."?v=".$jsVer);
$compareJsPath = SITE_TEMPLATE_PATH."/assets/compare.js";
$compareJsVer = @filemtime($_SERVER["DOCUMENT_ROOT"].$compareJsPath) ?: time();
$asset->addJs($compareJsPath."?v=".$compareJsVer);

// Счётчик сравнения — реальные данные сессии (не мок, в отличие от счётчика корзины),
// пересчитывается на каждой загрузке страницы из $_SESSION["COMPARE_LIST"]["ITEMS"].
$eportaCompareCount = !empty($_SESSION["COMPARE_LIST"]["ITEMS"]) ? count($_SESSION["COMPARE_LIST"]["ITEMS"]) : 0;

// Счётчик корзины — реальные данные Bitrix Sale (тот же способ подсчёта, что у вендорского
// dresscode:personal.info: сумма QUANTITY по строкам текущего FUSER без привязки к заказу),
// а не sessionStorage-мок, как было раньше.
$eportaCartCount = 0;
if (\Bitrix\Main\Loader::includeModule("sale")) {
	$dbEportaBasket = \CSaleBasket::GetList(
		array("ID" => "ASC"),
		array(
			"FUSER_ID" => \CSaleBasket::GetBasketUserID(),
			"LID" => SITE_ID,
			"ORDER_ID" => "NULL",
		),
		false,
		false,
		array("QUANTITY")
	);
	while ($arEportaBasketItem = $dbEportaBasket->Fetch()) {
		$eportaCartCount += (int)$arEportaBasketItem["QUANTITY"];
	}
}

// Телефон/почта: параметры шаблона (.parameters.php), с фолбэком на dw.deluxe.
// Заодно берём IBLOCK_ID каталога у dw.deluxe — тот же источник, что /search/index.php
// использует для dresscode:search, нужен для подсказок поиска в шапке (act=search).
$templatePhone = $arParams["TEMPLATE_TELEPHONE_1"] ?? "+7 (495) 120-11-38";
$templateEmail = $arParams["TEMPLATE_EMAIL_1"] ?? "info@eporta.ru";
$eportaSearchIblockId = 0;
if (\Bitrix\Main\Loader::includeModule("dw.deluxe")) {
	$arTemplateSettings = \DwSettings::getInstance()->getCurrentSettings();
	if (!empty($arTemplateSettings["TEMPLATE_PRODUCT_IBLOCK_ID"])) {
		$eportaSearchIblockId = (int)$arTemplateSettings["TEMPLATE_PRODUCT_IBLOCK_ID"];
	}
	if (empty($arParams["TEMPLATE_TELEPHONE_1"]) && !empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"])) {
		$templatePhone = $arTemplateSettings["TEMPLATE_TELEPHONE_1"];
	}
	if (empty($arParams["TEMPLATE_TELEPHONE_1"]) && !empty($arTemplateSettings["TEMPLATE_EMAIL_1"])) {
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
	<form class="header-search" action="/search/" method="get" autocomplete="off" data-iblock="<?= $eportaSearchIblockId ?>">
		<span class="ico">⌕</span>
		<input type="text" name="q" id="headerSearchInput" placeholder="Поиск двери по названию, стилю, цвету…" autocomplete="off">
		<div class="header-search-suggest" id="headerSearchSuggest"></div>
	</form>
	<div class="header-phone">
		<strong><?= htmlspecialcharsbx($templatePhone) ?></strong>
		<a href="#">Заказать звонок</a>
	</div>
	<div class="header-actions">
		<a href="<?= $USER->IsAuthorized() ? "/personal/" : "/auth/" ?>" class="btn-account" aria-label="Личный кабинет">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"></path></svg>
		</a>
		<a href="/compare/" class="compare-btn" aria-label="Сравнение">
			<span class="icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v14a2 2 0 0 0 2 2h9"></path><path d="M3 8h9a2 2 0 0 1 2 2v11"></path></svg>
			</span>
			<span class="badge"><?= $eportaCompareCount ?></span>
		</a>
		<a href="/personal/cart/" class="cart-btn" aria-label="Корзина">
			<span class="icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.4 12.2a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 8H6"></path><circle cx="9.5" cy="20" r="1.4"></circle><circle cx="17.5" cy="20" r="1.4"></circle></svg>
			</span>
			<span class="badge"><?= $eportaCartCount ?></span>
		</a>
	</div>
</div>

<?php
// Активный пункт навигации по текущему URL (раньше "active" был захардкожен на "Каталог" —
// подсвечивался всегда, даже на страницах коллекций). Коллекции живут и на /collection/ (хаб),
// и на /catalog/collections/<code>/ (SEF-страница конкретной коллекции внутри того же каталога,
// см. project_phase_b_data_map, ЭТАП 5) — это самый специфичный префикс, проверяем его первым.
$eportaCurPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$eportaNavCollections = str_starts_with($eportaCurPath, "/collection/") || str_starts_with($eportaCurPath, "/catalog/collections/");
$eportaNavCatalog = !$eportaNavCollections && str_starts_with($eportaCurPath, "/catalog/");
?>
<!-- Навигация -->
<div class="cat-nav">
	<a href="/catalog/" class="<?= $eportaNavCatalog ? "active" : "" ?>">Каталог</a>
	<a href="/collection/" class="<?= $eportaNavCollections ? "active" : "" ?>">Коллекции</a>
	<span class="nav-item nav-sale"><span class="dot-red"></span>Распродажа</span>
	<span class="nav-item nav-new">Новинки<span class="badge-new">NEW</span></span>
	<span class="spacer"></span>
	<span class="sale-badge"><span class="dot"></span>Акция: рассрочка 0%</span>
</div>
