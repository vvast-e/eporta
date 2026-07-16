<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Заказы");

// Дев-превью нового шаблона eporta: реальная история заказов (Этап 6, Фаза B).
// Боевой bitrix:sale.personal.order (шаблон .default) не трогаем.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-breadcrumb"><a href="/">Главная</a> · <a href="/personal/">Личный кабинет</a> · История заказов</div>
		<div class="lk-title"><h1>История заказов</h1></div>
		<?php $active = 'orders'; require $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . '/include/lk-tabs.php'; ?>
		<div style="padding:0 28px 40px">
		<?$APPLICATION->IncludeComponent(
			"bitrix:sale.personal.order",
			"eporta",
			array(
				"SEF_MODE" => "Y",
				"SEF_FOLDER" => "/personal/order/",
				"ORDERS_PER_PAGE" => "10",
				"PATH_TO_PAYMENT" => "/personal/order/payment/",
				"PATH_TO_BASKET" => "/personal/cart/",
				"SET_TITLE" => "Y",
				"SAVE_IN_SESSION" => "N",
				"NAV_TEMPLATE" => "round",
				"SHOW_ACCOUNT_NUMBER" => "Y",
				"ACTIVE_DATE_FORMAT" => "d.m.Y",
				"CACHE_TYPE" => "A",
				"CACHE_TIME" => "3600",
				"CACHE_GROUPS" => "Y",
				"HISTORIC_STATUSES" => array(
					0 => "F",
				),
				"SEF_URL_TEMPLATES" => array(
					"list" => "index.php",
					"detail" => "detail/#ID#/",
					"cancel" => "cancel/#ID#/",
				)
			),
			false
		);?>
		</div>
	</div>
	</div>
<?else:?>
<h1>История заказов</h1>
<?$APPLICATION->IncludeComponent("bitrix:menu", "personal", Array(
	"COMPONENT_TEMPLATE" => ".default",
		"ROOT_MENU_TYPE" => "personal",	// Тип меню для первого уровня
		"MENU_CACHE_TYPE" => "A",	// Тип кеширования
		"MENU_CACHE_TIME" => "3600000",	// Время кеширования (сек.)
		"MENU_CACHE_USE_GROUPS" => "Y",	// Учитывать права доступа
		"MENU_CACHE_GET_VARS" => "",	// Значимые переменные запроса
		"MAX_LEVEL" => "1",	// Уровень вложенности меню
		"CHILD_MENU_TYPE" => "",	// Тип меню для остальных уровней
		"USE_EXT" => "N",	// Подключать файлы с именами вида .тип_меню.menu_ext.php
		"DELAY" => "N",	// Откладывать выполнение шаблона меню
		"ALLOW_MULTI_SELECT" => "N",	// Разрешить несколько активных пунктов одновременно
	),
	false
);?><?$APPLICATION->IncludeComponent(
	"bitrix:sale.personal.order", 
	".default", 
	array(
		"SEF_MODE" => "Y",
		"SEF_FOLDER" => "/personal/order/",
		"ORDERS_PER_PAGE" => "10",
		"PATH_TO_PAYMENT" => "/personal/order/payment/",
		"PATH_TO_BASKET" => "/personal/cart/",
		"SET_TITLE" => "Y",
		"SAVE_IN_SESSION" => "N",
		"NAV_TEMPLATE" => "round",
		"SHOW_ACCOUNT_NUMBER" => "Y",
		"COMPONENT_TEMPLATE" => ".default",
		"PROP_1" => array(
		),
		"PROP_2" => array(
		),
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "3600",
		"CACHE_GROUPS" => "Y",
		"CUSTOM_SELECT_PROPS" => array(
		),
		"HISTORIC_STATUSES" => array(
			0 => "F",
		),
		"SEF_URL_TEMPLATES" => array(
			"list" => "index.php",
			"detail" => "detail/#ID#/",
			"cancel" => "cancel/#ID#/",
		)
	),
	false
);?><?endif;?><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>