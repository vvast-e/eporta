<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Страница поиска");
?>
<?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");

	//vars
	$catalogIblockId = null;
	$arPriceCodes = array();

	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
	if(!empty($arTemplateSettings)){
		$catalogIblockId = $arTemplateSettings["TEMPLATE_PRODUCT_IBLOCK_ID"];
		$arPriceCodes = explode(", ", $arTemplateSettings["TEMPLATE_PRICE_CODES"]);
	}
?>
<?
	// Этап «Поиск»: под шаблоном eporta вендорский dresscode:search обходится целиком —
	// его .default-шаблон крашится 500-й на пустых результатах (несуществующий ключ
	// $arResult["SECTIONS"], всегда вызывает bitrix:menu/emptyMenu, которого нет в eporta),
	// не экранирует $_REQUEST["q"] в заголовках (reflected XSS) и рендерит сетку на jQuery,
	// которой в eporta нет. Вместо этого — CSearch (та же морфология/раскладка, что у вендора,
	// см. component.php) + переопределённая карточка bitrix:catalog.section. Вендорская ветка
	// ниже (dverimarket.ru и любой другой шаблон) не изменена.
	$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):
	\Bitrix\Main\Loader::includeModule("iblock");
	\Bitrix\Main\Loader::includeModule("search");
	\Bitrix\Main\Loader::includeModule("catalog");

	$eportaQuery = trim((string)\Bitrix\Main\Context::getCurrent()->getRequest()->get("q"));
	$eportaFoundIds = [];

	if ($eportaQuery !== "" && mb_strlen($eportaQuery) > 1) {
		$obSearch = new CSearch;
		$obSearch->Search(
			[
				"QUERY" => $eportaQuery,
				"SITE_ID" => SITE_ID,
				"MODULE_ID" => "iblock",
				"PARAM2" => $catalogIblockId,
			],
			[],
			["STEMMING" => "N"]
		);
		while ($searchItem = $obSearch->fetch()) {
			if (is_numeric($searchItem["ITEM_ID"])) {
				$eportaFoundIds[(int)$searchItem["ITEM_ID"]] = (int)$searchItem["ITEM_ID"];
			}
		}
	}

	$APPLICATION->SetTitle("Результаты поиска — «".htmlspecialcharsbx($eportaQuery)."»");
