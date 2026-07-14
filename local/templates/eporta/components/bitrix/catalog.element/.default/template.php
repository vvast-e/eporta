<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var array $arParams */

$placeholderImg = SITE_TEMPLATE_PATH . "/assets/img/hit-1.jpg";

if (!function_exists("plural")) {
	function plural($n, $one, $few, $many) {
		$m = abs($n) % 100;
		$m1 = $m % 10;
		if ($m > 10 && $m < 20) return $many;
		if ($m1 > 1 && $m1 < 5) return $few;
		if ($m1 === 1) return $one;
		return $many;
	}
}

// Свойство: сначала DISPLAY_PROPERTIES (человекочитаемое значение списка), иначе сырое VALUE.
if (!function_exists("eportaPropText")) {
function eportaPropText($arResult, $code) {
	if (!empty($arResult["DISPLAY_PROPERTIES"][$code]["DISPLAY_VALUE"])) {
		$v = $arResult["DISPLAY_PROPERTIES"][$code]["DISPLAY_VALUE"];
		return is_array($v) ? implode(", ", $v) : (string)$v;
	}
	if (!empty($arResult["PROPERTIES"][$code]["VALUE_ENUM"])) {
		$v = $arResult["PROPERTIES"][$code]["VALUE_ENUM"];
		return is_array($v) ? implode(", ", $v) : (string)$v;
	}
	if (!empty($arResult["PROPERTIES"][$code]["VALUE"])) {
		$v = $arResult["PROPERTIES"][$code]["VALUE"];
		return is_array($v) ? implode(", ", $v) : (string)$v;
	}
	return "";
}
}

$rating = (float)eportaPropText($arResult, "RATING");
$voteCount = (int)eportaPropText($arResult, "VOTE_COUNT");
$ratingRounded = max(0, min(5, (int)round($rating)));
$stars = str_repeat("★", $ratingRounded) . str_repeat("☆", 5 - $ratingRounded);
$isHit = eportaPropText($arResult, "PRODUCT_DAY") !== "";
$article = eportaPropText($arResult, "CML2_ARTICLE");

// Реальное добавление в корзину: официальный compatible-mode механизм
// bitrix:catalog.element (см. ACTION_VARIABLE="action"/PRODUCT_ID_VARIABLE="id"
// в catalog/index.php). ~ADD_URL_TEMPLATE — СЫРОЙ (не HTML-экранированный) URL
// текущей страницы с параметром ?action=ADD2BASKET&id=#ID#; ADD_URL_TEMPLATE
// (без ~) — та же строка, но с &amp; для вставки в HTML-атрибут, для fetch()
// в JS не годится. ajax_basket=Y просим у ядра чистый JSON-ответ
// {"STATUS":"OK"|"ERROR",...} вместо редиректа/полного рендера страницы
// (см. processLinkAction() в bitrix/modules/iblock/lib/component/base.php).
$addToBasketUrl = !empty($arResult["~ADD_URL_TEMPLATE"])
	? str_replace("#ID#", $arResult["ID"], $arResult["~ADD_URL_TEMPLATE"]) . "&ajax_basket=Y"
	: null;

$price = $arResult["PRICES"]["BASE"] ?? null;
$hasDiscount = $price && !empty($price["DISCOUNT_VALUE"]) && $price["DISCOUNT_VALUE"] < $price["VALUE"];
$priceValue = $price["DISCOUNT_VALUE"] ?? $price["VALUE"] ?? 0;
$priceOldValue = $price["VALUE"] ?? 0;

// Галерея: основное фото + MORE_PHOTO (на большинстве товаров — одно доп. фото или ни одного).
$mainPhotoSrc = $arResult["DETAIL_PICTURE"]["SRC"] ?? ($arResult["PREVIEW_PICTURE"]["SRC"] ?? $placeholderImg);
$galleryPhotos = [$mainPhotoSrc];
if (!empty($arResult["MORE_PHOTO"]) && is_array($arResult["MORE_PHOTO"])) {
	foreach ($arResult["MORE_PHOTO"] as $morePhoto) {
		$src = $morePhoto["SRC"] ?? null;
		if ($src && !in_array($src, $galleryPhotos, true)) {
			$galleryPhotos[] = $src;
		}
	}
}

