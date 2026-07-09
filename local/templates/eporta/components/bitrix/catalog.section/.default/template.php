<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var array $arParams */

$placeholderImg = SITE_TEMPLATE_PATH . "/assets/img/hit-1.jpg";
?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
<?php foreach ($arResult["ITEMS"] as $arItem):
	$rating = (int)($arItem["PROPERTIES"]["RATING"]["VALUE"] ?? 0);
	$voteCount = (int)($arItem["PROPERTIES"]["VOTE_COUNT"]["VALUE"] ?? 0);
	$stars = str_repeat("★", max(0, min(5, round($rating)))) . str_repeat("☆", 5 - max(0, min(5, round($rating))));
	$isHit = !empty($arItem["PROPERTIES"]["PRODUCT_DAY"]["VALUE"]);

	$price = $arItem["MIN_PRICE"] ?? null;
	$hasDiscount = $price && !empty($price["DISCOUNT_VALUE"]) && $price["DISCOUNT_VALUE"] < $price["VALUE"];

	$imgSrc = $arItem["PREVIEW_PICTURE"]["SRC"] ?? ($arItem["DETAIL_PICTURE"]["SRC"] ?? $placeholderImg);
?>
	<a href="<?= htmlspecialcharsbx($arItem["DETAIL_PAGE_URL"]) ?>" class="product-card">
		<div class="img-wrap">
			<img src="<?= htmlspecialcharsbx($imgSrc) ?>" alt="<?= htmlspecialcharsbx($arItem["NAME"]) ?>">
			<?php if ($isHit): ?><span class="badge hit">ХИТ</span><?php endif; ?>
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
				<button class="btn-cart" onclick="event.preventDefault()">В корзину</button>
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
