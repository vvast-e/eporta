<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
{
	die();
}

\Bitrix\Main\UI\Extension::load('ui.fonts.opensans');

if (is_array($arResult["ACCOUNT_LIST"])):
?>
<div class="lk-bill-balance">
	<div class="lk-bill-balance-card">
		<div class="lk-bill-balance-label">Состояние счёта на <?= $arResult["DATE"] ?></div>
		<?php foreach ($arResult["ACCOUNT_LIST"] as $accountValue): ?>
			<div class="lk-bill-balance-row">
				<div>
					<div class="lk-bill-balance-code"><?= htmlspecialcharsbx($accountValue['CURRENCY']) ?></div>
					<div class="lk-bill-balance-name"><?= htmlspecialcharsbx($accountValue['CURRENCY_FULL_NAME']) ?></div>
				</div>
				<div class="lk-bill-balance-sum"><?= $accountValue['SUM'] ?></div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>
