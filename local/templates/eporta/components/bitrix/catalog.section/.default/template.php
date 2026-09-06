<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var array $arParams */

$eportaCols = (int)($arParams["LINE_ELEMENT_COUNT"] ?? 3) ?: 3;
// На странице коллекции кружки цвета переехали в блок "Модели коллекции" (catalog/index.php) —
// здесь, в блоке "Все товары коллекции", их не показываем (SHOW_SWATCHES="N" из вызывающего
// кода). Во всех остальных местах (обычный каталог, главная, wishlist, simple.offers) — как раньше.
$eportaShowSwatches = ($arParams["SHOW_SWATCHES"] ?? "Y") !== "N";
// Вид плитка/список — общий для всех вызовов этого шаблона (каталог/коллекции/wishlist/
// simple.offers), кука eporta_view переключается кнопками в catalog/index.php.
$eportaGridView = ($_COOKIE["eporta_view"] ?? "") === "list" ? " eporta-product-grid--list" : "";

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
		["ID", "IBLOCK_ID", "PROPERTY_RATING", "PROPERTY_VOTE_COUNT", "PROPERTY_PRODUCT_DAY", "PROPERTY_DISCOUNT", "PROPERTY_MODEL", "PROPERTY_COATING_COLOR"]
	);
	while ($eportaPropsEl = $eportaPropsRes->GetNextElement()) {
		$eportaFields = $eportaPropsEl->GetFields();
		$eportaExtraProps[$eportaFields["ID"]] = [
			"RATING" => $eportaFields["PROPERTY_RATING_VALUE"] ?? 0,
			"VOTE_COUNT" => $eportaFields["PROPERTY_VOTE_COUNT_VALUE"] ?? 0,
			"PRODUCT_DAY" => $eportaFields["PROPERTY_PRODUCT_DAY_VALUE"] ?? "",
			"DISCOUNT" => $eportaFields["PROPERTY_DISCOUNT_VALUE"] ?? 0,
			"MODEL" => $eportaFields["PROPERTY_MODEL_VALUE"] ?? "",
			// Собственный цвет карточки — не полагаемся на $arItem["PROPERTIES"] компонента
			// (см. комментарий выше про RATING/VOTE_COUNT/PRODUCT_DAY), берём из того же
			// bulk-запроса, что и остальные "досчитанные" свойства.
			"COATING_COLOR" => $eportaFields["PROPERTY_COATING_COLOR_VALUE"] ?? "",
		];
	}
}

