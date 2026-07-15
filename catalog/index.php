<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Каталог товаров");
$APPLICATION->SetTitle("Каталог товаров");
?>
<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");

	//vars
	$catalogIblockId = null;
	$arPriceCodes = array();

	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
	if(!empty($arTemplateSettings)){
		$catalogIblockId = $arTemplateSettings["TEMPLATE_PRODUCT_IBLOCK_ID"];
		$arPriceCodes = explode(", ", $arTemplateSettings["TEMPLATE_PRICE_CODES"]);
	}
?>
<?
	// Дев-превью нового шаблона eporta: статичная вёрстка (Этап 1, Фаза A).
	// Боевой dresscode:catalog не трогаем — при любом другом активном шаблоне
	// страница работает как прежде.
	$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
	// Деталь товара vs список различаем по SEF-шаблону компонента dresscode:catalog:
	// element => "#SECTION_CODE_PATH#/#ELEMENT_CODE#.html", section => "#SECTION_CODE_PATH#/".
	// Деталь всегда заканчивается на ".html", список — нет.
	$isEportaProductDetail = $isEportaTemplate && preg_match('~\.html(?:$|\?)~', $_SERVER["REQUEST_URI"] ?? "");

	// Этап 5 — Коллекции: ровно 8 дверных коллекций, подразделы раздела 183 "Коллекции" в
	// IBLOCK 19. URL приходит по штатному SEF-шаблону как /catalog/collections/<code>/
	// (SECTION_PAGE_URL секции). Список ID зафиксирован явно (см. collection/index.php —
	// фильтр CIBlockSection::GetList по IBLOCK_SECTION_ID не работает как ожидалось, возвращает
	// вообще все секции инфоблока), поэтому матчим CODE только среди этих 8 ID — иначе обычный
	// раздел каталога с тем же кодом сегмента URL мог бы случайно попасть под резолвер коллекции.
	$eportaCollectionIds = [151, 152, 153, 154, 155, 156, 157, 158];
	$eportaCollectionSection = null;
	if ($isEportaTemplate && !$isEportaProductDetail) {
		$eportaReqPath = parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);
		// SECTION_CODE_PATH коллекции включает код родителя: /catalog/collections/<code>/
		if (preg_match('~^/catalog/(?:[^/]+/)*([^/]+)/?$~', $eportaReqPath, $eportaCollMatch)) {
			\Bitrix\Main\Loader::includeModule("iblock");
			$eportaCollectionSection = \CIBlockSection::GetList(
				[],
				["IBLOCK_ID" => 19, "CODE" => $eportaCollMatch[1], "ID" => $eportaCollectionIds, "ACTIVE" => "Y"],
				false,
				["ID", "NAME", "DESCRIPTION", "PICTURE", "DETAIL_PICTURE", "UF_TOP_DESCRIPTION", "UF_DESC"]
			)->Fetch();
			if ($eportaCollectionSection) {
				$APPLICATION->SetPageProperty("title", "Коллекция ".$eportaCollectionSection["NAME"]);
				$APPLICATION->SetTitle("Коллекция ".$eportaCollectionSection["NAME"]);
				$eportaCollectionSection["ELEMENT_CNT"] = \CIBlockElement::GetList(
					[],
					["IBLOCK_ID" => 19, "SECTION_ID" => $eportaCollectionSection["ID"], "ACTIVE" => "Y"],
					["SECTION_ID"]
				)->SelectedRowsCount();
			}
		}
	}
?>
<?if ($isEportaProductDetail):?>

<?
	// Код элемента из SEF URL детали: "#SECTION_CODE_PATH#/#ELEMENT_CODE#.html"
	preg_match('~/([^/]+)\.html~', $_SERVER["REQUEST_URI"], $eportaUrlMatch);
	$eportaElementCode = $eportaUrlMatch[1] ?? "";
?>
	<?$APPLICATION->IncludeComponent(
		"bitrix:catalog.element",
		".default",
		[
			"IBLOCK_TYPE" => "catalog",
			"IBLOCK_ID" => "19",
			"ELEMENT_CODE" => $eportaElementCode,
			"ELEMENT_ID" => "",
			"PRODUCT_ID_VARIABLE" => "id",
			"PRODUCT_QUANTITY_VARIABLE" => "quantity",
			"PRODUCT_PROPS_VARIABLE" => "prop",
			"ADD_PROPERTIES_TO_BASKET" => "Y",
			"PARTIAL_PRODUCT_PROPERTIES" => "N",
			"USE_PRODUCT_QUANTITY" => "N",
			"BASKET_URL" => "/personal/cart/",
			"ACTION_VARIABLE" => "action",
			"PROPERTY_CODE" => ["STYLE", "COATING_COLOR", "GLAZING", "MAIN_COLOR", "CML2_ARTICLE", "RATING", "VOTE_COUNT", "PRODUCT_DAY", "COLLECTION"],
			"ADD_PICT_PROP" => "MORE_PHOTO",
			"OFFER_ADD_PICT_PROP" => "MORE_PHOTO",
			"OFFERS_FIELD_CODE" => [],
			"OFFERS_PROPERTY_CODE" => ["COLOR", "TP_GLASS", "TP_SIZE"],
			"OFFER_TREE_PROPS" => ["COLOR", "TP_GLASS", "TP_SIZE"],
			"PRICE_CODE" => ["BASE"],
			"USE_PRICE_COUNT" => "N",
			"SHOW_PRICE_COUNT" => "1",
			"PRICE_VAT_INCLUDE" => "Y",
			"CONVERT_CURRENCY" => "N",
			"SHOW_DISCOUNT_PERCENT" => "Y",
			"SHOW_OLD_PRICE" => "Y",
			"MESS_BTN_BUY" => "Купить",
			"MESS_BTN_ADD_TO_BASKET" => "В корзину",
			"MESS_NOT_AVAILABLE" => "Нет в наличии",
			"DETAIL_USE_VOTE_RATING" => "Y",
			"DETAIL_VOTE_DISPLAY_AS_RATING" => "rating",
			"HIDE_NOT_AVAILABLE" => "N",
			"HIDE_NOT_AVAILABLE_OFFERS" => "N",
			"CACHE_TYPE" => "A",
			"CACHE_TIME" => "3600",
			"CACHE_GROUPS" => "N",
			"USE_STORE" => "N",
			"SET_STATUS_404" => "N",
			"SET_TITLE" => "Y",
			"ADD_SECTIONS_CHAIN" => "N",
			"DISPLAY_COMPARE" => "N",
			"CHECK_SECTION_ID_VARIABLE" => "N",
			"SEF_MODE" => "N",
			"COMPATIBLE_MODE" => "Y",
			"TEMPLATE_THEME" => "site",
		],
		false
	);?>

