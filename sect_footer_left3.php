<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_FULL_ADDRESS"])):?><p class="hr"><?=$arTemplateSettings["TEMPLATE_FULL_ADDRESS"]?> <a href="<?=SITE_DIR?>about/contacts/" class="showMap">Посмотреть на карте</a></p><?endif;?>