// Свотчи цвета: catalog/index.php схлопывает выдачу до одного элемента на модель (иначе одна
// модель со множеством цветов занимала всю страницу — см. комментарий там), поэтому здесь под
// карточкой показываем остальные цвета кружками-ссылками — тот же паттерн, что на детальной
// странице (catalog.element/.default/template.php:101-142), но компактно и без выбора
// остекления. Один общий запрос на все модели текущей страницы, не по одному на карточку.
$eportaSwatchesByModel = [];
$eportaModels = $eportaShowSwatches ? array_unique(array_filter(array_column($eportaExtraProps, "MODEL"))) : [];
if ($eportaModels) {
	$eportaVariantsRes = \CIBlockElement::GetList(
		[],
		["IBLOCK_ID" => $arParams["IBLOCK_ID"], "ACTIVE" => "Y", "PROPERTY_MODEL" => $eportaModels],
		false,
		false,
		["ID", "NAME", "CODE", "PROPERTY_MODEL", "PROPERTY_COATING_COLOR", "PROPERTY_DISCOUNT", "DETAIL_PAGE_URL", "PREVIEW_PICTURE", "DETAIL_PICTURE", "CATALOG_PRICE_1"]
	);
	while ($eportaV = $eportaVariantsRes->Fetch()) {
		$eportaVModel = $eportaV["PROPERTY_MODEL_VALUE"] ?? "";
		if ($eportaVModel === "") continue;
		$eportaVColor = (string)($eportaV["PROPERTY_COATING_COLOR_VALUE"] ?? "");
		$eportaVImg = $eportaV["PREVIEW_PICTURE"] ?: $eportaV["DETAIL_PICTURE"];
		$eportaVUrl = $eportaV["DETAIL_PAGE_URL"] ?: ($eportaV["CODE"] ? "/catalog/" . $eportaV["CODE"] . ".html" : "");
		// Один свотч на цвет (не на элемент) — если у модели несколько вариантов одного цвета
		// (разное остекление/размер), достаточно одной ссылки на первый попавшийся.
		if (!isset($eportaSwatchesByModel[$eportaVModel][$eportaVColor])) {
			$eportaSwatchesByModel[$eportaVModel][$eportaVColor] = [
				"id" => (int)$eportaV["ID"],
				"name" => (string)($eportaV["NAME"] ?? ""),
				"url" => $eportaVUrl,
				"photo" => $eportaVImg ? \CFile::GetPath($eportaVImg) : "",
				"color" => $eportaVColor,
				// Цена этого конкретного цвета — CATALOG_PRICE_1 уже посчитан импортёром ПОСЛЕ
				// скидки (тот же принцип, что MIN_PRICE основных карточек), см. коммент выше по
				// файлу и аналогичный расчёт MIN_PRICE моделей коллекции в catalog/index.php.
				"price" => (float)($eportaV["CATALOG_PRICE_1"] ?? 0),
				"discount" => (float)($eportaV["PROPERTY_DISCOUNT_VALUE"] ?? 0),
			];
		}
	}
}
?>
<div class="eporta-product-grid<?=$eportaGridView?>" style="--eporta-cols:<?=$eportaCols?>">
<?php $eportaImgIndex = 0; foreach ($arResult["ITEMS"] as $arItem):
	$eportaExtra = $eportaExtraProps[$arItem["ID"]] ?? ["RATING" => 0, "VOTE_COUNT" => 0, "PRODUCT_DAY" => "", "DISCOUNT" => 0, "MODEL" => "", "COATING_COLOR" => ""];
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

	// Раньше при отсутствии обеих картинок сюда подставлялся общий hit-1.jpg — визуально
	// правдоподобное, но не относящееся к товару фото (см. catalog.element/.default/template.php,
	// найдено на арт. 0593 через поиск по каталогу). Теперь явная "Нет фото" вместо угадывания.
	$eportaHasPhoto = !empty($arItem["PREVIEW_PICTURE"]["SRC"]) || !empty($arItem["DETAIL_PICTURE"]["SRC"]);
	$imgSrc = $arItem["PREVIEW_PICTURE"]["SRC"] ?? ($arItem["DETAIL_PICTURE"]["SRC"] ?? "");
	// Первый ряд карточек — кандидат в LCP, грузим сразу; остальные ниже экрана — lazy.
	$eportaIsEager = $eportaImgIndex < $eportaCols;
	$eportaImgIndex++;

	// DETAIL_PAGE_URL остаётся NULL при SEF_MODE=>"N" этого вызова компонента — строим ссылку
	// сами по CODE элемента. Роутинг детали в catalog/index.php резолвит товар по regex
	// "/([^/]+)\.html/" из REQUEST_URI, так что префикс пути не важен, важен только code+".html".
	$elementUrl = $arItem["DETAIL_PAGE_URL"] ?? null;
	if (!$elementUrl && !empty($arItem["CODE"])) {
		$elementUrl = "/catalog/" . $arItem["CODE"] . ".html";
	}

	// Свотчи этой карточки: остальные цвета той же модели, без самого текущего элемента.
	// Максимум 5 кружков + "+N" — компактная строка, не на всю ширину карточки.
	$eportaCardSwatches = $eportaSwatchesByModel[$eportaExtra["MODEL"]] ?? [];
	unset($eportaCardSwatches[$eportaExtra["COATING_COLOR"] !== "" ? $eportaExtra["COATING_COLOR"] : "\0__none__"]);
	$eportaCardSwatches = array_values(array_filter($eportaCardSwatches, fn($v) => $v["id"] !== (int)$arItem["ID"]));
	$eportaSwatchesShown = array_slice($eportaCardSwatches, 0, 5);
	$eportaSwatchesMore = count($eportaCardSwatches) - count($eportaSwatchesShown);

	// Дефолтные фото и цена карточки — рендерим один раз через ob_start и переиспользуем и в
	// самой разметке, и в data-атрибутах .product-card (см. ниже), чтобы app.js мог вернуть
	// карточку в исходное состояние при уходе курсора со свотчей без повторного похода в PHP —
	// гарантированно тот же HTML, не отдельная копия логики форматирования.
	ob_start();
	if ($eportaHasPhoto) {
		eportaPicture($imgSrc, $arItem["NAME"], [
			"loading" => $eportaIsEager ? "eager" : "lazy",
			"decoding" => "async",
		], true);
	} else {
		echo '<div class="img-noimg">Нет фото</div>';
	}
	$eportaDefaultPictureHtml = ob_get_clean();

	ob_start();
	if ($price) {
		if ($hasDiscount) {
			echo '<span class="price">' . $price["PRINT_VALUE"] . '</span> <span class="price-old">' . $priceOldPrint . '</span>';
		} else {
			echo '<div class="price">' . $price["PRINT_VALUE"] . '</div>';
		}
	} else {
		echo '<div class="price">по запросу</div>';
	}
	$eportaDefaultPriceHtml = ob_get_clean();
