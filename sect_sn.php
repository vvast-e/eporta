<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<noindex>
	<ul class="sn">
		<?if(!empty($arTemplateSettings["TEMPLATE_TELEGRAM_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_TELEGRAM_LINK"]?>" class="telegram" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_WHATSAPP_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_WHATSAPP_LINK"]?>" class="whatsapp" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_RUTUBE_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_RUTUBE_LINK"]?>" class="rutube" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_DZEN_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_DZEN_LINK"]?>" class="dzen" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_VIBER_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_VIBER_LINK"]?>" class="viber" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_TIKTOK_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_TIKTOK_LINK"]?>" class="tiktok" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_VK_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_VK_LINK"]?>" class="vk" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_FACEBOOK_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_FACEBOOK_LINK"]?>" class="fb" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_ODNOKLASSNIKI_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_ODNOKLASSNIKI_LINK"]?>" class="od" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_TWITTER_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_TWITTER_LINK"]?>" class="tw" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_INSTAGRAM_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_INSTAGRAM_LINK"]?>" class="go" rel="nofollow"></a></li><?endif;?>
		<?if(!empty($arTemplateSettings["TEMPLATE_YOUTUBE_LINK"])):?><li><a href="<?=$arTemplateSettings["TEMPLATE_YOUTUBE_LINK"]?>" class="yo" rel="nofollow"></a></li><?endif;?>
	</ul>
</noindex>
