<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"])):?><a href="tel:<?=$arTemplateSettings["TEMPLATE_TELEPHONE_1"]?>" class="telephone"><?=$arTemplateSettings["TEMPLATE_TELEPHONE_1"]?></a><?endif;?>
