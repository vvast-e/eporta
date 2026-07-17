<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Страница поиска");
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
	// Этап «Поиск»: под шаблоном eporta вендорский dresscode:search обходится целиком —
	// его .default-шаблон крашится 500-й на пустых результатах (несуществующий ключ
	// $arResult["SECTIONS"], всегда вызывает bitrix:menu/emptyMenu, которого нет в eporta),
	// не экранирует $_REQUEST["q"] в заголовках (reflected XSS) и рендерит сетку на jQuery,
	// которой в eporta нет. Вместо этого — CSearch (та же морфология/раскладка, что у вендора,
	// см. component.php) + переопределённая карточка bitrix:catalog.section. Вендорская ветка
	// ниже (dverimarket.ru и любой другой шаблон) не изменена.
	$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):
	\Bitrix\Main\Loader::includeModule("iblock");
	\Bitrix\Main\Loader::includeModule("search");
	\Bitrix\Main\Loader::includeModule("catalog");

	$eportaQuery = trim((string)\Bitrix\Main\Context::getCurrent()->getRequest()->get("q"));
	$eportaFoundIds = [];

	if ($eportaQuery !== "" && mb_strlen($eportaQuery) > 1) {
		$obSearch = new CSearch;
		$obSearch->Search(
			[
				"QUERY" => $eportaQuery,
				"SITE_ID" => SITE_ID,
				"MODULE_ID" => "iblock",
				"PARAM2" => $catalogIblockId,
			],
			[],
			["STEMMING" => "N"]
		);
		while ($searchItem = $obSearch->fetch()) {
			if (is_numeric($searchItem["ITEM_ID"])) {
				$eportaFoundIds[(int)$searchItem["ITEM_ID"]] = (int)$searchItem["ITEM_ID"];
			}
		}
	}

	$APPLICATION->SetTitle("Результаты поиска — «".htmlspecialcharsbx($eportaQuery)."»");
