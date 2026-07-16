<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Личный счет");

// Дев-превью нового шаблона eporta: реальный личный счёт (Этап 6, Фаза C).
// Боевые sale.personal.account / sale.account.pay (шаблон .default) не трогаем.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-breadcrumb"><a href="/">Главная</a> · <a href="/personal/">Личный кабинет</a> · Личный счёт</div>
		<div class="lk-title"><h1>Личный счёт</h1></div>
		<?php $active = 'bill'; require $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . '/include/lk-tabs.php'; ?>
		<div class="lk-bill-row">
		<?$APPLICATION->IncludeComponent("bitrix:sale.personal.account", "eporta", Array(

			),
			false
		);?><?$APPLICATION->IncludeComponent(
			"bitrix:sale.account.pay",
			"eporta",
			array(
				"COMPONENT_TEMPLATE" => "eporta",
				"REFRESHED_COMPONENT_MODE" => "Y",
				"PATH_TO_BASKET" => "/personal/cart",
				"PATH_TO_PAYMENT" => "/personal/order/payment",
				"SELL_CURRENCY" => "RUB",
				"PERSON_TYPE" => "1",
				"ELIMINATED_PAY_SYSTEMS" => array(
					0 => "1",
					1 => "2",
				),
				"SELL_VALUES_FROM_VAR" => "N",
				"SELL_SHOW_FIXED_VALUES" => "Y",
				"SELL_TOTAL" => array(
					0 => "100",
					1 => "200",
					2 => "500",
					3 => "1000",
					4 => "5000",
					5 => "",
				),
				"SELL_USER_INPUT" => "Y",
				"SET_TITLE" => "Y"
			),
			false
		);?>
		</div>
	</div>
	</div>
<?else:?>
<h1>Личный счет</h1>
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
);?><?$APPLICATION->IncludeComponent("bitrix:sale.personal.account", ".default", Array(
	
	),
	false
);?><?$APPLICATION->IncludeComponent(
	"bitrix:sale.account.pay", 
	".default", 
	array(
		"COMPONENT_TEMPLATE" => ".default",
		"REFRESHED_COMPONENT_MODE" => "Y",
		"PATH_TO_BASKET" => "/personal/cart",
		"PATH_TO_PAYMENT" => "/personal/order/payment",
		"SELL_CURRENCY" => "RUB",
		"PERSON_TYPE" => "1",
		"ELIMINATED_PAY_SYSTEMS" => array(
			0 => "1",
			1 => "2",
		),
		"SELL_VALUES_FROM_VAR" => "N",
		"SELL_SHOW_FIXED_VALUES" => "Y",
		"SELL_TOTAL" => array(
			0 => "100",
			1 => "200",
			2 => "500",
			3 => "1000",
			4 => "5000",
			5 => "",
		),
		"SELL_USER_INPUT" => "Y",
		"SET_TITLE" => "Y"
	),
	false
);?><br><?endif;?><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>