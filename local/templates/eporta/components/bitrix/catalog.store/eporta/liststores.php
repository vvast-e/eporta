<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?><?
// EPORTA: делегатор SEF liststores → catalog.store.list "eporta" (Этап 4).
// Копия вендорского bootstrap_v4/liststores.php, меняем только COMPONENT_TEMPLATE.
$APPLICATION->IncludeComponent(
	"bitrix:catalog.store.list",
	"eporta",
	array(
		"CACHE_TIME" => $arParams["CACHE_TIME"],
		"CACHE_TYPE" => $arParams["CACHE_TYPE"],
		"PHONE" => $arParams["PHONE"],
		"SCHEDULE" => $arParams["SCHEDULE"],
		"TITLE" => $arParams["TITLE"],
		"SET_TITLE" => $arParams["SET_TITLE"],
		"PATH_TO_ELEMENT" => $arResult["PATH_TO_ELEMENT"],
		"MAP_TYPE" => $arParams["MAP_TYPE"],
	),
	$component
);
