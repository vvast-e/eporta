<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Корзина");

// Дев-превью нового шаблона eporta: реальная корзина (Этап 4, Фаза B).
// Боевой dresscode:sale.basket.basket не трогаем — при любом другом активном
// шаблоне страница работает как прежде.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>

	<!-- Хлебные крошки -->
	<div class="breadcrumb" style="padding:12px 56px 0"><a href="/">Главная</a> · Корзина</div>

	<!-- Заголовок -->
	<div style="display:flex;align-items:baseline;justify-content:space-between;padding:10px 56px 4px">
		<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Корзина</h1>
	</div>

	<!-- Живая корзина: реальные данные (Этап 4, Фаза B).
	     Используем родной .default шаблон bitrix:sale.basket.basket (не dresscode) —
	     рабочие AJAX кол-во/удаление/купон "из коробки", вид переопределён CSS
	     под EPORTA (см. template_styles.css, блок "Корзина (bx-basket)"). -->
	<div style="padding:0 56px 40px">
		<?$APPLICATION->IncludeComponent(
			"bitrix:sale.basket.basket",
			".default",
			[
				"HIDE_COUPON" => "N",
				"PATH_TO_ORDER" => "/personal/order/make/",
				"PATH_TO_BASKET" => "/personal/cart/",
				"PATH_TO_PERSONAL" => "/personal/",
				"BASKET_WITH_ORDER_INTEGRATION" => "N",
				"HIDE_NOT_AVAILABLE" => "N",
				"DISPLAY_MODE" => "extended",
				"PRICE_DISPLAY_MODE" => "N",
				"PRODUCT_BLOCKS_ORDER" => "props,sku,columns",
				"USE_GIFTS" => "N",
				"USE_DYNAMIC_SCROLL" => "N",
				"SHOW_FILTER" => "N",
				"TOTAL_BLOCK_DISPLAY" => ["bottom"],
				"TEMPLATE_THEME" => "site",
				"SET_TITLE" => "N",
			],
			false
		);?>
	</div>

<?else:?>
	<h1>Корзина</h1><?$APPLICATION->IncludeComponent("dresscode:sale.basket.basket", "standartOrder", array(
			"HIDE_MEASURES" => "N",
			"BASKET_PICTURE_WIDTH" => "220",
			"BASKET_PICTURE_HEIGHT" => "200",
			"HIDE_NOT_AVAILABLE" => "N",
			"PRODUCT_PRICE_CODE" => array(
			),
			"GIFT_CONVERT_CURRENCY" => "N",
			"PATH_TO_PAYMENT" => "/personal/cart/payment/",
			"CACHE_TYPE" => "A",
			"CACHE_TIME" => "3600",
			"COMPONENT_TEMPLATE" => ".default",
			"PATH_TO_PAYMENT" => "",
			"MIN_SUM_TO_PAYMENT" => "",
			"REGISTER_USER" => "Y",
			"PART_STORES_AVAILABLE" => "",
			"ALL_STORES_AVAILABLE" => "",
			"NO_STORES_AVAILABLE" => ""
		),
		false
	);?><br />
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
