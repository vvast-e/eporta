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

// Варианты модели (цвет покрытия/остекление): группировка по свойству MODEL — задел под
// будущую выгрузку из 1С (project_import_table_v2, колонка "Модель"), сейчас MODEL заполнен
// вручную демо-затравкой на Dorsum 1 (см. план "transient-swimming-ullman"). Каждый вариант —
// отдельный элемент IBLOCK 19 со своей ценой/фото/артикулом, свотч — обычная ссылка на него.
$eportaModel = eportaPropText($arResult, "MODEL");
$eportaCurrentColor = eportaPropText($arResult, "COATING_COLOR");
$eportaCurrentGlazing = eportaPropText($arResult, "GLAZING");
$eportaColorOptions = [];
$eportaGlazingOptions = [];

if ($eportaModel !== "") {
	$eportaVariantsRes = CIBlockElement::GetList(
		[],
		["IBLOCK_ID" => 19, "PROPERTY_MODEL" => $eportaModel, "ACTIVE" => "Y"],
		false,
		false,
		["ID", "PROPERTY_COATING_COLOR", "PROPERTY_GLAZING", "DETAIL_PAGE_URL", "PREVIEW_PICTURE"]
	);
	$eportaVariants = [];
	while ($v = $eportaVariantsRes->GetNext()) {
		$eportaVariants[] = [
			"id" => (int)$v["ID"],
				"color" => (string)($v["PROPERTY_COATING_COLOR_VALUE"] ?? ""),
			"glazing" => (string)($v["PROPERTY_GLAZING_VALUE"] ?? ""),
			"url" => $v["DETAIL_PAGE_URL"],
			"photo" => $v["PREVIEW_PICTURE"] ? CFile::GetPath($v["PREVIEW_PICTURE"]) : "",
		];
	}
	$eportaCurrentId = (int)$arResult["ID"];
	// Для каждого значения оси — сам текущий товар (если это и есть этот вариант), иначе
	// вариант с текущим значением второй оси, иначе первый попавшийся с этим значением.
	foreach ($eportaVariants as $v) {
		if ($v["color"] === "") continue;
		$existing = $eportaColorOptions[$v["color"]] ?? null;
		$better = !$existing
			|| $v["id"] === $eportaCurrentId
			|| ($existing["id"] !== $eportaCurrentId && $v["glazing"] === $eportaCurrentGlazing && $existing["glazing"] !== $eportaCurrentGlazing);
		if ($better) $eportaColorOptions[$v["color"]] = $v;
	}
	foreach ($eportaVariants as $v) {
		if ($v["glazing"] === "") continue;
		$existing = $eportaGlazingOptions[$v["glazing"]] ?? null;
		$better = !$existing
			|| $v["id"] === $eportaCurrentId
			|| ($existing["id"] !== $eportaCurrentId && $v["color"] === $eportaCurrentColor && $existing["color"] !== $eportaCurrentColor);
		if ($better) $eportaGlazingOptions[$v["glazing"]] = $v;
	}
}
$eportaShowVariantSelectors = count($eportaColorOptions) > 1 || count($eportaGlazingOptions) > 1;

// Размеры (свойство SIZES, многозначное): значение "ШxВ" или "ШxВ:надбавка" — формат-задел
// под будущую выгрузку, надбавка пока в основном 0 (демо-данные без пересчёта).
$eportaSizeOptions = [];
$eportaSizesRaw = $arResult["DISPLAY_PROPERTIES"]["SIZES"]["DISPLAY_VALUE"]
	?? ($arResult["PROPERTIES"]["SIZES"]["VALUE"] ?? []);
