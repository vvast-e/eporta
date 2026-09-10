<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var array $arParams */

$eportaCols = (int)($arParams["LINE_ELEMENT_COUNT"] ?? 3) ?: 3;
// Вид плитка/список — общий для всех вызовов этого шаблона (каталог/коллекции/wishlist/
// simple.offers), кука eporta_view переключается кнопками в catalog/index.php.
$eportaGridView = ($_COOKIE["eporta_view"] ?? "") === "list" ? " eporta-product-grid--list" : "";

// Явный порядок вывода (закреплённые товары в заданном порядке, потом автоотбор) — компонентными
// параметрами не выражается, задаётся глобальной переменной перед IncludeComponent (см. табы
// главной, eportaHomeTabsRenderCatalogSection в eporta_home_tabs_common.php). Необязательно —
// если не задано, порядок остаётся как отдал компонент (ELEMENT_SORT_FIELD).
global $arrEportaHomeTabOrder;
if (!empty($arrEportaHomeTabOrder) && is_array($arrEportaHomeTabOrder)) {
	$eportaOrderPos = array_flip(array_map("strval", $arrEportaHomeTabOrder));
	usort($arResult["ITEMS"], function ($a, $b) use ($eportaOrderPos) {
		$posA = $eportaOrderPos[$a["ID"]] ?? PHP_INT_MAX;
		$posB = $eportaOrderPos[$b["ID"]] ?? PHP_INT_MAX;
		return $posA <=> $posB;
	});
	// Одноразово — следующий вызов компонента (другой таб/другая секция) не должен унаследовать
	// чужой порядок.
	$arrEportaHomeTabOrder = null;
}

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
<div class="eporta-product-grid<?=$eportaGridView?>" style="--eporta-cols:<?=$eportaCols?>">
<?php $eportaImgIndex = 0; foreach ($arResult["ITEMS"] as $arItem):
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

	// Название карточки в две строки (задача 06.09.2026): NAME из импорта устроен как
	// "Модель, Покрытие Цвет" (например "Dorsum-Eco 12.2, Экошпон Дуб Мадейра янтарь Н") —
	// делим по первой запятой на модель (крупнее, жирнее) и покрытие+цвет (мельче, приглушённый
	// цвет), каждая строка обрезается по своей ширине независимо. Названий без запятой в данных
	// не встречалось, но на случай пустой второй части просто не выводим вторую строку.
	// eportaCleanDisplayName — убирает "Эмаль Эмаль белая" → "Эмаль белая" (см. init.php),
	// склейка "Покрытие"+"Цвет" при импорте, столбец "Цвет" в выгрузке иногда сам уже содержит
	// название покрытия. Не трогает NAME в базе, только то, что видит покупатель.
	$eportaDisplayName = eportaCleanDisplayName((string)$arItem["NAME"]);
	$eportaNameParts = explode(",", $eportaDisplayName, 2);
	$eportaModelNamePart = trim($eportaNameParts[0]);
	$eportaCoatingNamePart = isset($eportaNameParts[1]) ? trim($eportaNameParts[1]) : "";

	// Дефолтные фото и цена карточки — рендерим один раз через ob_start и переиспользуем и в
	// самой разметке, и в data-атрибутах .product-card (см. ниже), чтобы app.js мог вернуть
	// карточку в исходное состояние при уходе курсора со свотчей без повторного похода в PHP —
	// гарантированно тот же HTML, не отдельная копия логики форматирования.
	ob_start();
	if ($eportaHasPhoto) {
		eportaPicture($imgSrc, $eportaDisplayName, [
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
			<?php if ($hasDiscount): ?><span class="badge" style="background:#c2670a;padding-left:6px;padding-right:6px;top:<?= ($isHit || $isNew) ? "44px" : "10px" ?>">−<?= round($discountPercent) ?>%</span><?php endif; ?>
			<?php if (($arParams["SHOW_WISHLIST_REMOVE"] ?? "N") === "Y"): ?>
				<button type="button" class="wishlist-remove-btn" data-id="<?= (int)$arItem["ID"] ?>" onclick="event.preventDefault();event.stopPropagation();removeFromWishlistCard(this)" title="Удалить из избранного">×</button>
			<?php endif; ?>
			<!-- Рейтинг перенесён сюда из .info оверлеем в правый верхний угол (задача 10.09.2026:
			"карточки каталога") — компактный формат "★ 4.9" вместо пяти символов звёзд. Бейджи
			выше — в левом углу, не пересекаются. При rating<=0 (он же "Новинка") не показываем. -->
			<?php if ($rating > 0): ?><span class="card-rating">★ <?= number_format($rating, 1, ".", "") ?></span><?php endif; ?>
			<!-- Сравнение — раньше жило в .card-actions под ценой вместе с "В корзину" (задача
			10.09.2026), теперь всегда на виду иконкой в углу фото — единственное действие, не
			требующее наведения (в отличие от .card-hover-actions ниже). Внутри .img-wrap, а не
			<a>, клик по нему не переходит по ссылке товара — addCompare() сам останавливает
			всплытие, как и раньше. -->
			<button class="btn-compare" onclick="addCompare(event, <?= (int)$arItem["ID"] ?>)" title="Сравнить">⇄</button>
		</div>
		<div class="info">
			<div class="name" title="<?= htmlspecialcharsbx($eportaDisplayName) ?>"><?= htmlspecialcharsbx($eportaModelNamePart) ?></div>
			<?php if ($eportaCoatingNamePart !== ""): ?>
			<div class="coating" title="<?= htmlspecialcharsbx($eportaCoatingNamePart) ?>"><?= htmlspecialcharsbx($eportaCoatingNamePart) ?></div>
			<?php endif; ?>
			<div class="price-row">
				<div class="price-block"><?= $eportaDefaultPriceHtml ?></div>
			</div>
		</div>
	</a>
		<!-- "В корзину" убрали с плитки насовсем (задача 10.09.2026), но по правке заказчика в тот
		     же день вернули — теперь только по наведению: карточка "проседает" вниз и освобождает
		     место под кнопку (.card-hover-actions, max-height 0→auto в template_styles.css). Вне
		     <a> — отдельное действие, как и раньше. -->
		<div class="card-hover-actions">
			<button class="btn-cart" onclick="addCartAjax(event, <?= (int)$arItem["ID"] ?>)">В корзину</button>
		</div>
	</div>
<?php endforeach; ?>
</div>

<?php if ($arResult["NAV_RESULT"] && $arResult["NAV_RESULT"]->NavPageCount > 1): ?>
	<div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:28px">
		<?= $arResult["NAV_STRING"] ?>
	</div>
<?php endif; ?>
