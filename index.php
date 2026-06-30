<?define("INDEX_PAGE", "Y");?> <?define("MAIN_PAGE", true);?> <?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("keywords", "Eporta");
$APPLICATION->SetPageProperty("description", "Eporta");
$APPLICATION->SetTitle("Eporta");?> <?
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
?> <?$APPLICATION->IncludeComponent(
	"dresscode:slider",
	"promoSlider",
	Array(
		"CACHE_TIME" => "3600000",
		"CACHE_TYPE" => "Y",
		"COMPONENT_TEMPLATE" => "promoSlider",
		"COMPOSITE_FRAME_MODE" => "A",
		"COMPOSITE_FRAME_TYPE" => "AUTO",
		"IBLOCK_ID" => "27",
		"IBLOCK_TYPE" => "slider",
		"LAZY_LOAD_PICTURES" => "Y",
		"PICTURE_HEIGHT" => "1080",
		"PICTURE_WIDTH" => "1920"
	)
);?> <?$APPLICATION->IncludeComponent(
	"dresscode:offers.product", 
	".default", 
	[
		"AJAX_OPTION_ADDITIONAL" => "offers_style_387",
		"CACHE_TIME" => "360000",
		"CACHE_TYPE" => "A",
		"COMPOSITE_FRAME_MODE" => "A",
		"COMPOSITE_FRAME_TYPE" => "AUTO",
		"CONVERT_CURRENCY" => "N",
		"ELEMENTS_COUNT" => "15",
		"HIDE_MEASURES" => "Y",
		"HIDE_NOT_AVAILABLE" => "N",
		"IBLOCK_ID" => "19",
		"IBLOCK_TYPE" => "catalog",
		"LAZY_LOAD_PICTURES" => "Y",
		"PICTURE_HEIGHT" => "280",
		"PICTURE_WIDTH" => "400",
		"PRODUCT_PRICE_CODE" => [
		],
		"PROP_NAME" => "OFFERS",
		"PROP_VALUE" => [
			0 => "_294",
			1 => "_296",
			2 => "_297",
		],
		"SORT_PROPERTY_NAME" => "PROPERTY_ORDER",
		"SORT_VALUE" => "DESC",
		"COMPONENT_TEMPLATE" => ".default"
	],
	false
);?>
<div id="infoTabsCaption">
	<div class="limiter">
		<div class="items">
			 <?$APPLICATION->ShowViewContent("main_news_view_content_tab");?><br>
			 <?$APPLICATION->ShowViewContent("main_collection_view_content_tab");?> <br>
			 <?$APPLICATION->ShowViewContent("main_service_view_content_tab");?>
		</div>
	</div>
</div>
<div id="infoTabs">
	<div class="items">
	</div>
</div>
 <br><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>