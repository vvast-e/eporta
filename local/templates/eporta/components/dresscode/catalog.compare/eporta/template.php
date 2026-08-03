<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$this->setFrameMode(true);

/** @var array $arResult */
$eportaCompareItems = $arResult["ITEMS"] ?? [];
$eportaCompareProps = $arResult["PROPERTIES"] ?? [];
uasort($eportaCompareProps, function ($a, $b) { return ($a["SORT"] ?? 500) <=> ($b["SORT"] ?? 500); });
?>
<?php if (empty($eportaCompareItems)): ?>
	<div class="lk-empty">
		<div class="lk-empty-icon">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v14a2 2 0 0 0 2 2h9"></path><path d="M3 8h9a2 2 0 0 1 2 2v11"></path></svg>
		</div>
		<div class="lk-empty-title">Список сравнения пуст</div>
		<div class="lk-empty-desc">Добавляйте двери на страницах каталога — иконкой «⇄» на карточке товара или на детальной странице.</div>
		<div class="lk-empty-actions">
			<a href="/catalog/" class="lk-btn-primary">Перейти в каталог</a>
		</div>
	</div>
<?php else: ?>
	<div class="compare-toolbar">
		<div class="compare-count">Сравниваем: <?= count($eportaCompareItems) ?></div>
		<button type="button" class="lk-btn-ghost" onclick="clearCompareAll()">Очистить список</button>
	</div>
	<div class="compare-table-wrap">
		<table class="compare-table">
			<thead>
				<tr>
					<th></th>
					<?php foreach ($eportaCompareItems as $arItem): ?>
						<th class="compare-item-col">
							<button type="button" class="compare-item-remove" title="Удалить" onclick="removeCompareItem(<?= (int)$arItem["ID"] ?>)">×</button>
							<?php
							$photoSrc = $arItem["PICTURE"]["src"] ?? (SITE_TEMPLATE_PATH . "/assets/img/hit-1.jpg");
							eportaPicture($photoSrc, $arItem["NAME"], ["class" => "compare-item-photo", "loading" => "lazy", "decoding" => "async"]);
							?>
							<a class="compare-item-name" href="<?= htmlspecialcharsbx($arItem["DETAIL_PAGE_URL"]) ?>"><?= htmlspecialcharsbx($arItem["NAME"]) ?></a>
							<div class="compare-item-price"><?= !empty($arItem["PRICE"]) ? $arItem["PRICE"] : "по запросу" ?></div>
							<div class="compare-item-actions">
								<button type="button" class="lk-btn-primary" onclick="addCartFromCompare(event, <?= (int)$arItem["ID"] ?>)">В корзину</button>
							</div>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($eportaCompareProps as $propCode => $arProp): ?>
					<tr>
						<td><?= htmlspecialcharsbx($arProp["NAME"]) ?></td>
						<?php foreach ($eportaCompareItems as $arItem): ?>
							<?php $displayValue = $arItem["PROPERTIES"][$propCode]["DISPLAY_VALUE"] ?? ""; ?>
							<td class="compare-prop-value"><?= $displayValue !== "" ? htmlspecialcharsbx(is_array($displayValue) ? implode(", ", $displayValue) : $displayValue) : "—" ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
