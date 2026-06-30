<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_2"])):?><span class="heading"><?=$arTemplateSettings["TEMPLATE_TELEPHONE_2"]?></span><?endif;?>