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
	<?else:
		// Уточняющие чипы: не сайдбар каталога (это страница поиска, не каталог) — лёгкая
		// строка тегов, посчитанная ТОЛЬКО среди уже найденных CSearch ID. Та же тройка
		// свойств IBLOCK 19, что в сайдбаре catalog/index.php (см. feedback_bitrix_property_
		// naming_gotcha: MAIN_COLOR это "Цвет", не COATING_COLOR), "умные" счётчики по тому
		// же паттерну (исключаем свою группу при подсчёте, чтобы не занулять сами себя).
		$eportaPropDefs = [
			"style" => ["CODE" => "STYLE", "LABEL" => "Стиль", "FILTER_KEY" => "PROPERTY_STYLE"],
			"coating" => ["CODE" => "COATING", "LABEL" => "Покрытие", "FILTER_KEY" => "PROPERTY_COATING"],
			"color" => ["CODE" => "MAIN_COLOR", "LABEL" => "Цвет", "FILTER_KEY" => "PROPERTY_MAIN_COLOR"],
		];
		$eportaSelected = [];
		foreach ($eportaPropDefs as $eportaKey => $eportaDef) {
			$eportaEnumRes = \CIBlockPropertyEnum::GetList(
				["SORT" => "ASC", "VALUE" => "ASC"],
				["IBLOCK_ID" => 19, "CODE" => $eportaDef["CODE"]]
			);
			$eportaValues = [];
			while ($eportaEnum = $eportaEnumRes->Fetch()) {
				$eportaValues[(int)$eportaEnum["ID"]] = $eportaEnum["VALUE"];
			}
			$eportaPropDefs[$eportaKey]["VALUES"] = $eportaValues;
			$eportaRawSel = array_map("intval", (array)($_GET[$eportaKey] ?? []));
			$eportaSelected[$eportaKey] = array_values(array_intersect($eportaRawSel, array_keys($eportaValues)));
		}

		$eportaScopeFilter = ["ID" => $eportaFoundIds, "ACTIVE" => "Y"];
		$eportaBuildFilter = function ($excludeKey) use ($eportaScopeFilter, $eportaPropDefs, $eportaSelected) {
			$filter = $eportaScopeFilter;
			foreach ($eportaPropDefs as $key => $def) {
				if ($key === $excludeKey || empty($eportaSelected[$key])) continue;
				$filter[$def["FILTER_KEY"]] = $eportaSelected[$key];
			}
			return $filter;
		};

		$eportaChipGroups = [];
		$eportaArrFilter = $eportaScopeFilter;
		foreach ($eportaPropDefs as $eportaKey => $eportaDef) {
			$eportaCountFilter = $eportaBuildFilter($eportaKey);
			$eportaItems = [];
			foreach ($eportaDef["VALUES"] as $eportaEnumId => $eportaEnumValue) {
				$eportaCnt = \CIBlockElement::GetList([], $eportaCountFilter + [$eportaDef["FILTER_KEY"] => $eportaEnumId], false, false, ["ID"])->SelectedRowsCount();
				if ($eportaCnt < 1) continue;
				$eportaItems[] = [
					"ID" => $eportaEnumId,
					"VALUE" => $eportaEnumValue,
					"COUNT" => $eportaCnt,
					"CHECKED" => in_array($eportaEnumId, $eportaSelected[$eportaKey], true),
				];
			}
			usort($eportaItems, fn($a, $b) => $b["COUNT"] <=> $a["COUNT"]);
			// Строка чипов лёгкая по дизайну — не более 8 значений на группу, не полный список,
			// как в сайдбаре каталога.
			$eportaChipGroups[$eportaKey] = ["LABEL" => $eportaDef["LABEL"], "ITEMS" => array_slice($eportaItems, 0, 8)];

			if (!empty($eportaSelected[$eportaKey])) {
				$eportaArrFilter[$eportaDef["FILTER_KEY"]] = $eportaSelected[$eportaKey];
			}
		}

		$eportaFilteredCount = \CIBlockElement::GetList([], $eportaArrFilter, false, false, ["ID"])->SelectedRowsCount();
		$eportaHasActiveChips = array_filter($eportaSelected);
		$eportaBasePath = parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);

		// Toggle-ссылка на чип: добавляет/убирает конкретное значение в query-массиве
		// своей группы, сохраняя остальные GET-параметры (включая q и другие группы),
		// сбрасывает номер страницы при смене фильтра.
		$eportaChipUrl = function ($key, $valueId, $checked) use ($eportaBasePath) {
			$params = $_GET;
			unset($params["PAGEN_1"]);
			$current = array_map("intval", (array)($params[$key] ?? []));
			$current = $checked
				? array_values(array_diff($current, [$valueId]))
				: array_merge($current, [$valueId]);
			if ($current) {
				$params[$key] = $current;
			} else {
				unset($params[$key]);
			}
			return $eportaBasePath.($params ? "?".http_build_query($params) : "");
		};
		$eportaResetChipsUrl = $eportaBasePath."?q=".urlencode($eportaQuery);
	?>
	<div style="padding:20px 56px 4px">
		<form method="get" action="/search/" style="display:flex;align-items:center;gap:12px;max-width:640px;border:1.5px solid var(--border-soft, #e7e3db);border-radius:14px;padding:6px 6px 6px 18px;margin-bottom:16px">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a39e95" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
			<input type="text" name="q" value="<?=htmlspecialcharsbx($eportaQuery)?>" style="flex:1;border:none;outline:none;font:600 15px 'Manrope';background:transparent">
			<button type="submit" style="background:#e8820a;color:#fff;font:700 13px 'Manrope';padding:11px 20px;border-radius:10px;border:none;cursor:pointer">Найти</button>
		</form>
		<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Найдено <?=$eportaFilteredCount?> по запросу «<?=htmlspecialcharsbx($eportaQuery)?>»</h1>
	</div>

	<?if (array_filter($eportaChipGroups, fn($g) => $g["ITEMS"])):?>
	<div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 8px;padding:14px 56px 4px">
		<?foreach ($eportaChipGroups as $eportaGroupKey => $eportaGroup):
			if (!$eportaGroup["ITEMS"]) continue;
		?>
			<span style="font:700 12px 'Manrope';color:#a39e95;text-transform:uppercase;letter-spacing:.04em;margin-right:2px"><?=htmlspecialcharsbx($eportaGroup["LABEL"])?>:</span>
			<?foreach ($eportaGroup["ITEMS"] as $eportaItem):
				$eportaChecked = $eportaItem["CHECKED"];
			?>
			<a href="<?=htmlspecialcharsbx($eportaChipUrl($eportaGroupKey, $eportaItem["ID"], $eportaChecked))?>"
			   style="display:inline-flex;align-items:center;gap:6px;font:600 12.5px 'Manrope';padding:8px 13px;border-radius:20px;text-decoration:none;cursor:pointer;<?=$eportaChecked
					? "background:#e8820a;color:#fff;"
					: "background:#f3f1ec;color:#3a3631;"?>">
				<?=htmlspecialcharsbx($eportaItem["VALUE"])?>
				<?if ($eportaChecked):?><span>✕</span><?else:?><span style="color:<?=$eportaChecked ? "#fff" : "#c2bdb2"?>;font-weight:600"><?=$eportaItem["COUNT"]?></span><?endif;?>
			</a>
			<?endforeach;?>
		<?endforeach;?>
		<?if ($eportaHasActiveChips):?>
			<a href="<?=htmlspecialcharsbx($eportaResetChipsUrl)?>" style="font:700 12.5px 'Manrope';color:#c2670a;padding:8px 6px;cursor:pointer;text-decoration:none;margin-left:4px">Сбросить фильтры</a>
		<?endif;?>
	</div>
	<?endif;?>

	<?if ($eportaFilteredCount < 1):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-empty">
			<div class="lk-empty-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
			</div>
			<div class="lk-empty-title">По этим фильтрам ничего не нашлось</div>
			<div class="lk-empty-actions">
				<a href="<?=htmlspecialcharsbx($eportaResetChipsUrl)?>" class="lk-btn-primary">Сбросить фильтры</a>
			</div>
		</div>
	</div>
	</div>
	<?else:?>
	<div style="padding:8px 56px 28px">
		<?
			$arrFilter = $eportaArrFilter;
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
					"PAGE_ELEMENT_COUNT" => "12",
					"LINE_ELEMENT_COUNT" => "4",
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