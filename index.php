<?define("INDEX_PAGE", "Y");?> <?define("MAIN_PAGE", true);?> <?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("keywords", "Eporta");
$APPLICATION->SetPageProperty("description", "Eporta");
$APPLICATION->SetTitle("Eporta");?> <?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	\Bitrix\Main\Loader::includeModule("iblock");
	require_once($_SERVER["DOCUMENT_ROOT"]."/local/admin_tools/eporta_banners/lib.php");

	//vars
	$catalogIblockId = null;
	$arPriceCodes = array();

	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
	if(!empty($arTemplateSettings)){
		$catalogIblockId = $arTemplateSettings["TEMPLATE_PRODUCT_IBLOCK_ID"];
		$arPriceCodes = explode(", ", $arTemplateSettings["TEMPLATE_PRICE_CODES"]);
	}
?> <?
	// Дев-превью нового шаблона eporta: статичная вёрстка главной (Этап 3, Фаза A).
	// Боевую dresscode-логику (слайдер/подборка/табы) не трогаем — при любом другом
	// активном шаблоне страница работает как прежде.
	$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
	if ($isEportaTemplate) {
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/templates/eporta/inc/categories.php");
	}
?>
<?if ($isEportaTemplate):?>

	<!-- Карусель(и) баннеров: общая высота подобрана так, чтобы над сгибом (viewport)
	     оставался виден край мозаики "Каталог по категориям". Слайды берутся из IBLOCK 27
	     (тип slider, редактируется через админку Bitrix: NAME=заголовок, DETAIL_PICTURE=
	     картинка, свойства SUBTITLE/LINK/CTA_TEXT/PLACEMENT). PLACEMENT раскладывает слайды
	     по трём местам: main (большой слева) / side1 (маленький сверху справа) /
	     side2 (маленький снизу справа). Если боковые места пустые — большой слайдер
	     растягивается на всю ширину, как раньше. -->
	<?
		// GetProperties() у элемента здесь ненадёжен (не возвращает значения для этого
		// инфоблока — похоже на не сброшенный кэш метаданных свойств), поэтому значения
		// свойств выбираются напрямую полями PROPERTY_* в GetList().
		CModule::IncludeModule("iblock");
		$arPlacementXmlIdByEnumId = [];
		$rsPlacementEnum = CIBlockPropertyEnum::GetList([], ["IBLOCK_ID" => 27, "CODE" => "PLACEMENT"]);
		while ($arEnum = $rsPlacementEnum->Fetch()) {
			$arPlacementXmlIdByEnumId[$arEnum["ID"]] = $arEnum["XML_ID"];
		}
		// Затенение поверх фото — включатель на баннер (свойство-список OVERLAY, значения
		// Y/N, XML_ID совпадает со значением; см. scripts/add_iblock27_overlay.php). Если
		// свойства ещё нет на окружении — карта пустая, ниже это трактуется как "Y" (старое
		// поведение по умолчанию).
		$arOverlayXmlIdByEnumId = [];
		$rsOverlayEnum = CIBlockPropertyEnum::GetList([], ["IBLOCK_ID" => 27, "CODE" => "OVERLAY"]);
		while ($arEnum = $rsOverlayEnum->Fetch()) {
			$arOverlayXmlIdByEnumId[$arEnum["ID"]] = $arEnum["XML_ID"];
		}

		$arHomeBannerSlides = ["main" => [], "side1" => [], "side2" => []];
		$rsHomeBannerSlides = CIBlockElement::GetList(
			["SORT" => "ASC"],
			["IBLOCK_ID" => 27, "ACTIVE" => "Y"],
			false,
			false,
			["ID", "NAME", "DETAIL_PICTURE", "PREVIEW_PICTURE", "PROPERTY_PLACEMENT", "PROPERTY_OVERLAY", "PROPERTY_SUBTITLE", "PROPERTY_LINK", "PROPERTY_CTA_TEXT"]
		);
		while ($arSlideFields = $rsHomeBannerSlides->Fetch()) {
			if (empty($arSlideFields["DETAIL_PICTURE"])) {
				continue;
			}
			// Тот же IBLOCK 27 хранит и слайды карусели (PLACEMENT main/side1/side2), и слотовые
			// баннеры плиток "Категории"/"Коллекции" (PLACEMENT cat_*/coll_*, заливаются через
			// local/admin_tools/eporta_banners/ — см. eportaBannersSlots() в lib.php). Слайды без
			// PLACEMENT вообще (старые, до PLACEMENT) идут в "main", как и раньше; слайды с чужим
			// (не main/side1/side2) PLACEMENT — это слотовые баннеры, их сюда не подмешиваем.
			// Пустой/неизвестный XML_ID (значение списка заведено вручную через штатную админку
			// Битрикса, без XML_ID) раньше отбрасывал слайд молча — трактуем как "main", как и
			// полностью отсутствующее свойство. Отбрасываем только явно чужие слотовые коды
			// (cat_*/coll_*, см. eportaBannersSlots() в lib.php) — это баннеры плиток, не карусели.
			$enumIdSet = $arSlideFields["PROPERTY_PLACEMENT_ENUM_ID"] ?? null;
			$slidePlacementRaw = ($enumIdSet === null || $enumIdSet === false || $enumIdSet === "")
				? ""
				: ($arPlacementXmlIdByEnumId[$enumIdSet] ?? "");
			if (isset($arHomeBannerSlides[$slidePlacementRaw])) {
				// Известное место карусели (main/side1/side2).
				$slidePlacement = $slidePlacementRaw;
			} elseif (strpos($slidePlacementRaw, "cat_") === 0 || strpos($slidePlacementRaw, "coll_") === 0 || strpos($slidePlacementRaw, "megamenu_") === 0) {
				// Слотовый баннер плитки (eporta_banners) — не наш, сюда не подмешиваем.
				// megamenu_* — промо-плитки выпадающего меню "Каталог" (header.php), тот же
				// IBLOCK 27, но выводятся отдельно через window.EPORTA_MEGAMENU (app.js), а не
				// через эту карусель. Раньше не были исключены здесь — из-за этого баннер,
				// залитый в слот мегаменю, дублировался ещё и в большой карусели главной
				// (трактовался как "незнакомый PLACEMENT" → падал в "main"), см. баг 22.09.2026.
				continue;
			} else {
				// Пустой/незнакомый XML_ID (в т.ч. значение списка, заведённое вручную через
				// штатную админку Битрикса без XML_ID) — трактуем как "main", как и раньше
				// делалось для полностью отсутствующего свойства.
				$slidePlacement = "main";
			}
			// DETAIL_PICTURE может быть не заполнена, если фото залили только в "Картинку для
			// анонса" (PREVIEW_PICTURE) — тот же фолбэк, что и для карточек моделей коллекции
			// (catalog/index.php).
			$eportaBannerPictureId = $arSlideFields["DETAIL_PICTURE"] ?: $arSlideFields["PREVIEW_PICTURE"];
			if (!$eportaBannerPictureId) {
				continue;
			}
			// WebP уже готов заранее (scripts/import/convert_webp.php проходит весь upload/iblock
			// целиком, включая инфоблок баннеров 27 — не только товары IBLOCK 19). Раньше .webp
			// подставлялся как единственный URL (?: $eportaBannerSrc) — если файл на диске битый
			// или нулевого размера, картинка пропадала целиком. Теперь храним оба варианта и всегда
			// оставляем оригинал доступным как запасной (см. renderHomeBannerCarousel).
			$eportaBannerSrc = CFile::GetPath($eportaBannerPictureId);
			$eportaBannerWebp = eportaWebpVariant($eportaBannerSrc);
			$eportaOverlayEnumId = $arSlideFields["PROPERTY_OVERLAY_ENUM_ID"] ?? null;
			$eportaOverlayXmlId = $eportaOverlayEnumId ? ($arOverlayXmlIdByEnumId[$eportaOverlayEnumId] ?? "") : "";
			$arHomeBannerSlides[$slidePlacement][] = [
				"IMAGE" => $eportaBannerSrc,
				"IMAGE_WEBP" => $eportaBannerWebp,
				"TITLE" => $arSlideFields["NAME"],
				"SUBTITLE" => $arSlideFields["PROPERTY_SUBTITLE_VALUE"] ?? "",
				// Санитизация схемы — LINK редактируется и через штатную админку Bitrix (не только
				// через save_meta в eporta_banners/ajax.php, где значение уже проверяется на
				// входе), так что javascript:-ссылка могла попасть сюда в обход того фильтра.
				"LINK" => eportaSanitizeBannerLink((string)($arSlideFields["PROPERTY_LINK_VALUE"] ?? "")) ?: "/catalog/",
				// Пусто = кнопки нет вообще (не дефолтный текст, как раньше) — управляется чекбоксом
				// "Показывать кнопку" в местном admin_tools/eporta_banners/ (Этап 5, 11.09.2026).
				"CTA_TEXT" => $arSlideFields["PROPERTY_CTA_TEXT_VALUE"] ?? "",
				// Пусто (в т.ч. "(нет)" в штатной форме элемента, значение без XML_ID) — затенение
				// ВЫКЛЮЧЕНО. Раньше было наоборот (!== "N"), из-за чего "(нет)" и "Да" визуально
				// не различались — см. eportaBannersGetSlotElements() в eporta_banners/lib.php.
				"OVERLAY" => ($eportaOverlayXmlId === "Y"),
			];
		}

		function renderHomeBannerCarousel($arSlides, $htmlId, $cssClass) {
			if (count($arSlides) < 1) {
				return;
			}
			?>
			<div class="<?= $cssClass ?>" id="<?= htmlspecialcharsbx($htmlId) ?>">
				<div class="hbc-track">
					<?foreach ($arSlides as $eportaSlideIndex => $arSlide):?>
					<?
						// Первый слайд каждой карусели виден без скролла сразу при загрузке — именно он
						// LCP-кандидат Lighthouse. Как background-image в CSS он был НЕ виден preload-
						// сканеру браузера (тот парсит только HTML, до применения CSSOM не знает про
						// картинку) и Lighthouse требовал fetchpriority=high, которого у CSS-фона в
						// принципе не бывает. Поэтому первый слайд — обычный <img fetchpriority="high">
						// прямо в HTML (без loading=lazy — по умолчанию и так eager), остальные слайды
						// (не видны до пролистывания) остаются на background-image, как раньше.
						$eportaSlideIsFirst = ($eportaSlideIndex === 0);
					?>
					<?
						// Затенение выключено — только картинка и название, без градиента и без
						// подложки под текстом (заказчик рисует своё оформление на самом фото,
						// см. .hbc-slide-content--plain в template_styles.css: текстовая тень вместо
						// плашки, чтобы название не терялось на светлом фото).
						$eportaSlideBgStyle = "";
						if (!$eportaSlideIsFirst) {
							// ВАЖНО: раньше здесь стоял CSS image-set(...type("image/webp")) — вложенные
							// двойные кавычки внутри HTML-атрибута style="..." обрывали атрибут на первой
							// же внутренней кавычке (браузер видел style="...type(" и всё, background-image
							// не применялся ВООБЩЕ ни у одного слайда, кроме первого). PHP и так уже выбрал
							// webp-или-оригинал с проверкой наличия файла на диске (eportaWebpVariant) —
							// никакого CSS-fallback поверх не нужно, только один валидный url().
							$eportaSlideBgStyle = "background-image:url(" . htmlspecialcharsbx($arSlide["IMAGE_WEBP"] ?: $arSlide["IMAGE"]) . ")";
						}
					?>
					<a href="<?= htmlspecialcharsbx($arSlide["LINK"]) ?>" class="hbc-slide"<?= $eportaSlideBgStyle ? ' style="'.$eportaSlideBgStyle.'"' : "" ?>>
						<?if ($eportaSlideIsFirst):?>
						<?php eportaPicture($arSlide["IMAGE"], "", ["fetchpriority" => "high", "class" => "hbc-slide-img"]); ?>
						<?endif;?>
						<?if ($arSlide["OVERLAY"]):?><div class="hbc-slide-overlay"></div><?endif;?>
						<div class="hbc-slide-content<?= $arSlide["OVERLAY"] ? "" : " hbc-slide-content--plain" ?>">
							<div class="hbc-title"><?= htmlspecialcharsbx($arSlide["TITLE"]) ?></div>
							<?if ($arSlide["SUBTITLE"]):?><div class="hbc-subtitle"><?= htmlspecialcharsbx($arSlide["SUBTITLE"]) ?></div><?endif;?>
							<?if ($arSlide["CTA_TEXT"]):?><span class="hbc-cta"><?= htmlspecialcharsbx($arSlide["CTA_TEXT"]) ?></span><?endif;?>
						</div>
					</a>
					<?endforeach;?>
				</div>
				<?if (count($arSlides) >= 2):?>
				<button type="button" class="hbc-arrow hbc-prev" aria-label="Предыдущий баннер">‹</button>
				<button type="button" class="hbc-arrow hbc-next" aria-label="Следующий баннер">›</button>
				<div class="hbc-dots"></div>
				<?endif;?>
			</div>
			<?
		}

		$hasHomeBannerMain = count($arHomeBannerSlides["main"]) >= 1;
		$hasHomeBannerSide = count($arHomeBannerSlides["side1"]) >= 1 || count($arHomeBannerSlides["side2"]) >= 1;
	?>
	<?if ($hasHomeBannerMain):?>
	<div style="padding:28px var(--pad-x) 8px">
		<?if ($hasHomeBannerSide):?>
		<div class="home-banner-mosaic">
			<?renderHomeBannerCarousel($arHomeBannerSlides["main"], "homeBannerMain", "home-banner-carousel hbc-main");?>
			<div class="hbc-side-stack">
				<?renderHomeBannerCarousel($arHomeBannerSlides["side1"], "homeBannerSide1", "home-banner-carousel home-banner-carousel--compact");?>
				<?renderHomeBannerCarousel($arHomeBannerSlides["side2"], "homeBannerSide2", "home-banner-carousel home-banner-carousel--compact");?>
			</div>
		</div>
		<?else:?>
			<?renderHomeBannerCarousel($arHomeBannerSlides["main"], "homeBannerCarousel", "home-banner-carousel");?>
		<?endif;?>
	</div>
	<?endif;?>

	<!-- Каталог по категориям: подписи с количеством товаров/моделей под плитками убраны
	     (заявка заказчика 08.09.2026) — считать их больше не нужно, остался только
	     eportaGetCategoryMap() для общего числа категорий в "Все N категорий →" ниже.
	     Ссылки ведут на /catalog/?category=<key> — фильтр обрабатывается в catalog/index.php. -->
	<?
		$eportaHomeCatTotal = count(eportaGetCategoryMap());
	?>
	<div style="padding:28px var(--pad-x) 8px">
		<div class="section-heading">
			<h2>Каталог по категориям</h2>
			<a href="/catalog/">Все <?=$eportaHomeCatTotal?> категорий →</a>
		</div>
		<div class="eporta-mosaic-grid" style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1.2fr;grid-auto-rows:168px;gap:12px">

			<!-- Затенение (свойство OVERLAY, IBLOCK 27) для этих 6 плиток раньше не читалось вообще —
			     градиент был безусловным инлайн-стилем, галочка в админке eporta_banners для них
			     ни на что не влияла. Теперь каждая плитка проверяет свой слот, как уже сделано для
			     плиток коллекций ниже. -->
			<?
				$eportaCatOverlayMkd = eportaBannersSlotOverlayEnabled("cat_mkd");
				$eportaCatOverlayHidden = eportaBannersSlotOverlayEnabled("cat_hidden");
				$eportaCatOverlaySliding = eportaBannersSlotOverlayEnabled("cat_sliding");
				$eportaCatOverlayEntrance = eportaBannersSlotOverlayEnabled("cat_entrance");
				$eportaCatOverlayArch = eportaBannersSlotOverlayEnabled("cat_arch");
				$eportaCatOverlayHardware = eportaBannersSlotOverlayEnabled("cat_hardware");
			?>

			<!-- Большая плитка: Межкомнатные -->
			<a href="/catalog/?category=mkd" style="grid-column:1;grid-row:1/span 2;position:relative;border-radius:14px;overflow:hidden;cursor:pointer;display:block">
				<?php eportaPicture(eportaBannersResolveImage("cat_mkd", SITE_TEMPLATE_PATH . "/assets/img/cat-mezh.jpg"), "Межкомнатные", ["style" => "position:absolute;inset:0;width:100%;height:100%;object-fit:cover"]); ?>
				<?if ($eportaCatOverlayMkd):?><div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 42%,rgba(20,17,12,.72) 100%)"></div><?endif;?>
				<div style="position:absolute;left:20px;right:20px;bottom:18px;display:flex;align-items:flex-end;justify-content:space-between">
					<div><div style="font:800 22px 'Manrope';color:#fff;line-height:1.05<?=$eportaCatOverlayMkd ? "" : ";text-shadow:0 1px 6px rgba(0,0,0,.55)"?>">Межкомнатные</div></div>
					<span style="width:38px;height:38px;border-radius:50%;background:#e8820a;color:#fff;font-size:17px;display:flex;align-items:center;justify-content:center;flex:none">→</span>
				</div>
			</a>

			<!-- Пара: Скрытые / Раздвижные -->
			<a href="/catalog/?category=hidden" style="grid-column:2;grid-row:1;position:relative;border-radius:14px;overflow:hidden;display:block">
				<?php eportaPicture(eportaBannersResolveImage("cat_hidden", SITE_TEMPLATE_PATH . "/assets/img/cat-skryt.jpg"), "Скрытые", ["style" => "position:absolute;inset:0;width:100%;height:100%;object-fit:cover"]); ?>
				<?if ($eportaCatOverlayHidden):?><div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(20,17,12,.7) 100%)"></div><?endif;?>
				<div style="position:absolute;left:15px;bottom:13px"><div style="font:700 15.5px 'Manrope';color:#fff<?=$eportaCatOverlayHidden ? "" : ";text-shadow:0 1px 6px rgba(0,0,0,.55)"?>">Скрытые</div></div>
			</a>
			<a href="/catalog/?category=sliding" style="grid-column:2;grid-row:2;position:relative;border-radius:14px;overflow:hidden;display:block">
				<?php eportaPicture(eportaBannersResolveImage("cat_sliding", SITE_TEMPLATE_PATH . "/assets/img/cat-razdv.jpg"), "Раздвижные", ["style" => "position:absolute;inset:0;width:100%;height:100%;object-fit:cover"]); ?>
				<?if ($eportaCatOverlaySliding):?><div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(20,17,12,.7) 100%)"></div><?endif;?>
				<div style="position:absolute;left:15px;bottom:13px"><div style="font:700 15.5px 'Manrope';color:#fff<?=$eportaCatOverlaySliding ? "" : ";text-shadow:0 1px 6px rgba(0,0,0,.55)"?>">Раздвижные</div></div>
			</a>

			<!-- Пара: Входные / Арки -->
			<a href="/catalog/?category=entrance" style="grid-column:3;grid-row:1;position:relative;border-radius:14px;overflow:hidden;display:block">
				<?php eportaPicture(eportaBannersResolveImage("cat_entrance", SITE_TEMPLATE_PATH . "/assets/img/cat-vhod.jpg"), "Входные", ["style" => "position:absolute;inset:0;width:100%;height:100%;object-fit:cover"]); ?>
				<?if ($eportaCatOverlayEntrance):?><div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(20,17,12,.7) 100%)"></div><?endif;?>
				<div style="position:absolute;left:15px;bottom:13px"><div style="font:700 15.5px 'Manrope';color:#fff<?=$eportaCatOverlayEntrance ? "" : ";text-shadow:0 1px 6px rgba(0,0,0,.55)"?>">Входные</div></div>
			</a>
			<a href="/catalog/?category=arch" style="grid-column:3;grid-row:2;position:relative;border-radius:14px;overflow:hidden;display:block">
				<?php eportaPicture(eportaBannersResolveImage("cat_arch", SITE_TEMPLATE_PATH . "/assets/img/cat-arki.jpg"), "Арки и порталы", ["style" => "position:absolute;inset:0;width:100%;height:100%;object-fit:cover"]); ?>
				<?if ($eportaCatOverlayArch):?><div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(20,17,12,.7) 100%)"></div><?endif;?>
				<div style="position:absolute;left:15px;bottom:13px"><div style="font:700 15.5px 'Manrope';color:#fff<?=$eportaCatOverlayArch ? "" : ";text-shadow:0 1px 6px rgba(0,0,0,.55)"?>">Арки и порталы</div></div>
			</a>

			<!-- Высокая: Фурнитура -->
			<a href="/catalog/?category=hardware" style="grid-column:4;grid-row:1/span 2;position:relative;border-radius:14px;overflow:hidden;display:block">
				<?php eportaPicture(eportaBannersResolveImage("cat_hardware", SITE_TEMPLATE_PATH . "/assets/img/cat-furn.jpg"), "Фурнитура", ["style" => "position:absolute;inset:0;width:100%;height:100%;object-fit:cover"]); ?>
				<?if ($eportaCatOverlayHardware):?><div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 48%,rgba(20,17,12,.72) 100%)"></div><?endif;?>
				<div style="position:absolute;left:18px;right:18px;bottom:16px;display:flex;align-items:flex-end;justify-content:space-between">
					<div><div style="font:800 18px 'Manrope';color:#fff<?=$eportaCatOverlayHardware ? "" : ";text-shadow:0 1px 6px rgba(0,0,0,.55)"?>">Фурнитура</div></div>
					<span style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.92);color:#1b1a17;font-size:15px;display:flex;align-items:center;justify-content:center;flex:none">→</span>
				</div>
			</a>
		</div>
	</div>

	<!-- Баннер-табы "Хиты / Распродажа / Новинки" (Этап 3.1, 10.09.2026) — между блоками
	     "Каталог по категориям" и "Коллекции фабрики". Слева вертикальный список переключателей,
	     справа один ряд карточек с горизонтальной прокруткой (стрелки), максимум 10 карточек на
	     таб. Все три ряда рендерятся серверно сразу — переключение только display в JS ниже
	     (не AJAX, чтобы не ломать LCP и не заводить новый эндпоинт). Настройки (заголовки/лимиты/
	     закреплённые товары) — local/admin_tools/eporta_home_tabs/,
	     local/php_interface/include/eporta_home_tabs_common.php. -->
	<?
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/eporta_home_tabs_common.php");
		$eportaHomeTabsKeys = eportaHomeTabsKeys();
		$eportaHomeTabsConfig = eportaHomeTabsGetConfig();
		$eportaHomeTabsVarNames = [
			"hit" => "arrEportaHomeTabHit",
			"sale" => "arrEportaHomeTabSale",
			"new" => "arrEportaHomeTabNew",
		];
		$eportaHomeTabsIds = [];
		foreach ($eportaHomeTabsKeys as $eportaHomeTabKey) {
			$eportaHomeTabsIds[$eportaHomeTabKey] = eportaHomeTabsResolveIds($eportaHomeTabKey, $eportaHomeTabsConfig[$eportaHomeTabKey]);
		}
	?>
	<div class="home-tabs-banner">
		<div class="home-tabs-banner__nav">
			<?foreach ($eportaHomeTabsKeys as $eportaHomeTabIndex => $eportaHomeTabKey):?>
			<div class="home-tabs-banner__tab<?=$eportaHomeTabIndex === 0 ? " active" : ""?>" data-home-tab="<?=$eportaHomeTabKey?>"><?=htmlspecialcharsbx($eportaHomeTabsConfig[$eportaHomeTabKey]["title"])?></div>
			<?endforeach;?>
		</div>
		<div class="home-tabs-banner__body">
			<button type="button" class="home-tabs-banner__arrow home-tabs-banner__arrow--left" aria-label="Назад">‹</button>
			<?foreach ($eportaHomeTabsKeys as $eportaHomeTabIndex => $eportaHomeTabKey):?>
			<div class="home-tabs-banner__scroll" data-home-tab-panel="<?=$eportaHomeTabKey?>"<?=$eportaHomeTabIndex === 0 ? "" : ' style="display:none"'?>>
				<?eportaHomeTabsRenderCatalogSection($eportaHomeTabKey, $eportaHomeTabsVarNames[$eportaHomeTabKey], $eportaHomeTabsIds[$eportaHomeTabKey]);?>
			</div>
			<?endforeach;?>
			<button type="button" class="home-tabs-banner__arrow home-tabs-banner__arrow--right" aria-label="Вперёд">›</button>
		</div>
	</div>
	<script>
	(function () {
		var root = document.querySelector(".home-tabs-banner");
		if (!root) return;
		var tabs = root.querySelectorAll(".home-tabs-banner__tab");
		var panels = root.querySelectorAll(".home-tabs-banner__scroll");
		var btnLeft = root.querySelector(".home-tabs-banner__arrow--left");
		var btnRight = root.querySelector(".home-tabs-banner__arrow--right");
		var activeKey = tabs.length ? tabs[0].getAttribute("data-home-tab") : null;

		function activePanel() {
			return root.querySelector('.home-tabs-banner__scroll[data-home-tab-panel="' + activeKey + '"]');
		}
		function updateArrows() {
			var p = activePanel();
			if (!p || !btnLeft || !btnRight) return;
			btnLeft.disabled = p.scrollLeft <= 2;
			btnRight.disabled = p.scrollLeft >= (p.scrollWidth - p.clientWidth - 2);
		}
		tabs.forEach(function (tab) {
			tab.addEventListener("click", function () {
				activeKey = tab.getAttribute("data-home-tab");
				tabs.forEach(function (t) { t.classList.toggle("active", t === tab); });
				panels.forEach(function (p) {
					p.style.display = (p.getAttribute("data-home-tab-panel") === activeKey) ? "" : "none";
				});
				updateArrows();
			});
		});
		if (btnLeft) btnLeft.addEventListener("click", function () {
			var p = activePanel();
			if (p) p.scrollBy({ left: -432, behavior: "smooth" });
		});
		if (btnRight) btnRight.addEventListener("click", function () {
			var p = activePanel();
			if (p) p.scrollBy({ left: 432, behavior: "smooth" });
		});
		panels.forEach(function (p) {
			p.addEventListener("scroll", function () {
				if (p.getAttribute("data-home-tab-panel") === activeKey) updateArrows();
			});
		});
		updateArrows();
	})();
	</script>

	<!-- Коллекции фабрики: единый источник данных local/lib/eporta_collections.php (все активные
	     подразделы 183 из IBLOCK 19, в порядке SORT). Раньше здесь были захардкожены ровно 6
	     коллекций с фиксированным описанием и кнопкой "Все коллекции" на отдельный хаб — теперь
	     показываются все коллекции сразу, сеткой 4 в ряд (см. .eporta-tile-grid--coll в
	     template_styles.css), кнопка убрана (заявка заказчика 05.09.2026). Описание — реальное
	     поле DESCRIPTION секции, редактируется в local/admin_tools/eporta_collections/. -->
	<?
		// Подпись с числом товаров под плиткой коллекции убрана (заявка заказчика 08.09.2026) —
		// eportaCollectionsElementCounts() больше не нужен.
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/lib/eporta_collections.php");
		$eportaHomeCollections = eportaCollections();
	?>
	<div style="padding:26px var(--pad-x) 4px">
		<h2 style="margin:0 0 6px;font:800 27px 'Manrope';letter-spacing:-0.01em">Коллекции фабрики</h2>
		<div style="font:500 13.5px;color:#8a857b;margin-bottom:18px">Серии дверей с единым дизайном — от полотна до фурнитуры</div>
		<div class="eporta-tile-grid eporta-tile-grid--coll">
			<?foreach ($eportaHomeCollections as $eportaHomeColl):
				$eportaHomeCollSlot = eportaCollectionSlotCode($eportaHomeColl["CODE"]);
				// Квадрат 1:1 с фоновой подложкой (как на alfaporta.ru) — картинка вписывается
				// целиком (object-fit:contain), не обрезается при несовпадении пропорций с блоком.
				$eportaCollOverlayOn = eportaBannersSlotOverlayEnabled($eportaHomeCollSlot);
			?>
			<a href="/catalog/collections/<?=htmlspecialcharsbx($eportaHomeColl["CODE"])?>/" style="position:relative;border-radius:16px;overflow:hidden;cursor:pointer;aspect-ratio:1/1;display:block;text-decoration:none;background:#f2efe9">
				<?php eportaPicture(eportaBannersResolveImage($eportaHomeCollSlot, ""), $eportaHomeColl["NAME"], ["style" => "position:absolute;inset:0;width:100%;height:100%;object-fit:contain"]); ?>
				<?if ($eportaCollOverlayOn):?>
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 38%,rgba(20,17,12,.8) 100%)"></div>
				<?endif;?>
				<!-- Описание коллекции под названием убрано (заявка заказчика 09.09.2026, вслед за
				     подписью с числом товаров) — на плитке остаётся только название. -->
				<div style="position:absolute;left:20px;right:20px;bottom:18px"><div style="font:800 22px 'Manrope';color:#fff;letter-spacing:.01em<?=$eportaCollOverlayOn ? "" : ";text-shadow:0 1px 6px rgba(0,0,0,.55)"?>"><?=htmlspecialcharsbx($eportaHomeColl["NAME"])?></div></div>
			</a>
			<?endforeach;?>
		</div>
	</div>

	<!-- Популярные запросы -->
	<div style="padding:26px var(--pad-x) 4px">
		<div style="font:800 18px 'Manrope';letter-spacing:-0.01em;margin-bottom:14px">Популярные запросы</div>
		<div style="display:flex;flex-wrap:wrap;gap:10px">
			<a href="/catalog/" style="font:600 13px 'Manrope';color:#3a3631;background:#f4f1ea;border:1px solid #ece7de;border-radius:999px;padding:9px 16px;cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s">Белые двери</a>
			<a href="/catalog/" style="font:600 13px 'Manrope';color:#3a3631;background:#f4f1ea;border:1px solid #ece7de;border-radius:999px;padding:9px 16px;cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s">Современные двери</a>
			<a href="/catalog/" style="font:600 13px 'Manrope';color:#3a3631;background:#f4f1ea;border:1px solid #ece7de;border-radius:999px;padding:9px 16px;cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s">Классические двери</a>
			<a href="/catalog/" style="font:600 13px 'Manrope';color:#3a3631;background:#f4f1ea;border:1px solid #ece7de;border-radius:999px;padding:9px 16px;cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s">Двери с терморазрывом</a>
			<a href="/catalog/" style="font:600 13px 'Manrope';color:#3a3631;background:#f4f1ea;border:1px solid #ece7de;border-radius:999px;padding:9px 16px;cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s">Двери экошпон</a>
			<a href="/catalog/" style="font:600 13px 'Manrope';color:#3a3631;background:#f4f1ea;border:1px solid #ece7de;border-radius:999px;padding:9px 16px;cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s">Ульяновские двери</a>
		</div>
	</div>

	<?
		// Захардкоженная строка соцдоказательства (звёзды/число отзывов) убрана по заявке
		// заказчика 24.09.2026 — цифры были придуманные и не совпадали с реальным блоком
		// отзывов ниже. Сам блок .social-proof в template_styles.css оставлен нетронутым
		// на случай, если понадобится вернуть похожую плашку с реальными цифрами.
	?>
	<?
		// Новые блоки в самом низу главной (Наши работы / Отзывы / Карта салонов, заявка
		// заказчика 24.09.2026) — на проде видны только в preview-режиме до утверждения макета
		// заказчиком: тот же токен, что и переключатель шаблона в header.php (dev_preview
		// в $_REQUEST либо уже закреплённый в cookie), не новая сущность — см. project memory
		// project_template_switch_mechanism. Когда заказчик одобрит — заменить условие на
		// постоянное "true" (или убрать вовсе), блоки станут видны всем посетителям.
		$eportaPreviewBlocks = (($_REQUEST["dev_preview"] ?? "") === "x7Qm2pR9vL") || (($_COOKIE["dev_preview"] ?? "") === "x7Qm2pR9vL");
	?>
	<?if ($eportaPreviewBlocks):
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/eporta_works_common.php");
		$eportaHomeWorks = eportaWorksList(10);
	?>
	<?if ($eportaHomeWorks):?>
	<!-- Наши работы — источник: инфоблок EPORTA_WORKS_IBLOCK_ID, наполняется через
	     local/admin_tools/eporta_works/. Пустой список -> блок не выводится (см.
	     eportaWorksList()). Первая работа — featured-карточка (в 2 раза шире, крупнее подпись),
	     остальные — обычная лента (вариант "A", согласовано с пользователем 25.09.2026, см.
	     .home-work-card--featured в template_styles.css). Подпись поверх фото с градиентом —
	     тот же приём, что и у карусели баннеров (.hbc-*) и плиток коллекций выше на странице. -->
	<div style="padding:26px var(--pad-x) 4px">
		<div class="section-heading"><h2>Наши работы</h2></div>
		<div class="home-works-scroll">
			<?foreach ($eportaHomeWorks as $eportaWorkIdx => $eportaWork):?>
			<div class="home-work-card<?=$eportaWorkIdx === 0 ? " home-work-card--featured" : ""?>">
				<?if ($eportaWork["PREVIEW_PICTURE_SRC"]):?>
				<?php eportaPicture($eportaWork["PREVIEW_PICTURE_SRC"], $eportaWork["NAME"]); ?>
				<?endif;?>
				<div class="home-work-card__caption">
					<div class="home-work-card__title"><?=htmlspecialcharsbx($eportaWork["NAME"])?></div>
					<?if ($eportaWork["CITY"] || $eportaWork["COLLECTION"]):?>
					<div class="home-work-card__meta">
						<?=htmlspecialcharsbx(trim($eportaWork["CITY"] . ($eportaWork["CITY"] && $eportaWork["COLLECTION"] ? " · " : "") . $eportaWork["COLLECTION"]))?>
					</div>
					<?endif;?>
				</div>
			</div>
			<?endforeach;?>
		</div>
	</div>
	<?endif;?>
	<?endif;?>
	<!-- /Наши работы -->

	<?if ($eportaPreviewBlocks):
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/eporta_reviews_common.php");
		$eportaHomeReviews = eportaReviewsList(10);
		$eportaReviewsAgg = eportaReviewsAggregate();
	?>
	<?if ($eportaHomeReviews):?>
	<!-- Отзывы — источник: инфоблок EPORTA_REVIEWS_IBLOCK_ID, наполняется только через
	     local/admin_tools/eporta_reviews/ (публичной формы "оставить отзыв" нет, решение
	     пользователя 24.09.2026). Пустой список -> блок не выводится (см. eportaReviewsList()).
	     Средняя оценка/количество в подзаголовке — реальные (eportaReviewsAggregate()), не
	     захардкожены, как было в прежней строке .social-proof (убрана выше).
	     Бегущая строка (вариант "B", согласовано с пользователем 25.09.2026): контент
	     отрисовывается дважды подряд (eportaReviewCardHtml переиспользуется для обоих наборов) —
	     второй набор помечен aria-hidden и .home-review-set--dup (см. template_styles.css: под
	     reduced-motion дубль скрывается, лента становится обычным прокручиваемым рядом).
	     Длительность анимации зависит от числа отзывов (--marquee-duration), чтобы скорость
	     ленты не менялась заметно при добавлении новых отзывов через админку. -->
	<?
		$eportaReviewCardHtml = function ($eportaReview) {
			ob_start();
			?>
			<div class="home-review-card">
				<span class="home-review-card__quote-mark">&#8220;</span>
				<div class="home-review-card__rating"><?=str_repeat("★", $eportaReview["RATING"])?><span class="dim"><?=str_repeat("★", 5 - $eportaReview["RATING"])?></span></div>
				<div class="home-review-card__text"><?=nl2br(htmlspecialcharsbx($eportaReview["PREVIEW_TEXT"]))?></div>
				<div class="home-review-card__author"><?=htmlspecialcharsbx($eportaReview["NAME"])?></div>
				<?if ($eportaReview["CITY"]):?>
				<div class="home-review-card__city"><?=htmlspecialcharsbx($eportaReview["CITY"])?></div>
				<?endif;?>
			</div>
			<?
			return ob_get_clean();
		};
		$eportaMarqueeDuration = max(18, count($eportaHomeReviews) * 6) . "s";
	?>
	<div style="padding:26px var(--pad-x) 4px">
		<div class="section-heading">
			<h2>Отзывы покупателей</h2>
			<?if ($eportaReviewsAgg):?>
			<span class="home-reviews-agg"><span class="star">★</span> <?=htmlspecialcharsbx((string)$eportaReviewsAgg["average"])?> · <?=(int)$eportaReviewsAgg["count"]?> отзывов</span>
			<?endif;?>
		</div>
		<div class="home-reviews-marquee">
			<div class="home-reviews-track" style="--marquee-duration:<?=htmlspecialcharsbx($eportaMarqueeDuration)?>">
				<div class="home-review-set">
					<?foreach ($eportaHomeReviews as $eportaReview):?>
					<?=$eportaReviewCardHtml($eportaReview)?>
					<?endforeach;?>
				</div>
				<div class="home-review-set home-review-set--dup" aria-hidden="true">
					<?foreach ($eportaHomeReviews as $eportaReview):?>
					<?=$eportaReviewCardHtml($eportaReview)?>
					<?endforeach;?>
				</div>
			</div>
		</div>
	</div>
	<?endif;?>
	<?endif;?>
	<!-- /Отзывы -->

	<?if ($eportaPreviewBlocks):
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/eporta_stores_map_common.php");
		$eportaHomeStores = eportaStoresMapList();
		$eportaStoresApiKey = eportaStoresMapApiKey();
	?>
	<?if ($eportaHomeStores):?>
	<!-- Карта салонов — источник: штатный модуль catalog (b_catalog_store, тот же, что у
	     /stores/), наполняется через штатную админку Bitrix "Магазины -> Склады", не кастомную.
	     Список рендерится всегда (SSR, работает без JS); сама карта Яндекса подключается ЛЕНИВО
	     через IntersectionObserver только когда блок подходит к вьюпорту (перф: не тянем чужой
	     скрипт в общий бандл) и только если есть API-ключ (eportaStoresMapApiKey()) — пока ключа
	     нет, вместо карты показывается статичная заглушка фиксированной высоты (CLS не растёт).
	     Карта на всю ширину + плавающий список поверх (вариант "A", согласовано с пользователем
	     25.09.2026, см. .home-stores-hero/.home-stores-overlay в template_styles.css); на
	     мобильном (<780px) оверлей превращается в обычный блок под картой — правило описано
	     тут же в CSS, а не молча оставлено браузеру. -->
	<div style="padding:26px var(--pad-x) 4px">
		<div class="section-heading">
			<h2>Наши салоны</h2>
			<a href="/stores/">Все салоны</a>
		</div>
		<div class="home-stores-hero">
			<div id="eporta-stores-map" class="home-stores-map">
				Карта загрузится при прокрутке до этого блока
			</div>
			<div id="eporta-stores-list" class="home-stores-overlay">
				<?foreach ($eportaHomeStores as $eportaStore):?>
				<div class="home-store-row<?=$eportaStore["HAS_COORDS"] ? " home-store-row--clickable" : ""?>" data-lat="<?=htmlspecialcharsbx($eportaStore["LAT"])?>" data-lon="<?=htmlspecialcharsbx($eportaStore["LON"])?>">
					<div class="home-store-row__title"><?=htmlspecialcharsbx($eportaStore["TITLE"])?></div>
					<div class="home-store-row__address"><?=htmlspecialcharsbx($eportaStore["ADDRESS"])?></div>
					<?if ($eportaStore["PHONE"]):?>
					<div class="home-store-row__phone"><?=htmlspecialcharsbx($eportaStore["PHONE"])?></div>
					<?endif;?>
					<?if ($eportaStore["SCHEDULE"]):?>
					<div class="home-store-row__schedule"><?=htmlspecialcharsbx($eportaStore["SCHEDULE"])?></div>
					<?endif;?>
				</div>
				<?endforeach;?>
			</div>
		</div>
	</div>
	<script>
	(function () {
		var mapEl = document.getElementById('eporta-stores-map');
		var listEl = document.getElementById('eporta-stores-list');
		if (!mapEl || !listEl) return;
		var API_KEY = <?=json_encode($eportaStoresApiKey)?>;
		var POINTS = <?=json_encode(array_values(array_map(function ($s) {
			return ['title' => $s["TITLE"], 'address' => $s["ADDRESS"], 'lat' => (float)$s["LAT"], 'lon' => (float)$s["LON"]];
		}, array_filter($eportaHomeStores, function ($s) { return $s["HAS_COORDS"]; }))), JSON_UNESCAPED_UNICODE)?>;

		// balloonContent у ymaps.Placemark рендерится как HTML, а не текст — title/address
		// приходят из штатной админки Bitrix (Магазины -> Склады) и не гарантированно чистые
		// (это чужая, не наша, форма ввода). Без экранирования это stored XSS: контент-менеджер
		// (или кто угодно с доступом к той админке) мог бы вписать в название/адрес склада
		// <script>/onerror и получить выполнение в браузере каждого посетителя, открывшего балун.
		function eportaEscapeHtml(s) {
			return String(s).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		var ymap = null;
		function initMap() {
			if (!window.ymaps || !POINTS.length) {
				mapEl.textContent = 'Карта временно недоступна';
				return;
			}
			ymaps.ready(function () {
				mapEl.textContent = '';
				ymap = new ymaps.Map(mapEl, {
					center: [POINTS[0].lat, POINTS[0].lon],
					zoom: 10,
					controls: ['zoomControl']
				});
				POINTS.forEach(function (p) {
					ymap.geoObjects.add(new ymaps.Placemark([p.lat, p.lon], { balloonContent: eportaEscapeHtml(p.title) + '<br>' + eportaEscapeHtml(p.address) }));
				});
				if (POINTS.length > 1) {
					ymap.setBounds(ymap.geoObjects.getBounds(), { checkZoomRange: true, zoomMargin: 30 });
				}
			});
		}

		function loadMap() {
			if (!API_KEY) {
				mapEl.textContent = 'Карта временно недоступна';
				return;
			}
			if (window.ymaps) { initMap(); return; }
			var s = document.createElement('script');
			s.src = 'https://api-maps.yandex.ru/2.1/?apikey=' + encodeURIComponent(API_KEY) + '&lang=ru_RU';
			s.onload = initMap;
			s.onerror = function () { mapEl.textContent = 'Не удалось загрузить карту'; };
			document.head.appendChild(s);
		}

		if ('IntersectionObserver' in window) {
			var obs = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						loadMap();
						obs.disconnect();
					}
				});
			}, { rootMargin: '200px' });
			obs.observe(mapEl);
		} else {
			loadMap();
		}

		listEl.addEventListener('click', function (e) {
			var row = e.target.closest('.home-store-row');
			if (!row || !row.dataset.lat || !row.dataset.lon || !ymap) return;
			listEl.querySelectorAll('.home-store-row.is-active').forEach(function (r) { r.classList.remove('is-active'); });
			row.classList.add('is-active');
			ymap.setCenter([parseFloat(row.dataset.lat), parseFloat(row.dataset.lon)], 14, { duration: 300 });
		});
	})();
	</script>
	<?endif;?>
	<?endif;?>
	<!-- /Карта салонов -->