// SKU-офферы (цвет/остекление): IBLOCK 54 на проде пока пуст — блок опционален,
// показывается только если у товара реально есть офферы.
$hasOffers = !empty($arResult["OFFERS"]);

// Похожие двери — по тому же разделу, что и товар, исключая сам товар.
$eportaSectionId = $arResult["IBLOCK_SECTION_ID"] ?? ($arResult["SECTION"]["ID"] ?? false);
global $arrFilterEportaSimilar;
$arrFilterEportaSimilar = ["!ID" => $arResult["ID"]];
?>

<div style="display:flex;gap:30px;padding:14px 56px 30px;align-items:flex-start">

	<!-- Галерея -->
	<div style="flex:1.15;display:flex;flex-direction:column;gap:12px;height:560px">
		<div style="position:relative;flex:1;min-height:0">
			<img id="mainPhoto" src="<?= htmlspecialcharsbx($galleryPhotos[0]) ?>" style="width:100%;height:100%;object-fit:contain;background:#f6f4ef;border-radius:16px" alt="<?= htmlspecialcharsbx($arResult["NAME"]) ?>">
			<?php if ($isHit): ?><span style="position:absolute;top:14px;left:14px;background:#e8820a;color:#fff;font:700 11px 'Manrope';padding:6px 12px;border-radius:7px">ХИТ ПРОДАЖ</span><?php endif; ?>
		</div>
		<?php if (count($galleryPhotos) > 1): ?>
		<div style="display:flex;gap:12px;flex:none;height:96px">
			<?php foreach ($galleryPhotos as $i => $photoSrc): ?>
				<img src="<?= htmlspecialcharsbx($photoSrc) ?>" class="thumb<?= $i === 0 ? " active-thumb" : "" ?>" onclick="changePhoto(this)" style="width:96px;height:96px;object-fit:contain;background:#f6f4ef;border-radius:10px;border:<?= $i === 0 ? "2px solid #e8820a" : "1.5px solid #e7e3db" ?>;cursor:pointer" alt="">
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>

	<!-- Панель покупки -->
	<div style="flex:1;align-self:flex-start">
		<h1 style="margin:0 0 8px;font:800 24px/1.2 'Manrope';letter-spacing:-0.01em"><?= htmlspecialcharsbx($arResult["NAME"]) ?></h1>
		<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
			<?php if ($voteCount > 0): ?>
				<span style="color:#e8820a;font-size:13px;letter-spacing:1px"><?= $stars ?></span>
				<span style="font:600 13px;color:#5f5a51"><?= number_format($rating, 1, ".", "") ?> · <?= $voteCount ?> <?= plural($voteCount, "отзыв", "отзыва", "отзывов") ?></span>
			<?php else: ?>
				<span style="font:600 13px;color:#8a857b">Пока нет отзывов</span>
			<?php endif; ?>
			<span style="font:700 12px;color:#1f8a4c;padding:5px 10px;border-radius:7px;background:#eaf6ee">● В наличии</span>
			<?php if ($article !== ""): ?><span style="font:500 12.5px;color:#b3aea4">арт. <span id="articleNum"><?= htmlspecialcharsbx($article) ?></span></span><?php endif; ?>
		</div>

		<div style="display:flex;align-items:baseline;gap:12px;margin-bottom:4px">
			<?php if ($price): ?>
				<div id="priceBig" style="font:800 40px 'Manrope';letter-spacing:-0.02em"><?= $price["PRINT_DISCOUNT_VALUE"] ?? $price["PRINT_VALUE"] ?></div>
				<?php if ($hasDiscount): ?>
					<div id="oldPrice" style="font:600 16px;color:#a39e95;text-decoration:line-through"><?= $price["PRINT_VALUE"] ?></div>
					<span id="discountBadge" style="font:700 12px;color:#c2670a;background:#fbecd9;padding:5px 9px;border-radius:6px">−<?= round((1 - $priceValue / $priceOldValue) * 100) ?>%</span>
				<?php endif; ?>
			<?php else: ?>
				<div id="priceBig" style="font:800 40px 'Manrope';letter-spacing:-0.02em">по запросу</div>
			<?php endif; ?>
		</div>
		<div style="font:500 13px;color:#8a857b;margin-bottom:16px">цена от фабрики · без розничной наценки</div>

		<?php if ($hasOffers): ?>
		<!-- Цвет и остекление: реальные SKU-офферы -->
		<div style="border-top:1px solid #efece6;border-bottom:1px solid #efece6;padding:14px 0;margin-bottom:14px;display:flex;flex-direction:column;gap:14px">
			<div>
				<div class="swatch-label"><span class="sl-name">Цвет покрытия:</span><span class="sl-val" id="colorLabel"></span></div>
				<div class="swatch-wrap" id="colorSwatches"></div>
			</div>
			<div>
				<div class="swatch-label"><span class="sl-name">Остекление:</span><span class="sl-val" id="glazingLabel"></span></div>
				<div class="swatch-wrap" id="glazingSwatches"></div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Комплектация (kit-modal, статичная JS-модалка без реальных данных — RELATED_PRODUCT/SIMILAR_PRODUCT на проде не заполнены) -->
		<div style="font:700 13px 'Manrope';margin-bottom:8px">Комплектация</div>
		<button class="kit-trigger" onclick="openKit()" id="kitTrigger" style="margin-bottom:18px">
			<div class="kt-icon">+</div>
			<div style="flex:1">
				<div class="kt-label" id="ktLabel">Собрать комплект</div>
				<div class="kt-sub" id="ktSub">Коробка, наличники, петли, ручки и другое</div>
			</div>
			<span class="kt-arrow">›</span>
		</button>

		<button id="ctaBtn" onclick="addMainToCart(event)" style="display:block;width:100%;background:#e8820a;color:#fff;font-weight:800;font-size:18px;text-align:center;padding:17px;border-radius:13px;box-shadow:0 10px 24px rgba(232,130,10,.32);border:none;cursor:pointer">В корзину · <span id="ctaPrice"><?= $price ? ($price["PRINT_DISCOUNT_VALUE"] ?? $price["PRINT_VALUE"]) : "по запросу" ?></span></button>
		<div style="display:flex;gap:10px;margin-top:10px">
			<div style="flex:1;background:#fff;color:#1b1a17;font-weight:700;font-size:14px;text-align:center;padding:13px;border-radius:12px;border:1.6px solid #1b1a17;cursor:pointer">Купить в 1 клик</div>
			<div id="favBtn" onclick="toggleFav()" style="flex:none;width:50px;background:#fff;border:1.6px solid #e7e3db;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#a39e95;cursor:pointer">♡</div>
		</div>

		<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:14px;font:600 12.5px 'Manrope';color:#5f5a51">
			<span style="font:700 12.5px 'Manrope';color:#c2670a;background:#fbecd9;border-radius:8px;padding:7px 11px">Доставка 1–3 дня</span>
			<span>✓ Гарантия 2 года</span><span>✓ Возврат 14 дней</span><span>✓ Замер бесплатно</span>
		</div>
	</div>
