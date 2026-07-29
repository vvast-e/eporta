<?
define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

//globals
global $USER;

$userName = $USER->GetFullName();
if (!$userName)
	$userName = $USER->GetLogin();
?>
<script>
	<?if ($userName):?>
	BX.localStorage.set("eshop_user_name", "<?=CUtil::JSEscape($userName)?>", 604800);
	<?else:?>
	BX.localStorage.remove("eshop_user_name");
	<?endif?>

	<?if (isset($_REQUEST["backurl"]) && strlen($_REQUEST["backurl"])>0 && preg_match('#^/\w#', $_REQUEST["backurl"])):?>
	document.location.href = "<?=CUtil::JSEscape($_REQUEST["backurl"])?>";
	<?endif?>
</script>

<?
// Эта ветка исполняется только для УЖЕ авторизованного пользователя — для анонима
// NEED_AUTH=true отдаёт форму входа средствами ядра ещё до этой точки (см.
// memory/project_lk_redesign_2026_07_16.md). Вендорская заглушка ниже (шаблон
// dresscode/.default) зовёт bitrix:menu/emptyMenuAuth — этого шаблона нет в
// local/templates/eporta/components/bitrix/menu/, поэтому под нашим шаблоном
// авторизованный визит на /auth/ падал с "Cannot find 'emptyMenuAuth' template".
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>
	<?$APPLICATION->SetTitle("Вы авторизованы");?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-empty">
			<div class="lk-empty-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"></path></svg>
			</div>
			<div class="lk-empty-title">Вы авторизованы<?= $userName ? " как " . htmlspecialcharsbx($userName) : "" ?></div>
			<div class="lk-empty-actions">
				<a href="/personal/" class="lk-btn-primary">В личный кабинет</a>
			</div>
		</div>
	</div>
	</div>
<?else:?>
<?$APPLICATION->SetTitle("Вы зарегистрированы и успешно авторизовались.");?>
<h1>Авторизация</h1>
<p>Вы зарегистрированы и успешно авторизовались.</p>
<p><a href="<?=SITE_DIR?>" class="backToIndexPage">Вернуться на главную страницу</a></p>
<div id="empty">
	<?$APPLICATION->IncludeComponent("bitrix:menu", "emptyMenuAuth", Array(
		"ROOT_MENU_TYPE" => "left",
			"MENU_CACHE_TYPE" => "N",
			"MENU_CACHE_TIME" => "3600",
			"MENU_CACHE_USE_GROUPS" => "Y",
			"MENU_CACHE_GET_VARS" => "",
			"MAX_LEVEL" => "1",
			"CHILD_MENU_TYPE" => "left",
			"USE_EXT" => "Y",
			"DELAY" => "N",
			"ALLOW_MULTI_SELECT" => "N",
		),
		false
	);?>
</div>
<?endif?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>