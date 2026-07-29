<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
?>
<!-- EPORTA: оверрайд .default для bitrix:system.auth.authorize — тот же компонент,
     что ядро Bitrix само вызывает на любой NEED_AUTH-странице (см. /auth/index.php,
     вызов не наш, а ядра main/include/prolog_before.php). Меняем только вёрстку,
     все поля/имена/hidden-инпуты — как у вендорского .default (см. память
     feedback_bitrix_override_gotchas — здесь mutator.php у вендора нет). -->
<div class="auth-page-wrap">
<div class="lk-card">
	<div class="lk-breadcrumb"><a href="/">Главная</a> · Авторизация</div>
	<div class="lk-title"><h1>Авторизация</h1></div>

	<div class="auth-tabs">
		<span class="auth-tab active">Вход</span>
		<a href="<?=$arResult["AUTH_REGISTER_URL"]?>" class="auth-tab">Регистрация</a>
		<a href="<?=$arResult["AUTH_FORGOT_PASSWORD_URL"]?>" class="auth-tab">Забыли пароль?</a>
	</div>

	<?if (!empty($arParams["~AUTH_RESULT"]) || !empty($arResult['ERROR_MESSAGE'])):?>
	<div class="auth-error">
		<?if (!empty($arParams["~AUTH_RESULT"])) ShowMessage($arParams["~AUTH_RESULT"]);?>
		<?if (!empty($arResult['ERROR_MESSAGE'])) ShowMessage($arResult['ERROR_MESSAGE']);?>
	</div>
	<?endif?>

	<div class="auth-body">
		<div class="auth-form-col">
			<form name="form_auth" method="post" target="_top" action="<?=$arResult["AUTH_URL"]?>">
				<input type="hidden" name="AUTH_FORM" value="Y" />
				<input type="hidden" name="TYPE" value="AUTH" />
				<?= bitrix_sessid_post(); ?>
				<?if ($arResult["BACKURL"] <> ''):?>
				<input type="hidden" name="backurl" value="<?=$arResult["BACKURL"]?>" />
				<?endif?>
				<?foreach ($arResult["POST"] as $key => $value):?>
				<input type="hidden" name="<?=$key?>" value="<?=$value?>" />
				<?endforeach?>

				<div class="lk-field-label">Логин или email</div>
				<input class="epi" type="text" name="USER_LOGIN" maxlength="255" value="<?=$arResult["LAST_LOGIN"]?>">

				<div class="lk-field-label">Пароль</div>
				<input class="epi" type="password" name="USER_PASSWORD" maxlength="255" autocomplete="off" placeholder="••••••••">

				<?if($arResult["CAPTCHA_CODE"]):?>
				<div class="auth-captcha" style="margin:16px 0">
					<input type="hidden" name="captcha_sid" value="<?=$arResult["CAPTCHA_CODE"]?>" />
					<img src="/bitrix/tools/captcha.php?captcha_sid=<?=$arResult["CAPTCHA_CODE"]?>" width="180" height="40" alt="CAPTCHA" />
					<div class="lk-field-label">Код с картинки</div>
					<input class="epi" type="text" name="captcha_word" maxlength="50" value="" autocomplete="off">
				</div>
				<?endif?>

				<?if ($arResult["STORE_PASSWORD"] == "Y"):?>
				<label class="lk-check-row" for="USER_REMEMBER" style="cursor:pointer">
					<input type="checkbox" id="USER_REMEMBER" name="USER_REMEMBER" value="Y">
					<span>Запомнить меня на этом устройстве</span>
				</label>
				<?endif?>

				<button type="submit" class="lk-btn-primary" name="Login" style="width:100%;text-align:center;margin-top:4px">Войти</button>
			</form>
		</div>

		<?if($arParams["NOT_SHOW_LINKS"] != "Y" && $arResult["NEW_USER_REGISTRATION"] == "Y"):?>
		<div class="auth-side-col">
			<h2>Ещё нет аккаунта?</h2>
			<p>Зарегистрируйтесь, чтобы отслеживать статус заказов, сохранять избранное и получать персональные предложения.</p>
			<a href="<?=$arResult["AUTH_REGISTER_URL"]?>" class="lk-btn-ghost" rel="nofollow">Зарегистрироваться</a>
		</div>
		<?endif?>
	</div>
</div>
</div>

<script>
<?if ($arResult["LAST_LOGIN"] <> ''):?>
try{document.form_auth.USER_PASSWORD.focus();}catch(e){}
<?else:?>
try{document.form_auth.USER_LOGIN.focus();}catch(e){}
<?endif?>
</script>

<?if($arResult["AUTH_SERVICES"]):?>
<?
$APPLICATION->IncludeComponent("bitrix:socserv.auth.form", "",
	array(
		"AUTH_SERVICES" => $arResult["AUTH_SERVICES"],
		"CURRENT_SERVICE" => $arResult["CURRENT_SERVICE"],
		"AUTH_URL" => $arResult["AUTH_URL"],
		"POST" => $arResult["POST"],
		"SHOW_TITLES" => $arResult["FOR_INTRANET"]?'N':'Y',
		"FOR_SPLIT" => $arResult["FOR_INTRANET"]?'Y':'N',
		"AUTH_LINE" => $arResult["FOR_INTRANET"]?'N':'Y',
	),
	$component,
	array("HIDE_ICONS"=>"Y")
);
?>
<?endif?>