</div>

<!-- Описание + характеристики -->
<div style="padding:26px 56px 6px">
	<div style="display:flex;gap:36px">
		<div style="flex:1.1">
			<h2 style="margin:0 0 12px;font:800 20px 'Manrope';letter-spacing:-0.01em">Описание</h2>
			<p style="margin:0;font:400 14.5px/1.6 'Manrope';color:#3a3631"><?php
				$description = trim(strip_tags($arResult["PREVIEW_TEXT"] ?? $arResult["DETAIL_TEXT"] ?? ""));
				echo $description !== "" ? nl2br(htmlspecialcharsbx($description)) : "Описание уточняется у менеджера.";
			?></p>
		</div>
		<div style="flex:1">
			<h2 style="margin:0 0 12px;font:800 20px 'Manrope';letter-spacing:-0.01em">Характеристики</h2>
			<?php
				$specs = [
					"Стиль" => eportaPropText($arResult, "STYLE"),
					"Цвет покрытия" => eportaPropText($arResult, "COATING_COLOR"),
					"Остекление" => eportaPropText($arResult, "GLAZING"),
					"Основной цвет" => eportaPropText($arResult, "MAIN_COLOR"),
				];
			?>
			<?php foreach ($specs as $specName => $specValue): if ($specValue === "") continue; ?>
				<div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #efece6"><span style="font:500 13.5px;color:#8a857b"><?= htmlspecialcharsbx($specName) ?></span><span style="font:600 13.5px 'Manrope'"><?= htmlspecialcharsbx($specValue) ?></span></div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<?php if ($eportaSectionId): ?>
