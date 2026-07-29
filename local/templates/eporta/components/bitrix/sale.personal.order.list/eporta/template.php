<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
{
	die();
}

/** @var array $arParams */
/** @var array $arResult */

// EPORTA: собственная вёрстка списка заказов (Этап 6, Фаза B) — структура данных
// ($arResult["ORDERS"]/"INFO"/"NAV_STRING") скопирована с вендорского .default,
// но без ajax-смены способа оплаты (BX.Sale.PersonalOrderComponent.PersonalOrderList) —
// вне объёма этой фазы, "Оплатить" ведёт напрямую по PSA_ACTION_FILE.
if (!empty($arResult['ERRORS']['FATAL']))
{
	foreach ($arResult['ERRORS']['FATAL'] as $error)
	{
		ShowError($error);
	}
}
else
{
	if (!empty($arResult['ERRORS']['NONFATAL']))
	{
		foreach ($arResult['ERRORS']['NONFATAL'] as $error)
		{
			ShowError($error);
		}
	}

	if (empty($arResult['ORDERS']))
	{
		?>
		<div class="lk-empty">
			<div class="lk-empty-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="4" width="16" height="16" rx="4" stroke-width="2"/></svg>
			</div>
			<div class="lk-empty-title">Текущие заказы не найдены</div>
			<div class="lk-empty-desc">Оформленные заказы появятся здесь — вы сможете отслеживать их статус и повторить покупку в один клик</div>
			<div class="lk-empty-actions">
				<a href="<?=htmlspecialcharsbx($arParams['PATH_TO_CATALOG'])?>" class="lk-btn-primary">Перейти в каталог</a>
			</div>
		</div>
		<?
	}
	else
	{
		?>
		<div class="lk-orders-list">
		<?
		foreach ($arResult['ORDERS'] as $order):
			$statusName = $arResult['INFO']['STATUS'][$order['ORDER']['STATUS_ID']]['NAME'] ?? '';
			$isPaid = false;
			$payUrl = '';
			foreach ($order['PAYMENT'] as $payment)
			{
				if ($payment['PAID'] === 'Y')
				{
					$isPaid = true;
				}
				elseif ($payUrl === '' && $order['ORDER']['IS_ALLOW_PAY'] != 'N' && $payment['IS_CASH'] !== 'Y' && $payment['ACTION_FILE'] !== 'cash')
				{
					$payUrl = $payment['PSA_ACTION_FILE'];
				}
			}
			?>
			<div class="lk-order-card">
				<div class="lk-order-head">
					<div>
						<div class="lk-order-number">Заказ №<?=htmlspecialcharsbx($order['ORDER']['ACCOUNT_NUMBER'])?></div>
						<div class="lk-order-date">
							от <?=$order['ORDER']['DATE_INSERT_FORMATED']?> ·
							<?=count($order['BASKET_ITEMS'])?> поз.
						</div>
					</div>
					<?if ($statusName !== ''):?>
					<div class="lk-order-status<?=$isPaid ? ' is-paid' : ''?>"><?=htmlspecialcharsbx($statusName)?></div>
					<?endif?>
				</div>
				<div class="lk-order-sum"><?=$order['ORDER']['FORMATED_PRICE']?></div>
				<div class="lk-order-actions">
					<a href="<?=htmlspecialcharsbx($order['ORDER']['URL_TO_DETAIL'])?>" class="lk-btn-ghost">Подробнее</a>
					<a href="<?=htmlspecialcharsbx($order['ORDER']['URL_TO_COPY'])?>" class="lk-btn-ghost">Повторить заказ</a>
					<?if (!$isPaid && $payUrl !== ''):?>
					<a href="<?=htmlspecialcharsbx($payUrl)?>" class="lk-btn-primary" style="padding:10px 18px;font-size:13px">Оплатить</a>
					<?endif?>
					<?if ($order['ORDER']['CAN_CANCEL'] !== 'N'):?>
					<a href="<?=htmlspecialcharsbx($order['ORDER']['URL_TO_CANCEL'])?>" class="lk-order-cancel">Отменить</a>
					<?endif?>
				</div>
			</div>
			<?
		endforeach;
		?>
		</div>
		<?
		echo $arResult['NAV_STRING'];
	}
}
?>
