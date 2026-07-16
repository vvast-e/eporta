<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

// EPORTA: страница отмены заказа делегирована в вендорский .default ребёнка
// "sale.personal.order.cancel" — SEF (/personal/order/cancel/#ID#/) сохранён.
if ($arParams['DISALLOW_CANCEL'] === 'Y')
{
	LocalRedirect($arResult['PATH_TO_LIST']);
}
$APPLICATION->IncludeComponent(
	"bitrix:sale.personal.order.cancel",
	"",
	array(
		"PATH_TO_LIST" => $arResult["PATH_TO_LIST"],
		"PATH_TO_DETAIL" => $arResult["PATH_TO_DETAIL"],
		"SET_TITLE" => $arParams["SET_TITLE"],
		"ID" => $arResult["VARIABLES"]["ID"],
	),
	$component
);
?>