<!-- Похожие двери: тот же раздел IBLOCK 19, исключая текущий товар -->
<div style="padding:24px 56px 36px">
	<h2 style="margin:0 0 16px;font:800 20px 'Manrope';letter-spacing:-0.01em">Похожие двери</h2>
	<?$APPLICATION->IncludeComponent(
		"bitrix:catalog.section",
		".default",
		[
			"IBLOCK_TYPE" => "catalog",
			"IBLOCK_ID" => "19",
			"SECTION_ID" => $eportaSectionId,
			"SECTION_CODE" => "",
			"ELEMENT_SORT_FIELD" => "sort",
			"ELEMENT_SORT_ORDER" => "asc",
			"ELEMENT_SORT_FIELD2" => "id",
			"ELEMENT_SORT_ORDER2" => "desc",
			"FILTER_NAME" => "arrFilterEportaSimilar",
			"HIDE_NOT_AVAILABLE" => "N",
			"PAGE_ELEMENT_COUNT" => "4",
			"LINE_ELEMENT_COUNT" => "4",
			"PROPERTY_CODE" => ["RATING", "VOTE_COUNT", "PRODUCT_DAY"],
			"PRICE_CODE" => ["BASE"],
			"USE_PRICE_COUNT" => "N",
			"PRICE_VAT_INCLUDE" => "Y",
			"CONVERT_CURRENCY" => "N",
			"BASKET_URL" => "/personal/cart/",
			"ACTION_VARIABLE" => "action",
			"PRODUCT_ID_VARIABLE" => "id",
			"PRODUCT_QUANTITY_VARIABLE" => "quantity",
			"CACHE_TYPE" => "A",
			"CACHE_TIME" => "3600",
			"SET_TITLE" => "N",
			"SEF_MODE" => "N",
			"DISPLAY_TOP_PAGER" => "N",
			"DISPLAY_BOTTOM_PAGER" => "N",
			"COMPATIBLE_MODE" => "Y",
			"AJAX_MODE" => "N",
			"TEMPLATE_THEME" => "site",
		],
		false
	);?>
</div>
<?php endif; ?>

