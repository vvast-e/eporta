<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Web\Json;

// EPORTA: оверрайд .default для bitrix:system.auth.registration — тот же компонент,
// что ядро вызывает на /auth/?register=yes. Поля/hidden-инпуты/капча/согласие на ПД
// (bitrix:main.userconsent.request) сохранены как у вендора — меняется только вёрстка.
if ($arResult["SHOW_SMS_FIELD"] == true) {
	CJSCore::Init('phone_auth');
}
?>
<div class="auth-page-wrap">
<div class="lk-card">
	<div class="lk-breadcrumb"><a href="/">Главная</a> · <a href="<?=$arResult["AUTH_AUTH_URL"]?>">Авторизация</a> · Регистрация</div>
	<div class="lk-title"><h1>Регистрация</h1></div>

	<div class="auth-tabs">
		<a href="<?=$arResult["AUTH_AUTH_URL"]?>" class="auth-tab">Вход</a>
		<span class="auth-tab active">Регистрация</span>
	</div>

	<?if (!empty($arParams["~AUTH_RESULT"])):?>
	<div class="auth-error"><?ShowMessage($arParams["~AUTH_RESULT"]);?></div>
	<?endif?>

	<div style="padding:28px 28px 40px">
	<?if($arResult["SHOW_EMAIL_SENT_CONFIRMATION"]):?>
		<p style="font:600 14px var(--font)"><?=GetMessage("AUTH_EMAIL_SENT")?></p>

	<?elseif(!$arResult["SHOW_EMAIL_SENT_CONFIRMATION"] && $arResult["USE_EMAIL_CONFIRMATION"] === "Y"):?>
		<p style="font:500 13.5px var(--font);color:var(--text-mid);margin-bottom:20px"><?=GetMessage("AUTH_EMAIL_WILL_BE_SENT")?></p>
	<?endif?>

	<?if($arResult["SHOW_SMS_FIELD"] == true):?>
		<!-- Подтверждение по СМС — редкий сценарий (не в макете), оставляем вендорскую вёрстку как есть. -->
		<form method="post" action="<?=$arResult["AUTH_URL"]?>" name="regform">
			<input type="hidden" name="SIGNED_DATA" value="<?=htmlspecialcharsbx($arResult["SIGNED_DATA"])?>" />
			<div class="lk-field-label">Код из SMS</div>
			<input class="epi" size="30" type="text" name="SMS_CODE" value="<?=htmlspecialcharsbx($arResult["SMS_CODE"])?>" autocomplete="off" />
			<button type="submit" class="lk-btn-primary" name="code_submit_button" style="margin-top:16px">Отправить код</button>
		</form>
		<script>
		new BX.PhoneAuth({
			containerId: 'bx_register_resend',
			errorContainerId: 'bx_register_error',
			interval: <?=$arResult["PHONE_CODE_RESEND_INTERVAL"]?>,
			data: <?= Json::encode(['signedData' => $arResult["SIGNED_DATA"]]) ?>,
			onError: function(response) {
				var errorDiv = BX('bx_register_error');
				var errorNode = BX.findChildByClassName(errorDiv, 'errortext');
				errorNode.innerHTML = '';
				for (var i = 0; i < response.errors.length; i++) {
					errorNode.innerHTML = errorNode.innerHTML + BX.util.htmlspecialchars(response.errors[i].message) + '<br>';
				}
				errorDiv.style.display = '';
			}
		});
		</script>
		<div id="bx_register_error" style="display:none"><?ShowError("error")?></div>
		<div id="bx_register_resend"></div>

	<?elseif(!$arResult["SHOW_EMAIL_SENT_CONFIRMATION"]):?>
		<div style="font:500 14px/1.6 var(--font);color:var(--text-mid);max-width:640px;margin-bottom:26px">
			Укажите данные один раз — они будут использоваться при оформлении заказов, а вы будете видеть
			их статус и историю покупок в личном кабинете.
		</div>

		<form method="post" action="<?=$arResult["AUTH_URL"]?>" name="bform" enctype="multipart/form-data">
			<input type="hidden" name="AUTH_FORM" value="Y" />
			<input type="hidden" name="TYPE" value="REGISTRATION" />
			<?= bitrix_sessid_post(); ?>

			<div class="auth-grid-2">
				<div>
					<div class="lk-field-label">Имя</div>
					<input class="epi" type="text" name="USER_NAME" maxlength="50" value="<?=$arResult["USER_NAME"]?>">
				</div>
				<div>
					<div class="lk-field-label">Фамилия</div>
					<input class="epi" type="text" name="USER_LAST_NAME" maxlength="50" value="<?=$arResult["USER_LAST_NAME"]?>">
				</div>
				<div>
					<div class="lk-field-label">Логин (минимум 3 символа)</div>
					<input class="epi" type="text" name="USER_LOGIN" maxlength="50" value="<?=$arResult["USER_LOGIN"]?>">
				</div>
				<?if($arResult["EMAIL_REGISTRATION"]):?>
				<div>
					<div class="lk-field-label">E-mail<?if($arResult["EMAIL_REQUIRED"]):?> *<?endif?></div>
					<input class="epi" type="text" name="USER_EMAIL" maxlength="255" value="<?=$arResult["USER_EMAIL"]?>">
				</div>
				<?endif?>
				<div>
					<div class="lk-field-label">Пароль</div>
					<input class="epi" type="password" name="USER_PASSWORD" maxlength="255" value="<?=$arResult["USER_PASSWORD"]?>" autocomplete="off">
				</div>
				<div>
					<div class="lk-field-label">Подтверждение пароля</div>
					<input class="epi" type="password" name="USER_CONFIRM_PASSWORD" maxlength="255" value="<?=$arResult["USER_CONFIRM_PASSWORD"]?>" autocomplete="off">
				</div>
				<?if($arResult["PHONE_REGISTRATION"]):?>
				<div>
					<div class="lk-field-label">Телефон<?if($arResult["PHONE_REQUIRED"]):?> *<?endif?></div>
					<input class="epi" type="text" name="USER_PHONE_NUMBER" maxlength="255" value="<?=$arResult["USER_PHONE_NUMBER"]?>">
				</div>
				<?endif?>
			</div>

			<?// Доп. пользовательские свойства (UF-поля формы регистрации), если заведены в админке.?>
			<?if($arResult["USER_PROPERTIES"]["SHOW"] == "Y"):?>
			<div style="margin-top:20px;max-width:820px">
				<div class="lk-field-label" style="margin-top:0"><?=trim($arParams["USER_PROPERTY_NAME"]) <> '' ? $arParams["USER_PROPERTY_NAME"] : GetMessage("USER_TYPE_EDIT_TAB")?></div>
				<?foreach ($arResult["USER_PROPERTIES"]["DATA"] as $FIELD_NAME => $arUserField):?>
				<div style="margin-bottom:14px">
					<div class="lk-field-label"><?if ($arUserField["MANDATORY"]=="Y"):?>* <?endif;?><?=$arUserField["EDIT_FORM_LABEL"]?></div>
					<?$APPLICATION->IncludeComponent(
						"bitrix:system.field.edit",
						$arUserField["USER_TYPE"]["USER_TYPE_ID"],
						array("bVarsFromForm" => $arResult["bVarsFromForm"], "arUserField" => $arUserField, "form_name" => "bform"), null, array("HIDE_ICONS"=>"Y"));?>
				</div>
				<?endforeach;?>
			</div>
			<?endif;?>

			<?if ($arResult["USE_CAPTCHA"] == "Y"):?>
			<div class="auth-captcha" style="margin-top:20px">
				<input type="hidden" name="captcha_sid" value="<?=$arResult["CAPTCHA_CODE"]?>" />
				<img src="/bitrix/tools/captcha.php?captcha_sid=<?=$arResult["CAPTCHA_CODE"]?>" width="180" height="40" alt="CAPTCHA" />
				<div class="lk-field-label">Код с картинки</div>
				<input class="epi" type="text" name="captcha_word" maxlength="50" value="" autocomplete="off" style="max-width:240px">
			</div>
			<?endif?>

			<div style="margin-top:22px;max-width:820px">
				<?$APPLICATION->IncludeComponent("bitrix:main.userconsent.request", "",
					array(
						"ID" => COption::getOptionString("main", "new_user_agreement", ""),
						"IS_CHECKED" => "Y",
						"AUTO_SAVE" => "N",
						"IS_LOADED" => "Y",
						"ORIGINATOR_ID" => $arResult["AGREEMENT_ORIGINATOR_ID"],
						"ORIGIN_ID" => $arResult["AGREEMENT_ORIGIN_ID"],
						"INPUT_NAME" => $arResult["AGREEMENT_INPUT_NAME"],
						"REPLACE" => array(
							"button_caption" => "Зарегистрироваться",
							"fields" => array("Имя", "Фамилия", "Логин", "Пароль", "E-mail"),
						),
					)
				);?>
			</div>

			<button type="submit" class="lk-btn-primary" name="Register" style="margin-top:22px">Зарегистрироваться</button>
		</form>

		<?if (!empty($arResult["GROUP_POLICY"]["PASSWORD_REQUIREMENTS"])):?>
		<p style="font:500 12px var(--font);color:var(--text-hint);margin-top:16px"><?=$arResult["GROUP_POLICY"]["PASSWORD_REQUIREMENTS"]?></p>
		<?endif?>

		<script>
		try { document.bform.USER_NAME.focus(); } catch(e) {}
		</script>
	<?endif?>
	</div>
</div>
</div>
