<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"])):?><div class="telephone"><?=$arTemplateSettings["TEMPLATE_TELEPHONE_1"]?></div><?endif;?>
<?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_1"])):?><div class="email">Email: <a href="mailto:<?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?>"><?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?></a></div><?endif;?>
<?if(!empty($arTemplateSettings["TEMPLATE_WORKING_TIME"])):?><ul class="list"><li><?=$arTemplateSettings["TEMPLATE_WORKING_TIME"]?></li></ul><?endif;?>