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
		["ID", "IBLOCK_ID", "PROPERTY_RATING", "PROPERTY_VOTE_COUNT", "PROPERTY_PRODUCT_DAY"]
	);
	while ($eportaPropsEl = $eportaPropsRes->GetNextElement()) {
		$eportaFields = $eportaPropsEl->GetFields();
		$eportaExtraProps[$eportaFields["ID"]] = [
			"RATING" => $eportaFields["PROPERTY_RATING_VALUE"] ?? 0,
			"VOTE_COUNT" => $eportaFields["PROPERTY_VOTE_COUNT_VALUE"] ?? 0,
			"PRODUCT_DAY" => $eportaFields["PROPERTY_PRODUCT_DAY_VALUE"] ?? "",
		];
	}
}
?>
<div style="display:grid;grid-template-columns:repeat(<?=$eportaCols?>,1fr);gap:16px">
<?php foreach ($arResult["ITEMS"] as $arItem):
	$eportaExtra = $eportaExtraProps[$arItem["ID"]] ?? ["RATING" => 0, "VOTE_COUNT" => 0, "PRODUCT_DAY" => ""];
	$rating = (int)$eportaExtra["RATING"];
	$voteCount = (int)$eportaExtra["VOTE_COUNT"];
	$stars = str_repeat("★", max(0, min(5, round($rating)))) . str_repeat("☆", 5 - max(0, min(5, round($rating))));
	$isHit = !empty($eportaExtra["PRODUCT_DAY"]);

	$price = $arItem["MIN_PRICE"] ?? null;
	$hasDiscount = $price && !empty($price["DISCOUNT_VALUE"]) && $price["DISCOUNT_VALUE"] < $price["VALUE"];

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
			<?php if (($arParams["SHOW_WISHLIST_REMOVE"] ?? "N") === "Y"): ?>
				<button type="button" class="wishlist-remove-btn" data-id="<?= (int)$arItem["ID"] ?>" onclick="event.preventDefault();event.stopPropagation();removeFromWishlistCard(this)" title="Удалить из избранного">×</button>
			<?php endif; ?>
		</div>
		<div class="info">
			<div class="stars"><?= $stars ?> <span><?= $voteCount ?></span></div>
			<div class="name"><?= htmlspecialcharsbx($arItem["NAME"]) ?></div>
			<div class="price-row">
				<?php if ($price): ?>
					<?php if ($hasDiscount): ?>
						<div><span class="price"><?= $price["PRINT_DISCOUNT_VALUE"] ?></span> <span class="price-old"><?= $price["PRINT_VALUE"] ?></span></div>
					<?php else: ?>
						<div class="price"><?= $price["PRINT_VALUE"] ?></div>
					<?php endif; ?>
				<?php else: ?>
					<div class="price">по запросу</div>
				<?php endif; ?>
				<div class="price-row-tools">
					<button class="btn-compare" onclick="addCompare(event, <?= (int)$arItem["ID"] ?>)" title="Сравнить">⇄</button>
					<button class="btn-cart" onclick="event.preventDefault()">В корзину</button>
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