<?elseif ($isEportaTemplate):?>

	<!-- Хлебные крошки -->
	<?if ($eportaCollectionSection):?>
	<div class="breadcrumb" style="padding:12px 56px 0">Главная · <a href="/collection/" style="color:inherit">Коллекции</a> · <?=htmlspecialcharsbx($eportaCollectionSection["NAME"])?></div>
	<?else:?>
	<div class="breadcrumb" style="padding:12px 56px 0">Главная · Каталог · Межкомнатные</div>
	<?endif;?>

	<?if ($eportaCollectionSection):
		$eportaCollBannerSrc = \CFile::GetPath($eportaCollectionSection["DETAIL_PICTURE"] ?: $eportaCollectionSection["PICTURE"]);
	?>
	<!-- Баннер коллекции: DETAIL_PICTURE/PICTURE + DESCRIPTION секции IBLOCK 19 -->
	<div style="position:relative;margin:18px 56px 0;border-radius:22px;overflow:hidden;min-height:220px;background:#e5e0d5<?=$eportaCollBannerSrc ? ";background-image:url('".htmlspecialcharsbx($eportaCollBannerSrc)."');background-size:cover;background-position:center" : ""?>">
		<div style="position:absolute;inset:0;background:linear-gradient(90deg,rgba(20,17,12,.86) 0%,rgba(20,17,12,.5) 55%,rgba(20,17,12,.08) 100%)"></div>
		<div style="position:relative;padding:36px 40px;max-width:560px">
			<div style="font:700 12.5px 'Manrope';letter-spacing:.08em;color:#ffd7b0;text-transform:uppercase;margin-bottom:10px">Коллекция фабрики EPORTA</div>
			<h1 style="margin:0;font:800 36px 'Manrope';color:#fff;letter-spacing:-0.01em"><?=htmlspecialcharsbx($eportaCollectionSection["NAME"])?></h1>
			<?if ($eportaCollectionSection["DESCRIPTION"]):?>
			<div style="font:700 14px 'Manrope';color:#ffd7b0;margin-top:8px"><?=htmlspecialcharsbx($eportaCollectionSection["DESCRIPTION"])?></div>
			<?endif;?>
			<?if ($eportaCollectionSection["UF_TOP_DESCRIPTION"] || $eportaCollectionSection["UF_DESC"]):?>
			<p style="margin:14px 0 0;font:500 14px/1.6 'Manrope';color:rgba(255,255,255,.88)"><?=htmlspecialcharsbx($eportaCollectionSection["UF_TOP_DESCRIPTION"] ?: $eportaCollectionSection["UF_DESC"])?></p>
			<?endif;?>
		</div>
	</div>
	<?endif;?>

	<!-- Заголовок + сортировка -->
	<div style="display:flex;align-items:flex-end;justify-content:space-between;padding:8px 56px 4px">
		<div>
			<?if ($eportaCollectionSection):?>
			<h2 style="margin:0;font:800 24px 'Manrope';letter-spacing:-0.01em">Товары коллекции <?=htmlspecialcharsbx($eportaCollectionSection["NAME"])?></h2>
			<?else:?>
			<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Межкомнатные двери</h1>
			<?endif;?>
			<div style="font:500 13px;color:#8a857b;margin-top:5px">Найдено <?=$eportaCollectionSection ? $eportaCollectionSection["ELEMENT_CNT"] : "2 418"?> моделей</div>
		</div>
		<div style="display:flex;align-items:center;gap:10px">
			<div style="display:flex;align-items:center;gap:9px;border:1.5px solid #e7e3db;border-radius:10px;padding:10px 14px;font:600 13px 'Manrope';color:#3a3631;cursor:pointer">Сначала популярные <span style="color:#a39e95">▾</span></div>
			<div style="display:flex;border:1.5px solid #e7e3db;border-radius:10px;overflow:hidden">
				<span style="width:38px;height:40px;display:flex;align-items:center;justify-content:center;background:#1b1a17;cursor:pointer">
					<span style="display:grid;grid-template-columns:1fr 1fr;gap:2px"><span style="width:5px;height:5px;background:#fff"></span><span style="width:5px;height:5px;background:#fff"></span><span style="width:5px;height:5px;background:#fff"></span><span style="width:5px;height:5px;background:#fff"></span></span>
				</span>
				<span style="width:38px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer">
					<span style="display:flex;flex-direction:column;gap:3px"><span style="width:14px;height:3px;background:#b3aea2"></span><span style="width:14px;height:3px;background:#b3aea2"></span><span style="width:14px;height:3px;background:#b3aea2"></span></span>
				</span>
			</div>
		</div>
	</div>

	<!-- Активные фильтры -->
	<div style="display:flex;align-items:center;gap:9px;padding:14px 56px 6px;flex-wrap:wrap">
		<span style="display:inline-flex;align-items:center;gap:8px;font:600 12.5px 'Manrope';background:#f3f1ec;color:#3a3631;padding:8px 12px;border-radius:20px;cursor:pointer">Экошпон <span style="color:#a39e95">✕</span></span>
		<span style="display:inline-flex;align-items:center;gap:8px;font:600 12.5px 'Manrope';background:#f3f1ec;color:#3a3631;padding:8px 12px;border-radius:20px;cursor:pointer">Белый <span style="color:#a39e95">✕</span></span>
		<span style="display:inline-flex;align-items:center;gap:8px;font:600 12.5px 'Manrope';background:#f3f1ec;color:#3a3631;padding:8px 12px;border-radius:20px;cursor:pointer">до 10 000 ₽ <span style="color:#a39e95">✕</span></span>
		<span style="font:700 12.5px 'Manrope';color:#c2670a;padding:8px 6px;cursor:pointer">Сбросить всё</span>
	</div>

	<!-- Фильтры + сетка -->
	<div style="display:flex;gap:22px;padding:8px 56px 28px;align-items:flex-start">

		<!-- Боковой фильтр -->
		<div style="flex:none;width:248px">
			<!-- Цена -->
			<div style="border-bottom:1px solid #efece6;padding:6px 0 18px">
				<div style="font:800 14px 'Manrope';margin-bottom:14px">Цена, ₽</div>
				<div style="position:relative;height:4px;background:#e7e3db;border-radius:3px;margin:20px 6px 16px">
					<div style="position:absolute;left:12%;right:38%;top:0;height:4px;background:#e8820a;border-radius:3px"></div>
					<span style="position:absolute;left:12%;top:-6px;width:16px;height:16px;border-radius:50%;background:#fff;border:2px solid #e8820a;margin-left:-8px;cursor:pointer"></span>
					<span style="position:absolute;right:38%;top:-6px;width:16px;height:16px;border-radius:50%;background:#fff;border:2px solid #e8820a;margin-right:-8px;cursor:pointer"></span>
				</div>
				<div style="display:flex;gap:8px">
					<div style="flex:1;border:1.5px solid #e7e3db;border-radius:9px;padding:9px 11px;font:600 12.5px 'Manrope'">4 000</div>
					<div style="flex:1;border:1.5px solid #e7e3db;border-radius:9px;padding:9px 11px;font:600 12.5px 'Manrope'">40 000</div>
				</div>
			</div>

			<!-- Стиль -->
			<div style="border-bottom:1px solid #efece6;padding:16px 0">
				<div style="font:800 14px 'Manrope';margin-bottom:12px">Стиль</div>
				<div style="display:flex;align-items:center;gap:10px;padding:5px 0;font:600 13px 'Manrope';cursor:pointer">
					<span style="width:18px;height:18px;border-radius:5px;background:#e8820a;position:relative;flex:none"><span style="position:absolute;left:6px;top:3px;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)"></span></span>
					Модерн / хай-тек <span style="color:#c2bdb2;margin-left:auto;font-weight:600">862</span>
				</div>
				<div style="display:flex;align-items:center;gap:10px;padding:5px 0;font:600 13px 'Manrope';cursor:pointer">
					<span style="width:18px;height:18px;border-radius:5px;border:1.5px solid #e0dbd4;flex:none"></span>
					Классика <span style="color:#c2bdb2;margin-left:auto;font-weight:600">504</span>
				</div>
				<div style="display:flex;align-items:center;gap:10px;padding:5px 0;font:600 13px 'Manrope';cursor:pointer">
					<span style="width:18px;height:18px;border-radius:5px;border:1.5px solid #e0dbd4;flex:none"></span>
					Лофт <span style="color:#c2bdb2;margin-left:auto;font-weight:600">218</span>
				</div>
				<div style="display:flex;align-items:center;gap:10px;padding:5px 0;font:600 13px 'Manrope';cursor:pointer">
					<span style="width:18px;height:18px;border-radius:5px;border:1.5px solid #e0dbd4;flex:none"></span>
					Минимализм <span style="color:#c2bdb2;margin-left:auto;font-weight:600">312</span>
				</div>
			</div>

			<!-- Покрытие -->
			<div style="border-bottom:1px solid #efece6;padding:16px 0">
				<div style="font:800 14px 'Manrope';margin-bottom:12px">Покрытие</div>
				<div style="display:flex;align-items:center;gap:10px;padding:5px 0;font:600 13px 'Manrope';cursor:pointer">
					<span style="width:18px;height:18px;border-radius:5px;background:#e8820a;position:relative;flex:none"><span style="position:absolute;left:6px;top:3px;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)"></span></span>
					Экошпон <span style="color:#c2bdb2;margin-left:auto;font-weight:600">1 204</span>
				</div>
				<div style="display:flex;align-items:center;gap:10px;padding:5px 0;font:600 13px 'Manrope';cursor:pointer">
					<span style="width:18px;height:18px;border-radius:5px;border:1.5px solid #e0dbd4;flex:none"></span>
					Эмаль <span style="color:#c2bdb2;margin-left:auto;font-weight:600">386</span>
				</div>
				<div style="display:flex;align-items:center;gap:10px;padding:5px 0;font:600 13px 'Manrope';cursor:pointer">
					<span style="width:18px;height:18px;border-radius:5px;border:1.5px solid #e0dbd4;flex:none"></span>
					Шпон <span style="color:#c2bdb2;margin-left:auto;font-weight:600">228</span>
				</div>
			</div>

			<!-- Цвет -->
			<div style="padding:16px 0">
				<div style="font:800 14px 'Manrope';margin-bottom:12px">Цвет</div>
				<div style="display:flex;flex-wrap:wrap;gap:8px">
					<span title="Белый" style="width:30px;height:30px;border-radius:50%;background:#fff;border:2px solid #e8820a;cursor:pointer"></span>
					<span title="Чёрный" style="width:30px;height:30px;border-radius:50%;background:#1b1a17;border:2px solid transparent;cursor:pointer"></span>
					<span title="Серый" style="width:30px;height:30px;border-radius:50%;background:#9a9182;border:2px solid transparent;cursor:pointer"></span>
					<span title="Дуб" style="width:30px;height:30px;border-radius:50%;background:#c8a96e;border:2px solid transparent;cursor:pointer"></span>
					<span title="Орех" style="width:30px;height:30px;border-radius:50%;background:#7b4a2d;border:2px solid transparent;cursor:pointer"></span>
					<span title="Бежевый" style="width:30px;height:30px;border-radius:50%;background:#e8dfc8;border:2px solid transparent;cursor:pointer"></span>
				</div>
			</div>

			<button style="width:100%;background:#e8820a;color:#fff;font:700 14px 'Manrope';padding:13px;border-radius:12px;border:none;cursor:pointer;box-shadow:0 8px 20px rgba(232,130,10,.28)">Показать 247 моделей</button>
		</div>

		<!-- Сетка товаров: реальные данные IBLOCK 19 (Этап 1, Фаза B) -->
		<div style="flex:1">
			<?$APPLICATION->IncludeComponent(
				"bitrix:catalog.section",
				".default",
				[
					"IBLOCK_TYPE" => "catalog",
					"IBLOCK_ID" => "19",
					"SECTION_ID" => $eportaCollectionSection ? $eportaCollectionSection["ID"] : false,
					"SECTION_CODE" => "",
					"SECTION_USER_FIELDS" => [],
					"ELEMENT_SORT_FIELD" => "sort",
					"ELEMENT_SORT_ORDER" => "asc",
					"ELEMENT_SORT_FIELD2" => "id",
					"ELEMENT_SORT_ORDER2" => "desc",
					"FILTER_NAME" => "",
					"HIDE_NOT_AVAILABLE" => "N",
					"HIDE_NOT_AVAILABLE_OFFERS" => "N",
					"PAGE_ELEMENT_COUNT" => "9",
					"LINE_ELEMENT_COUNT" => "3",
					"PROPERTY_CODE" => ["STYLE", "COATING_COLOR", "GLAZING", "MAIN_COLOR", "PRODUCT_DAY", "RATING", "VOTE_COUNT", "CML2_ARTICLE"],
					"OFFERS_FIELD_CODE" => [],
					"OFFERS_PROPERTY_CODE" => [],
					"BACKGROUND_IMAGE" => "-",
					"LABEL_PROP" => "-",
					"PRODUCT_SUBSCRIPTION" => "N",
					"SHOW_DISCOUNT_PERCENT" => "Y",
					"SHOW_OLD_PRICE" => "Y",
					"PRICE_CODE" => ["BASE"],
					"USE_PRICE_COUNT" => "N",
					"SHOW_PRICE_COUNT" => "1",
					"PRICE_VAT_INCLUDE" => "Y",
					"CONVERT_CURRENCY" => "N",
					"BASKET_URL" => "/personal/cart/",
					"ACTION_VARIABLE" => "action",
					"PRODUCT_ID_VARIABLE" => "id",
					"PRODUCT_QUANTITY_VARIABLE" => "quantity",
					"ADD_PROPERTIES_TO_BASKET" => "Y",
					"PRODUCT_PROPS_VARIABLE" => "prop",
					"PARTIAL_PRODUCT_PROPERTIES" => "N",
					"USE_PRODUCT_QUANTITY" => "N",
					"CACHE_TYPE" => "A",
					"CACHE_TIME" => "3600",
					"CACHE_GROUPS" => "N",
					"CACHE_FILTER" => "N",
					"DISPLAY_COMPARE" => "N",
					"SET_TITLE" => "N",
					"SET_STATUS_404" => "N",
					"SEF_MODE" => "N",
					"PAGER_TEMPLATE" => "round",
					"DISPLAY_TOP_PAGER" => "N",
					"DISPLAY_BOTTOM_PAGER" => "Y",
					"PAGER_TITLE" => "Товары",
					"PAGER_SHOW_ALWAYS" => "N",
					"PAGER_SHOW_ALL" => "N",
					"ADD_SECTIONS_CHAIN" => "N",
					"COMPATIBLE_MODE" => "Y",
					"AJAX_MODE" => "N",
					"TEMPLATE_THEME" => "site",
				],
				false
			);?>
		</div>
	</div>