<!-- ======= Модалка конструктора (комплектация) ======= -->
<div class="kit-overlay" id="kitOverlay" onclick="closeKit()">
	<div class="kit-modal" onclick="event.stopPropagation()">
		<div class="kit-mhead">
			<div>
				<h2>Собрать комплект</h2>
				<div class="sub"><?= htmlspecialcharsbx($arResult["NAME"]) ?> · <span id="kitDoorPriceLabel"><?= $price ? ($price["PRINT_DISCOUNT_VALUE"] ?? $price["PRINT_VALUE"]) : "" ?></span> · выберите нужные позиции</div>
			</div>
			<button class="kit-close" onclick="closeKit()">✕</button>
		</div>
		<div class="kit-body">
			<div class="kit-tabs" id="kitTabs"></div>
			<div class="kit-panel">
				<div class="kit-search">
					<input type="text" id="kitSearchInput" placeholder="Поиск по названию…" oninput="renderItems()">
					<span class="scnt" id="kitSearchCount"></span>
				</div>
				<div class="kit-items" id="kitItemsList"></div>
			</div>
		</div>
		<div class="kit-foot">
			<div>
				<div class="total-info" id="kitTotalInfo">Полотно · допы не выбраны</div>
				<div class="total-price" id="kitTotalPrice"></div>
			</div>
			<button class="kit-cta" onclick="addKitToCart()">В корзину · <span id="kitCtaPrice"></span></button>
		</div>
	</div>
</div>

<!-- Тост -->
<div class="toast" id="toast"></div>

<script>
// ---- Реальная цена товара (Этап 2, Фаза B) ----
var DOOR_PRICE = <?= (int)$priceValue ?>;
var addonTotal = 0;
var ADD_TO_BASKET_URL = <?= $addToBasketUrl ? json_encode($addToBasketUrl, JSON_UNESCAPED_SLASHES) : "null" ?>;

function fmtPrice(n) { return n.toLocaleString('ru-RU') + ' ₽'; }

function changePhoto(img) {
	document.getElementById('mainPhoto').src = img.src;
	document.querySelectorAll('.thumb').forEach(function(t){ t.style.border = '1.5px solid #e7e3db'; t.classList.remove('active-thumb'); });
	img.style.border = '2px solid #e8820a';
	img.classList.add('active-thumb');
}

var isFav = false;
function toggleFav() {
	isFav = !isFav;
	var btn = document.getElementById('favBtn');
	btn.textContent = isFav ? '♥' : '♡';
	btn.style.color = isFav ? '#e8820a' : '#a39e95';
	showToast(isFav ? 'Добавлено в избранное' : 'Удалено из избранного');
}

function updateTotals() {
	document.getElementById('ctaPrice').textContent = fmtPrice(DOOR_PRICE + addonTotal);
}

function showToast(msg, withCartLink) {
	var t = document.getElementById('toast');
	t.innerHTML = '<span>' + msg + '</span>' + (withCartLink ? '<a href="/personal/cart/" class="toast-btn">В корзину →</a>' : '');
	t.classList.add('show');
	clearTimeout(showToast._timer);
	showToast._timer = setTimeout(function(){ t.classList.remove('show'); }, 3500);
}

