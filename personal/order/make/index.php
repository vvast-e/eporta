<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Заказы");

// Дев-превью нового шаблона eporta: редизайн оформления заказа (Этап 4, чекаут).
// ЭТО реальная точка входа — кнопка "Оформить заказ" в корзине (personal/cart/index.php,
// PATH_TO_ORDER) ведёт именно сюда, не на /personal/cart/order/ (там другой, более широкий
// набор параметров sale.order.ajax — ошибочная первая попытка, оставлена нетронутой как
// неиспользуемый файл). ТОЛЬКО дизайн — все параметры ниже перенесены без единого изменения
// (бизнес-логика/расчёт доставки/оплаты не трогается). Кастомизация — исключительно новый
// COMPONENT_TEMPLATE "eporta" (та же байт-в-байт копия вендорского .default, что уже сделана
// для /personal/cart/order/, см. local/templates/eporta/components/bitrix/sale.order.ajax/
// eporta/) + CSS поверх вендорских классов в template_styles.css. Боевая ветка (else) не тронута.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-breadcrumb"><a href="/">Главная</a> · <a href="/personal/cart/">Корзина</a> · Оформление заказа</div>
		<div class="lk-title"><h1>Оформление заказа</h1></div>
		<div style="padding:0 28px 40px">
		<?$APPLICATION->IncludeComponent("bitrix:sale.order.ajax", "eporta", array(
			"PAY_FROM_ACCOUNT" => "Y",
			"COUNT_DELIVERY_TAX" => "N",
			"COUNT_DISCOUNT_4_ALL_QUANTITY" => "N",
			"ONLY_FULL_PAY_FROM_ACCOUNT" => "N",
			"ALLOW_AUTO_REGISTER" => "Y",
			"SEND_NEW_USER_NOTIFY" => "Y",
			"DELIVERY_NO_AJAX" => "N",
			"TEMPLATE_LOCATION" => "popup",
			"PROP_1" => array(
			),
			"PATH_TO_BASKET" => "/personal/cart/",
			"PATH_TO_PERSONAL" => "/personal/order/",
			"PATH_TO_PAYMENT" => "/personal/order/payment/",
			"PATH_TO_ORDER" => "/personal/order/make/",
			"SET_TITLE" => "Y" ,
			"DELIVERY2PAY_SYSTEM" => Array(),
			"SHOW_ACCOUNT_NUMBER" => "Y",
			"DELIVERY_NO_SESSION" => "Y"
			),
			false
		);?>
		</div>
	</div>
	</div>
<?else:?>
<?$APPLICATION->IncludeComponent("bitrix:sale.order.ajax", "", array(
	"PAY_FROM_ACCOUNT" => "Y",
	"COUNT_DELIVERY_TAX" => "N",
	"COUNT_DISCOUNT_4_ALL_QUANTITY" => "N",
	"ONLY_FULL_PAY_FROM_ACCOUNT" => "N",
	"ALLOW_AUTO_REGISTER" => "Y",
	"SEND_NEW_USER_NOTIFY" => "Y",
	"DELIVERY_NO_AJAX" => "N",
	"TEMPLATE_LOCATION" => "popup",
	"PROP_1" => array(
	),
	"PATH_TO_BASKET" => "/personal/cart/",
	"PATH_TO_PERSONAL" => "/personal/order/",
	"PATH_TO_PAYMENT" => "/personal/order/payment/",
	"PATH_TO_ORDER" => "/personal/order/make/",
	"SET_TITLE" => "Y" ,
	"DELIVERY2PAY_SYSTEM" => Array(),
	"SHOW_ACCOUNT_NUMBER" => "Y",
	"DELIVERY_NO_SESSION" => "Y"
	),
	false
);?>
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>