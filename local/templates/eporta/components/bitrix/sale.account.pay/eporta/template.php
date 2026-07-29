<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

CJSCore::Init(array("popup"));

if (!empty($arResult["errorMessage"]))
{
	if (!is_array($arResult["errorMessage"]))
	{
		ShowError($arResult["errorMessage"]);
	}
	else
	{
		foreach ($arResult["errorMessage"] as $errorMessage)
		{
			ShowError($errorMessage);
		}
	}
}
else
{
	if ($arParams['REFRESHED_COMPONENT_MODE'] === 'Y')
	{
		$wrapperId = str_shuffle(mb_substr($arResult['SIGNED_PARAMS'], 0, 10));
		?>
		<div class="lk-bill-pay" id="bx-sap<?=$wrapperId?>">
			<?
			if ($arParams['SELL_VALUES_FROM_VAR'] != 'Y')
			{
				if ($arParams['SELL_SHOW_FIXED_VALUES'] === 'Y')
				{
					?>
					<div class="sale-acountpay-block">
						<div class="sale-acountpay-title"><?= Loc::getMessage("SAP_FIXED_PAYMENT") ?></div>
						<div class="sale-acountpay-fixedpay-container">
							<div class="sale-acountpay-fixedpay-list">
								<?
								foreach ($arParams["SELL_TOTAL"] as $valueChanging)
								{
									?>
									<div class="sale-acountpay-fixedpay-item">
										<?=CUtil::JSEscape(htmlspecialcharsbx($valueChanging))?>
									</div>
									<?
								}
								?>
							</div>
						</div>
					</div>
					<?
				}
				?>
				<div class="sale-acountpay-block">
					<div class="sale-acountpay-title"><?=Loc::getMessage("SAP_SUM")?></div>
					<input type="text" placeholder="0.00" class="epi sale-acountpay-input" value="0.00"
						name="<?=CUtil::JSEscape(htmlspecialcharsbx($arParams["VAR"]))?>"
						<?=($arParams['SELL_USER_INPUT'] === 'N' ? "disabled" : "")?>>
				</div>
				<?
			}
			else
			{
				if ($arParams['SELL_SHOW_RESULT_SUM'] === 'Y')
				{
					?>
					<div class="sale-acountpay-block">
						<div class="sale-acountpay-title"><?=Loc::getMessage("SAP_SUM")?></div>
						<div class="lk-bill-balance-sum"><?=SaleFormatCurrency($arResult["SELL_VAR_PRICE_VALUE"], $arParams['SELL_CURRENCY'])?></div>
					</div>
					<?
				}
				?>
				<input type="hidden" name="<?=CUtil::JSEscape(htmlspecialcharsbx($arParams["VAR"]))?>"
					class="sale-acountpay-input"
					value="<?=CUtil::JSEscape(htmlspecialcharsbx($arResult["SELL_VAR_PRICE_VALUE"]))?>">
				<?
			}
			?>
			<div class="sale-acountpay-block">
				<div class="sale-acountpay-title"><?=Loc::getMessage("SAP_TYPE_PAYMENT_TITLE")?></div>
				<div class="sale-acountpay-pp">
					<?
					foreach ($arResult['PAYSYSTEMS_LIST'] as $key => $paySystem)
					{
						?>
						<div class="sale-acountpay-pp-company <?= ($key == 0) ? 'bx-selected' : "" ?>">
							<div class="sale-acountpay-pp-company-graf-container">
								<input type="checkbox"
										class="sale-acountpay-pp-company-checkbox"
										name="PAY_SYSTEM_ID"
										value="<?=$paySystem['ID']?>"
										<?= ($key == 0) ? "checked='checked'" : "" ?>
								>
								<?
								if (isset($paySystem['LOGOTIP']))
								{
									?>
									<div class="sale-acountpay-pp-company-image"
										style="background-image: url(<?=$paySystem['LOGOTIP']?>);">
									</div>
									<?
								}
								?>
							</div>
							<div class="sale-acountpay-pp-company-smalltitle">
								<?=CUtil::JSEscape(htmlspecialcharsbx($paySystem['NAME']))?>
							</div>
						</div>
						<?
					}
					?>
				</div>
			</div>
			<a href="" class="btn lk-btn-primary sale-account-pay-button"><?=Loc::getMessage("SAP_BUTTON")?></a>
		</div>
		<?
		$javascriptParams = array(
			"alertMessages" => array("wrongInput" => Loc::getMessage('SAP_ERROR_INPUT')),
			"url" => CUtil::JSEscape($this->__component->GetPath().'/ajax.php'),
			"templateFolder" => CUtil::JSEscape($templateFolder),
			"templateName" => $this->__component->GetTemplateName(),
			"signedParams" => $arResult['SIGNED_PARAMS'],
			"wrapperId" => $wrapperId
		);
		$javascriptParams = CUtil::PhpToJSObject($javascriptParams);
		?>
		<script>
			var sc = new BX.saleAccountPay(<?=$javascriptParams?>);
		</script>
	<?
	}
	else
	{
		?>
		<div class="lk-bill-pay">
			<div class="sale-acountpay-title"><?=Loc::getMessage("SAP_BUY_MONEY")?></div>
			<form method="post" name="buyMoney" action="">
				<?
				foreach($arResult["AMOUNT_TO_SHOW"] as $value)
				{
					?>
					<label class="lk-check-row">
						<input type="radio" name="<?=CUtil::JSEscape(htmlspecialcharsbx($arParams["VAR"]))?>"
							value="<?=$value["ID"]?>" id="<?=CUtil::JSEscape(htmlspecialcharsbx($arParams["VAR"])).$value["ID"]?>">
						<span><?=$value["NAME"]?></span>
					</label>
					<?
				}
				?>
				<button type="submit" name="button" class="lk-btn-primary"><?=GetMessage("SAP_BUTTON")?></button>
			</form>
		</div>
		<?
	}
}
