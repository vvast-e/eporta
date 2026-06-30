<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Корзина");
?><h1>Корзина</h1><?$APPLICATION->IncludeComponent("dresscode:sale.basket.basket", "standartOrder", array(
		"HIDE_MEASURES" => "N",
		"BASKET_PICTURE_WIDTH" => "220",
		"BASKET_PICTURE_HEIGHT" => "200",
		"HIDE_NOT_AVAILABLE" => "N",
		"PRODUCT_PRICE_CODE" => array(
		),
		"GIFT_CONVERT_CURRENCY" => "N",
		"PATH_TO_PAYMENT" => "/personal/cart/payment/",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "36000000",
		"COMPONENT_TEMPLATE" => ".default",
		"PATH_TO_PAYMENT" => "",
		"MIN_SUM_TO_PAYMENT" => "",
		"REGISTER_USER" => "Y",
		"PART_STORES_AVAILABLE" => "",
		"ALL_STORES_AVAILABLE" => "",
		"NO_STORES_AVAILABLE" => ""
	),
	false
);?><br /><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>