<?else:?>
<?$APPLICATION->IncludeComponent(
	"dresscode:catalog",
	".default",
	[
		"IBLOCK_TYPE" => "catalog",
		"IBLOCK_ID" => "19",
		"TEMPLATE_THEME" => "site",
		"HIDE_NOT_AVAILABLE" => "N",
		"BASKET_URL" => "/personal/cart/",
		"ACTION_VARIABLE" => "action",
		"PRODUCT_ID_VARIABLE" => "id",
		"SECTION_ID_VARIABLE" => "SECTION_ID",
		"PRODUCT_QUANTITY_VARIABLE" => "quantity",
		"PRODUCT_PROPS_VARIABLE" => "prop",
		"SEF_MODE" => "Y",
		"SEF_FOLDER" => "/catalog/",
		"AJAX_MODE" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"AJAX_OPTION_HISTORY" => "N",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "3600",
		"CACHE_FILTER" => "Y",
		"CACHE_GROUPS" => "N",
		"SET_TITLE" => "Y",
		"ADD_SECTION_CHAIN" => "Y",
		"ADD_ELEMENT_CHAIN" => "Y",
		"SET_STATUS_404" => "Y",
		"DETAIL_DISPLAY_NAME" => "Y",
		"USE_ELEMENT_COUNTER" => "Y",
		"USE_FILTER" => "Y",
		"FILTER_NAME" => "arrFilter",
		"FILTER_VIEW_MODE" => "VERTICAL",
		"FILTER_FIELD_CODE" => [
			0 => "",
			1 => "",
		],
		"FILTER_PROPERTY_CODE" => [
			0 => "STYLE",
			1 => "GLAZING",
			2 => "CML2_ARTICLE",
			3 => "COLLECTION",
			4 => "ATT_BRAND",
			5 => "MORE_PROPERTIES",
			6 => "USER_ID",
			7 => "BLOG_POST_ID",
			8 => "VIDEO",
			9 => "BLOG_COMMENTS_CNT",
			10 => "VOTE_COUNT",
			11 => "OFFERS",
			12 => "SHOW_MENU",
			13 => "SIMILAR_PRODUCT",
			14 => "RATING",
			15 => "RELATED_PRODUCT",
			16 => "VOTE_SUM",
			17 => "MAIN_COLOR",
			18 => "MATERIAL",
			19 => "TOTAL_OUTPUT_POWER",
			20 => "VID_ZASTECHKI",
			21 => "VID_SUMKI",
			22 => "VYSOTA_RUCHEK",
			23 => "WARRANTY",
			24 => "OTSEKOV",
			25 => "CONVECTION",
			26 => "NAZNAZHENIE",
			27 => "BULK",
			28 => "PODKLADKA",
			29 => "SEASON",
			30 => "REF",
			31 => "COUNTRY_BRAND",
			32 => "SKU_COLOR",
			33 => "DELIVERY",
			34 => "PICKUP",
			35 => "COLOR",
			36 => "ZOOM2",
			37 => "BATTERY_LIFE",
			38 => "SWITCH",
			39 => "GRAF_PROC",
			40 => "LENGTH_OF_CORD",
			41 => "DISPLAY",
			42 => "LOADING_LAUNDRY",
			43 => "FULL_HD_VIDEO_RECORD",
			44 => "INTERFACE",
			45 => "COMPRESSORS",
			46 => "Number_of_Outlets",
			47 => "MAX_RESOLUTION_VIDEO",
			48 => "MAX_BUS_FREQUENCY",
			49 => "MAX_RESOLUTION",
			50 => "FREEZER",
			51 => "POWER_SUB",
			52 => "POWER",
			53 => "HARD_DRIVE_SPACE",
			54 => "MEMORY",
			55 => "OS",
			56 => "ZOOM",
			57 => "PAPER_FEED",
			58 => "SUPPORTED_STANDARTS",
			59 => "VIDEO_FORMAT",
			60 => "SUPPORT_2SIM",
			61 => "MP3",
			62 => "ETHERNET_PORTS",
			63 => "MATRIX",
			64 => "CAMERA",
			65 => "PHOTOSENSITIVITY",
			66 => "DEFROST",
			67 => "SPEED_WIFI",
			68 => "SPIN_SPEED",
			69 => "PRINT_SPEED",
			70 => "SOCKET",
			71 => "IMAGE_STABILIZER",
			72 => "GSM",
			73 => "SIM",
			74 => "TYPE",
			75 => "MEMORY_CARD",
			76 => "TYPE_BODY",
			77 => "TYPE_MOUSE",
			78 => "TYPE_PRINT",
			79 => "CONNECTION",
			80 => "TYPE_OF_CONTROL",
			81 => "TYPE_DISPLAY",
			82 => "TYPE2",
			83 => "REFRESH_RATE",
			84 => "RANGE",
			85 => "AMOUNT_MEMORY",
			86 => "MEMORY_CAPACITY",
			87 => "VIDEO_BRAND",
			88 => "DIAGONAL",
			89 => "RESOLUTION",
			90 => "TOUCH",
			91 => "CORES",
			92 => "LINE_PROC",
			93 => "PROCESSOR",
			94 => "CLOCK_SPEED",
			95 => "TYPE_PROCESSOR",
			96 => "PROCESSOR_SPEED",
			97 => "HARD_DRIVE",
			98 => "HARD_DRIVE_TYPE",
			99 => "Number_of_memory_slots",
			100 => "MAXIMUM_MEMORY_FREQUENCY",
			101 => "TYPE_MEMORY",
			102 => "BLUETOOTH",
			103 => "FM",
			104 => "GPS",
			105 => "HDMI",
			106 => "SMART_TV",
			107 => "USB",
			108 => "WIFI",
			109 => "FLASH",
			110 => "ROTARY_DISPLAY",
			111 => "SUPPORT_3D",
			112 => "SUPPORT_3G",
			113 => "WITH_COOLER",
			114 => "FINGERPRINT",
			115 => "PROFILE",
			116 => "GAS_CONTROL",
			117 => "GRILL",
			118 => "GENRE",
			119 => "INTAKE_POWER",
			120 => "SURFACE_COATING",
			121 => "brand_tyres",
			122 => "SEASONOST",
			123 => "DUST_COLLECTION",
			124 => "DRYING",
			125 => "REMOVABLE_TOP_COVER",
			126 => "TEST_TEST",
			127 => "CONTROL",
			128 => "FINE_FILTER",
			129 => "FORM_FAKTOR",
			130 => "",
		],
		"FILTER_PRICE_CODE" => [
			0 => "BASE",
		],
		"FILTER_OFFERS_FIELD_CODE" => [
			0 => "",
			1 => "",
		],
		"FILTER_OFFERS_PROPERTY_CODE" => [
			0 => "TP_GLASS",
			1 => "TP_SIZE",
			2 => "TP_COLOR",
			3 => "244",
			4 => "245",
			5 => "246",
			6 => "250",
			7 => "",
		],
		"USE_REVIEW" => "N",
		"MESSAGES_PER_PAGE" => "10",
		"USE_CAPTCHA" => "Y",
		"REVIEW_AJAX_POST" => "Y",
		"PATH_TO_SMILE" => "/bitrix/images/forum/smile/",
		"FORUM_ID" => "1",
		"URL_TEMPLATES_READ" => "",
		"SHOW_LINK_TO_FORUM" => "N",
		"USE_COMPARE" => "N",
		"PRICE_CODE" => [
			0 => "BASE",
		],
		"USE_PRICE_COUNT" => "N",
		"SHOW_PRICE_COUNT" => "1",
		"PRICE_VAT_INCLUDE" => "Y",
		"PRICE_VAT_SHOW_VALUE" => "N",
		"USE_PRODUCT_QUANTITY" => "Y",
		"CONVERT_CURRENCY" => "Y",
		"CURRENCY_ID" => "RUB",
		"QUANTITY_FLOAT" => "N",
		"OFFERS_CART_PROPERTIES" => [
			0 => "COLOR",
		],
		"SHOW_TOP_ELEMENTS" => "N",
		"SECTION_COUNT_ELEMENTS" => "N",
		"SECTION_TOP_DEPTH" => "4",
		"SECTIONS_VIEW_MODE" => "TEXT",
		"SECTIONS_SHOW_PARENT_NAME" => "N",
		"PAGE_ELEMENT_COUNT" => "30",
		"LINE_ELEMENT_COUNT" => "3",
		"ELEMENT_SORT_FIELD" => "sort",
		"ELEMENT_SORT_ORDER" => "asc",
		"ELEMENT_SORT_FIELD2" => "name",
		"ELEMENT_SORT_ORDER2" => "desc",
		"LIST_PROPERTY_CODE" => [
			0 => "OFFERS",
			1 => "ATT_BRAND",
			2 => "PRODUCT_DAY",
			3 => "COLOR",
			4 => "TIMER_DATE",
			5 => "TIMER_LOOP",
			6 => "ZOOM2",
			7 => "BATTERY_LIFE",
			8 => "SWITCH",
			9 => "GRAF_PROC",
			10 => "LENGTH_OF_CORD",
			11 => "DISPLAY",
			12 => "LOADING_LAUNDRY",
			13 => "FULL_HD_VIDEO_RECORD",
			14 => "INTERFACE",
			15 => "COMPRESSORS",
			16 => "Number_of_Outlets",
			17 => "MAX_RESOLUTION_VIDEO",
			18 => "MAX_BUS_FREQUENCY",
			19 => "MAX_RESOLUTION",
			20 => "FREEZER",
			21 => "POWER_SUB",
			22 => "POWER",
			23 => "HARD_DRIVE_SPACE",
			24 => "MEMORY",
			25 => "OS",
			26 => "ZOOM",
			27 => "PAPER_FEED",
			28 => "SUPPORTED_STANDARTS",
			29 => "VIDEO_FORMAT",
			30 => "SUPPORT_2SIM",
			31 => "MP3",
			32 => "ETHERNET_PORTS",
			33 => "MATRIX",
			34 => "CAMERA",
			35 => "PHOTOSENSITIVITY",
			36 => "DEFROST",
			37 => "SPEED_WIFI",
			38 => "SPIN_SPEED",
			39 => "PRINT_SPEED",
			40 => "SOCKET",
			41 => "IMAGE_STABILIZER",
			42 => "GSM",
			43 => "SIM",
			44 => "TYPE",
			45 => "MEMORY_CARD",
			46 => "TYPE_BODY",
			47 => "TYPE_MOUSE",
			48 => "TYPE_PRINT",
			49 => "CONNECTION",
			50 => "TYPE_OF_CONTROL",
			51 => "TYPE_DISPLAY",
			52 => "TYPE2",
			53 => "REFRESH_RATE",
			54 => "RANGE",
			55 => "AMOUNT_MEMORY",
			56 => "MEMORY_CAPACITY",
			57 => "VIDEO_BRAND",
			58 => "DIAGONAL",
			59 => "RESOLUTION",
			60 => "TOUCH",
			61 => "CORES",
			62 => "LINE_PROC",
			63 => "PROCESSOR",
			64 => "CLOCK_SPEED",
			65 => "TYPE_PROCESSOR",
			66 => "PROCESSOR_SPEED",
			67 => "HARD_DRIVE",
			68 => "HARD_DRIVE_TYPE",
			69 => "Number_of_memory_slots",
			70 => "MAXIMUM_MEMORY_FREQUENCY",
			71 => "TYPE_MEMORY",
			72 => "BLUETOOTH",
			73 => "FM",
			74 => "GPS",
			75 => "HDMI",
			76 => "SMART_TV",
			77 => "USB",
			78 => "WIFI",
			79 => "FLASH",
			80 => "ROTARY_DISPLAY",
			81 => "SUPPORT_3D",
			82 => "SUPPORT_3G",
			83 => "WITH_COOLER",
			84 => "FINGERPRINT",
			85 => "VOZRAST",
			86 => "ENERGOPOTREB",
			87 => "OBOROTY",
			88 => "MINI_BAR",
			89 => "SIZES_PRODUCT",
			90 => "DISPLAY_TYPE",
			91 => "TIP_ELEMENTOV_PITANIA",
			92 => "BELKI",
			93 => "ZHIRY",
			94 => "CALORIES",
			95 => "COLLECTION",
			96 => "UGLEVODY",
			97 => "TOTAL_OUTPUT_POWER",
			98 => "VID_ZASTECHKI",
			99 => "VID_SUMKI",
			100 => "PROFILE",
			101 => "VYSOTA_RUCHEK",
			102 => "GAS_CONTROL",
			103 => "WARRANTY",
			104 => "GRILL",
			105 => "MORE_PROPERTIES",
			106 => "GENRE",
			107 => "OTSEKOV",
			108 => "CONVECTION",
			109 => "MATERIAL",
			110 => "INTAKE_POWER",
			111 => "NAZNAZHENIE",
			112 => "BULK",
			113 => "PODKLADKA",
			114 => "SURFACE_COATING",
			115 => "brand_tyres",
			116 => "SEASON",
			117 => "SEASONOST",
			118 => "DUST_COLLECTION",
			119 => "REF",
			120 => "COUNTRY_BRAND",
			121 => "DRYING",
			122 => "REMOVABLE_TOP_COVER",
			123 => "TEST_TEST",
			124 => "CONTROL",
			125 => "FINE_FILTER",
			126 => "FORM_FAKTOR",
			127 => "SKU_COLOR",
			128 => "CML2_ARTICLE",
			129 => "DELIVERY",
			130 => "PICKUP",
			131 => "USER_ID",
			132 => "BLOG_POST_ID",
			133 => "VIDEO",
			134 => "BLOG_COMMENTS_CNT",
			135 => "VOTE_COUNT",
			136 => "SHOW_MENU",
			137 => "SIMILAR_PRODUCT",
			138 => "RATING",
			139 => "RELATED_PRODUCT",
			140 => "VOTE_SUM",
			141 => "MAXIMUM_PRICE",
			142 => "MINIMUM_PRICE",
			143 => "HTML",
			144 => "199",
			145 => "ATT_BRAND2",
			146 => "NEWPRODUCT",
			147 => "SALELEADER",
			148 => "SPECIALOFFER",
			149 => "",
		],
		"INCLUDE_SUBSECTIONS" => "Y",
		"LIST_META_KEYWORDS" => "-",
		"LIST_META_DESCRIPTION" => "-",
		"LIST_BROWSER_TITLE" => "-",
		"LIST_OFFERS_FIELD_CODE" => [
			0 => "",
			1 => "",
		],
		"LIST_OFFERS_PROPERTY_CODE" => [
			0 => "",
			1 => "SIZES_SHOES",
			2 => "SIZES_CLOTHES",
			3 => "ARTNUMBER",
			4 => "",
		],
		"LIST_OFFERS_LIMIT" => "1",
		"DETAIL_PROPERTY_CODE" => [
			0 => "OFFERS",
			1 => "ATT_BRAND",
			2 => "PRODUCT_DAY",
			3 => "COLOR",
			4 => "TIMER_DATE",
			5 => "TIMER_LOOP",
			6 => "ZOOM2",
			7 => "BATTERY_LIFE",
			8 => "SWITCH",
			9 => "GRAF_PROC",
			10 => "LENGTH_OF_CORD",
			11 => "DISPLAY",
			12 => "LOADING_LAUNDRY",
			13 => "FULL_HD_VIDEO_RECORD",
			14 => "INTERFACE",
			15 => "COMPRESSORS",
			16 => "Number_of_Outlets",
			17 => "MAX_RESOLUTION_VIDEO",
			18 => "MAX_BUS_FREQUENCY",
			19 => "MAX_RESOLUTION",
			20 => "FREEZER",
			21 => "POWER_SUB",
			22 => "POWER",
			23 => "HARD_DRIVE_SPACE",
			24 => "MEMORY",
			25 => "OS",
			26 => "ZOOM",
			27 => "PAPER_FEED",
			28 => "SUPPORTED_STANDARTS",
			29 => "VIDEO_FORMAT",
			30 => "SUPPORT_2SIM",
			31 => "MP3",
			32 => "ETHERNET_PORTS",
			33 => "MATRIX",
			34 => "CAMERA",
			35 => "PHOTOSENSITIVITY",
			36 => "DEFROST",
			37 => "SPEED_WIFI",
			38 => "SPIN_SPEED",
			39 => "PRINT_SPEED",
			40 => "SOCKET",
			41 => "IMAGE_STABILIZER",
			42 => "GSM",
			43 => "SIM",
			44 => "TYPE",
			45 => "MEMORY_CARD",
			46 => "TYPE_BODY",
			47 => "TYPE_MOUSE",
			48 => "TYPE_PRINT",
			49 => "CONNECTION",
			50 => "TYPE_OF_CONTROL",
			51 => "TYPE_DISPLAY",
			52 => "TYPE2",
			53 => "REFRESH_RATE",
			54 => "RANGE",
			55 => "AMOUNT_MEMORY",
			56 => "MEMORY_CAPACITY",
			57 => "VIDEO_BRAND",
			58 => "DIAGONAL",
			59 => "RESOLUTION",
			60 => "TOUCH",
			61 => "CORES",
			62 => "LINE_PROC",
			63 => "PROCESSOR",
			64 => "CLOCK_SPEED",
			65 => "TYPE_PROCESSOR",
			66 => "PROCESSOR_SPEED",
			67 => "HARD_DRIVE",
			68 => "HARD_DRIVE_TYPE",
			69 => "Number_of_memory_slots",
			70 => "MAXIMUM_MEMORY_FREQUENCY",
			71 => "TYPE_MEMORY",
			72 => "BLUETOOTH",
			73 => "FM",
			74 => "GPS",
			75 => "HDMI",
			76 => "SMART_TV",
			77 => "USB",
			78 => "WIFI",
			79 => "FLASH",
			80 => "ROTARY_DISPLAY",
			81 => "SUPPORT_3D",
			82 => "SUPPORT_3G",
			83 => "WITH_COOLER",
			84 => "FINGERPRINT",
			85 => "VOZRAST",
			86 => "ENERGOPOTREB",
			87 => "OBOROTY",
			88 => "MINI_BAR",
			89 => "SIZES_PRODUCT",
			90 => "DISPLAY_TYPE",
			91 => "TIP_ELEMENTOV_PITANIA",
			92 => "BELKI",
			93 => "ZHIRY",
			94 => "CALORIES",
			95 => "COLLECTION",
			96 => "UGLEVODY",
			97 => "TOTAL_OUTPUT_POWER",
			98 => "VID_ZASTECHKI",
			99 => "VID_SUMKI",
			100 => "PROFILE",
			101 => "VYSOTA_RUCHEK",
			102 => "GAS_CONTROL",
			103 => "WARRANTY",
			104 => "GRILL",
			105 => "MORE_PROPERTIES",
			106 => "GENRE",
			107 => "OTSEKOV",
			108 => "CONVECTION",
			109 => "MATERIAL",
			110 => "INTAKE_POWER",
			111 => "NAZNAZHENIE",
			112 => "BULK",
			113 => "PODKLADKA",
			114 => "SURFACE_COATING",
			115 => "brand_tyres",
			116 => "SEASON",
			117 => "SEASONOST",
			118 => "DUST_COLLECTION",
			119 => "REF",
			120 => "COUNTRY_BRAND",
			121 => "DRYING",
			122 => "REMOVABLE_TOP_COVER",
			123 => "TEST_TEST",
			124 => "CONTROL",
			125 => "FINE_FILTER",
			126 => "FORM_FAKTOR",
			127 => "SKU_COLOR",
			128 => "CML2_ARTICLE",
			129 => "DELIVERY",
			130 => "PICKUP",
			131 => "USER_ID",
			132 => "BLOG_POST_ID",
			133 => "VIDEO",
			134 => "BLOG_COMMENTS_CNT",
			135 => "VOTE_COUNT",
			136 => "SHOW_MENU",
			137 => "SIMILAR_PRODUCT",
			138 => "RATING",
			139 => "RELATED_PRODUCT",
			140 => "VOTE_SUM",
			141 => "MAXIMUM_PRICE",
			142 => "MINIMUM_PRICE",
			143 => "HTML",
			144 => "199",
			145 => "ATT_BRAND2",
			146 => "NEWPRODUCT",
			147 => "MANUFACTURER",
			148 => "",
		],
		"DETAIL_META_KEYWORDS" => "-",
		"DETAIL_META_DESCRIPTION" => "-",
		"DETAIL_BROWSER_TITLE" => "-",
		"DETAIL_OFFERS_FIELD_CODE" => [
			0 => "",
			1 => "",
		],
		"DETAIL_OFFERS_PROPERTY_CODE" => [
			0 => "",
			1 => "ARTNUMBER",
			2 => "SIZES_SHOES",
			3 => "SIZES_CLOTHES",
			4 => "",
		],
		"LINK_IBLOCK_TYPE" => "",
		"LINK_IBLOCK_ID" => "",
		"LINK_PROPERTY_SID" => "",
		"LINK_ELEMENTS_URL" => "link.php?PARENT_ELEMENT_ID=#ELEMENT_ID#",
		"USE_ALSO_BUY" => "N",
		"ALSO_BUY_ELEMENT_COUNT" => "4",
		"ALSO_BUY_MIN_BUYES" => "1",
		"OFFERS_SORT_FIELD" => "",
		"OFFERS_SORT_ORDER" => "",
		"OFFERS_SORT_FIELD2" => "",
		"OFFERS_SORT_ORDER2" => "",
		"PAGER_TEMPLATE" => "round",
		"DISPLAY_TOP_PAGER" => "Y",
		"DISPLAY_BOTTOM_PAGER" => "Y",
		"PAGER_TITLE" => "Товары",
		"PAGER_SHOW_ALWAYS" => "N",
		"PAGER_DESC_NUMBERING" => "N",
		"PAGER_DESC_NUMBERING_CACHE_TIME" => "36000000",
		"PAGER_SHOW_ALL" => "N",
		"ADD_PICT_PROP" => "MORE_PHOTO",
		"LABEL_PROP" => "-",
		"PRODUCT_DISPLAY_MODE" => "Y",
		"OFFER_ADD_PICT_PROP" => "MORE_PHOTO",
		"OFFER_TREE_PROPS" => [
			0 => "COLOR",
			1 => "SIZE_CLOTHES",
			2 => "MATERIAL",
		],
		"SHOW_DISCOUNT_PERCENT" => "Y",
		"SHOW_OLD_PRICE" => "Y",
		"MESS_BTN_BUY" => "Купить",
		"MESS_BTN_ADD_TO_BASKET" => "В корзину",
		"MESS_BTN_COMPARE" => "Сравнение",
		"MESS_BTN_DETAIL" => "Подробнее",
		"MESS_NOT_AVAILABLE" => "Нет в наличии",
		"DETAIL_USE_VOTE_RATING" => "Y",
		"DETAIL_VOTE_DISPLAY_AS_RATING" => "rating",
		"DETAIL_USE_COMMENTS" => "Y",
		"DETAIL_BLOG_USE" => "Y",
		"DETAIL_VK_USE" => "N",
		"DETAIL_FB_USE" => "Y",
		"AJAX_OPTION_ADDITIONAL" => "",
		"USE_STORE" => "N",
		"USE_STORE_PHONE" => "Y",
		"USE_STORE_SCHEDULE" => "Y",
		"USE_MIN_AMOUNT" => "N",
		"STORE_PATH" => "/store/#store_id#",
		"MAIN_TITLE" => "Наличие на складах",
		"MIN_AMOUNT" => "10",
		"DETAIL_BRAND_USE" => "Y",
		"DETAIL_BRAND_PROP_CODE" => [
			0 => "",
			1 => "BRAND_REF",
			2 => "",
		],
		"ADD_SECTIONS_CHAIN" => "Y",
		"COMMON_SHOW_CLOSE_POPUP" => "N",
		"DETAIL_SHOW_MAX_QUANTITY" => "N",
		"DETAIL_BLOG_URL" => "catalog_comments",
		"DETAIL_BLOG_EMAIL_NOTIFY" => "N",
		"DETAIL_FB_APP_ID" => "",
		"USE_SALE_BESTSELLERS" => "Y",
		"ADD_PROPERTIES_TO_BASKET" => "Y",
		"PARTIAL_PRODUCT_PROPERTIES" => "N",
		"USE_COMMON_SETTINGS_BASKET_POPUP" => "N",
		"TOP_ADD_TO_BASKET_ACTION" => "ADD",
		"SECTION_ADD_TO_BASKET_ACTION" => "ADD",
		"DETAIL_ADD_TO_BASKET_ACTION" => [
			0 => "BUY",
		],
		"DETAIL_SHOW_BASIS_PRICE" => "Y",
		"DETAIL_CHECK_SECTION_ID_VARIABLE" => "N",
		"DETAIL_DETAIL_PICTURE_MODE" => "IMG",
		"DETAIL_ADD_DETAIL_TO_SLIDER" => "N",
		"DETAIL_DISPLAY_PREVIEW_TEXT_MODE" => "E",
		"STORES" => [
			0 => "1",
		],
		"USER_FIELDS" => [
			0 => "",
			1 => "",
		],
		"FIELDS" => [
			0 => "TITLE",
			1 => "ADDRESS",
			2 => "DESCRIPTION",
			3 => "PHONE",
			4 => "SCHEDULE",
			5 => "EMAIL",
			6 => "IMAGE_ID",
			7 => "COORDINATES",
			8 => "",
		],
		"SHOW_EMPTY_STORE" => "Y",
		"SHOW_GENERAL_STORE_INFORMATION" => "N",
		"USE_BIG_DATA" => "Y",
		"BIG_DATA_RCM_TYPE" => "any",
		"COMMON_ADD_TO_BASKET_ACTION" => "ADD",
		"COMPONENT_TEMPLATE" => ".default",
		"USE_MAIN_ELEMENT_SECTION" => "Y",
		"SET_LAST_MODIFIED" => "N",
		"SECTION_BACKGROUND_IMAGE" => "-",
		"DETAIL_SET_CANONICAL_URL" => "Y",
		"DETAIL_BACKGROUND_IMAGE" => "-",
		"SHOW_DEACTIVATED" => "N",
		"PAGER_BASE_LINK_ENABLE" => "Y",
		"SHOW_404" => "Y",
		"MESSAGE_404" => "",
		"REVIEW_IBLOCK_TYPE" => "service",
		"REVIEW_IBLOCK_ID" => "32",
		"DISABLE_INIT_JS_IN_COMPONENT" => "N",
		"DETAIL_SET_VIEWED_IN_COMPONENT" => "N",
		"USE_GIFTS_DETAIL" => "N",
		"USE_GIFTS_SECTION" => "Y",
		"USE_GIFTS_MAIN_PR_SECTION_LIST" => "Y",
		"GIFTS_DETAIL_PAGE_ELEMENT_COUNT" => "12",
		"GIFTS_DETAIL_HIDE_BLOCK_TITLE" => "N",
		"GIFTS_DETAIL_BLOCK_TITLE" => "Выберите один из подарков",
		"GIFTS_DETAIL_TEXT_LABEL_GIFT" => "Подарок",
		"GIFTS_SECTION_LIST_PAGE_ELEMENT_COUNT" => "12",
		"GIFTS_SECTION_LIST_HIDE_BLOCK_TITLE" => "N",
		"GIFTS_SECTION_LIST_BLOCK_TITLE" => "Подарки к товарам этого раздела",
		"GIFTS_SECTION_LIST_TEXT_LABEL_GIFT" => "Подарок",
		"GIFTS_SHOW_DISCOUNT_PERCENT" => "Y",
		"GIFTS_SHOW_OLD_PRICE" => "Y",
		"GIFTS_SHOW_NAME" => "Y",
		"GIFTS_SHOW_IMAGE" => "Y",
		"GIFTS_MESS_BTN_BUY" => "Выбрать",
		"GIFTS_MAIN_PRODUCT_DETAIL_PAGE_ELEMENT_COUNT" => "12",
		"GIFTS_MAIN_PRODUCT_DETAIL_HIDE_BLOCK_TITLE" => "N",
		"GIFTS_MAIN_PRODUCT_DETAIL_BLOCK_TITLE" => "Выберите один из товаров, чтобы получить подарок",
		"SHOW_AVAILABLE_TAB" => "Y",
		"HIDE_AVAILABLE_TAB" => "N",
		"HIDE_MEASURES" => "N",
		"SHOW_SECTION_BANNER" => "Y",
		"FILE_404" => "",
		"HIDE_NOT_AVAILABLE_OFFERS" => "N",
		"DETAIL_STRICT_SECTION_CHECK" => "Y",
		"COMPATIBLE_MODE" => "Y",
		"PAGER_BASE_LINK" => "",
		"PAGER_PARAMS_NAME" => "arrPager",
		"USER_CONSENT" => "N",
		"USER_CONSENT_ID" => "0",
		"USER_CONSENT_IS_CHECKED" => "Y",
		"USER_CONSENT_IS_LOADED" => "N",
		"DISPLAY_CHEAPER" => "Y",
		"CHEAPER_FORM_ID" => "4",
		"DISPLAY_OFFERS_TABLE" => "Y",
		"OFFERS_TABLE_PAGER_COUNT" => "10",
		"OFFERS_TABLE_DISPLAY_PICTURE_COLUMN" => "Y",
		"SHOW_SERVICES" => "N",
		"SERVICES_IBLOCK_TYPE" => "info",
		"SERVICES_IBLOCK_ID" => "31",
		"SALE_IBLOCK_TYPE" => "catalog",
		"SALE_IBLOCK_ID" => "19",
		"HIDE_DELIVERY_CALC" => "N",
		"SHOW_MIDDLE_SLIDER" => "N",
		"LAZY_LOAD_PICTURES" => "Y",
		"CATALOG_SHOW_TAGS" => "Y",
		"CATALOG_MAX_TAGS" => "30",
		"CATALOG_TAGS_DETAIL_LINK_VARIANT" => "SEARCH",
		"CATALOG_TAGS_MAX_DEPTH_LEVEL" => "5",
		"CATALOG_MAX_VISIBLE_TAGS_DESKTOP" => "10",
		"CATALOG_MAX_VISIBLE_TAGS_MOBILE" => "6",
		"CATALOG_HIDE_TAGS_ON_MOBILE" => "N",
		"CATALOG_TAGS_SORT_FIELD" => "COUNTER",
		"CATALOG_TAGS_SORT_TYPE" => "DESC",
		"CATALOG_TAGS_DETAIL_SECTION_MAX_DELPH_LEVEL" => "5",
		"DISPLAY_SUBSCRIBE" => "N",
		"SUBSCRIBE_RUBRIC_ID" => "1",
		"SHOW_ADVANTAGES_IN_DETAIL" => "N",
		"ADVANTAGES_IN_DETAIL_IBLOCK_TYPE" => "info",
		"ADVANTAGES_IN_DETAIL_IBLOCK_ID" => "22",
		"DETAIL_CALCULATE_DELIVERY" => "N",
		"DETAIL_CALCULATE_DELIVERY_SHOW_IMAGES" => "Y",
		"DETAIL_ALLOW_ADD_REVIEW_NOT_REGISTER" => "N",
		"FILTER_INSTANT_RELOAD" => "Y",
		"CATALOG_TAGS_USE_IBLOCK_MAIN_SECTION" => "N",
		"DETAIL_CALCULATE_DELIVERY_GROUP_BUTTONS" => "",
		"DETAIL_COUNT_TOP_PROPERTIES" => "7",
		"DETAIL_DISABLE_PRINT_WEIGHT" => "N",
		"DETAIL_DISABLE_PRINT_DIMENSIONS" => "N",
		"MIDDLE_SLIDER_IBLOCK_TYPE" => "slider",
		"MIDDLE_SLIDER_IBLOCK_ID" => "8",
		"COMPOSITE_FRAME_MODE" => "A",
		"COMPOSITE_FRAME_TYPE" => "AUTO",
		"CATALOG_TAGS_SEARCH_PATH" => "/search/",
		"CATALOG_TAGS_SEARCH_PARAM" => "q",
		"SEF_URL_TEMPLATES" => [
			"sections" => "",
			"section" => "#SECTION_CODE_PATH#/",
			"element" => "#SECTION_CODE_PATH#/#ELEMENT_CODE#.html",
			"compare" => "compare/",
			"smart_filter" => "#SECTION_CODE_PATH#/filter/#SMART_FILTER_PATH#/",
		]
	],
	false
);?>
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
