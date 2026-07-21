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
	     Шаблон "eporta" — override родного .default (не dresscode): рабочие AJAX
	     кол-во/удаление/купон "из коробки" (JS-контроллер и вендорские ассеты
	     подключаются напрямую из .default, см. components/bitrix/sale.basket.basket/eporta/template.php),
	     вёрстка/структура — по эталону "EPORTA Прототип" (#cart), см. template_styles.css,
	     блок "Корзина (bx-basket)". Допы позиций — фронтенд-надстройка, см. assets/kit-modal.php + cart-kit.js. -->
	<?php require $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . '/include/kit-modal.php'; ?>
	<?php
	// Cache-busting по mtime файла: nginx отдаёт /assets/*.js как immutable,max-age=31536000
	// без версионирования (см. память проекта project_deploy_cache_layers) — без ?v=... браузер
	// (и наш собственный, при повторных проверках) продолжает использовать старую закэшированную
	// версию скрипта даже после деплоя правок на сервер, пока URL не изменится.
	$eportaKitJsPath = $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . "/assets/kit.js";
	$eportaCartKitJsPath = $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . "/assets/cart-kit.js";
	?>
	<script src="<?=SITE_TEMPLATE_PATH?>/assets/kit.js?v=<?=@filemtime($eportaKitJsPath) ?: time()?>"></script>
	<script src="<?=SITE_TEMPLATE_PATH?>/assets/cart-kit.js?v=<?=@filemtime($eportaCartKitJsPath) ?: time()?>"></script>
	<div style="padding:0 56px 40px">
		<?$APPLICATION->IncludeComponent(
			"bitrix:sale.basket.basket",
			"eporta",
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
				// Дефолт компонента (PREVIEW_PICTURE,DISCOUNT,DELETE,DELAY,TYPE,SUM) не включает
				// PROPS — без явного добавления блок "props" в PRODUCT_BLOCKS_ORDER просто не
				// рендерится, свойства позиции (напр. "Размер") нигде не показываются.
				"COLUMNS_LIST" => ["PREVIEW_PICTURE", "DISCOUNT", "DELETE", "DELAY", "TYPE", "SUM", "PROPS"],
				"USE_GIFTS" => "N",
				"USE_DYNAMIC_SCROLL" => "N",
				"SHOW_FILTER" => "N",
				// Без этого вендор после удаления переводит строку в состояние SHOW_RESTORE
				// ("Товар удалён… Восстановить", неоформленный блок в вендорском стиле) —
				// вместо undo-после теперь своя confirm-модалка до удаления (assets/cart-kit.js).
				"SHOW_RESTORE" => "N",
				"TOTAL_BLOCK_DISPLAY" => ["bottom"],
				// TEMPLATE_THEME намеренно не задаём: "site" здесь не создаёт
				// кастомную тему — компонент трактует это как спец-значение и
				// пытается разрешить через legacy-опцию wizard_*_theme_id
				// (актуально только для сайтов, поставленных мастером Bitrix),
				// не находит ничего и всё равно откатывается на "blue". Реальный
				// вид корзины полностью держится на #basket-root-переопределениях
				// в template_styles.css, а не на выборе вендорской темы.
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