// ---- Конструктор комплекта: статичный мок, без реального источника (Этап 2 — RELATED_PRODUCT/SIMILAR_PRODUCT пусты на проде) ----
var DOOR_FIT = 'eco';
var ADDON_CATEGORIES = [
	{ key:'frame',  name:'Коробка',              mode:'multi',  hint:'по числу проёмов' },
	{ key:'casing', name:'Наличники',            mode:'multi',  hint:'комплект на проём' },
	{ key:'ext',    name:'Доборы',               mode:'multi',  hint:'для широких стен' },
	{ key:'hinge',  name:'Петли',                mode:'multi',  hint:'' },
	{ key:'lock',   name:'Замки',                mode:'single', hint:'один на полотно' },
	{ key:'handle', name:'Ручки',                mode:'single', hint:'один комплект' },
	{ key:'stop',   name:'Упоры и ограничители', mode:'single', hint:'' }
];
var ADDON_POOL = [
	{ id:'fr-eco',    cat:'frame',  fit:'eco',  name:'Коробка телескопическая, экошпон в тон', sub:'МДФ · кромка ПВХ', price:1980 },
	{ id:'fr-eco2',   cat:'frame',  fit:'eco',  name:'Коробка прямая, экошпон в тон', sub:'фиксированная ширина', price:1490 },
	{ id:'fr-emal',   cat:'frame',  fit:'emal', name:'Коробка эмаль, под покраску', sub:'массив сосны + МДФ', price:2680 },
	{ id:'fr-steel',  cat:'frame',  fit:'dark', name:'Коробка стальная усиленная', sub:'для входной группы', price:5900 },
	{ id:'cs-tele',   cat:'casing', fit:'eco',  name:'Наличник телескопический, экошпон', sub:'комплект 5 шт · 2.2 м', price:1240 },
	{ id:'cs-flat',   cat:'casing', fit:'eco',  name:'Наличник плоский 70 мм, экошпон', sub:'комплект 5 шт · 2.2 м', price:960 },
	{ id:'cs-emal',   cat:'casing', fit:'emal', name:'Наличник фигурный, эмаль', sub:'комплект 5 шт · 2.2 м', price:1680 },
	{ id:'ex-100',    cat:'ext',    fit:'eco',  name:'Добор 100 мм, экошпон в тон', sub:'2 шт · стены до 120 мм', price:1120 },
	{ id:'ex-150',    cat:'ext',    fit:'eco',  name:'Добор 150 мм, экошпон в тон', sub:'2 шт · стены до 180 мм', price:1460 },
	{ id:'ex-emal',   cat:'ext',    fit:'emal', name:'Добор 100 мм, эмаль', sub:'2 шт · под покраску', price:1590 },
	{ id:'hg-univ',   cat:'hinge',  fit:'all',  name:'Петли универсальные, чёрные', sub:'пара · врезные 100 мм', price:640 },
	{ id:'hg-hidden', cat:'hinge',  fit:'all',  name:'Петли скрытые AGB', sub:'пара · регулировка 3D', price:2340 },
	{ id:'hg-brass',  cat:'hinge',  fit:'all',  name:'Петли латунь, бронза', sub:'пара · декоративные', price:1180 },
	{ id:'lk-mag',    cat:'lock',   fit:'all',  name:'Замок магнитный Morelli, чёрный', sub:'бесшумный', price:1890 },
	{ id:'lk-cyl',    cat:'lock',   fit:'all',  name:'Замок под цилиндр MC85BL', sub:'с ключом', price:2100 },
	{ id:'lk-wc',     cat:'lock',   fit:'all',  name:'Защёлка WC для санузла', sub:'с фиксатором', price:820 },
	{ id:'hn-black',  cat:'handle', fit:'all',  name:'Ручка Fimet, чёрный матовый', sub:'на розетке', price:2340 },
	{ id:'hn-nickel', cat:'handle', fit:'all',  name:'Ручка Morelli, никель', sub:'на планке', price:1980 },
	{ id:'hn-brass',  cat:'handle', fit:'all',  name:'Ручка Colombo, бронза', sub:'премиум', price:4650 },
	{ id:'hn-chrome', cat:'handle', fit:'all',  name:'Ручка Fuaro, хром глянец', sub:'на розетке', price:1460 },
	{ id:'st-floor',  cat:'stop',   fit:'all',  name:'Ограничитель напольный, чёрный', sub:'магнитный', price:480 },
	{ id:'st-wall',   cat:'stop',   fit:'all',  name:'Упор настенный, чёрный', sub:'силиконовый', price:290 }
];
var ADDON_FIT = { eco:['eco','all'], emal:['emal','all'], dark:['dark','eco','all'] };
var activeCat = 'frame';
var selection = {};

function getCompatible() { var fits = ADDON_FIT[DOOR_FIT] || ['all']; return ADDON_POOL.filter(function(a){ return fits.indexOf(a.fit) >= 0; }); }
function getCatItems(catKey) { return getCompatible().filter(function(a){ return a.cat === catKey; }); }
function plural(n, a, b, c) { var m = Math.abs(n) % 100, m1 = m % 10; if (m > 10 && m < 20) return c; if (m1 > 1 && m1 < 5) return b; if (m1 === 1) return a; return c; }
function calcAddonTotal() { var sum = 0; Object.keys(selection).forEach(function(id){ var item = ADDON_POOL.find(function(a){ return a.id === id; }); if (item) sum += item.price * selection[id]; }); return sum; }
function countCatSelected(catKey) {
	var cat = ADDON_CATEGORIES.find(function(c){ return c.key === catKey; });
	if (!cat) return 0;
	if (cat.mode === 'single') return getCatItems(catKey).some(function(a){ return selection[a.id]; }) ? 1 : 0;
	return getCatItems(catKey).reduce(function(s, a){ return s + (selection[a.id] || 0); }, 0);
}

