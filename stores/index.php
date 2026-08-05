<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Наши магазины");

// Дев-превью нового шаблона eporta: карта магазинов (Этап 4). Боевой .default не
// трогаем — при любом другом активном шаблоне страница работает как прежде.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
$storeComponentTemplate = $isEportaTemplate ? "eporta" : ".default";
if (!$isEportaTemplate):?><h1><?$APPLICATION->ShowTitle(true)?></h1><?endif;?><?$APPLICATION->IncludeComponent(
	"bitrix:catalog.store",
	$storeComponentTemplate,
	array(
		"CACHE_TIME" => "360000",
		"CACHE_TYPE" => "A",
		"COMPONENT_TEMPLATE" => $storeComponentTemplate,
		"MAP_TYPE" => "0",
		"PHONE" => "Y",
		"SCHEDULE" => "Y",
		"EMAIL" => "Y",
		"SEF_FOLDER" => "/stores/",
		"SEF_MODE" => "Y",
		"SET_TITLE" => $isEportaTemplate ? "Y" : "N",
		"TITLE" => $isEportaTemplate ? "Наши магазины" : "Список складов с подробной информацией",
		"SEF_URL_TEMPLATES" => array(
			"liststores" => "",
			"element" => "#store_id#/",
		)
	),
	false
);?><br /><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