?>
	<?if (empty($eportaFoundIds)):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-empty">
			<div class="lk-empty-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
			</div>
			<div class="lk-empty-title">Ничего не найдено по запросу «<?=htmlspecialcharsbx($eportaQuery)?>»</div>
			<div class="lk-empty-actions">
				<a href="/catalog/" class="lk-btn-primary">В каталог</a>
			</div>
		</div>
	</div>
	</div>
	<?else:
		// Уточняющие чипы: не сайдбар каталога (это страница поиска, не каталог) — лёгкая
		// строка тегов, посчитанная ТОЛЬКО среди уже найденных CSearch ID. Та же тройка
		// свойств IBLOCK 19, что в сайдбаре catalog/index.php (см. feedback_bitrix_property_
		// naming_gotcha: MAIN_COLOR это "Цвет", не COATING_COLOR), "умные" счётчики по тому
		// же паттерну (исключаем свою группу при подсчёте, чтобы не занулять сами себя).
		$eportaPropDefs = [
			"style" => ["CODE" => "STYLE", "LABEL" => "Стиль", "FILTER_KEY" => "PROPERTY_STYLE"],
			"coating" => ["CODE" => "COATING", "LABEL" => "Покрытие", "FILTER_KEY" => "PROPERTY_COATING"],
			"color" => ["CODE" => "MAIN_COLOR", "LABEL" => "Цвет", "FILTER_KEY" => "PROPERTY_MAIN_COLOR"],
		];
		$eportaSelected = [];
		foreach ($eportaPropDefs as $eportaKey => $eportaDef) {
			$eportaEnumRes = \CIBlockPropertyEnum::GetList(
				["SORT" => "ASC", "VALUE" => "ASC"],
				["IBLOCK_ID" => 19, "CODE" => $eportaDef["CODE"]]
			);
			$eportaValues = [];
			while ($eportaEnum = $eportaEnumRes->Fetch()) {
				$eportaValues[(int)$eportaEnum["ID"]] = $eportaEnum["VALUE"];
			}
			$eportaPropDefs[$eportaKey]["VALUES"] = $eportaValues;
			$eportaRawSel = array_map("intval", (array)($_GET[$eportaKey] ?? []));
			$eportaSelected[$eportaKey] = array_values(array_intersect($eportaRawSel, array_keys($eportaValues)));
		}

		$eportaScopeFilter = ["ID" => $eportaFoundIds, "ACTIVE" => "Y"];
		$eportaBuildFilter = function ($excludeKey) use ($eportaScopeFilter, $eportaPropDefs, $eportaSelected) {
			$filter = $eportaScopeFilter;
			foreach ($eportaPropDefs as $key => $def) {
				if ($key === $excludeKey || empty($eportaSelected[$key])) continue;
				$filter[$def["FILTER_KEY"]] = $eportaSelected[$key];
			}
			return $filter;
		};

		$eportaChipGroups = [];
		$eportaArrFilter = $eportaScopeFilter;
		foreach ($eportaPropDefs as $eportaKey => $eportaDef) {
			$eportaCountFilter = $eportaBuildFilter($eportaKey);
			$eportaItems = [];
			foreach ($eportaDef["VALUES"] as $eportaEnumId => $eportaEnumValue) {
				$eportaCnt = \CIBlockElement::GetList([], $eportaCountFilter + [$eportaDef["FILTER_KEY"] => $eportaEnumId], false, false, ["ID"])->SelectedRowsCount();
				if ($eportaCnt < 1) continue;
				$eportaItems[] = [
					"ID" => $eportaEnumId,
					"VALUE" => $eportaEnumValue,
					"COUNT" => $eportaCnt,
					"CHECKED" => in_array($eportaEnumId, $eportaSelected[$eportaKey], true),
				];
			}
			usort($eportaItems, fn($a, $b) => $b["COUNT"] <=> $a["COUNT"]);
			// Строка чипов лёгкая по дизайну — не более 8 значений на группу, не полный список,
			// как в сайдбаре каталога.
			$eportaChipGroups[$eportaKey] = ["LABEL" => $eportaDef["LABEL"], "ITEMS" => array_slice($eportaItems, 0, 8)];

			if (!empty($eportaSelected[$eportaKey])) {
				$eportaArrFilter[$eportaDef["FILTER_KEY"]] = $eportaSelected[$eportaKey];
			}
		}

		// КРИТИЧНО: bitrix:catalog.section/CIBlockElement::GetList всегда пересортировывает
		// выборку по ELEMENT_SORT_FIELD (в каталоге — поле "Сортировка" из админки,
		// мерчендайзинговое, никак не связанное с поиском) — порядок релевантности,
		// который вернул CSearch выше ($eportaFoundIds), полностью терялся. Именно из-за
		// этого по запросу "Входная дверь" на первых местах оказывались "Ручки дверные"
		// (у них SORT~78 против SORT=500 у настоящих входных дверей — ручки просто выше
		// приоритетом в каталоге, вообще не про релевантность). Фикс: получаем набор
		# подходящих ID (с учётом фильтров-чипов) ОТДЕЛЬНЫМ лёгким запросом, затем
		// восстанавливаем порядок вручную в PHP по $eportaFoundIds (который уже в порядке
		// релевантности CSearch) — SQL-сортировке эту выборку больше не доверяем.
		$eportaMatchedRes = \CIBlockElement::GetList([], $eportaArrFilter, false, false, ["ID"]);
		$eportaMatchedIdSet = [];
		while ($eportaMatchedRow = $eportaMatchedRes->Fetch()) {
			$eportaMatchedIdSet[(int)$eportaMatchedRow["ID"]] = true;
		}
		$eportaOrderedIds = [];
		foreach ($eportaFoundIds as $eportaId) {
			if (isset($eportaMatchedIdSet[$eportaId])) {
				$eportaOrderedIds[] = $eportaId;
			}
		}
		$eportaFilteredCount = count($eportaOrderedIds);
		$eportaHasActiveChips = array_filter($eportaSelected);
		$eportaBasePath = parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);

		// Toggle-ссылка на чип: добавляет/убирает конкретное значение в query-массиве
		// своей группы, сохраняя остальные GET-параметры (включая q и другие группы),
		// сбрасывает номер страницы при смене фильтра.
		$eportaChipUrl = function ($key, $valueId, $checked) use ($eportaBasePath) {
			$params = $_GET;
			unset($params["PAGEN_1"]);
			$current = array_map("intval", (array)($params[$key] ?? []));
			$current = $checked
				? array_values(array_diff($current, [$valueId]))
				: array_merge($current, [$valueId]);
			if ($current) {
				$params[$key] = $current;
			} else {
				unset($params[$key]);
			}
			return $eportaBasePath.($params ? "?".http_build_query($params) : "");
		};
		$eportaResetChipsUrl = $eportaBasePath."?q=".urlencode($eportaQuery);

		// Количество на странице — теперь выбирается пользователем (по умолчанию 24, было
		// зашито 12). Санитизация против фиксированного списка значений, не произвольное
		// число из GET.
		$eportaAllowedPerPage = [12, 24, 48];
		$eportaPerPage = (int)($_GET["per_page"] ?? 24);
		if (!in_array($eportaPerPage, $eportaAllowedPerPage, true)) {
			$eportaPerPage = 24;
		}
		$eportaPerPageUrl = function ($size) use ($eportaBasePath) {
			$params = $_GET;
			unset($params["PAGEN_1"]);
			if ($size === 24) {
				unset($params["per_page"]);
			} else {
				$params["per_page"] = $size;
			}
			return $eportaBasePath.($params ? "?".http_build_query($params) : "");
		};
	?>
	<div style="padding:20px var(--pad-x) 4px">
		<form method="get" action="/search/" style="display:flex;align-items:center;gap:12px;max-width:640px;border:1.5px solid var(--border-soft, #e7e3db);border-radius:14px;padding:6px 6px 6px 18px;margin-bottom:16px">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a39e95" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
			<input type="text" name="q" value="<?=htmlspecialcharsbx($eportaQuery)?>" style="flex:1;border:none;outline:none;font:600 15px 'Manrope';background:transparent">
			<button type="submit" style="background:#e8820a;color:#fff;font:700 13px 'Manrope';padding:11px 20px;border-radius:10px;border:none;cursor:pointer">Найти</button>
		</form>
		<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
			<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Найдено <?=$eportaFilteredCount?> по запросу «<?=htmlspecialcharsbx($eportaQuery)?>»</h1>
			<div style="display:flex;align-items:center;gap:7px">
				<span style="font:600 12.5px 'Manrope';color:#a39e95">Показывать:</span>
				<?foreach ($eportaAllowedPerPage as $eportaSize):?>
				<a href="<?=htmlspecialcharsbx($eportaPerPageUrl($eportaSize))?>"
				   style="font:700 12.5px 'Manrope';padding:7px 12px;border-radius:8px;text-decoration:none;<?=$eportaSize === $eportaPerPage
						? "background:#e8820a;color:#fff;"
						: "background:#f3f1ec;color:#3a3631;"?>"><?=$eportaSize?></a>
				<?endforeach;?>
			</div>
		</div>
	</div>

	<?
		// Вариант C (по решению пользователя): чипы дублируются в компактную плавающую
		// панель, которая появляется, только когда исходный блок (#eportaChipRow) уходит
		// за пределы экрана при скролле — не занимает место, пока пользователь наверху.
		// HTML самих чипов рендерим ОДИН раз через буфер и переиспользуем в обоих местах
		// (обычный блок + компактный sticky-бар), чтобы не дублировать PHP-цикл.
		$eportaHasChips = (bool)array_filter($eportaChipGroups, fn($g) => $g["ITEMS"]);
		$eportaChipsMarkup = "";
		if ($eportaHasChips):
			ob_start();
			foreach ($eportaChipGroups as $eportaGroupKey => $eportaGroup):
				if (!$eportaGroup["ITEMS"]) continue;
	?>
				<span style="font:700 12px 'Manrope';color:#a39e95;text-transform:uppercase;letter-spacing:.04em;margin-right:2px;flex:none"><?=htmlspecialcharsbx($eportaGroup["LABEL"])?>:</span>
				<?foreach ($eportaGroup["ITEMS"] as $eportaItem):
					$eportaChecked = $eportaItem["CHECKED"];
				?>
				<a href="<?=htmlspecialcharsbx($eportaChipUrl($eportaGroupKey, $eportaItem["ID"], $eportaChecked))?>"
				   style="display:inline-flex;align-items:center;gap:6px;font:600 12.5px 'Manrope';padding:8px 13px;border-radius:20px;text-decoration:none;cursor:pointer;flex:none;<?=$eportaChecked
						? "background:#e8820a;color:#fff;"
						: "background:#f3f1ec;color:#3a3631;"?>">
					<?=htmlspecialcharsbx($eportaItem["VALUE"])?>
					<?if ($eportaChecked):?><span>✕</span><?else:?><span style="color:<?=$eportaChecked ? "#fff" : "#c2bdb2"?>;font-weight:600"><?=$eportaItem["COUNT"]?></span><?endif;?>
				</a>
				<?php endforeach;
			endforeach;
			if ($eportaHasActiveChips):
	?>
				<a href="<?=htmlspecialcharsbx($eportaResetChipsUrl)?>" style="font:700 12.5px 'Manrope';color:#c2670a;padding:8px 6px;cursor:pointer;text-decoration:none;margin-left:4px;flex:none">Сбросить фильтры</a>
	<?php
			endif;
			$eportaChipsMarkup = ob_get_clean();
		endif;
	?>
	<?if ($eportaHasChips):?>
	<div id="eportaChipRow" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 8px;padding:14px var(--pad-x) 4px">
		<?=$eportaChipsMarkup?>
	</div>

	<!-- Плавающая компактная панель — скрыта по умолчанию (opacity/translateY через CSS
	     класса .eporta-sticky-chips), показывается JS-обсёрвером ниже при скролле мимо
	     #eportaChipRow. Однострочная, с горизонтальным скроллом вместо переноса — иначе
	     заняла бы 3 строки высоты, торча над сеткой постоянно. -->
	<div id="eportaStickyChips" class="eporta-sticky-chips">
		<div style="display:flex;align-items:center;gap:8px;overflow-x:auto;flex:1">
			<?=$eportaChipsMarkup?>
		</div>
		<a href="#" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;" style="flex:none;display:flex;align-items:center;gap:5px;font:700 12.5px 'Manrope';color:#726c62;text-decoration:none;padding:8px 12px;white-space:nowrap">↑ Наверх</a>
	</div>
	<script>
	(function(){
		var chipRow = document.getElementById('eportaChipRow');
		var stickyBar = document.getElementById('eportaStickyChips');
		if (!chipRow || !stickyBar || typeof IntersectionObserver === 'undefined') return;
		new IntersectionObserver(function(entries){
			entries.forEach(function(entry){
				if (entry.isIntersecting) {
					stickyBar.classList.remove('visible');
				} else if (entry.boundingClientRect.top < 0) {
					stickyBar.classList.add('visible');
				}
			});
		}, {threshold: 0}).observe(chipRow);
	})();
	</script>
	<?endif;?>

	<?if ($eportaFilteredCount < 1):?>
	<div class="lk-page-wrap">
	<div class="lk-card">
		<div class="lk-empty">
			<div class="lk-empty-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
			</div>
			<div class="lk-empty-title">По этим фильтрам ничего не нашлось</div>
			<div class="lk-empty-actions">
				<a href="<?=htmlspecialcharsbx($eportaResetChipsUrl)?>" class="lk-btn-primary">Сбросить фильтры</a>
			</div>
		</div>
	</div>
	</div>
	<?else:
		// Рендерим сетку САМИ (не через bitrix:catalog.section) — единственный способ
		// показать товары в порядке релевантности $eportaOrderedIds, а не в порядке поля
		// "Сортировка" каталога (см. комментарий выше, где строится $eportaOrderedIds).
		// Полные данные (цена/картинка/свойства) тянем только для текущей страницы —
		// не для всей найденной выборки, чтобы не терять эффективность.
		$eportaLineCount = 4;
		$eportaTotalPages = max(1, (int)ceil($eportaFilteredCount / $eportaPerPage));
		$eportaCurPage = max(1, min($eportaTotalPages, (int)($_GET["PAGEN_1"] ?? 1)));
		$eportaPageIds = array_slice($eportaOrderedIds, ($eportaCurPage - 1) * $eportaPerPage, $eportaPerPage);

		$eportaPageUrl = function ($page) use ($eportaBasePath) {
			$params = $_GET;
			if ($page > 1) {
				$params["PAGEN_1"] = $page;
			} else {
				unset($params["PAGEN_1"]);
			}
			return $eportaBasePath.($params ? "?".http_build_query($params) : "");
		};

		$eportaPageRes = \CIBlockElement::GetList(
			[],
			["ID" => $eportaPageIds, "ACTIVE" => "Y"],
			false,
			false,
			["ID", "NAME", "CODE", "DETAIL_PAGE_URL", "PREVIEW_PICTURE", "DETAIL_PICTURE", "CATALOG_PRICE_1", "PROPERTY_RATING", "PROPERTY_DISCOUNT"]
		);
		$eportaPageDataById = [];
		while ($eportaRow = $eportaPageRes->GetNext()) {
			$eportaPageDataById[(int)$eportaRow["ID"]] = $eportaRow;
		}
		// Порядок — из $eportaPageIds (уже релевантный), НЕ из результата запроса:
		// CIBlockElement::GetList не гарантирует порядок ID IN(...) в порядке массива.
		$eportaPageItems = [];
		foreach ($eportaPageIds as $eportaPid) {
			if (isset($eportaPageDataById[$eportaPid])) {
				$eportaPageItems[] = $eportaPageDataById[$eportaPid];
			}
		}
		\Bitrix\Main\Loader::includeModule("currency");
		$eportaPlaceholderImg = SITE_TEMPLATE_PATH."/assets/img/hit-1.jpg";
	?>
	<div style="padding:8px var(--pad-x) 4px">
		<div class="eporta-product-grid" style="--eporta-cols:<?=$eportaLineCount?>">
			<?foreach ($eportaPageItems as $eportaItem):
				// Паритет с карточкой каталога (components/bitrix/catalog.section/.default/template.php):
				// (float) рейтинг (не int — иначе 4.99 обрезается до 4 и мимо порога ХИТ), ХИТ/Новинка
				// по тому же порогу rating>=4.8 / rating<=0 (а не по пустому PRODUCT_DAY — импорт его
				// никогда не заполняет, поэтому раньше плашка ХИТ в поиске не показывалась вообще),
				// зачёркнутая старая цена из свойства DISCOUNT, рабочая ссылка на карточку товара.
				$eportaRating = (float)($eportaItem["PROPERTY_RATING_VALUE"] ?? 0);
				$eportaStars = str_repeat("★", max(0, min(5, round($eportaRating)))).str_repeat("☆", 5 - max(0, min(5, round($eportaRating))));
				$eportaIsHit = $eportaRating >= 4.8;
				$eportaIsNew = $eportaRating <= 0;

				$eportaPriceVal = (float)($eportaItem["CATALOG_PRICE_1"] ?? 0);
				$eportaHasPrice = ($eportaItem["CATALOG_PRICE_1"] ?? null) !== null && $eportaPriceVal > 0;
				$eportaDiscountPercent = (float)($eportaItem["PROPERTY_DISCOUNT_VALUE"] ?? 0);
				$eportaHasDiscount = $eportaHasPrice && $eportaDiscountPercent > 0;
				$eportaOldPriceVal = $eportaHasDiscount ? round($eportaPriceVal / (1 - $eportaDiscountPercent / 100)) : 0;

				$eportaImgId = $eportaItem["PREVIEW_PICTURE"] ?: $eportaItem["DETAIL_PICTURE"];
				$eportaImgSrc = $eportaImgId ? \CFile::GetPath($eportaImgId) : $eportaPlaceholderImg;

				$eportaItemUrl = $eportaItem["DETAIL_PAGE_URL"] ?: null;
				if (!$eportaItemUrl && !empty($eportaItem["CODE"])) {
					$eportaItemUrl = "/catalog/".$eportaItem["CODE"].".html";
				}
			?>
			<a href="<?=$eportaItemUrl ? htmlspecialcharsbx($eportaItemUrl) : "javascript:void(0)"?>" class="product-card">
				<div class="img-wrap">
					<img src="<?=htmlspecialcharsbx($eportaImgSrc)?>" alt="<?=htmlspecialcharsbx($eportaItem["NAME"])?>">
					<?if ($eportaIsHit):?><span class="badge hit">ХИТ</span><?endif;?>
					<?if ($eportaIsNew):?><span class="badge new">Новинка</span><?endif;?>
					<?if ($eportaHasDiscount):?><span class="badge" style="background:#c2670a;top:<?=($eportaIsHit || $eportaIsNew) ? "44px" : "10px"?>">−<?=round($eportaDiscountPercent)?>%</span><?endif;?>
				</div>
				<div class="info">
					<div class="stars"><?=$eportaStars?><?if ($eportaRating > 0):?> <span><?=number_format($eportaRating, 1, ".", "")?></span><?endif;?></div>
					<div class="name"><?=htmlspecialcharsbx($eportaItem["NAME"])?></div>
					<div class="price-row">
						<?if ($eportaHasPrice):?>
							<?if ($eportaHasDiscount):?>
								<div><span class="price"><?=\CCurrencyLang::CurrencyFormat($eportaPriceVal, "RUB", true)?></span> <span class="price-old"><?=\CCurrencyLang::CurrencyFormat($eportaOldPriceVal, "RUB", true)?></span></div>
							<?else:?>
								<div class="price"><?=\CCurrencyLang::CurrencyFormat($eportaPriceVal, "RUB", true)?></div>
							<?endif;?>
						<?else:?>
							<div class="price">по запросу</div>
						<?endif;?>
						<div class="price-row-tools">
							<button class="btn-compare" onclick="addCompare(event, <?=(int)$eportaItem["ID"]?>)" title="Сравнить">⇄</button>
							<button class="btn-cart" onclick="addCartAjax(event, <?=(int)$eportaItem["ID"]?>)">В корзину</button>
						</div>
					</div>
				</div>
			</a>
			<?endforeach;?>
		</div>
	</div>

	<?if ($eportaTotalPages > 1):
		$eportaPagerWindow = 2;
		$eportaPagerFrom = max(1, $eportaCurPage - $eportaPagerWindow);
		$eportaPagerTo = min($eportaTotalPages, $eportaCurPage + $eportaPagerWindow);
	?>
	<div class="bx-pagination">
		<div class="bx-pagination-container">
			<ul>
				<?if ($eportaCurPage > 1):?>
				<li class="bx-pag-prev"><a href="<?=htmlspecialcharsbx($eportaPageUrl($eportaCurPage - 1))?>"><span>Назад</span></a></li>
				<?else:?>
				<li class="bx-pag-prev"><span>Назад</span></li>
				<?endif;?>

				<?if ($eportaPagerFrom > 1):?>
				<li><a href="<?=htmlspecialcharsbx($eportaPageUrl(1))?>"><span>1</span></a></li>
				<?endif;?>

				<?for ($eportaP = $eportaPagerFrom; $eportaP <= $eportaPagerTo; $eportaP++):?>
					<?if ($eportaP == $eportaCurPage):?>
					<li class="bx-active"><span><?=$eportaP?></span></li>
					<?else:?>
					<li><a href="<?=htmlspecialcharsbx($eportaPageUrl($eportaP))?>"><span><?=$eportaP?></span></a></li>
					<?endif;?>
				<?endfor;?>

				<?if ($eportaPagerTo < $eportaTotalPages):?>
				<li><a href="<?=htmlspecialcharsbx($eportaPageUrl($eportaTotalPages))?>"><span><?=$eportaTotalPages?></span></a></li>
				<?endif;?>

				<?if ($eportaCurPage < $eportaTotalPages):?>
				<li class="bx-pag-next"><a href="<?=htmlspecialcharsbx($eportaPageUrl($eportaCurPage + 1))?>"><span>Вперед</span></a></li>
				<?else:?>
				<li class="bx-pag-next"><span>Вперед</span></li>
				<?endif;?>
			</ul>
		</div>
	</div>
	<?endif;?>
	<?endif;?>
	<?endif;?>
