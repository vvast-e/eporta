<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
{
	die();
}

/** @var array $arParams */
/** @var array $arResult */

// EPORTA: собственная вёрстка детальной карточки магазина (Этап 4). Карта отключена
// (MAP_TYPE=0) — $arResult['GPS_N']/GPS_S не используются, только адрес текстом.
?>
<div class="store-detail-page">
	<div class="store-breadcrumb">
		<a href="/">Главная</a>
		<?php if (isset($arResult["LIST_URL"])): ?>
			· <a href="<?=htmlspecialcharsbx($arResult["LIST_URL"])?>">Магазины</a>
		<?php endif; ?>
		· <?=htmlspecialcharsbx($arResult["TITLE"])?>
	</div>
	<div class="store-detail-card">
		<?php if ((int)$arResult["IMAGE_ID"] > 0): ?>
			<div class="store-detail-image">
				<?=CFile::ShowImage($arResult["IMAGE_ID"], 360, 280, "border=0", "", true)?>
			</div>
		<?php endif; ?>
		<div class="store-detail-body">
			<?php if ($arResult["TITLE"]): ?>
				<h1 class="store-detail-title"><?=htmlspecialcharsbx($arResult["TITLE"])?></h1>
			<?php endif; ?>
			<?php if ($arResult["DESCRIPTION"]): ?>
				<p class="store-detail-desc"><?=htmlspecialcharsbx($arResult["DESCRIPTION"])?></p>
			<?php endif; ?>
			<?php if ($arResult["ADDRESS"]): ?>
				<div class="store-detail-row">
					<div class="store-detail-label">Адрес</div>
					<div class="store-detail-value"><?=htmlspecialcharsbx($arResult["ADDRESS"])?></div>
				</div>
			<?php endif; ?>
			<?php if ($arResult["PHONE"] != ''): ?>
				<div class="store-detail-row">
					<div class="store-detail-label">Телефон</div>
					<div class="store-detail-value"><?=htmlspecialcharsbx($arResult["PHONE"])?></div>
				</div>
			<?php endif; ?>
			<?php if ($arResult["SCHEDULE"] != ''): ?>
				<div class="store-detail-row">
					<div class="store-detail-label">График работы</div>
					<div class="store-detail-value"><?=htmlspecialcharsbx($arResult["SCHEDULE"])?></div>
				</div>
			<?php endif; ?>
			<?php if (isset($arResult["LIST_URL"])): ?>
				<a class="lk-btn-ghost store-detail-back" href="<?=htmlspecialcharsbx($arResult["LIST_URL"])?>">К списку магазинов</a>
			<?php endif; ?>
		</div>
	</div>
</div>
