<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_1"])):?><span class="label"><?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?></span><?endif;?>
<?if(!empty($arTemplateSettings["TEMPLATE_ADDRESS"])):?><span class="label"><?=$arTemplateSettings["TEMPLATE_ADDRESS"]?></span><?endif;?>