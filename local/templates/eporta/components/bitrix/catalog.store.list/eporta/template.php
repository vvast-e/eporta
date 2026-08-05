<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
{
	die();
}

/** @var array $arParams */
/** @var array $arResult */

// EPORTA: собственная вёрстка списка магазинов/складов (Этап 4). Общий шаблон для
// /store/ и /stores/ — оба раздела зовут bitrix:catalog.store с разными параметрами
// (PHONE/SCHEDULE/TITLE), но одинаковой структурой $arResult["STORES"], так что
// разница показывается адаптивно (телефон/график печатаются, только если пришли).
// Карта отключена (MAP_TYPE=0 в обоих разделах) — $arResult['VIEW_MAP'] игнорируем,
// заведение карты — отдельный этап (см. project_phase_c_roadmap.md).
if ($arResult["ERROR_MESSAGE"] <> '')
{
	ShowError($arResult["ERROR_MESSAGE"]);
}
?>
<div class="store-list-page">
	<div class="store-breadcrumb"><a href="/">Главная</a> · <?=htmlspecialcharsbx($arParams["TITLE"] ?: "Магазины")?></div>
	<div class="store-title"><h1><?=htmlspecialcharsbx($arParams["TITLE"] ?: "Магазины")?></h1></div>
	<?php if (empty($arResult["STORES"])): ?>
		<div class="lk-empty">
			<div class="lk-empty-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="2" d="M4 10l1.5-5h13L20 10M4 10v9a1 1 0 001 1h3v-5h8v5h3a1 1 0 001-1v-9M4 10h16"/></svg>
			</div>
			<div class="lk-empty-title">Магазины не найдены</div>
			<div class="lk-empty-desc">Список пока пуст — загляните позже</div>
		</div>
	<?php else: ?>
		<div class="store-grid">
			<?php foreach ($arResult["STORES"] as $arStore): ?>
				<a class="store-card" href="<?=htmlspecialcharsbx($arStore["URL"])?>">
					<?php if (!empty($arStore["DETAIL_IMG"]["SRC"])): ?>
						<div class="store-card-image">
							<img src="<?=htmlspecialcharsbx($arStore["DETAIL_IMG"]["SRC"])?>" alt="<?=htmlspecialcharsbx($arStore["STORE_TITLE"])?>" loading="lazy">
						</div>
					<?php endif; ?>
					<div class="store-card-body">
						<div class="store-card-title"><?=htmlspecialcharsbx($arStore["STORE_TITLE"])?></div>
						<?php if ($arStore["ADDRESS"] != ''): ?>
							<div class="store-card-row store-card-address"><?=htmlspecialcharsbx($arStore["ADDRESS"])?></div>
						<?php endif; ?>
						<?php if ($arStore["PHONE"] !== null && $arStore["PHONE"] != ''): ?>
							<div class="store-card-row store-card-phone"><?=htmlspecialcharsbx($arStore["PHONE"])?></div>
						<?php endif; ?>
						<?php if ($arStore["SCHEDULE"] !== null && $arStore["SCHEDULE"] != ''): ?>
							<div class="store-card-row store-card-schedule"><?=htmlspecialcharsbx($arStore["SCHEDULE"])?></div>
						<?php endif; ?>
						<?php if ($arStore["DESCRIPTION"] != ''): ?>
							<div class="store-card-row store-card-desc"><?=htmlspecialcharsbx($arStore["DESCRIPTION"])?></div>
						<?php endif; ?>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
