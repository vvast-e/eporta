<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_1"])):?><a href="mailto:<?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?>" class="email"><?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?></a><?endif;?>