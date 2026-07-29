<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}
/** @var array $arResult */

//***********************************
//setting section
//***********************************
?>
<div class="lk-subscribe-heading">Настройки подписки</div>
<form action="<?=$arResult['FORM_ACTION']?>" method="post" class="lk-subscribe-form">
<?php echo bitrix_sessid_post();?>
	<div class="lk-field-label" style="margin-top:0">Ваш e-mail<span class="starrequired">*</span></div>
	<input class="epi" type="text" name="EMAIL" value="<?=$arResult['SUBSCRIPTION']['EMAIL'] != '' ? $arResult['SUBSCRIPTION']['EMAIL'] : $arResult['REQUEST']['EMAIL'];?>" size="30" maxlength="255">

	<div class="lk-field-label">Рубрики подписки<span class="starrequired">*</span></div>
	<div class="lk-subscribe-rubrics">
		<?php foreach ($arResult['RUBRICS'] as $itemValue):?>
			<label class="lk-check-row" style="margin:0">
				<input type="checkbox" name="RUB_ID[]" value="<?=$itemValue['ID']?>" <?php echo ($itemValue['CHECKED']) ? 'checked' : '';?>>
				<span><?=$itemValue['NAME']?></span>
			</label>
		<?php endforeach;?>
	</div>

	<div class="lk-field-label">Предпочтительный формат</div>
	<div class="lk-subscribe-radio-row">
		<label class="lk-subscribe-radio">
			<input type="radio" name="FORMAT" value="html" <?php echo ($arResult['SUBSCRIPTION']['FORMAT'] == 'html' || $arResult['SUBSCRIPTION']['FORMAT'] == '') ? 'checked' : '';?>>
			<span>HTML</span>
		</label>
		<label class="lk-subscribe-radio">
			<input type="radio" name="FORMAT" value="text" <?php echo ($arResult['SUBSCRIPTION']['FORMAT'] == 'text') ? 'checked' : '';?>>
			<span>Текст</span>
		</label>
	</div>

	<div class="lk-subscribe-note">После изменения адреса подписки на почту придёт код подтверждения — подписка активируется после его ввода.</div>

	<button type="submit" name="Save" class="lk-btn-primary" style="border:none;cursor:pointer"><?php echo ($arResult['ID'] > 0 ? GetMessage('subscr_upd') : GetMessage('subscr_add'))?></button>

	<input type="hidden" name="PostAction" value="<?php echo ($arResult['ID'] > 0 ? 'Update' : 'Add')?>">
	<input type="hidden" name="ID" value="<?php echo $arResult['SUBSCRIPTION']['ID'];?>">
	<?php if ($_REQUEST['register'] == 'YES'):?>
		<input type="hidden" name="register" value="YES">
	<?php endif;?>
	<?php if ($_REQUEST['authorize'] == 'YES'):?>
		<input type="hidden" name="authorize" value="YES">
	<?php endif;?>
</form>
