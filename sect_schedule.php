<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_WORKING_TIME_SHORT"])):?><span class="heading"><?=$arTemplateSettings["TEMPLATE_WORKING_TIME_SHORT"]?></span><?endif;?>