if (!is_array($eportaSizesRaw)) {
	$eportaSizesRaw = $eportaSizesRaw !== "" ? [$eportaSizesRaw] : [];
}
foreach ($eportaSizesRaw as $sizeRaw) {
	$sizeParts = explode(":", (string)$sizeRaw, 2);
	$eportaSizeOptions[] = [
		"label" => $sizeParts[0],
		"markup" => isset($sizeParts[1]) ? (int)$sizeParts[1] : 0,
	];
}

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

		<?php if ($eportaShowVariantSelectors): ?>
		<!-- Цвет и остекление: реальные варианты модели (склейка по PROPERTY_MODEL) -->
		<div style="border-top:1px solid #efece6;border-bottom:1px solid #efece6;padding:14px 0;margin-bottom:14px;display:flex;flex-direction:column;gap:14px">
			<?php if (count($eportaColorOptions) > 1):
				$eportaColorCarousel = count($eportaColorOptions) > 5;
			?>
			<div>
				<div class="swatch-label"><span class="sl-name">Цвет покрытия:</span><span class="sl-val"><?= htmlspecialcharsbx($eportaCurrentColor) ?></span></div>
				<div class="eporta-variant-row">
					<?php if ($eportaColorCarousel): ?><button type="button" class="eporta-variant-nav" onclick="eportaScrollVariants(this,-1)" aria-label="Назад">‹</button><?php endif; ?>
					<div class="eporta-variant-scroll"<?= $eportaColorCarousel ? ' style="width:246px"' : '' ?>>
						<?php foreach ($eportaColorOptions as $colorName => $variant): ?>
							<a href="<?= htmlspecialcharsbx($variant["url"]) ?>" class="eporta-variant-item<?= $colorName === $eportaCurrentColor ? " active" : "" ?>" title="<?= htmlspecialcharsbx($colorName) ?>">
								<?php if ($variant["photo"]): ?><img src="<?= htmlspecialcharsbx($variant["photo"]) ?>" alt="<?= htmlspecialcharsbx($colorName) ?>"><?php else: ?><span class="eporta-variant-noimg"><?= htmlspecialcharsbx(mb_substr($colorName, 0, 1)) ?></span><?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
					<?php if ($eportaColorCarousel): ?><button type="button" class="eporta-variant-nav" onclick="eportaScrollVariants(this,1)" aria-label="Вперёд">›</button><?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
			<?php if (count($eportaGlazingOptions) > 1):
				$eportaGlazingCarousel = count($eportaGlazingOptions) > 5;
			?>
			<div>
				<div class="swatch-label"><span class="sl-name">Остекление:</span><span class="sl-val"><?= htmlspecialcharsbx($eportaCurrentGlazing) ?></span></div>
				<div class="eporta-variant-row">
					<?php if ($eportaGlazingCarousel): ?><button type="button" class="eporta-variant-nav" onclick="eportaScrollVariants(this,-1)" aria-label="Назад">‹</button><?php endif; ?>
					<div class="eporta-variant-scroll"<?= $eportaGlazingCarousel ? ' style="width:246px"' : '' ?>>
						<?php foreach ($eportaGlazingOptions as $glazingName => $variant): ?>
							<a href="<?= htmlspecialcharsbx($variant["url"]) ?>" class="eporta-variant-item<?= $glazingName === $eportaCurrentGlazing ? " active" : "" ?>" title="<?= htmlspecialcharsbx($glazingName) ?>">
								<?php if ($variant["photo"]): ?><img src="<?= htmlspecialcharsbx($variant["photo"]) ?>" alt="<?= htmlspecialcharsbx($glazingName) ?>"><?php else: ?><span class="eporta-variant-noimg"><?= htmlspecialcharsbx(mb_substr($glazingName, 0, 1)) ?></span><?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
					<?php if ($eportaGlazingCarousel): ?><button type="button" class="eporta-variant-nav" onclick="eportaScrollVariants(this,1)" aria-label="Вперёд">›</button><?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if (count($eportaSizeOptions) > 1): ?>
		<!-- Размер: атрибут текущего товара (PROPERTY_SIZES), переключение без перехода — меняется только надбавка к цене -->
		<div style="margin-bottom:18px">
			<div class="swatch-label"><span class="sl-name">Размер:</span><span class="sl-val" id="sizeLabel"><?= htmlspecialcharsbx($eportaSizeOptions[0]["label"]) ?></span></div>
			<div class="eporta-size-wrap" id="sizeOptions">
				<?php foreach ($eportaSizeOptions as $i => $sizeOpt): ?>
					<button type="button" class="eporta-size-btn<?= $i === 0 ? " active" : "" ?>" data-markup="<?= (int)$sizeOpt["markup"] ?>" data-label="<?= htmlspecialcharsbx($sizeOpt["label"]) ?>" onclick="selectSize(this)"><?= htmlspecialcharsbx($sizeOpt["label"]) ?></button>
				<?php endforeach; ?>
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
				// Цвет/остекление дублировать в характеристиках не нужно, когда они уже вынесены
				// в переключаемые селекторы выше (count > 1) — там они хорошо видны и так.
				$specs = [
					"Стиль" => eportaPropText($arResult, "STYLE"),
					"Покрытие" => eportaPropText($arResult, "COATING"),
					"Цвет покрытия" => count($eportaColorOptions) > 1 ? "" : eportaPropText($arResult, "COATING_COLOR"),
					"Остекление" => count($eportaGlazingOptions) > 1 ? "" : eportaPropText($arResult, "GLAZING"),
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
var sizeMarkup = <?= (int)($eportaSizeOptions[0]["markup"] ?? 0) ?>; // надбавка выбранного размера (PROPERTY_SIZES "ШxВ:надбавка")
var DOOR_ID = <?= (int)$arResult["ID"] ?>;
var HAS_SIZE_OPTIONS = <?= count($eportaSizeOptions) > 1 ? "true" : "false" ?>; // есть ли реальный выбор размера (не единственный вариант)
var selectedSizeLabel = <?= json_encode($eportaSizeOptions[0]["label"] ?? "", JSON_UNESCAPED_UNICODE) ?>;
var ADD_TO_BASKET_URL = <?= $addToBasketUrl ? json_encode($addToBasketUrl, JSON_UNESCAPED_SLASHES) : "null" ?>;
var DOOR_NAME = <?= json_encode($arResult["NAME"], JSON_UNESCAPED_UNICODE) ?>;
var DOOR_PRICE_LABEL = <?= json_encode($price ? ($price["PRINT_DISCOUNT_VALUE"] ?? $price["PRINT_VALUE"]) : "", JSON_UNESCAPED_UNICODE) ?>;
var productKitSelection = {}; // сохраняем выбор допов между открытиями модалки на этой странице

function fmtPrice(n) { return KitModal.fmtPrice(n); }

// Карусель фото-свотчей (цвет/остекление) при >5 вариантов — стрелки листают на ширину ~3 превью.
function eportaScrollVariants(btn, dir) {
	var row = btn.closest('.eporta-variant-row');
	var scroller = row && row.querySelector('.eporta-variant-scroll');
	if (scroller) scroller.scrollBy({ left: dir * 150, behavior: 'smooth' });
}

// Размер (SIZES) — тот же товар, меняется только надбавка к отображаемой цене покупки.
function updateCtaPrice() {
	document.getElementById('ctaPrice').textContent = fmtPrice(DOOR_PRICE + sizeMarkup + addonTotal);
}
function selectSize(btn) {
	document.querySelectorAll('.eporta-size-btn').forEach(function(b){ b.classList.remove('active'); });
	btn.classList.add('active');
	sizeMarkup = parseInt(btn.dataset.markup, 10) || 0;
	selectedSizeLabel = btn.dataset.label;
	document.getElementById('sizeLabel').textContent = btn.dataset.label;
	updateCtaPrice();
}

// Патчим PROPS только что добавленной строки корзины выбранным размером — отдельным
// изолированным эндпоинтом (см. catalog/basket-set-size.php), без глобального фиче-флага
// на свойстве SIZES. Не блокирует основной тост добавления, ошибка тут не критична.
function eportaSyncBasketSize() {
	if (!HAS_SIZE_OPTIONS || !selectedSizeLabel) return;
	fetch('/catalog/basket-set-size.php', {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'product_id=' + encodeURIComponent(DOOR_ID) + '&size=' + encodeURIComponent(selectedSizeLabel)
	}).catch(function(err) { console.warn('basket-set-size failed', err); });
}

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
		doorPrice: DOOR_PRICE + sizeMarkup,
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
			eportaSyncBasketSize();
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
			eportaSyncBasketSize();
			showToast('Комплект добавлен в корзину · ' + fmtPrice(total), true);
		})
		.catch(function(err) {
			showToast((err && err.message) || 'Не удалось добавить в корзину', false);
		});
}
</script>
