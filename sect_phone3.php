<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_2"])):?><span class="heading"><?=$arTemplateSettings["TEMPLATE_TELEPHONE_2"]?></span><?endif;?>
<?if(!empty($arTemplateSettings["TEMPLATE_WORKING_TIME_SHORT"])):?><div class="schedule"><?=$arTemplateSettings["TEMPLATE_WORKING_TIME_SHORT"]?></div><?endif;?>