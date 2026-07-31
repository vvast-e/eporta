<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var array $arParams */

$placeholderImg = SITE_TEMPLATE_PATH . "/assets/img/hit-1.jpg";
$eportaCols = (int)($arParams["LINE_ELEMENT_COUNT"] ?? 3) ?: 3;

// Компонент bitrix:catalog.section (single-iblock режим) не досчитывает $arItem["PROPERTIES"]
// для RATING/VOTE_COUNT/PRODUCT_DAY в этой связке параметров — подтягиваем их напрямую
// классическим CIBlockElement::GetList, это не зависит от внутренней кухни компонента.
$eportaExtraProps = [];
$eportaItemIds = array_column($arResult["ITEMS"], "ID");
if ($eportaItemIds) {
	$eportaPropsRes = \CIBlockElement::GetList(
		[],
		["IBLOCK_ID" => $arParams["IBLOCK_ID"], "ID" => $eportaItemIds],
		false,
		false,
		["ID", "IBLOCK_ID", "PROPERTY_RATING", "PROPERTY_VOTE_COUNT", "PROPERTY_PRODUCT_DAY", "PROPERTY_DISCOUNT"]
	);
	while ($eportaPropsEl = $eportaPropsRes->GetNextElement()) {
		$eportaFields = $eportaPropsEl->GetFields();
		$eportaExtraProps[$eportaFields["ID"]] = [
			"RATING" => $eportaFields["PROPERTY_RATING_VALUE"] ?? 0,
			"VOTE_COUNT" => $eportaFields["PROPERTY_VOTE_COUNT_VALUE"] ?? 0,
			"PRODUCT_DAY" => $eportaFields["PROPERTY_PRODUCT_DAY_VALUE"] ?? "",
			"DISCOUNT" => $eportaFields["PROPERTY_DISCOUNT_VALUE"] ?? 0,
		];
	}
}
?>
<div class="eporta-product-grid" style="display:grid;grid-template-columns:repeat(<?=$eportaCols?>,1fr);gap:16px">
<?php foreach ($arResult["ITEMS"] as $arItem):
	$eportaExtra = $eportaExtraProps[$arItem["ID"]] ?? ["RATING" => 0, "VOTE_COUNT" => 0, "PRODUCT_DAY" => "", "DISCOUNT" => 0];
	// (float), не (int) — иначе 4.99 обрезается до 4 и попадает мимо порога ХИТ ниже.
	$rating = (float)$eportaExtra["RATING"];
	$voteCount = (int)$eportaExtra["VOTE_COUNT"];
	$stars = str_repeat("★", max(0, min(5, round($rating)))) . str_repeat("☆", 5 - max(0, min(5, round($rating))));
	// ХИТ определяем автоматически по рейтингу (в выгрузке нет отдельной колонки
	// хит/популярность) — PRODUCT_DAY импортом никогда не заполняется, порог 4.8 согласован
	// с заказчиком. См. тот же порог в catalog.element/.default/template.php.
	$isHit = $rating >= 4.8;
	// "Новинка" — товар без рейтинга (0 или пусто в выгрузке, до накопления отзывов).
	// Взаимоисключающе с ХИТ (rating>=4.8), поэтому обе плашки одновременно не показываются.
	$isNew = $rating <= 0;

	// MIN_PRICE в БАЗЕ уже посчитан импортёром как цена ПОСЛЕ скидки (см. eportaImportOneProduct) —
	// зачёркнутую "старую" цену для витрины восстанавливаем обратно из процента (свойство DISCOUNT).
	$price = $arItem["MIN_PRICE"] ?? null;
	$discountPercent = (float)$eportaExtra["DISCOUNT"];
	$priceValue = (float)($price["VALUE"] ?? 0);
	$hasDiscount = $price && $discountPercent > 0 && $priceValue > 0;
	$priceOldPrint = $hasDiscount ? \CCurrencyLang::CurrencyFormat(round($priceValue / (1 - $discountPercent / 100)), $price["CURRENCY"] ?? "RUB") : "";

	$imgSrc = $arItem["PREVIEW_PICTURE"]["SRC"] ?? ($arItem["DETAIL_PICTURE"]["SRC"] ?? $placeholderImg);

	// DETAIL_PAGE_URL остаётся NULL при SEF_MODE=>"N" этого вызова компонента — строим ссылку
	// сами по CODE элемента. Роутинг детали в catalog/index.php резолвит товар по regex
	// "/([^/]+)\.html/" из REQUEST_URI, так что префикс пути не важен, важен только code+".html".
	$elementUrl = $arItem["DETAIL_PAGE_URL"] ?? null;
	if (!$elementUrl && !empty($arItem["CODE"])) {
		$elementUrl = "/catalog/" . $arItem["CODE"] . ".html";
	}
?>
	<a href="<?= $elementUrl ? htmlspecialcharsbx($elementUrl) : "javascript:void(0)" ?>" class="product-card" data-id="<?= (int)$arItem["ID"] ?>">
		<div class="img-wrap">
			<img src="<?= htmlspecialcharsbx($imgSrc) ?>" alt="<?= htmlspecialcharsbx($arItem["NAME"]) ?>">
			<?php if ($isHit): ?><span class="badge hit">ХИТ</span><?php endif; ?>
			<?php if ($isNew): ?><span class="badge new">Новинка</span><?php endif; ?>
			<?php if ($hasDiscount): ?><span class="badge" style="background:#c2670a;top:<?= ($isHit || $isNew) ? "44px" : "10px" ?>">−<?= round($discountPercent) ?>%</span><?php endif; ?>
			<?php if (($arParams["SHOW_WISHLIST_REMOVE"] ?? "N") === "Y"): ?>
				<button type="button" class="wishlist-remove-btn" data-id="<?= (int)$arItem["ID"] ?>" onclick="event.preventDefault();event.stopPropagation();removeFromWishlistCard(this)" title="Удалить из избранного">×</button>
			<?php endif; ?>
		</div>
		<div class="info">
			<!-- Раньше тут выводился $voteCount (число отзывов, всегда 0 — импорт его не
			заполняет), из-за чего рядом со звёздами у всех товаров стоял "0". Значение
			рейтинга нагляднее и совпадает с тем, что показывается на детальной странице. -->
			<div class="stars"><?= $stars ?><?php if ($rating > 0): ?> <span><?= number_format($rating, 1, ".", "") ?></span><?php endif; ?></div>
			<div class="name"><?= htmlspecialcharsbx($arItem["NAME"]) ?></div>
			<div class="price-row">
				<?php if ($price): ?>
					<?php if ($hasDiscount): ?>
						<div><span class="price"><?= $price["PRINT_VALUE"] ?></span> <span class="price-old"><?= $priceOldPrint ?></span></div>
					<?php else: ?>
						<div class="price"><?= $price["PRINT_VALUE"] ?></div>
					<?php endif; ?>
				<?php else: ?>
					<div class="price">по запросу</div>
				<?php endif; ?>
				<div class="price-row-tools">
					<button class="btn-compare" onclick="addCompare(event, <?= (int)$arItem["ID"] ?>)" title="Сравнить">⇄</button>
					<button class="btn-cart" onclick="addCartAjax(event, <?= (int)$arItem["ID"] ?>)">В корзину</button>
				</div>
			</div>
		</div>
	</a>
<?php endforeach; ?>
</div>

<?php if ($arResult["NAV_RESULT"] && $arResult["NAV_RESULT"]->NavPageCount > 1): ?>
	<div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:28px">
		<?= $arResult["NAV_STRING"] ?>
	</div>
<?php endif; ?>
