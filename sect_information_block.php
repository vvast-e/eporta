<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?>
<div class="global-information-block-cn">
	<div class="global-information-block-hide-scroll">
		<div class="global-information-block-hide-scroll-cn">
			<div class="information-heading">Есть вопросы?</div>
			<div class="information-text">свяжитесь с нами удобным Вам способом</div>
			<div class="information-list">
				<?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"]) || !empty($arTemplateSettings["TEMPLATE_TELEPHONE_2"])):?>
					<div class="information-list-item">
						<div class="tb">
							<div class="information-item-icon tc">
								<img src="<?=SITE_TEMPLATE_PATH?>/images/cont1.png">
							</div>
							<div class="tc">
								<?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"])):?><?=$arTemplateSettings["TEMPLATE_TELEPHONE_1"]?><br><?endif;?>
								<?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_2"])):?><?=$arTemplateSettings["TEMPLATE_TELEPHONE_2"]?><br><?endif;?>
							</div>
						</div>
					</div>
				<?endif;?>
				<?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_1"]) || !empty($arTemplateSettings["TEMPLATE_EMAIL_2"])):?>
					<div class="information-list-item">
						<div class="tb">
							<div class="information-item-icon tc">
								<img src="<?=SITE_TEMPLATE_PATH?>/images/cont2.png">
							</div>
							<div class="tc">
								<?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_1"])):?><a href="mailto:<?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?>"><?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?></a><br><?endif;?>
								<?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_2"])):?><a href="mailto:<?=$arTemplateSettings["TEMPLATE_EMAIL_2"]?>"><?=$arTemplateSettings["TEMPLATE_EMAIL_2"]?></a><br><?endif;?>
							</div>
						</div>
					</div>
				<?endif;?>
				<?if(!empty($arTemplateSettings["TEMPLATE_FULL_ADDRESS"])):?>
					<div class="information-list-item">
						<div class="tb">
							<div class="information-item-icon tc">
								<img src="<?=SITE_TEMPLATE_PATH?>/images/cont3.png">
							</div>
							<div class="tc">
								<?=$arTemplateSettings["TEMPLATE_FULL_ADDRESS"]?>
							</div>
						</div>
					</div>
				<?endif;?>
				<?if(!empty($arTemplateSettings["TEMPLATE_WORKING_TIME"])):?>
					<div class="information-list-item">
						<div class="tb">
							<div class="information-item-icon tc">
								<img src="<?=SITE_TEMPLATE_PATH?>/images/cont4.png">
							</div>
							<div class="tc"><?=$arTemplateSettings["TEMPLATE_WORKING_TIME"]?></div>
						</div>
					</div>
				<?endif;?>
			</div>
			<div class="information-feedback-container">
				<a href="<?=SITE_DIR?>callback/" class="information-feedback">Обратная связь</a>
			</div>
		</div>
	</div>
</div>