?>
	<div class="product-card" data-id="<?= (int)$arItem["ID"] ?>" data-default-picture="<?= htmlspecialcharsbx($eportaDefaultPictureHtml) ?>" data-default-price="<?= htmlspecialcharsbx($eportaDefaultPriceHtml) ?>">
	<a href="<?= $elementUrl ? htmlspecialcharsbx($elementUrl) : "javascript:void(0)" ?>" class="product-card-link">
		<div class="img-wrap">
			<?= $eportaDefaultPictureHtml ?>
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
			<div class="name" title="<?= htmlspecialcharsbx($arItem["NAME"]) ?>"><?= htmlspecialcharsbx($arItem["NAME"]) ?></div>
			<div class="price-row">
				<div class="price-block"><?= $eportaDefaultPriceHtml ?></div>
				<div class="price-row-tools">
					<button class="btn-compare" onclick="addCompare(event, <?= (int)$arItem["ID"] ?>)" title="Сравнить">⇄</button>
					<button class="btn-cart" onclick="addCartAjax(event, <?= (int)$arItem["ID"] ?>)">В корзину</button>
				</div>
			</div>
		</div>
	</a>
	<?php if ($eportaSwatchesShown): ?>
	<div class="product-swatches">
		<?php foreach ($eportaSwatchesShown as $eportaSwatch):
			// Фото и цена этого цвета — заранее отрендерены (тот же webp/jpg и то же форматирование
			// цены, что и на основной карточке выше), чтобы наведение на кружок (app.js, hover без
			// клика) подменяло их на месте без перехода на страницу другого цвета.
			// Без фото (вариант не залит) атрибуты не выводятся, наведение ничего не подменяет, а
			// клик остаётся обычной ссылкой на страницу варианта.
			$eportaSwatchPictureHtml = "";
			if ($eportaSwatch["photo"]) {
				ob_start();
				eportaPicture($eportaSwatch["photo"], trim($eportaSwatch["name"] . " — " . $eportaSwatch["color"]), [
					"loading" => "eager",
					"decoding" => "async",
				], true);
				$eportaSwatchPictureHtml = ob_get_clean();
			}
			// Цена — всегда своя для этого цвета, а не "если отличается": у разных вариантов
			// модели цена не всегда посчитана (например, ещё не проставлена в выгрузке), поэтому
			// строим блок и на случай "по запросу", чтобы наведение никогда не оставляло цену
			// чужого (текущего показанного) элемента.
			if ($eportaSwatch["price"] > 0) {
				$eportaSwatchPricePrint = \CCurrencyLang::CurrencyFormat($eportaSwatch["price"], "RUB");
				if ($eportaSwatch["discount"] > 0) {
					$eportaSwatchOldPricePrint = \CCurrencyLang::CurrencyFormat(round($eportaSwatch["price"] / (1 - $eportaSwatch["discount"] / 100)), "RUB");
					$eportaSwatchPriceHtml = '<span class="price">' . $eportaSwatchPricePrint . '</span> <span class="price-old">' . $eportaSwatchOldPricePrint . '</span>';
				} else {
					$eportaSwatchPriceHtml = '<div class="price">' . $eportaSwatchPricePrint . '</div>';
				}
			} else {
				$eportaSwatchPriceHtml = '<div class="price">по запросу</div>';
			}
		?>
		<a href="<?= $eportaSwatch["url"] ? htmlspecialcharsbx($eportaSwatch["url"]) : "javascript:void(0)" ?>"
		   class="swatch"
		   title="<?= htmlspecialcharsbx($eportaSwatch["color"]) ?>"
		   <?= $eportaSwatch["photo"] ? 'style="background-image:url(' . htmlspecialcharsbx($eportaSwatch["photo"]) . ')"' : "" ?>
		   <?= $eportaSwatchPictureHtml ? 'data-picture="' . htmlspecialcharsbx($eportaSwatchPictureHtml) . '"' : "" ?>
		   <?= $eportaSwatchPriceHtml ? 'data-price="' . htmlspecialcharsbx($eportaSwatchPriceHtml) . '"' : "" ?>></a>
		<?php endforeach; ?>
		<?php if ($eportaSwatchesMore > 0): ?><span class="swatch-more">+<?= $eportaSwatchesMore ?></span><?php endif; ?>
	</div>
	<?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php if ($arResult["NAV_RESULT"] && $arResult["NAV_RESULT"]->NavPageCount > 1): ?>
	<div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:28px">
		<?= $arResult["NAV_STRING"] ?>
	</div>
<?php endif; ?>
