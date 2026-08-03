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

// Сайт ещё не готов к публичному запуску (данные/контакты не окончательные).
// Без dev_preview-куки/параметра — заглушка "в разработке" вместо реального контента.
// У кого кука уже стоит (команда) — видят обычный сайт как раньше.
$eportaDevAccess = ($_COOKIE["dev_preview"] ?? "") === "x7Qm2pR9vL" || ($_REQUEST["dev_preview"] ?? "") === "x7Qm2pR9vL";
if (!$eportaDevAccess) {
	header("HTTP/1.1 503 Service Unavailable");
	header("Retry-After: 3600");
	header("X-Robots-Tag: noindex, nofollow");
	?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Сайт готовится к запуску — Eporta</title>
<style>
	html,body{height:100%;margin:0;}
	body{
		display:flex;align-items:center;justify-content:center;
		min-height:100vh;padding:24px;box-sizing:border-box;
		background:#0f0f10;color:#f2f0ec;
		font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
		text-align:center;
	}
	.wrap{max-width:520px;}
	h1{font-size:26px;font-weight:600;margin:0 0 12px;}
	p{font-size:15px;line-height:1.5;color:#b7b3ac;margin:0;}
</style>
</head>
<body>
	<div class="wrap">
		<h1>Сайт готовится к запуску</h1>
		<p>Мы обновляем каталог и контактные данные. Загляните чуть позже — скоро здесь будет новый сайт Eporta.</p>
	</div>
</body>
</html>
<?php
	die();
}

// Мега-меню "Каталог": пункты "По стилю"/"По покрытию" ведут на /catalog/ с реальным фильтром
// (?style[]=ID / ?coating[]=ID), "Тип дверей" — на /catalog/?category=<key> (см.
// inc/categories.php). Меню строится на клиенте (assets/app.js), где enum-ID свойств
// недоступны — поэтому резолвим их здесь и прокидываем как window.EPORTA_MEGAMENU.
\Bitrix\Main\Loader::includeModule("iblock");
require_once($_SERVER["DOCUMENT_ROOT"]."/local/templates/eporta/inc/categories.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/local/templates/eporta/inc/buyer_info_pages.php");
function eportaResolveEnumMap($code) {
	$map = [];
	$rs = \CIBlockPropertyEnum::GetList(["SORT" => "ASC"], ["IBLOCK_ID" => 19, "CODE" => $code]);
	while ($e = $rs->Fetch()) { $map[$e["VALUE"]] = (int)$e["ID"]; }
	return $map;
}
$eportaStyleEnum = eportaResolveEnumMap("STYLE");
$eportaCoatingEnum = eportaResolveEnumMap("COATING");
$eportaMegaMenuConfig = [
	"style" => [
		"Модерн / хай-тек" => $eportaStyleEnum["Модерн"] ?? ($eportaStyleEnum["Хай-тек"] ?? null),
		"Классика" => $eportaStyleEnum["Классика"] ?? null,
		"Лофт" => $eportaStyleEnum["Лофт"] ?? null,
		"Скандинавский" => $eportaStyleEnum["Скандинавский"] ?? null,
	],
	"coating" => [
		"Экошпон" => $eportaCoatingEnum["Экошпон"] ?? null,
		"Эмаль" => $eportaCoatingEnum["Эмаль"] ?? null,
		// "Эмалит" в справочнике COATING отсутствует (нет такого значения у товаров) — ссылка
		// без фильтра, пока в выгрузке не появится соответствующий вариант.
		"Эмалит" => null,
		"Натуральный шпон" => $eportaCoatingEnum["Шпон натуральный"] ?? null,
	],
	"category" => [
		"Межкомнатные" => "mkd",
		"Раздвижные перегородки" => "sliding",
		"Входные" => "entrance",
		"Фурнитура" => "hardware",
	],
];

$asset = Asset::getInstance();
$asset->addString('<script>window.EPORTA_MEGAMENU = '.json_encode($eportaMegaMenuConfig, JSON_UNESCAPED_UNICODE).';</script>');

// Сайт-wide CSRF-токен для ajax.php (dw.deluxe, act=addCart/addCompare/compDEL/clearCompare —
// все требуют check_bitrix_sessid() и id строго через POST-тело, не GET). compare.js раньше
// слал обычный GET без токена — эндпоинт молча не срабатывал (сессия просто не совпадала).
$asset->addString('<script>window.BX_SESSID = '.json_encode(bitrix_sessid(), JSON_UNESCAPED_SLASHES).';</script>');
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
		<?php
		// Без явного backurl вход через /auth/ всегда возвращал на дефолтный экран ("Вы
		// авторизованы" -> кнопка "В личный кабинет"), а не туда, откуда пользователь пришёл
		// логиниться — раздражало на страницах каталога/товара. auth/index.php уже умеет
		// редиректить на backurl из запроса (см. его JS), не хватало только передать его сюда.
		$eportaAuthHref = "/auth/?backurl=".urlencode($_SERVER["REQUEST_URI"]);
		?>
		<a href="<?= $USER->IsAuthorized() ? "/personal/" : $eportaAuthHref ?>" class="btn-account" aria-label="Личный кабинет">
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
$eportaNavSale = !empty($_GET["sale"]);
$eportaNavNew = !empty($_GET["new"]);
$eportaNavCollections = str_starts_with($eportaCurPath, "/collection/") || str_starts_with($eportaCurPath, "/catalog/collections/");
$eportaNavCatalog = !$eportaNavCollections && !$eportaNavSale && !$eportaNavNew && str_starts_with($eportaCurPath, "/catalog/");
?>
<!-- Навигация -->
<div class="cat-nav">
	<a href="/catalog/" class="<?= $eportaNavCatalog ? "active" : "" ?>">Каталог</a>
	<a href="/collection/" class="<?= $eportaNavCollections ? "active" : "" ?>">Коллекции</a>
	<a href="/catalog/?sale=1" class="nav-item nav-sale<?= $eportaNavSale ? " active" : "" ?>"><span class="dot-red"></span>Распродажа</a>
	<a href="/catalog/?new=1" class="nav-item nav-new<?= $eportaNavNew ? " active" : "" ?>">Новинки<span class="badge-new">NEW</span></a>
	<span class="spacer"></span>
	<div class="buyer-nav" id="buyerNav">
		<button type="button" class="buyer-nav-toggle" id="buyerNavToggle">Покупателю <i class="buyer-nav-arrow"></i></button>
		<div class="buyer-nav-dropdown" id="buyerNavDropdown">
			<?php foreach (eportaBuyerInfoPages() as $eportaBuyerPage): ?>
				<a href="<?= htmlspecialcharsbx($eportaBuyerPage["href"]) ?>"<?= $eportaCurPath === $eportaBuyerPage["href"] ? ' class="active"' : "" ?>><?= htmlspecialcharsbx($eportaBuyerPage["label"]) ?></a>
			<?php endforeach; ?>
		</div>
	</div>
</div>
