<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<span class="heading">Контакты</span>
<ul class="information">
	<?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"])):?><li><span class="label">Телефон:</span> <span class="inf"> <?=$arTemplateSettings["TEMPLATE_TELEPHONE_1"]?></span></li><?endif;?>
	<?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_1"])):?><li><span class="label">Email:</span> <span class="inf"><a href="mailto:<?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?>"><?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?></a></span></li><?endif;?>
	<?if(!empty($arTemplateSettings["TEMPLATE_ADDRESS_FULL"])):?><li><span class="label">Адрес:</span> <span class="inf"><?=$arTemplateSettings["TEMPLATE_ADDRESS_FULL"]?></span></li><?endif;?>
	<li><span class="label"></span><span class="inf"><a href="<?=SITE_DIR?>contacts/" class="goContact">Посмотреть на карте</a></span></li>
</ul>