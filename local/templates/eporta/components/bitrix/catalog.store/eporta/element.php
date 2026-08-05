<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?><?
// EPORTA: делегатор SEF element → catalog.store.detail "eporta" (Этап 4).
// Копия вендорского bootstrap_v4/element.php, меняем только COMPONENT_TEMPLATE.
$APPLICATION->IncludeComponent(
	"bitrix:catalog.store.detail",
	"eporta",
	array(
		"CACHE_TIME" => $arParams["CACHE_TIME"],
		"CACHE_TYPE" => $arParams["CACHE_TYPE"],
		"STORE" => $arResult["STORE"],
		"PATH_TO_LISTSTORES" => $arResult["PATH_TO_LISTSTORES"],
		"SET_TITLE" => $arParams["SET_TITLE"],
		"MAP_TYPE" => $arParams["MAP_TYPE"],
	),
	$component
);
