<?php
// Подсказки поиска в шапке — раньше дёргали вендорский dw.deluxe act=search (общий модуль
// с dverimarket.ru, substring/LIKE по name) — движок расходился с самой страницей /search/
// (CSearch, полнотекстовый индекс с морфологией/раскладкой): один и тот же запрос мог дать
// разные результаты в подсказке и на странице. Этот эндпоинт — свой, использует ТОТ ЖЕ
// CSearch с теми же параметрами, что search/index.php, просто без пагинации/чипов и с
// лимитом на 5 штук (выпадающий список). Контракт ответа (JSON-массив объектов
// {NAME, DETAIL_PAGE_URL, DETAIL_PICTURE, PRICE}) идентичен старому эндпоинту — JS в
// assets/app.js менять не пришлось, только URL запроса.
define("NO_KEEP_STATISTIC", true);
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

header("Content-Type: application/json; charset=UTF-8");

\Bitrix\Main\Loader::includeModule("iblock");
\Bitrix\Main\Loader::includeModule("search");
\Bitrix\Main\Loader::includeModule("catalog");
\Bitrix\Main\Loader::includeModule("currency");
\Bitrix\Main\Loader::includeModule("dw.deluxe");

$catalogIblockId = null;
$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
if (!empty($arTemplateSettings)) {
	$catalogIblockId = $arTemplateSettings["TEMPLATE_PRODUCT_IBLOCK_ID"];
}

$query = trim((string)\Bitrix\Main\Context::getCurrent()->getRequest()->get("name"));
$items = [];

if ($catalogIblockId && $query !== "" && mb_strlen($query) > 1) {
	// Артикул — сначала (см. eportaFindArticleMatches в local/php_interface/init.php),
	// CSearch добирает остальное до лимита в 5, без дублей.
	$foundIds = eportaFindArticleMatches($query, (int)$catalogIblockId, 5);

	$obSearch = new CSearch;
	$obSearch->Search(
		[
			"QUERY" => $query,
			"SITE_ID" => SITE_ID,
			"MODULE_ID" => "iblock",
			"PARAM2" => $catalogIblockId,
		],
		[],
		["STEMMING" => "N"]
	);
	while ($searchItem = $obSearch->fetch()) {
		if (count($foundIds) >= 5) {
			break;
		}
		if (is_numeric($searchItem["ITEM_ID"]) && !in_array((int)$searchItem["ITEM_ID"], $foundIds, true)) {
			$foundIds[] = (int)$searchItem["ITEM_ID"];
		}
	}

	if ($foundIds) {
		// CODE — нужен для фоллбэка ссылки ниже: DETAIL_PAGE_URL иногда приходит с
		// неподставленным макросом "#ELEMENT_CODE#" (SEF-шаблон компонента не резолвит его
		// вне компонента bitrix:catalog.*), браузер трактует такую ссылку как якорь и клик
		// по подсказке никуда не ведёт — см. тот же фоллбэк в search/index.php.
		$res = \CIBlockElement::GetList(
			[],
			["ID" => $foundIds, "ACTIVE" => "Y", "IBLOCK_ID" => (int)$catalogIblockId],
			false,
			false,
			["ID", "NAME", "CODE", "DETAIL_PAGE_URL", "PREVIEW_PICTURE", "DETAIL_PICTURE", "CATALOG_PRICE_1"]
		);
		$dataById = [];
		while ($row = $res->GetNext()) {
			$dataById[(int)$row["ID"]] = $row;
		}
		// Порядок — из $foundIds (порядок релевантности CSearch), не из результата запроса.
		foreach ($foundIds as $id) {
			if (!isset($dataById[$id])) {
				continue;
			}
			$row = $dataById[$id];
			$imgId = $row["PREVIEW_PICTURE"] ?: $row["DETAIL_PICTURE"];
			$priceVal = $row["CATALOG_PRICE_1"] ?? null;

			$url = $row["DETAIL_PAGE_URL"] ?? "";
			if ($url === "" || strpos($url, "#") !== false) {
				$url = $row["CODE"] ? "/catalog/" . $row["CODE"] . ".html" : "";
			}
			if ($url === "") {
				continue;
			}

			$items[] = [
				"ID" => $id,
				"NAME" => $row["NAME"],
				"DETAIL_PAGE_URL" => $url,
				"DETAIL_PICTURE" => $imgId ? \CFile::GetPath($imgId) : "",
				// CurrencyFormat(..., true) отдаёт HTML-сущности (&nbsp;/&#8381;) — годится для
				// вставки через innerHTML, но JS (assets/app.js) кладёт значение через
				// textContent, поэтому сущности раньше показывались как есть. Декодируем здесь,
				// чтобы контракт ответа остался "готовая строка для textContent".
				"PRICE" => ($priceVal !== null && $priceVal !== "")
					? html_entity_decode(\CCurrencyLang::CurrencyFormat((float)$priceVal, "RUB", true), ENT_QUOTES | ENT_HTML5, "UTF-8")
					: "",
			];
		}
	}
}

echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
