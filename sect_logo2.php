<?if(MAIN_PAGE === TRUE):?>
	<span><img src="<?=SITE_TEMPLATE_PATH?>/images/logo.png" alt=""></span>
<?else:?>
	<a href="<?=SITE_DIR?>" class="logo"><img src="<?=SITE_TEMPLATE_PATH?>/images/logo.png"></a>
<?endif;?>
<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<?if(!empty($arTemplateSettings["TEMPLATE_COPYRIGHT"])):?><p><?=$arTemplateSettings["TEMPLATE_COPYRIGHT"]?></p><?endif;?>