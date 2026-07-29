<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Рассылки");

// Дев-превью нового шаблона eporta: реальная подписка на рассылку (Этап 6, Фаза C).
// Боевой bitrix:subscribe.edit (шаблон .default) не трогаем.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-breadcrumb"><a href="/">Главная</a> · <a href="/personal/">Личный кабинет</a> · Подписка на рассылку</div>
		<div class="lk-title"><h1>Настройка подписки</h1></div>
		<?php $active = 'subscribe'; require $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . '/include/lk-tabs.php'; ?>
		<div style="padding:28px 28px 8px">
		<?$APPLICATION->IncludeComponent("bitrix:subscribe.edit", "eporta", Array(
			"AJAX_MODE" => "N",	// Включить режим AJAX
				"SHOW_HIDDEN" => "N",	// Показать скрытые рубрики подписки
				"ALLOW_ANONYMOUS" => "Y",	// Разрешить анонимную подписку
				"SHOW_AUTH_LINKS" => "Y",	// Показывать ссылки на авторизацию при анонимной подписке
				"CACHE_TYPE" => "A",	// Тип кеширования
				"CACHE_TIME" => "36000000",	// Время кеширования (сек.)
				"SET_TITLE" => "N",	// Устанавливать заголовок страницы
				"AJAX_OPTION_SHADOW" => "Y",
				"AJAX_OPTION_JUMP" => "N",	// Включить прокрутку к началу компонента
				"AJAX_OPTION_STYLE" => "Y",	// Включить подгрузку стилей
				"AJAX_OPTION_HISTORY" => "N",	// Включить эмуляцию навигации браузера
			),
			false
		);?>
		</div>
	</div>
	</div>
<?else:?>
<h1>Настройка подписки</h1>
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
);?><?$APPLICATION->IncludeComponent("bitrix:subscribe.edit", ".default", Array(
	"AJAX_MODE" => "N",	// Включить режим AJAX
		"SHOW_HIDDEN" => "N",	// Показать скрытые рубрики подписки
		"ALLOW_ANONYMOUS" => "Y",	// Разрешить анонимную подписку
		"SHOW_AUTH_LINKS" => "Y",	// Показывать ссылки на авторизацию при анонимной подписке
		"CACHE_TYPE" => "A",	// Тип кеширования
		"CACHE_TIME" => "36000000",	// Время кеширования (сек.)
		"SET_TITLE" => "N",	// Устанавливать заголовок страницы
		"AJAX_OPTION_SHADOW" => "Y",
		"AJAX_OPTION_JUMP" => "N",	// Включить прокрутку к началу компонента
		"AJAX_OPTION_STYLE" => "Y",	// Включить подгрузку стилей
		"AJAX_OPTION_HISTORY" => "N",	// Включить эмуляцию навигации браузера
	),
	false
);?><?endif;?><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>