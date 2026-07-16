<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}
/** @var array $arResult */
/** @var CUser $USER */

//*************************************
//show current authorization section
//*************************************
?>
<form action="<?=$arResult['FORM_ACTION']?>" method="post" class="lk-subscribe-banner">
<?php echo bitrix_sessid_post();?>
	<div class="lk-subscribe-banner-text">
		Вы авторизованы как <b><?php echo htmlspecialcharsbx($USER->GetFormattedName(false));?></b> (<?php echo htmlspecialcharsbx($USER->GetLogin())?>)
	</div>
	<a class="lk-subscribe-logout" href="<?php echo $arResult['FORM_ACTION']?>?logout=yes&amp;<?=bitrix_sessid_get()?>&amp;sf_EMAIL=<?php echo $arResult['REQUEST']['EMAIL']?><?php echo $arResult['REQUEST']['RUBRICS_PARAM']?>">Завершить сеанс</a>
</form>