<?else:?>
<?$APPLICATION->IncludeComponent(
	"dresscode:search",
	".default",
	array(
		"IBLOCK_TYPE" => "catalog",
		"IBLOCK_ID" => $catalogIblockId,
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "3600000",
		"PRICE_CODE" => $arPriceCodes,
		"COMPONENT_TEMPLATE" => ".default",
		"CONVERT_CURRENCY" => "Y",
		"CURRENCY_ID" => "RUB",
		"PROPERTY_CODE" => array(
			0 => "OFFERS",
			1 => "ATT_BRAND",
			2 => "COLOR",
			3 => "ZOOM2",
			4 => "BATTERY_LIFE",
			5 => "SWITCH",
			6 => "GRAF_PROC",
			7 => "LENGTH_OF_CORD",
			8 => "DISPLAY",
			9 => "LOADING_LAUNDRY",
			10 => "FULL_HD_VIDEO_RECORD",
			11 => "INTERFACE",
			12 => "COMPRESSORS",
			13 => "Number_of_Outlets",
			14 => "MAX_RESOLUTION_VIDEO",
			15 => "MAX_BUS_FREQUENCY",
			16 => "MAX_RESOLUTION",
			17 => "FREEZER",
			18 => "POWER_SUB",
			19 => "POWER",
			20 => "HARD_DRIVE_SPACE",
			21 => "MEMORY",
			22 => "OS",
			23 => "ZOOM",
			24 => "PAPER_FEED",
			25 => "SUPPORTED_STANDARTS",
			26 => "VIDEO_FORMAT",
			27 => "SUPPORT_2SIM",
			28 => "MP3",
			29 => "ETHERNET_PORTS",
			30 => "MATRIX",
			31 => "CAMERA",
			32 => "PHOTOSENSITIVITY",
			33 => "DEFROST",
			34 => "SPEED_WIFI",
			35 => "SPIN_SPEED",
			36 => "PRINT_SPEED",
			37 => "SOCKET",
			38 => "IMAGE_STABILIZER",
			39 => "GSM",
			40 => "SIM",
			41 => "TYPE",
			42 => "MEMORY_CARD",
			43 => "TYPE_BODY",
			44 => "TYPE_MOUSE",
			45 => "TYPE_PRINT",
			46 => "CONNECTION",
			47 => "TYPE_OF_CONTROL",
			48 => "TYPE_DISPLAY",
			49 => "TYPE2",
			50 => "REFRESH_RATE",
			51 => "RANGE",
			52 => "AMOUNT_MEMORY",
			53 => "MEMORY_CAPACITY",
			54 => "VIDEO_BRAND",
			55 => "DIAGONAL",
			56 => "RESOLUTION",
			57 => "TOUCH",
			58 => "CORES",
			59 => "LINE_PROC",
			60 => "PROCESSOR",
			61 => "CLOCK_SPEED",
			62 => "TYPE_PROCESSOR",
			63 => "PROCESSOR_SPEED",
			64 => "HARD_DRIVE",
			65 => "HARD_DRIVE_TYPE",
			66 => "Number_of_memory_slots",
			67 => "MAXIMUM_MEMORY_FREQUENCY",
			68 => "TYPE_MEMORY",
			69 => "BLUETOOTH",
			70 => "FM",
			71 => "GPS",
			72 => "HDMI",
			73 => "SMART_TV",
			74 => "USB",
			75 => "WIFI",
			76 => "FLASH",
			77 => "ROTARY_DISPLAY",
			78 => "SUPPORT_3D",
			79 => "SUPPORT_3G",
			80 => "WITH_COOLER",
			81 => "FINGERPRINT",
			82 => "COLLECTION",
			83 => "TOTAL_OUTPUT_POWER",
			84 => "HTML",
			85 => "VID_ZASTECHKI",
			86 => "VID_SUMKI",
			87 => "VIDEO",
			88 => "PROFILE",
			89 => "VYSOTA_RUCHEK",
			90 => "GAS_CONTROL",
			91 => "WARRANTY",
			92 => "GRILL",
			93 => "MORE_PROPERTIES",
			94 => "GENRE",
			95 => "OTSEKOV",
			96 => "CONVECTION",
			97 => "INTAKE_POWER",
			98 => "NAZNAZHENIE",
			99 => "BULK",
			100 => "PODKLADKA",
			101 => "SHOW_MENU",
			102 => "SURFACE_COATING",
			103 => "brand_tyres",
			104 => "SEASON",
			105 => "SEASONOST",
			106 => "DUST_COLLECTION",
			107 => "REF",
			108 => "COUNTRY_BRAND",
			109 => "DRYING",
			110 => "REMOVABLE_TOP_COVER",
			111 => "CONTROL",
			112 => "FINE_FILTER",
			113 => "FORM_FAKTOR",
			114 => "SKU_COLOR",
			115 => "USER_ID",
			116 => "BLOG_POST_ID",
			117 => "CML2_ARTICLE",
			118 => "DELIVERY",
			119 => "BLOG_COMMENTS_CNT",
			120 => "VOTE_COUNT",
			121 => "MARKER_PHOTO",
			122 => "NEW",
			123 => "DELIVERY_DESC",
			124 => "SIMILAR_PRODUCT",
			125 => "SALE",
			126 => "RATING",
			127 => "PICKUP",
			128 => "RELATED_PRODUCT",
			129 => "VOTE_SUM",
			130 => "MARKER",
			131 => "POPULAR",
			132 => "WEIGHT",
			133 => "HEIGHT",
			134 => "DEPTH",
			135 => "WIDTH",
			136 => "",
		)
	),
	false
);?>
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>