<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

/**
 * @var array $arParams
 * @var array $arResult
 * @var $APPLICATION CMain
 */

if ($arParams["SET_TITLE"] == "Y")
{
	$APPLICATION->SetTitle(Loc::getMessage("SOA_ORDER_COMPLETE"));
}
?>

<? if (!empty($arResult["ORDER"])): ?>

	<div class="lk-empty">
		<div class="lk-empty-icon">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>
		</div>
		<div class="lk-empty-title">
			<?=Loc::getMessage("SOA_ORDER_SUC", array(
				"#ORDER_DATE#" => $arResult["ORDER"]["DATE_INSERT"]->toUserTime()->format('d.m.Y H:i'),
				"#ORDER_ID#" => htmlspecialcharsbx($arResult["ORDER"]["ACCOUNT_NUMBER"])
			))?>
			<? if (!empty($arResult['ORDER']["PAYMENT_ID"])): ?>
				<?=Loc::getMessage("SOA_PAYMENT_SUC", array(
					"#PAYMENT_ID#" => htmlspecialcharsbx($arResult['PAYMENT'][$arResult['ORDER']["PAYMENT_ID"]]['ACCOUNT_NUMBER'])
				))?>
			<? endif ?>
		</div>
		<? if ($arParams['NO_PERSONAL'] !== 'Y'): ?>
		<div class="lk-empty-desc"><?=Loc::getMessage('SOA_ORDER_SUC1', ['#LINK#' => $arParams['PATH_TO_PERSONAL']])?></div>
		<? endif; ?>
	</div>

	<?
	if ($arResult["ORDER"]["IS_ALLOW_PAY"] === 'Y')
	{
		if (!empty($arResult["PAYMENT"]))
		{
			foreach ($arResult["PAYMENT"] as $payment)
			{
				if ($payment["PAID"] != 'Y')
				{
					if (!empty($arResult['PAY_SYSTEM_LIST'])
						&& array_key_exists($payment["PAY_SYSTEM_ID"], $arResult['PAY_SYSTEM_LIST'])
					)
					{
						$arPaySystem = $arResult['PAY_SYSTEM_LIST_BY_PAYMENT_ID'][$payment["ID"]];

						if (empty($arPaySystem["ERROR"]))
						{
							?>
							<div class="lk-order-pay-block">
								<div class="lk-order-pay-header">
									<?=CFile::ShowImage($arPaySystem["LOGOTIP"], 64, 64, "class=\"lk-order-pay-logo\"", "", false) ?>
									<div>
										<div class="lk-order-pay-label"><?=Loc::getMessage("SOA_PAY") ?></div>
										<div class="lk-order-pay-name"><?=$arPaySystem["NAME"] ?></div>
									</div>
								</div>
								<div class="lk-order-pay-action">
									<? if ($arPaySystem["ACTION_FILE"] <> '' && $arPaySystem["NEW_WINDOW"] == "Y" && $arPaySystem["IS_CASH"] != "Y"): ?>
										<?
										$orderAccountNumber = urlencode(urlencode($arResult["ORDER"]["ACCOUNT_NUMBER"]));
										$paymentAccountNumber = $payment["ACCOUNT_NUMBER"];
										?>
										<script>
											window.open('<?=$arParams["PATH_TO_PAYMENT"]?>?ORDER_ID=<?=$orderAccountNumber?>&PAYMENT_ID=<?=$paymentAccountNumber?>');
										</script>
									<?=Loc::getMessage("SOA_PAY_LINK", array("#LINK#" => $arParams["PATH_TO_PAYMENT"]."?ORDER_ID=".$orderAccountNumber."&PAYMENT_ID=".$paymentAccountNumber))?>
									<? if (CSalePdf::isPdfAvailable() && $arPaySystem['IS_AFFORD_PDF']): ?>
									<br/>
										<?=Loc::getMessage("SOA_PAY_PDF", array("#LINK#" => $arParams["PATH_TO_PAYMENT"]."?ORDER_ID=".$orderAccountNumber."&pdf=1&DOWNLOAD=Y"))?>
									<? endif ?>
									<? else: ?>
										<?=$arPaySystem["BUFFERED_OUTPUT"]?>
									<? endif ?>
								</div>
							</div>

							<?
						}
						else
						{
							?>
							<div class="lk-order-pay-error"><?=Loc::getMessage("SOA_ORDER_PS_ERROR")?></div>
							<?
						}
					}
					else
					{
						?>
						<div class="lk-order-pay-error"><?=Loc::getMessage("SOA_ORDER_PS_ERROR")?></div>
						<?
					}
				}
			}
		}
	}
	else
	{
		?>
		<div class="lk-order-pay-error"><strong><?=$arParams['MESS_PAY_SYSTEM_PAYABLE_ERROR']?></strong></div>
		<?
	}
	?>

<? else: ?>

	<div class="lk-empty">
		<div class="lk-empty-icon">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4M12 16h.01"></path></svg>
		</div>
		<div class="lk-empty-title"><?=Loc::getMessage("SOA_ERROR_ORDER")?></div>
		<div class="lk-empty-desc">
			<?=Loc::getMessage("SOA_ERROR_ORDER_LOST", ["#ORDER_ID#" => htmlspecialcharsbx($arResult["ACCOUNT_NUMBER"])])?>
			<?=Loc::getMessage("SOA_ERROR_ORDER_LOST1")?>
		</div>
		<div class="lk-empty-actions">
			<a href="/catalog/" class="lk-btn-primary">В каталог</a>
		</div>
	</div>

<? endif ?>