function renderTabs() {
	var html = '';
	ADDON_CATEGORIES.forEach(function(cat){
		var cnt = countCatSelected(cat.key);
		var isActive = cat.key === activeCat;
		html += '<div class="kit-tab' + (isActive ? ' active' : '') + '" onclick="switchCat(\'' + cat.key + '\')">' +
			'<div><div class="tname">' + cat.name + '</div>' +
			(cat.hint ? '<div class="thint">' + cat.hint + '</div>' : '') + '</div>' +
			(cnt > 0 ? '<div class="tbadge">' + cnt + '</div>' : '') +
			'</div>';
	});
	document.getElementById('kitTabs').innerHTML = html;
}

function renderItems() {
	var cat = ADDON_CATEGORIES.find(function(c){ return c.key === activeCat; });
	var items = getCatItems(activeCat);
	var q = (document.getElementById('kitSearchInput').value || '').toLowerCase().trim();
	if (q) items = items.filter(function(a){ return (a.name + a.sub).toLowerCase().indexOf(q) >= 0; });
	document.getElementById('kitSearchCount').textContent = items.length + ' ' + plural(items.length,'вариант','варианта','вариантов');
	if (!items.length) {
		document.getElementById('kitItemsList').innerHTML = '<div class="kit-empty">По запросу ничего не найдено<br><span style="font-size:12px;margin-top:6px;display:block">Попробуйте другой запрос</span></div>';
		return;
	}
	var html = '';
	items.forEach(function(item){
		var qty = selection[item.id] || 0;
		var sel = qty > 0;
		html += '<div class="kit-row' + (sel ? ' selected' : '') + '" id="row-' + item.id + '">';
		if (cat.mode === 'single') {
			html += '<span class="rmark">' + (sel ? '✓' : '') + '</span>';
			html += '<div style="flex:1;min-width:0" onclick="toggleSingle(\'' + item.id + '\')">' +
				'<div class="rname">' + item.name + '</div>' +
				'<div class="rsub">' + item.sub + '</div></div>';
			html += '<div class="rprice" onclick="toggleSingle(\'' + item.id + '\')">+' + fmtPrice(item.price) + '</div>';
		} else {
			html += '<div style="flex:1;min-width:0;cursor:pointer" onclick="toggleMulti(\'' + item.id + '\')">' +
				'<div class="rname">' + item.name + '</div>' +
				'<div class="rsub">' + item.sub + '</div></div>';
			html += '<div class="rprice" style="margin-right:10px">+' + fmtPrice(item.price) + '</div>';
			if (sel) {
				html += '<div class="kit-stepper">' +
					'<button onclick="changeQtyAddon(\'' + item.id + '\',-1)">−</button>' +
					'<span>' + qty + '</span>' +
					'<button onclick="changeQtyAddon(\'' + item.id + '\',1)">+</button>' +
					'</div>';
			} else {
				html += '<div style="width:34px;height:34px;border-radius:9px;background:#f4f1ea;color:#8a857b;display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer" onclick="toggleMulti(\'' + item.id + '\')">+</div>';
			}
		}
		html += '</div>';
	});
	document.getElementById('kitItemsList').innerHTML = html;
}

