<?define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Личный кабинет");

// Дев-превью нового шаблона eporta: реальный профиль (Этап 6, Фаза B).
// Боевой dresscode:personal.info не трогаем — при любом другом активном
// шаблоне страница работает как прежде.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-breadcrumb"><a href="/">Главная</a> · Личный кабинет · Персональные данные</div>
		<div class="lk-title"><h1>Личный кабинет</h1></div>
		<?php $active = 'profile'; require $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . '/include/lk-tabs.php'; ?>
		<?$APPLICATION->IncludeComponent(
			"dresscode:personal.info",
			"eporta",
			[],
			false
		);?>
	</div>
	</div>
<?else:?>
<h1>Личный кабинет</h1>
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
	"dresscode:personal.info",
	"",
	Array(
	)
);?>
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
