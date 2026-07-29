<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Сравнение товаров");

//include module
\Bitrix\Main\Loader::includeModule("dw.deluxe");

//vars
$catalogIblockId = null;
$arPriceCodes = array();

//get template settings
$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
if(!empty($arTemplateSettings)){
	$catalogIblockId = $arTemplateSettings["TEMPLATE_PRODUCT_IBLOCK_ID"];
	$arPriceCodes = explode(", ", $arTemplateSettings["TEMPLATE_PRICE_CODES"]);
}

// Дев-превью нового шаблона eporta: реальное сравнение товаров (Этап 6, Фаза D).
// Боевой dresscode:catalog.compare/.default не трогаем.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-breadcrumb"><a href="/">Главная</a> · Сравнение товаров</div>
		<div class="lk-title"><h1>Сравнение товаров</h1></div>
		<?php $active = 'compare'; require $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . '/include/lk-tabs.php'; ?>
		<?$APPLICATION->IncludeComponent("dresscode:catalog.compare", "eporta", Array(
				"IBLOCK_TYPE" => "catalog",
				"IBLOCK_ID" => $catalogIblockId,
				"CACHE_TYPE" => "N",
			),
			false
		);?>
	</div>
	</div>
<?else:?>
	<h1>Список сравнения</h1>
	<?$APPLICATION->IncludeComponent("dresscode:catalog.compare", ".default", Array(
			"IBLOCK_TYPE" => "catalog",
			"IBLOCK_ID" => $catalogIblockId,
			"CACHE_TYPE" => "A",	// Тип кеширования
			"CACHE_TIME" => "360000",	// Время кеширования (сек.)
		),
		false
	);?><br>
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>