function updateKitFooter() {
	var addons = calcAddonTotal();
	var total = DOOR_PRICE + addons;
	var cnt = Object.keys(selection).filter(function(id){ return selection[id] > 0; }).length;
	var info = cnt > 0 ? 'Полотно + ' + cnt + ' ' + plural(cnt,'доп.','доп.','доп.') : 'Полотно · допы не выбраны';
	document.getElementById('kitTotalInfo').textContent = info;
	document.getElementById('kitTotalPrice').textContent = fmtPrice(total);
	document.getElementById('kitCtaPrice').textContent = fmtPrice(total);
	addonTotal = addons;
	updateTotals();
	if (cnt > 0) {
		document.getElementById('ktLabel').textContent = 'Комплект собран · ' + cnt + ' ' + plural(cnt,'доп.','доп.','доп.');
		document.getElementById('ktSub').textContent = '+' + fmtPrice(addons) + ' к стоимости';
	} else {
		document.getElementById('ktLabel').textContent = 'Собрать комплект';
		document.getElementById('ktSub').textContent = 'Коробка, наличники, петли, ручки и другое';
	}
}

function toggleSingle(id) {
	getCatItems(activeCat).forEach(function(a){ delete selection[a.id]; });
	if (!selection[id]) selection[id] = 1;
	renderItems(); renderTabs(); updateKitFooter();
}
function toggleMulti(id) {
	if (selection[id]) delete selection[id]; else selection[id] = 1;
	renderItems(); renderTabs(); updateKitFooter();
}
function changeQtyAddon(id, delta) {
	selection[id] = (selection[id] || 0) + delta;
	if (selection[id] <= 0) delete selection[id];
	renderItems(); renderTabs(); updateKitFooter();
}
function switchCat(key) {
	activeCat = key;
	document.getElementById('kitSearchInput').value = '';
	renderTabs(); renderItems();
}
function openKit() {
	document.getElementById('kitOverlay').classList.add('open');
	document.body.style.overflow = 'hidden';
	renderTabs(); renderItems(); updateKitFooter();
}
function closeKit() {
	document.getElementById('kitOverlay').classList.remove('open');
	document.body.style.overflow = '';
}

// Реальное добавление в корзину (Этап 4): фоновый запрос по официальному
// compatible-mode URL компонента — тот же путь, что при обычном переходе по
// ссылке с ?action=ADD2BASKET&id=..., только без перезагрузки страницы.
function addMainToCart(e) {
	e.preventDefault();
	if (!ADD_TO_BASKET_URL) {
		showToast('Не удалось добавить в корзину', false);
		return;
	}
	var btn = document.getElementById('ctaBtn');
	btn.style.pointerEvents = 'none';
	fetch(ADD_TO_BASKET_URL, { credentials: 'same-origin' })
		.then(function(resp) { return resp.json(); })
		.then(function(data) {
			btn.style.pointerEvents = '';
			if (data.STATUS !== 'OK') throw new Error(data.MESSAGE || 'add failed');
			showToast('Дверь добавлена в корзину · ' + document.getElementById('ctaPrice').textContent, true);
		})
		.catch(function(err) {
			btn.style.pointerEvents = '';
			showToast((err && err.message) || 'Не удалось добавить в корзину', false);
		});
}

// Комплектация (допы) пока мок без реальных Bitrix-товаров (см. коммент выше
// ADDON_POOL) — в корзину реально уходит только полотно двери, допы в неё не
// попадают до появления настоящих SKU/услуг на проде.
function addKitToCart() {
	var total = DOOR_PRICE + calcAddonTotal();
	closeKit();
	if (!ADD_TO_BASKET_URL) {
		showToast('Не удалось добавить в корзину', false);
		return;
	}
	fetch(ADD_TO_BASKET_URL, { credentials: 'same-origin' })
		.then(function(resp) { return resp.json(); })
		.then(function(data) {
			if (data.STATUS !== 'OK') throw new Error(data.MESSAGE || 'add failed');
			showToast('Комплект добавлен в корзину · ' + fmtPrice(total), true);
		})
		.catch(function(err) {
			showToast((err && err.message) || 'Не удалось добавить в корзину', false);
		});
}

document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeKit(); });
</script>