<?else:?>

<?$APPLICATION->IncludeComponent(
	"dresscode:slider",
	"promoSlider",
	Array(
		"CACHE_TIME" => "86400",
		"CACHE_TYPE" => "Y",
		"COMPONENT_TEMPLATE" => "promoSlider",
		"COMPOSITE_FRAME_MODE" => "A",
		"COMPOSITE_FRAME_TYPE" => "AUTO",
		"IBLOCK_ID" => "27",
		"IBLOCK_TYPE" => "slider",
		"LAZY_LOAD_PICTURES" => "Y",
		"PICTURE_HEIGHT" => "1080",
		"PICTURE_WIDTH" => "1920"
	)
);?> <?$APPLICATION->IncludeComponent(
	"dresscode:offers.product",
	".default",
	[
		"AJAX_OPTION_ADDITIONAL" => "offers_style_387",
		"CACHE_TIME" => "3600",
		"CACHE_TYPE" => "A",
		"COMPOSITE_FRAME_MODE" => "A",
		"COMPOSITE_FRAME_TYPE" => "AUTO",
		"CONVERT_CURRENCY" => "N",
		"ELEMENTS_COUNT" => "15",
		"HIDE_MEASURES" => "Y",
		"HIDE_NOT_AVAILABLE" => "N",
		"IBLOCK_ID" => "19",
		"IBLOCK_TYPE" => "catalog",
		"LAZY_LOAD_PICTURES" => "Y",
		"PICTURE_HEIGHT" => "280",
		"PICTURE_WIDTH" => "400",
		"PRODUCT_PRICE_CODE" => [
		],
		"PROP_NAME" => "OFFERS",
		"PROP_VALUE" => [
			0 => "_294",
			1 => "_296",
			2 => "_297",
		],
		"SORT_PROPERTY_NAME" => "PROPERTY_ORDER",
		"SORT_VALUE" => "DESC",
		"COMPONENT_TEMPLATE" => ".default"
	],
	false
);?>
<div id="infoTabsCaption">
	<div class="limiter">
		<div class="items">
			 <?$APPLICATION->ShowViewContent("main_news_view_content_tab");?><br>
			 <?$APPLICATION->ShowViewContent("main_collection_view_content_tab");?> <br>
			 <?$APPLICATION->ShowViewContent("main_service_view_content_tab");?>
		</div>
	</div>
</div>
<div id="infoTabs">
	<div class="items">
	</div>
</div>
 <br>
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>