?>
	<?if (empty($eportaFoundIds)):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-empty">
			<div class="lk-empty-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
			</div>
			<div class="lk-empty-title">Ничего не найдено по запросу «<?=htmlspecialcharsbx($eportaQuery)?>»</div>
			<div class="lk-empty-actions">
				<a href="/catalog/" class="lk-btn-primary">В каталог</a>
			</div>
		</div>
	</div>
	</div>
	<?else:?>
	<div style="padding:8px 56px 4px">
		<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Найдено <?=count($eportaFoundIds)?> по запросу «<?=htmlspecialcharsbx($eportaQuery)?>»</h1>
	</div>
	<div style="padding:8px 56px 28px">
		<?
			$arrFilter = ["ID" => $eportaFoundIds, "ACTIVE" => "Y"];
			$APPLICATION->IncludeComponent(
				"bitrix:catalog.section",
				".default",
				[
					"IBLOCK_TYPE" => "catalog",
					"IBLOCK_ID" => "19",
					"SECTION_ID" => false,
					"SECTION_CODE" => "",
					"SECTION_USER_FIELDS" => [],
					"ELEMENT_SORT_FIELD" => "sort",
					"ELEMENT_SORT_ORDER" => "asc",
					"ELEMENT_SORT_FIELD2" => "id",
					"ELEMENT_SORT_ORDER2" => "desc",
					"FILTER_NAME" => "arrFilter",
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
					"CACHE_FILTER" => "Y",
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
			);
		?>
	</div>
	<?endif;?>
<?else:?>
<?$APPLICATION->IncludeComponent(
	"dresscode:search",
	".default",
	array(
		"IBLOCK_TYPE" => "catalog",
		"IBLOCK_ID" => $catalogIblockId,
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "3600000",
		"PRICE_CODE" => $arPriceCodes,
		"COMPONENT_TEMPLATE" => ".default",
		"CONVERT_CURRENCY" => "Y",
		"CURRENCY_ID" => "RUB",
		"PROPERTY_CODE" => array(
			0 => "OFFERS",
			1 => "ATT_BRAND",
			2 => "COLOR",
			3 => "ZOOM2",
			4 => "BATTERY_LIFE",
			5 => "SWITCH",
			6 => "GRAF_PROC",
			7 => "LENGTH_OF_CORD",
			8 => "DISPLAY",
			9 => "LOADING_LAUNDRY",
			10 => "FULL_HD_VIDEO_RECORD",
			11 => "INTERFACE",
			12 => "COMPRESSORS",
			13 => "Number_of_Outlets",
			14 => "MAX_RESOLUTION_VIDEO",
			15 => "MAX_BUS_FREQUENCY",
			16 => "MAX_RESOLUTION",
			17 => "FREEZER",
			18 => "POWER_SUB",
			19 => "POWER",
			20 => "HARD_DRIVE_SPACE",
			21 => "MEMORY",
			22 => "OS",
			23 => "ZOOM",
			24 => "PAPER_FEED",
			25 => "SUPPORTED_STANDARTS",
			26 => "VIDEO_FORMAT",
			27 => "SUPPORT_2SIM",
			28 => "MP3",
			29 => "ETHERNET_PORTS",
			30 => "MATRIX",
			31 => "CAMERA",
			32 => "PHOTOSENSITIVITY",
			33 => "DEFROST",
			34 => "SPEED_WIFI",
			35 => "SPIN_SPEED",
			36 => "PRINT_SPEED",
			37 => "SOCKET",
			38 => "IMAGE_STABILIZER",
			39 => "GSM",
			40 => "SIM",
			41 => "TYPE",
			42 => "MEMORY_CARD",
			43 => "TYPE_BODY",
			44 => "TYPE_MOUSE",
			45 => "TYPE_PRINT",
			46 => "CONNECTION",
			47 => "TYPE_OF_CONTROL",
			48 => "TYPE_DISPLAY",
			49 => "TYPE2",
			50 => "REFRESH_RATE",
			51 => "RANGE",
			52 => "AMOUNT_MEMORY",
			53 => "MEMORY_CAPACITY",
			54 => "VIDEO_BRAND",
			55 => "DIAGONAL",
			56 => "RESOLUTION",
			57 => "TOUCH",
			58 => "CORES",
			59 => "LINE_PROC",
			60 => "PROCESSOR",
			61 => "CLOCK_SPEED",
			62 => "TYPE_PROCESSOR",
			63 => "PROCESSOR_SPEED",
			64 => "HARD_DRIVE",
			65 => "HARD_DRIVE_TYPE",
			66 => "Number_of_memory_slots",
			67 => "MAXIMUM_MEMORY_FREQUENCY",
			68 => "TYPE_MEMORY",
			69 => "BLUETOOTH",
			70 => "FM",
			71 => "GPS",
			72 => "HDMI",
			73 => "SMART_TV",
			74 => "USB",
			75 => "WIFI",
			76 => "FLASH",
			77 => "ROTARY_DISPLAY",
			78 => "SUPPORT_3D",
			79 => "SUPPORT_3G",
			80 => "WITH_COOLER",
			81 => "FINGERPRINT",
			82 => "COLLECTION",
			83 => "TOTAL_OUTPUT_POWER",
			84 => "HTML",
			85 => "VID_ZASTECHKI",
			86 => "VID_SUMKI",
			87 => "VIDEO",
			88 => "PROFILE",
			89 => "VYSOTA_RUCHEK",
			90 => "GAS_CONTROL",
			91 => "WARRANTY",
			92 => "GRILL",
			93 => "MORE_PROPERTIES",
			94 => "GENRE",
			95 => "OTSEKOV",
			96 => "CONVECTION",
			97 => "INTAKE_POWER",
			98 => "NAZNAZHENIE",
			99 => "BULK",
			100 => "PODKLADKA",
			101 => "SHOW_MENU",
			102 => "SURFACE_COATING",
			103 => "brand_tyres",
			104 => "SEASON",
			105 => "SEASONOST",
			106 => "DUST_COLLECTION",
			107 => "REF",
			108 => "COUNTRY_BRAND",
			109 => "DRYING",
			110 => "REMOVABLE_TOP_COVER",
			111 => "CONTROL",
			112 => "FINE_FILTER",
			113 => "FORM_FAKTOR",
			114 => "SKU_COLOR",
			115 => "USER_ID",
			116 => "BLOG_POST_ID",
			117 => "CML2_ARTICLE",
			118 => "DELIVERY",
			119 => "BLOG_COMMENTS_CNT",
			120 => "VOTE_COUNT",
			121 => "MARKER_PHOTO",
			122 => "NEW",
			123 => "DELIVERY_DESC",
			124 => "SIMILAR_PRODUCT",
			125 => "SALE",
			126 => "RATING",
			127 => "PICKUP",
			128 => "RELATED_PRODUCT",
			129 => "VOTE_SUM",
			130 => "MARKER",
			131 => "POPULAR",
			132 => "WEIGHT",
			133 => "HEIGHT",
			134 => "DEPTH",
			135 => "WIDTH",
			136 => "",
		)
	),
	false
);?>
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>