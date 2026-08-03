<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$this->setFrameMode(true);
if ($arResult["SHOW_TEMPLATE"] === false) return;

global $APPLICATION, $arrFilter;
?>
<div style="padding:0 var(--pad-x) 4px">
	<div style="font:500 13px;color:#8a857b">Найдено <?= count($arResult["ITEMS"]) ?> моделей</div>
</div>

<div style="padding:8px var(--pad-x) 28px">
	<?php $APPLICATION->IncludeComponent(
		"bitrix:catalog.section",
		".default",
		[
			"IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
			"IBLOCK_ID" => $arParams["IBLOCK_ID"],
			"SECTION_ID" => false,
			"FILTER_NAME" => $arParams["FILTER_NAME"] ?: "arrFilter",
			"ELEMENT_SORT_FIELD" => "sort",
			"ELEMENT_SORT_ORDER" => "asc",
			"ELEMENT_SORT_FIELD2" => "id",
			"ELEMENT_SORT_ORDER2" => "desc",
			"HIDE_NOT_AVAILABLE" => $arParams["HIDE_NOT_AVAILABLE"] ?: "N",
			"HIDE_NOT_AVAILABLE_OFFERS" => "N",
			"PAGE_ELEMENT_COUNT" => "30",
			"LINE_ELEMENT_COUNT" => "3",
			"PROPERTY_CODE" => ["STYLE", "COATING_COLOR", "GLAZING", "MAIN_COLOR", "PRODUCT_DAY", "RATING", "VOTE_COUNT", "CML2_ARTICLE"],
			"OFFERS_FIELD_CODE" => [],
			"OFFERS_PROPERTY_CODE" => [],
			"BACKGROUND_IMAGE" => "-",
			"LABEL_PROP" => "-",
			"PRODUCT_SUBSCRIPTION" => "N",
			"SHOW_DISCOUNT_PERCENT" => "Y",
			"SHOW_OLD_PRICE" => "Y",
			"PRICE_CODE" => $arParams["PRICE_CODE"],
			"USE_PRICE_COUNT" => "N",
			"SHOW_PRICE_COUNT" => "1",
			"PRICE_VAT_INCLUDE" => "Y",
			"CONVERT_CURRENCY" => $arParams["CONVERT_CURRENCY"],
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
	); ?>
</div>
