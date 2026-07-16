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
			<button onclick="addCompare(event, <?= (int)$arResult["ID"] ?>); this.blur()" title="Сравнить" style="flex:none;width:50px;background:#fff;border:1.6px solid #e7e3db;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#a39e95;cursor:pointer">⇄</button>
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

<?php require $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . '/include/kit-modal.php'; ?>

<!-- Тост -->
<div class="toast" id="toast"></div>

<?php
// Cache-busting по mtime: nginx отдаёт /assets/*.js как immutable без версионирования
// (project_deploy_cache_layers) — без ?v=... браузер не подхватывает правки kit.js после деплоя.
$eportaKitJsPath = $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . "/assets/kit.js";
?>
<script src="<?=SITE_TEMPLATE_PATH?>/assets/kit.js?v=<?=@filemtime($eportaKitJsPath) ?: time()?>"></script>
<script>
// ---- Реальная цена товара (Этап 2, Фаза B) ----
var DOOR_PRICE = <?= (int)$priceValue ?>;
var DOOR_FIT = 'eco'; // мок: реальный подбор комплектующих по типу двери (SKU) на проде пока не заполнен
var addonTotal = 0;
var ADD_TO_BASKET_URL = <?= $addToBasketUrl ? json_encode($addToBasketUrl, JSON_UNESCAPED_SLASHES) : "null" ?>;
var DOOR_NAME = <?= json_encode($arResult["NAME"], JSON_UNESCAPED_UNICODE) ?>;
var DOOR_PRICE_LABEL = <?= json_encode($price ? ($price["PRINT_DISCOUNT_VALUE"] ?? $price["PRINT_VALUE"]) : "", JSON_UNESCAPED_UNICODE) ?>;
var productKitSelection = {}; // сохраняем выбор допов между открытиями модалки на этой странице

function fmtPrice(n) { return KitModal.fmtPrice(n); }

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

function showToast(msg, withCartLink) {
	var t = document.getElementById('toast');
	t.innerHTML = '<span>' + msg + '</span>' + (withCartLink ? '<a href="/personal/cart/" class="toast-btn">В корзину →</a>' : '');
	t.classList.add('show');
	clearTimeout(showToast._timer);
	showToast._timer = setTimeout(function(){ t.classList.remove('show'); }, 3500);
}

// Общий конструктор допов вынесен в assets/kit.js (KitModal) — переиспользуется
// и в корзине (см. assets/cart-kit.js). Здесь только колбэки контекста "карточка товара".
function onProductKitChange(selection, addonsTotal, total) {
	addonTotal = addonsTotal;
	productKitSelection = selection;
	document.getElementById('ctaPrice').textContent = fmtPrice(total);
	var cnt = Object.keys(selection).filter(function(id){ return selection[id] > 0; }).length;
	if (cnt > 0) {
		document.getElementById('ktLabel').textContent = 'Комплект собран · ' + cnt + ' ' + KitModal.plural(cnt,'доп.','доп.','доп.');
		document.getElementById('ktSub').textContent = '+' + fmtPrice(addonsTotal) + ' к стоимости';
	} else {
		document.getElementById('ktLabel').textContent = 'Собрать комплект';
		document.getElementById('ktSub').textContent = 'Коробка, наличники, петли, ручки и другое';
	}
}

function openKit() {
	KitModal.open({
		key: 'main',
		doorPrice: DOOR_PRICE,
		doorFit: DOOR_FIT,
		title: DOOR_NAME,
		priceLabel: DOOR_PRICE_LABEL,
		ctaLabel: 'В корзину',
		initialSelection: productKitSelection,
		onChange: onProductKitChange,
		onSubmit: addKitToCart
	});
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
			eportaCartBadge(eportaCartCount() + 1);
			showToast('Дверь добавлена в корзину · ' + document.getElementById('ctaPrice').textContent, true);
		})
		.catch(function(err) {
			btn.style.pointerEvents = '';
			showToast((err && err.message) || 'Не удалось добавить в корзину', false);
		});
}

// Комплектация (допы) пока мок без реальных Bitrix-товаров (см. комментарий в
// assets/kit.js над ADDON_POOL) — в корзину реально уходит только полотно двери,
// допы в неё не попадают до появления настоящих SKU/услуг на проде.
function addKitToCart(selection, addonsTotal, total) {
	if (!ADD_TO_BASKET_URL) {
		showToast('Не удалось добавить в корзину', false);
		return;
	}
	fetch(ADD_TO_BASKET_URL, { credentials: 'same-origin' })
		.then(function(resp) { return resp.json(); })
		.then(function(data) {
			if (data.STATUS !== 'OK') throw new Error(data.MESSAGE || 'add failed');
			eportaCartBadge(eportaCartCount() + 1);
			showToast('Комплект добавлен в корзину · ' + fmtPrice(total), true);
		})
		.catch(function(err) {
			showToast((err && err.message) || 'Не удалось добавить в корзину', false);
		});
}
</script>
