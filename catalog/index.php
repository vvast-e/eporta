<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$eportaCatalogPageTitle = !empty($_GET["sale"]) ? "Распродажа" : (!empty($_GET["new"]) ? "Новинки" : "Каталог товаров");
$APPLICATION->SetPageProperty("title", $eportaCatalogPageTitle);
$APPLICATION->SetTitle($eportaCatalogPageTitle);
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

	// Бесконечная подгрузка: ?eporta_ajax=grid&PAGEN_1=N — тот же список/фильтр/сортировка,
	// что и обычный запрос страницы, но в ответ уходит только фрагмент сетки+пейджера
	// (см. короткое замыкание перед выводом HTML ниже), без хедера/сайдбара/подвала.
	$eportaIsAjaxGrid = $isEportaTemplate && !$isEportaProductDetail && ($_GET["eporta_ajax"] ?? "") === "grid";

	// Число карточек в строке зафиксировано — сетка адаптивная (CSS-брейкпоинты в
	// template_styles.css), выбор "4/5/6" был лишним контролом поверх уже готового адаптива.
	// PAGE_ELEMENT_COUNT остаётся кратным этому числу (3 полных ряда), чтобы на последней
	// странице не было неполного ряда и пустого места перед пагинацией/футером.
	$eportaCols = 4;
	$eportaPageElementCount = $eportaCols * 3;

	// Вид отображения — плитка/список, источник истины кука eporta_view (аналогично тому,
	// как раньше хранился eporta_cols): визуальный режим, бэкенд не трогает.
	$eportaView = ($_COOKIE["eporta_view"] ?? "") === "list" ? "list" : "grid";

	// Сортировка — GET-параметр sort с whitelist (по образцу per_page в search/index.php),
	// значение реально прокидывается в ELEMENT_SORT_FIELD/ORDER компонента ниже. Раньше здесь
	// был декоративный <div>"Сначала популярные"</div> без списка опций и без обработчика —
	// сортировка была всегда одна и переключить её было нельзя.
	$eportaSortOptions = [
		"popular" => ["LABEL" => "Сначала популярные", "FIELD" => "PROPERTY_RATING", "ORDER" => "DESC"],
		"price_asc" => ["LABEL" => "Сначала дешевле", "FIELD" => "CATALOG_PRICE_1", "ORDER" => "ASC"],
		"price_desc" => ["LABEL" => "Сначала дороже", "FIELD" => "CATALOG_PRICE_1", "ORDER" => "DESC"],
		"new" => ["LABEL" => "Сначала новые", "FIELD" => "ID", "ORDER" => "DESC"],
		"name" => ["LABEL" => "По названию", "FIELD" => "NAME", "ORDER" => "ASC"],
	];
	$eportaSort = isset($_GET["sort"]) && isset($eportaSortOptions[$_GET["sort"]]) ? $_GET["sort"] : "popular";

	// Этап 5 — Коллекции: подразделы раздела 183 "Коллекции" в IBLOCK 19 (плюс Dorsum-F/
	// Dorsum-Eco, добавлены 2026-08-10 — см. collection/index.php). URL приходит по штатному
	// SEF-шаблону как /catalog/collections/<code>/ (SECTION_PAGE_URL секции). Список ID
	// зафиксирован явно (фильтр CIBlockSection::GetList по IBLOCK_SECTION_ID не работает как
	// ожидалось, возвращает вообще все секции инфоблока), поэтому матчим CODE только среди этих
	// ID — иначе обычный раздел каталога с тем же кодом сегмента URL мог бы случайно попасть
	// под резолвер коллекции.
	$eportaCollectionIds = [184, 185, 186, 187, 188, 189, 190, 191, 193, 194];
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

	// Сайдбар фильтров (сквозной для /catalog/ и всех коллекций): реальные свойства IBLOCK 19.
	// STYLE ("Стиль"), COATING ("Покрытие"), MAIN_COLOR ("Оттенок" — используется как "Цвет" в
	// сайдбаре, обычный чекбокс-список без свотчей: COATING_COLOR отпадает — 70+ конкретных
	// оттенков отделки, не сводится к дизайн-кружкам; MAIN_COLOR даёт вменяемые ~32 значения).
	// Цена — PRICE_CODE=BASE, CATALOG_GROUP_ID=1 (CATALOG_PRICE_1 в фильтре/сортировке CIBlockElement).
	$eportaFilterGroups = [];
	$eportaArrFilter = [];
	$eportaActiveChips = [];
	$eportaPriceMinBound = 0;
	$eportaPriceMaxBound = 0;
	$eportaPriceSelMin = null;
	$eportaPriceSelMax = null;
	$eportaFoundCount = 0;
	if ($isEportaTemplate && !$isEportaProductDetail) {
		\Bitrix\Main\Loader::includeModule("iblock");
		\Bitrix\Main\Loader::includeModule("catalog");

		$eportaFilterSectionId = $eportaCollectionSection ? (int)$eportaCollectionSection["ID"] : false;
		$eportaScopeFilter = ["IBLOCK_ID" => 19, "ACTIVE" => "Y"];
		if ($eportaFilterSectionId) {
			$eportaScopeFilter["SECTION_ID"] = $eportaFilterSectionId;
		}

		// Категория (PROPERTY_CATEGORY, строковое свойство — см. inc/categories.php) — привязка
		// пунктов мега-меню "Тип дверей" и плиток "Каталог по категориям" на главной. Не enum,
		// поэтому фильтруется по массиву точных строковых значений (ALIASES), а не по ID варианта.
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/templates/eporta/inc/categories.php");
		$eportaCategoryMap = eportaGetCategoryMap();
		$eportaSelectedCategory = (!empty($_GET["category"]) && isset($eportaCategoryMap[$_GET["category"]]))
			? $_GET["category"] : null;
		if ($eportaSelectedCategory) {
			$eportaScopeFilter["PROPERTY_CATEGORY"] = $eportaCategoryMap[$eportaSelectedCategory]["ALIASES"];
			$eportaActiveChips[] = ["LABEL" => $eportaCategoryMap[$eportaSelectedCategory]["LABEL"], "REMOVE_KEY" => "category", "REMOVE_VALUE" => null];
		}

		// "Распродажа" (шапка, /catalog/?sale=1) — те же товары, для которых карточка
		// показывает бейдж скидки (см. $hasDiscount в catalog.section/.default/template.php).
		$eportaOnlySale = !empty($_GET["sale"]);
		if ($eportaOnlySale) {
			$eportaScopeFilter[">PROPERTY_DISCOUNT"] = 0;
			$eportaActiveChips[] = ["LABEL" => "Распродажа", "REMOVE_KEY" => "sale", "REMOVE_VALUE" => null];
		}

		// "Новинки" (шапка, /catalog/?new=1) — те же товары, что дают бейдж "Новинка"
		// ($isNew = rating <= 0 в том же template.php). Старый CIBlockElement::GetList (в отличие
		// от D7 ORM) НЕ поддерживает вложенные подфильтры с LOGIC=>OR — такой ключ просто молча
		// игнорируется, и фильтр не отсекает ничего (было замечено вживую: "Новинки" находили
		// все 68 товаров). Импортёр eporta всегда пишет RATING (хотя бы 0, см. eportaImportOneProduct
		// в lib.php), так что в реальных данных свойство не бывает вообще незаполненным — простое
		// "<=0" достаточно, без подстраховки на "PROPERTY_RATING => false".
		$eportaOnlyNew = !empty($_GET["new"]);
		if ($eportaOnlyNew) {
			$eportaScopeFilter["<=PROPERTY_RATING"] = 0;
			$eportaActiveChips[] = ["LABEL" => "Новинки", "REMOVE_KEY" => "new", "REMOVE_VALUE" => null];
		}

		// Свойства-чекбоксы: код свойства => [ query-параметр, подпись, ключ фильтра CIBlockElement ]
		$eportaPropDefs = [
			"style" => ["CODE" => "STYLE", "LABEL" => "Стиль", "FILTER_KEY" => "PROPERTY_STYLE"],
			"coating" => ["CODE" => "COATING", "LABEL" => "Покрытие", "FILTER_KEY" => "PROPERTY_COATING"],
			"color" => ["CODE" => "MAIN_COLOR", "LABEL" => "Цвет", "FILTER_KEY" => "PROPERTY_MAIN_COLOR"],
		];

		// Выбранные значения из GET, санитизация — только реально существующие ID вариантов свойства.
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

		// Реальный диапазон цен в текущей области (раздел коллекции или весь каталог).
		$eportaPriceBoundFilter = $eportaScopeFilter + [">CATALOG_PRICE_1" => 0];
		$eportaPriceMinRow = \CIBlockElement::GetList(["CATALOG_PRICE_1" => "ASC"], $eportaPriceBoundFilter, false, ["nTopCount" => 1], ["ID", "CATALOG_PRICE_1"])->Fetch();
		$eportaPriceMaxRow = \CIBlockElement::GetList(["CATALOG_PRICE_1" => "DESC"], $eportaPriceBoundFilter, false, ["nTopCount" => 1], ["ID", "CATALOG_PRICE_1"])->Fetch();
		$eportaPriceMinBound = $eportaPriceMinRow ? (int)floor($eportaPriceMinRow["CATALOG_PRICE_1"]) : 0;
		$eportaPriceMaxBound = $eportaPriceMaxRow ? (int)ceil($eportaPriceMaxRow["CATALOG_PRICE_1"]) : 0;

		$eportaPriceSelMin = isset($_GET["price_min"]) && $_GET["price_min"] !== "" ? max($eportaPriceMinBound, (int)$_GET["price_min"]) : $eportaPriceMinBound;
		$eportaPriceSelMax = isset($_GET["price_max"]) && $_GET["price_max"] !== "" ? min($eportaPriceMaxBound, (int)$_GET["price_max"]) : $eportaPriceMaxBound;
		if ($eportaPriceSelMin > $eportaPriceSelMax) {
			$eportaPriceSelMin = $eportaPriceMinBound;
			$eportaPriceSelMax = $eportaPriceMaxBound;
		}
		$eportaPriceActive = ($eportaPriceSelMin > $eportaPriceMinBound || $eportaPriceSelMax < $eportaPriceMaxBound);

		// Сборка фильтра CIBlockElement/CIBlockElement::GetList, исключая одну группу свойств —
		// нужно для "умных" счётчиков: сколько товаров останется, если добавить конкретное значение
		// к уже выбранным фильтрам ДРУГИХ групп.
		$eportaBuildFilter = function ($excludeKey) use ($eportaScopeFilter, $eportaPropDefs, $eportaSelected, $eportaPriceActive, $eportaPriceSelMin, $eportaPriceSelMax) {
			$filter = $eportaScopeFilter;
			foreach ($eportaPropDefs as $key => $def) {
				if ($key === $excludeKey || empty($eportaSelected[$key])) continue;
				$filter[$def["FILTER_KEY"]] = $eportaSelected[$key];
			}
			if ($eportaPriceActive) {
				$filter[">=CATALOG_PRICE_1"] = $eportaPriceSelMin;
				$filter["<=CATALOG_PRICE_1"] = $eportaPriceSelMax;
			}
			return $filter;
		};

		// Счётчики по каждому значению чекбокса (учитывая уже применённые фильтры других групп).
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
			$eportaFilterGroups[$eportaKey] = ["LABEL" => $eportaDef["LABEL"], "ITEMS" => $eportaItems];

			if (!empty($eportaSelected[$eportaKey])) {
				$eportaArrFilter[$eportaDef["FILTER_KEY"]] = $eportaSelected[$eportaKey];
				foreach ($eportaDef["VALUES"] as $eportaEnumId => $eportaEnumValue) {
					if (in_array($eportaEnumId, $eportaSelected[$eportaKey], true)) {
						$eportaActiveChips[] = ["LABEL" => $eportaEnumValue, "REMOVE_KEY" => $eportaKey, "REMOVE_VALUE" => $eportaEnumId];
					}
				}
			}
		}

		if ($eportaPriceActive) {
			$eportaArrFilter[">=CATALOG_PRICE_1"] = $eportaPriceSelMin;
			$eportaArrFilter["<=CATALOG_PRICE_1"] = $eportaPriceSelMax;
			$eportaActiveChips[] = ["LABEL" => number_format($eportaPriceSelMin, 0, "", " ")." – ".number_format($eportaPriceSelMax, 0, "", " ")." ₽", "REMOVE_KEY" => "price", "REMOVE_VALUE" => null];
		}

		// Раскладка "круговая по моделям": раньше здесь схлопывали выдачу до одного
		// представителя на модель (иначе одна модель со множеством цветов — в выгрузке каждый
		// цвет отдельный элемент, см. eportaImportComposeName — занимала всю страницу своими же
		// вариантами), но это ПРЯТАЛО остальные цвета из каталога вовсе. По решению пользователя:
		// показываем ВСЕ товары (ничего не скрываем), а очерёдность строим так, чтобы первые
		// страницы показывали максимум РАЗНЫХ моделей, а не все цвета одной подряд. Лёгкий
		// пред-запрос ID+MODEL с тем же фильтром, что и у сетки (включая SECTION_ID коллекции),
		// плюс поле текущей сортировки — группируем по MODEL, внутри каждой группы сортируем по
		// выбранному критерию ($eportaSort), группы тоже упорядочиваем по лучшему товару в них
		// (тем же критерием). Затем round-robin: раунд 1 — по одному (лучшему) товару из каждой
		// модели, раунд 2 — по второму и т.д. Ничего не теряется, просто сначала показываются
		// разные модели, а повторные цвета одной модели уходят дальше по списку/страницам.
		$eportaGroupFilter = $eportaScopeFilter + $eportaArrFilter + ["IBLOCK_ID" => 19, "ACTIVE" => "Y"];
		if ($eportaCollectionSection) {
			$eportaGroupFilter["SECTION_ID"] = $eportaCollectionSection["ID"];
		}
		$eportaSortField = $eportaSortOptions[$eportaSort]["FIELD"];
		$eportaSortOrder = $eportaSortOptions[$eportaSort]["ORDER"];
		$eportaGroupSelect = ["ID", "PROPERTY_MODEL"];
		if ($eportaSortField === "PROPERTY_RATING") {
			$eportaGroupSelect[] = "PROPERTY_RATING";
		} elseif ($eportaSortField === "CATALOG_PRICE_1") {
			$eportaGroupSelect[] = "CATALOG_PRICE_1";
		} elseif ($eportaSortField === "NAME") {
			$eportaGroupSelect[] = "NAME";
		}
		$eportaModelGroupsRes = CIBlockElement::GetList([], $eportaGroupFilter, false, false, $eportaGroupSelect);
		$eportaModelGroups = [];
		while ($eportaGroupRow = $eportaModelGroupsRes->Fetch()) {
			$eportaGroupKey = $eportaGroupRow["PROPERTY_MODEL_VALUE"] ?: ("__id_" . $eportaGroupRow["ID"]);
			if ($eportaSortField === "PROPERTY_RATING") {
				$eportaSortVal = (float)($eportaGroupRow["PROPERTY_RATING_VALUE"] ?? 0);
			} elseif ($eportaSortField === "CATALOG_PRICE_1") {
				$eportaSortVal = (float)($eportaGroupRow["CATALOG_PRICE_1"] ?? 0);
			} elseif ($eportaSortField === "NAME") {
				$eportaSortVal = (string)($eportaGroupRow["NAME"] ?? "");
			} else {
				$eportaSortVal = (int)$eportaGroupRow["ID"];
			}
			$eportaModelGroups[$eportaGroupKey][] = ["ID" => (int)$eportaGroupRow["ID"], "SORT" => $eportaSortVal];
		}

		// Компаратор по выбранной сортировке, с ID как стабильным тай-брейком (иначе порядок
		// "плывёт" между запросами при равных значениях — важно для пагинации).
		$eportaSortCmp = function ($a, $b) use ($eportaSortOrder) {
			if ($a["SORT"] === $b["SORT"]) {
				return $b["ID"] <=> $a["ID"];
			}
			$cmp = $a["SORT"] <=> $b["SORT"];
			return $eportaSortOrder === "ASC" ? $cmp : -$cmp;
		};
		foreach ($eportaModelGroups as &$eportaGroupItems) {
			usort($eportaGroupItems, $eportaSortCmp);
		}
		unset($eportaGroupItems);
		// Сами модели тоже упорядочиваем по их лучшему товару — модели-лидеры выбранной
		// сортировки идут в раскладке первыми среди "раунда 1".
		uasort($eportaModelGroups, function ($eportaGroupA, $eportaGroupB) use ($eportaSortCmp) {
			return $eportaSortCmp($eportaGroupA[0], $eportaGroupB[0]);
		});

		$eportaOrderedIds = [];
		$eportaMaxGroupSize = $eportaModelGroups ? max(array_map("count", $eportaModelGroups)) : 0;
		for ($eportaRound = 0; $eportaRound < $eportaMaxGroupSize; $eportaRound++) {
			foreach ($eportaModelGroups as $eportaGroupItems) {
				if (isset($eportaGroupItems[$eportaRound])) {
					$eportaOrderedIds[] = $eportaGroupItems[$eportaRound]["ID"];
				}
			}
		}
		$eportaModelCount = count($eportaModelGroups);
		$eportaFoundCount = count($eportaOrderedIds);

		// Пагинация — своя (не компонентная): bitrix:catalog.section всегда пересортировывает
		// выборку по ELEMENT_SORT_FIELD, наш круговой порядок в $eportaOrderedIds потерялся бы.
		// Тот же приём, что в search/index.php — нарезаем ID текущей страницы сами, компоненту
		// ниже отдаём готовый список ID (arrFilter["ID"]), его собственный пейджер отключаем.
		$eportaTotalPages = max(1, (int)ceil($eportaFoundCount / $eportaPageElementCount));
		$eportaCurPage = max(1, min($eportaTotalPages, (int)($_GET["PAGEN_1"] ?? 1)));
		$eportaPageIds = array_slice($eportaOrderedIds, ($eportaCurPage - 1) * $eportaPageElementCount, $eportaPageElementCount);

		// URL "Сбросить всё" — текущий путь без query-строки.
		$eportaResetUrl = parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);

		// Ссылка на конкретную страницу каталога (сохраняет остальные GET-параметры — фильтры,
		// сортировку), используется и обычным пейджером, и JS-подгрузкой (см. ниже).
		$eportaCatalogPageUrl = function ($eportaPage) use ($eportaResetUrl) {
			$eportaParams = $_GET;
			unset($eportaParams["eporta_ajax"]);
			if ($eportaPage > 1) {
				$eportaParams["PAGEN_1"] = $eportaPage;
			} else {
				unset($eportaParams["PAGEN_1"]);
			}
			return $eportaResetUrl . ($eportaParams ? "?" . http_build_query($eportaParams) : "");
		};

		// Сетка товаров — общая функция для обычного рендера страницы и AJAX-подгрузки (см.
		// короткое замыкание ниже, ?eporta_ajax=grid), чтобы не дублировать параметры компонента.
		// FILTER_NAME=>"arrFilter" ниже читает ГЛОБАЛЬНУЮ переменную "arrFilter" — обязательно
		// global, иначе компонент её не увидит (в отличие от вызова на верхнем уровне скрипта,
		// внутри функции локальная переменная не совпадает с глобальной).
		function eportaRenderCatalogGrid($eportaIds, $eportaSectionId, $eportaSortField, $eportaSortOrder, $eportaColsArg, $eportaPageElementCountArg) {
			global $arrFilter, $APPLICATION;
			// $eportaIds уже полностью отфильтрован (категория/распродажа/новинки/чекбоксы/цена/
			// коллекция — см. $eportaGroupFilter выше), доп. условия компоненту не нужны.
			$arrFilter = ["ID" => $eportaIds ?: [0]];
			$APPLICATION->IncludeComponent(
				"bitrix:catalog.section",
				".default",
				[
					"IBLOCK_TYPE" => "catalog",
					"IBLOCK_ID" => "19",
					"SECTION_ID" => $eportaSectionId,
					"SECTION_CODE" => "",
					"SECTION_USER_FIELDS" => [],
					// Сортировка внутри страницы — так же управляется дропдауном ($eportaSort);
					// реальную раскладку "все модели вперемешку" даёт уже готовый порядок $eportaIds,
					// это поле лишь стабилизирует порядок внутри одной страницы компонента.
					"ELEMENT_SORT_FIELD" => $eportaSortField,
					"ELEMENT_SORT_ORDER" => $eportaSortOrder,
					"ELEMENT_SORT_FIELD2" => "id",
					"ELEMENT_SORT_ORDER2" => "desc",
					"FILTER_NAME" => "arrFilter",
					"HIDE_NOT_AVAILABLE" => "N",
					"HIDE_NOT_AVAILABLE_OFFERS" => "N",
					"PAGE_ELEMENT_COUNT" => (string)$eportaPageElementCountArg,
					"LINE_ELEMENT_COUNT" => (string)$eportaColsArg,
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
					// Свой пейджер рендерится отдельно (eportaRenderCatalogPager) — компонентный
					// отключаем, иначе он бы пересчитывал страницы по PAGE_ELEMENT_COUNT от полного
					// arrFilter, а не от уже нарезанного вручную $eportaIds.
					"DISPLAY_TOP_PAGER" => "N",
					"DISPLAY_BOTTOM_PAGER" => "N",
					"ADD_SECTIONS_CHAIN" => "N",
					"COMPATIBLE_MODE" => "Y",
					"AJAX_MODE" => "N",
					"TEMPLATE_THEME" => "site",
				],
				false
			);
		}

		// Свой пейджер (компонентный отключён выше) — та же разметка/классы .bx-pagination, что
		// на /search/ (search/index.php), стили уже есть в template_styles.css. data-атрибуты —
		// для JS-подгрузки при скролле (assets/app.js), не влияют на обычные ссылки-переходы.
		function eportaRenderCatalogPager($eportaCurPageArg, $eportaTotalPagesArg, callable $eportaUrlFn) {
			if ($eportaTotalPagesArg <= 1) {
				return;
			}
			$eportaPagerWindow = 2;
			$eportaPagerFrom = max(1, $eportaCurPageArg - $eportaPagerWindow);
			$eportaPagerTo = min($eportaTotalPagesArg, $eportaCurPageArg + $eportaPagerWindow);
			?>
			<div class="bx-pagination" id="eportaCatalogPager" data-cur-page="<?=$eportaCurPageArg?>" data-total-pages="<?=$eportaTotalPagesArg?>" data-next-url="<?=$eportaCurPageArg < $eportaTotalPagesArg ? htmlspecialcharsbx($eportaUrlFn($eportaCurPageArg + 1)) : ""?>">
				<div class="bx-pagination-container">
					<ul>
						<?if ($eportaCurPageArg > 1):?>
						<li class="bx-pag-prev"><a href="<?=htmlspecialcharsbx($eportaUrlFn($eportaCurPageArg - 1))?>"><span>Назад</span></a></li>
						<?else:?>
						<li class="bx-pag-prev"><span>Назад</span></li>
						<?endif;?>

						<?if ($eportaPagerFrom > 1):?>
						<li><a href="<?=htmlspecialcharsbx($eportaUrlFn(1))?>"><span>1</span></a></li>
						<?endif;?>

						<?for ($eportaP = $eportaPagerFrom; $eportaP <= $eportaPagerTo; $eportaP++):?>
							<?if ($eportaP == $eportaCurPageArg):?>
							<li class="bx-active"><span><?=$eportaP?></span></li>
							<?else:?>
							<li><a href="<?=htmlspecialcharsbx($eportaUrlFn($eportaP))?>"><span><?=$eportaP?></span></a></li>
							<?endif;?>
						<?endfor;?>

						<?if ($eportaPagerTo < $eportaTotalPagesArg):?>
						<li><a href="<?=htmlspecialcharsbx($eportaUrlFn($eportaTotalPagesArg))?>"><span><?=$eportaTotalPagesArg?></span></a></li>
						<?endif;?>

						<?if ($eportaCurPageArg < $eportaTotalPagesArg):?>
						<li class="bx-pag-next"><a href="<?=htmlspecialcharsbx($eportaUrlFn($eportaCurPageArg + 1))?>"><span>Вперед</span></a></li>
						<?else:?>
						<li class="bx-pag-next"><span>Вперед</span></li>
						<?endif;?>
					</ul>
				</div>
			</div>
			<?php
		}

		// Кнопка "Показать ещё" — общая для обычного рендера страницы и AJAX-подгрузки (см.
		// eportaIsAjaxGrid ниже), как и eportaRenderCatalogGrid/eportaRenderCatalogPager. Раньше
		// была захардкожена только в обычном рендере — AJAX-ответ её не содержал, поэтому JS
		// (app.js) не находил #eportaCatalogLoadMore в подгруженном фрагменте и удалял кнопку
		// после первого клика, даже если следующая страница ещё есть.
		function eportaRenderCatalogLoadMoreBtn($eportaCurPageArg, $eportaTotalPagesArg) {
			if ($eportaCurPageArg >= $eportaTotalPagesArg) {
				return;
			}
			?>
			<button type="button" id="eportaCatalogLoadMore" style="display:block;margin:20px auto 0;padding:13px 32px;background:#fff;color:#2b2620;font:700 14px 'Manrope';border:1.6px solid #e7e3db;border-radius:12px;cursor:pointer">Показать ещё</button>
			<?php
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
			"PROPERTY_CODE" => ["STYLE", "COATING", "COATING_COLOR", "GLAZING", "MAIN_COLOR", "CML2_ARTICLE", "RATING", "VOTE_COUNT", "PRODUCT_DAY", "COLLECTION", "MODEL", "SIZES"],
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

	<?php if ($eportaIsAjaxGrid):
		// Бесконечная подгрузка: только фрагмент сетки+пейджера, без хедера/сайдбара/футера.
		// RestartBuffer сбрасывает всё, что core Bitrix уже вывел в буфер до этой точки
		// (шапка/нав из header.php) — обычный приём для ajax-эндпоинтов внутри страниц движка.
		$APPLICATION->RestartBuffer();
		header("Content-Type: text/html; charset=UTF-8");
		header("Cache-Control: no-store");
		eportaRenderCatalogGrid(
			$eportaPageIds ?: [0],
			$eportaCollectionSection ? $eportaCollectionSection["ID"] : false,
			$eportaSortOptions[$eportaSort]["FIELD"],
			$eportaSortOptions[$eportaSort]["ORDER"],
			$eportaCols,
			$eportaPageElementCount
		);
		eportaRenderCatalogPager($eportaCurPage, $eportaTotalPages, $eportaCatalogPageUrl);
		eportaRenderCatalogLoadMoreBtn($eportaCurPage, $eportaTotalPages);
		die();
	endif; ?>

	<!-- Хлебные крошки -->
	<?if ($eportaCollectionSection):?>
	<div class="breadcrumb" style="padding:12px var(--pad-x) 0">Главная · <a href="/collection/" style="color:inherit">Коллекции</a> · <?=htmlspecialcharsbx($eportaCollectionSection["NAME"])?></div>
	<?else:?>
	<div class="breadcrumb" style="padding:12px var(--pad-x) 0">Главная · Каталог · Межкомнатные</div>
	<?endif;?>

	<?if ($eportaCollectionSection):
		$eportaCollBannerSrc = \CFile::GetPath($eportaCollectionSection["DETAIL_PICTURE"] ?: $eportaCollectionSection["PICTURE"]);
	?>
	<!-- Баннер коллекции: DETAIL_PICTURE/PICTURE + DESCRIPTION секции IBLOCK 19 -->
	<div style="position:relative;margin:18px var(--pad-x) 0;border-radius:22px;overflow:hidden;min-height:220px;background:#e5e0d5<?=$eportaCollBannerSrc ? ";background-image:url('".htmlspecialcharsbx($eportaCollBannerSrc)."');background-size:cover;background-position:center" : ""?>">
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
	<div style="display:flex;align-items:flex-end;justify-content:space-between;padding:8px var(--pad-x) 4px">
		<div>
			<?if ($eportaCollectionSection):?>
			<h2 style="margin:0;font:800 24px 'Manrope';letter-spacing:-0.01em">Товары коллекции <?=htmlspecialcharsbx($eportaCollectionSection["NAME"])?></h2>
			<?elseif ($eportaOnlySale):?>
			<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Распродажа</h1>
			<?elseif ($eportaOnlyNew):?>
			<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Новинки</h1>
			<?else:?>
			<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Межкомнатные двери</h1>
			<?endif;?>
			<div style="font:500 13px;color:#8a857b;margin-top:5px">Найдено <?=$eportaFoundCount?> <?=eportaPluralRu($eportaFoundCount, "товар", "товара", "товаров")?> (<?=$eportaModelCount?> <?=eportaPluralRu($eportaModelCount, "модель", "модели", "моделей")?>)</div>
		</div>
		<div style="display:flex;align-items:center;gap:10px">
			<!-- Сортировка: реальный dropdown ($eportaSortOptions), значение уходит в GET ?sort=
			     и прокидывается в ELEMENT_SORT_FIELD/ORDER компонента ниже. <details>/<summary> —
			     без зависимости от JS-библиотек (в шаблоне их нет). -->
			<details class="eporta-sort-dd">
				<summary><?=htmlspecialcharsbx($eportaSortOptions[$eportaSort]["LABEL"])?> <span style="color:#a39e95">▾</span></summary>
				<div class="eporta-sort-dd-menu">
					<?foreach ($eportaSortOptions as $eportaSortKey => $eportaSortOpt):
						$eportaSortParams = $_GET;
						unset($eportaSortParams["PAGEN_1"]);
						if ($eportaSortKey === "popular") {
							unset($eportaSortParams["sort"]);
						} else {
							$eportaSortParams["sort"] = $eportaSortKey;
						}
						// $eportaResetUrl — реальный SEF-путь текущей страницы (parse_url(REQUEST_URI))
						// того же вычисления, что и у пейджера/сброса фильтров ниже. PHP_SELF отдаёт
						// физический путь исполняемого скрипта (/catalog/index.php) — на любой
						// SEF-странице каталога (категория, коллекция) это НЕ совпадает с адресной
						// строкой, и ссылка сортировки вела на URL, не покрытый urlrewrite (404) —
						// воспроизводилось не у всех, а только при заходе не через /catalog/ напрямую.
						$eportaSortUrl = $eportaResetUrl.($eportaSortParams ? "?".http_build_query($eportaSortParams) : "");
					?>
					<a href="<?=htmlspecialcharsbx($eportaSortUrl)?>" class="<?=$eportaSortKey === $eportaSort ? "active" : ""?>"><?=htmlspecialcharsbx($eportaSortOpt["LABEL"])?></a>
					<?endforeach;?>
				</div>
			</details>
			<!-- Переключатель плитка/список — визуальный (кука eporta_view), бэкенд не меняет. -->
			<div class="eporta-view-switch">
				<button type="button" class="eporta-view-btn<?=$eportaView === "grid" ? " active" : ""?>" data-view="grid" onclick="eportaSetView('grid')" title="Плитка" aria-label="Плитка">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>
				</button>
				<button type="button" class="eporta-view-btn<?=$eportaView === "list" ? " active" : ""?>" data-view="list" onclick="eportaSetView('list')" title="Список" aria-label="Список">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
				</button>
			</div>
		</div>
	</div>

	<!-- Активные фильтры: реально применённые GET-параметры -->
	<?if ($eportaActiveChips):?>
	<div style="display:flex;align-items:center;gap:9px;padding:14px var(--pad-x) 6px;flex-wrap:wrap">
		<?foreach ($eportaActiveChips as $eportaChip):
			$eportaChipParams = [];
			parse_str(parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_QUERY) ?: "", $eportaChipParams);
			if ($eportaChip["REMOVE_KEY"] === "price") {
				unset($eportaChipParams["price_min"], $eportaChipParams["price_max"]);
			} elseif ($eportaChip["REMOVE_VALUE"] === null) {
				unset($eportaChipParams[$eportaChip["REMOVE_KEY"]]);
			} else {
				$eportaChipParams[$eportaChip["REMOVE_KEY"]] = array_values(array_diff((array)($eportaChipParams[$eportaChip["REMOVE_KEY"]] ?? []), [(string)$eportaChip["REMOVE_VALUE"]]));
				if (empty($eportaChipParams[$eportaChip["REMOVE_KEY"]])) unset($eportaChipParams[$eportaChip["REMOVE_KEY"]]);
			}
			$eportaChipUrl = $eportaResetUrl.($eportaChipParams ? "?".http_build_query($eportaChipParams) : "");
		?>
		<a href="<?=htmlspecialcharsbx($eportaChipUrl)?>" style="display:inline-flex;align-items:center;gap:8px;font:600 12.5px 'Manrope';background:#f3f1ec;color:#3a3631;padding:8px 12px;border-radius:20px;cursor:pointer;text-decoration:none"><?=htmlspecialcharsbx($eportaChip["LABEL"])?> <span style="color:#a39e95">✕</span></a>
		<?endforeach;?>
		<a href="<?=htmlspecialcharsbx($eportaResetUrl)?>" style="font:700 12.5px 'Manrope';color:#c2670a;padding:8px 6px;cursor:pointer;text-decoration:none">Сбросить всё</a>
	</div>
	<?endif;?>

	<!-- Фильтры + сетка -->
	<div style="display:flex;gap:22px;padding:8px var(--pad-x) 28px;align-items:flex-start">

		<!-- Боковой фильтр: обычный GET-submit, реальные свойства IBLOCK 19 -->
		<form method="get" action="" style="flex:none;width:248px">
			<!-- Цена: реальный диапазон CATALOG_PRICE_1 (BASE) в текущей области -->
			<div style="border-bottom:1px solid #efece6;padding:6px 0 18px">
				<div style="font:800 14px 'Manrope';margin-bottom:14px">Цена, ₽</div>
				<div style="display:flex;gap:8px">
					<input type="number" name="price_min" value="<?=(int)$eportaPriceSelMin?>" min="<?=(int)$eportaPriceMinBound?>" max="<?=(int)$eportaPriceMaxBound?>" style="flex:1;width:0;border:1.5px solid #e7e3db;border-radius:9px;padding:9px 11px;font:600 12.5px 'Manrope'">
					<input type="number" name="price_max" value="<?=(int)$eportaPriceSelMax?>" min="<?=(int)$eportaPriceMinBound?>" max="<?=(int)$eportaPriceMaxBound?>" style="flex:1;width:0;border:1.5px solid #e7e3db;border-radius:9px;padding:9px 11px;font:600 12.5px 'Manrope'">
				</div>
			</div>

			<?foreach ($eportaFilterGroups as $eportaGroupKey => $eportaGroup):
				if (!$eportaGroup["ITEMS"]) continue;
			?>
			<!-- <?=htmlspecialcharsbx($eportaGroup["LABEL"])?> -->
			<div style="border-bottom:1px solid #efece6;padding:16px 0">
				<div style="font:800 14px 'Manrope';margin-bottom:12px"><?=htmlspecialcharsbx($eportaGroup["LABEL"])?></div>
				<?foreach ($eportaGroup["ITEMS"] as $eportaItem):?>
				<label style="display:flex;align-items:center;gap:10px;padding:5px 0;font:600 13px 'Manrope';cursor:pointer">
					<input type="checkbox" name="<?=htmlspecialcharsbx($eportaGroupKey)?>[]" value="<?=(int)$eportaItem["ID"]?>" <?=$eportaItem["CHECKED"] ? "checked" : ""?> style="width:18px;height:18px;accent-color:#e8820a;flex:none">
					<?=htmlspecialcharsbx($eportaItem["VALUE"])?> <span style="color:#c2bdb2;margin-left:auto;font-weight:600"><?=$eportaItem["COUNT"]?></span>
				</label>
				<?endforeach;?>
			</div>
			<?endforeach;?>

			<button type="submit" style="width:100%;background:#e8820a;color:#fff;font:700 14px 'Manrope';padding:13px;border-radius:12px;border:none;cursor:pointer;box-shadow:0 8px 20px rgba(232,130,10,.28)">Показать <?=$eportaFoundCount?> <?=eportaPluralRu($eportaFoundCount, "товар", "товара", "товаров")?></button>
		</form>

		<!-- Сетка товаров: реальные данные IBLOCK 19. Раскладка "круговая по моделям" и
		     пагинация посчитаны выше ($eportaPageIds/$eportaCurPage/$eportaTotalPages), рендерят
		     их eportaRenderCatalogGrid/eportaRenderCatalogPager — те же функции, что использует
		     AJAX-подгрузка (короткое замыкание в начале eporta-ветки), без дублирования кода. -->
		<div style="flex:1" id="eportaCatalogGrid">
			<?php eportaRenderCatalogGrid(
				$eportaPageIds ?: [0],
				$eportaCollectionSection ? $eportaCollectionSection["ID"] : false,
				$eportaSortOptions[$eportaSort]["FIELD"],
				$eportaSortOptions[$eportaSort]["ORDER"],
				$eportaCols,
				$eportaPageElementCount
			); ?>
			<?php eportaRenderCatalogPager($eportaCurPage, $eportaTotalPages, $eportaCatalogPageUrl); ?>
			<!-- Кнопка "Показать ещё" (eportaRenderCatalogLoadMoreBtn — общая с AJAX-подгрузкой
			     выше, иначе ответ ?eporta_ajax=grid её не содержит и JS удаляет кнопку после
			     первого клика, даже если следующая страница ещё есть): по клику assets/app.js
			     подгружает следующую страницу и дописывает карточки в сетку выше, не трогая
			     URL/историю (раньше это была автоподгрузка по скроллу с history.replaceState —
			     при обновлении страницы или возврате "назад" открывало последнюю подгруженную
			     страницу пагинации). Обычные ссылки пейджера (.bx-pagination) остаются рабочими
			     без JS. -->
			<?php eportaRenderCatalogLoadMoreBtn($eportaCurPage, $eportaTotalPages); ?>
		</div>
	</div>

	<script>
	(function(){
		// Плитка/список — переключаем класс на уже отрисованной сетке сразу (без перезагрузки),
		// кука eporta_view нужна только для того, чтобы следующая загруженная страница/пагинация
		// сразу отрендерилась в выбранном виде (см. чтение куки в catalog.section/.default/template.php).
		window.eportaSetView = function(view) {
			view = view === "list" ? "list" : "grid";
			document.cookie = "eporta_view=" + view + ";path=/;max-age=31536000";
			document.querySelectorAll(".eporta-product-grid").forEach(function(grid){
				grid.classList.toggle("eporta-product-grid--list", view === "list");
			});
			document.querySelectorAll(".eporta-view-btn").forEach(function(btn){
				btn.classList.toggle("active", btn.getAttribute("data-view") === view);
			});
		};
	})();
	</script>

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
