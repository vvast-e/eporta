<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Склады");

// Дев-превью нового шаблона eporta: карта магазинов (Этап 4). Боевой bootstrap_v4
// не трогаем — при любом другом активном шаблоне страница работает как прежде.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
$storeComponentTemplate = $isEportaTemplate ? "eporta" : "bootstrap_v4";
?><?$APPLICATION->IncludeComponent(
	"bitrix:catalog.store",
	$storeComponentTemplate,
	Array(
		"SEF_MODE" => "Y",
		"PHONE" => "N",
		"SCHEDULE" => "N",
		"SET_TITLE" => "Y",
		"TITLE" => "Список складов с подробной информацией",
		"MAP_TYPE" => "0",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "3600",
		"CACHE_NOTES" => "",
		"SEF_FOLDER" => "/store/",
		"SEF_URL_TEMPLATES" => Array(
			"liststores" => "index.php",
			"element" => "#store_id#"
		),
		"VARIABLE_ALIASES" => Array(
			"liststores" => Array(),
			"element" => Array(),
		)
	),
false
);?> <?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>