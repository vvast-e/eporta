<?define("INDEX_PAGE", "Y");?> <?define("MAIN_PAGE", true);?> <?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("keywords", "Eporta");
$APPLICATION->SetPageProperty("description", "Eporta");
$APPLICATION->SetTitle("Eporta");?> <?
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
?> <?
	// Дев-превью нового шаблона eporta: статичная вёрстка главной (Этап 3, Фаза A).
	// Боевую dresscode-логику (слайдер/подборка/табы) не трогаем — при любом другом
	// активном шаблоне страница работает как прежде.
	$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
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

		$arHomeBannerSlides = ["main" => [], "side1" => [], "side2" => []];
		$rsHomeBannerSlides = CIBlockElement::GetList(
			["SORT" => "ASC"],
			["IBLOCK_ID" => 27, "ACTIVE" => "Y"],
			false,
			false,
			["ID", "NAME", "DETAIL_PICTURE", "PROPERTY_PLACEMENT", "PROPERTY_SUBTITLE", "PROPERTY_LINK", "PROPERTY_CTA_TEXT"]
		);
		while ($arSlideFields = $rsHomeBannerSlides->Fetch()) {
			if (empty($arSlideFields["DETAIL_PICTURE"])) {
				continue;
			}
			$slidePlacement = $arPlacementXmlIdByEnumId[$arSlideFields["PROPERTY_PLACEMENT_ENUM_ID"]] ?? "main";
			if (!isset($arHomeBannerSlides[$slidePlacement])) {
				$slidePlacement = "main";
			}
			$arHomeBannerSlides[$slidePlacement][] = [
				"IMAGE" => CFile::GetPath($arSlideFields["DETAIL_PICTURE"]),
				"TITLE" => $arSlideFields["NAME"],
				"SUBTITLE" => $arSlideFields["PROPERTY_SUBTITLE_VALUE"] ?? "",
				"LINK" => $arSlideFields["PROPERTY_LINK_VALUE"] ?: "/catalog/",
				"CTA_TEXT" => $arSlideFields["PROPERTY_CTA_TEXT_VALUE"] ?: "Подробнее →",
			];
		}

		function renderHomeBannerCarousel($arSlides, $htmlId, $cssClass) {
			if (count($arSlides) < 1) {
				return;
			}
			?>
			<div class="<?= $cssClass ?>" id="<?= htmlspecialcharsbx($htmlId) ?>">
				<div class="hbc-track">
					<?foreach ($arSlides as $arSlide):?>
					<a href="<?= htmlspecialcharsbx($arSlide["LINK"]) ?>" class="hbc-slide" style="background-image:url(<?= htmlspecialcharsbx($arSlide["IMAGE"]) ?>)">
						<div class="hbc-slide-overlay"></div>
						<div class="hbc-slide-content">
							<div class="hbc-title"><?= htmlspecialcharsbx($arSlide["TITLE"]) ?></div>
							<?if ($arSlide["SUBTITLE"]):?><div class="hbc-subtitle"><?= htmlspecialcharsbx($arSlide["SUBTITLE"]) ?></div><?endif;?>
							<span class="hbc-cta"><?= htmlspecialcharsbx($arSlide["CTA_TEXT"]) ?></span>
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
	<div style="padding:28px 56px 8px">
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

	<!-- Каталог по категориям -->
	<div style="padding:28px 56px 8px">
		<div class="section-heading">
			<h2>Каталог по категориям</h2>
			<a href="/catalog/">Все 12 категорий →</a>
		</div>
		<div style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1.2fr;grid-auto-rows:168px;gap:12px">

			<!-- Большая плитка: Межкомнатные -->
			<a href="/catalog/" style="grid-column:1;grid-row:1/span 2;position:relative;border-radius:14px;overflow:hidden;cursor:pointer;display:block">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/cat-mezh.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Межкомнатные">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 42%,rgba(20,17,12,.72) 100%)"></div>
				<div style="position:absolute;left:20px;right:20px;bottom:18px;display:flex;align-items:flex-end;justify-content:space-between">
					<div><div style="font:800 22px 'Manrope';color:#fff;line-height:1.05">Межкомнатные</div><div style="font:600 12.5px 'Manrope';color:rgba(255,255,255,.72);margin-top:4px">2 400 моделей</div></div>
					<span style="width:38px;height:38px;border-radius:50%;background:#e8820a;color:#fff;font-size:17px;display:flex;align-items:center;justify-content:center;flex:none">→</span>
				</div>
			</a>

			<!-- Пара: Скрытые / Раздвижные -->
			<a href="/catalog/" style="grid-column:2;grid-row:1;position:relative;border-radius:14px;overflow:hidden;display:block">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/cat-skryt.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Скрытые">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(20,17,12,.7) 100%)"></div>
				<div style="position:absolute;left:15px;bottom:13px"><div style="font:700 15.5px 'Manrope';color:#fff">Скрытые</div><div style="font:600 11.5px 'Manrope';color:rgba(255,255,255,.72);margin-top:2px">180</div></div>
			</a>
			<a href="/catalog/" style="grid-column:2;grid-row:2;position:relative;border-radius:14px;overflow:hidden;display:block">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/cat-razdv.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Раздвижные">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(20,17,12,.7) 100%)"></div>
				<div style="position:absolute;left:15px;bottom:13px"><div style="font:700 15.5px 'Manrope';color:#fff">Раздвижные</div><div style="font:600 11.5px 'Manrope';color:rgba(255,255,255,.72);margin-top:2px">90</div></div>
			</a>

			<!-- Пара: Входные / Арки -->
			<a href="/catalog/" style="grid-column:3;grid-row:1;position:relative;border-radius:14px;overflow:hidden;display:block">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/cat-vhod.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Входные">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(20,17,12,.7) 100%)"></div>
				<div style="position:absolute;left:15px;bottom:13px"><div style="font:700 15.5px 'Manrope';color:#fff">Входные</div><div style="font:600 11.5px 'Manrope';color:rgba(255,255,255,.72);margin-top:2px">320</div></div>
			</a>
			<a href="/catalog/" style="grid-column:3;grid-row:2;position:relative;border-radius:14px;overflow:hidden;display:block">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/cat-arki.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Арки и порталы">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 45%,rgba(20,17,12,.7) 100%)"></div>
				<div style="position:absolute;left:15px;bottom:13px"><div style="font:700 15.5px 'Manrope';color:#fff">Арки и порталы</div><div style="font:600 11.5px 'Manrope';color:rgba(255,255,255,.72);margin-top:2px">60</div></div>
			</a>

			<!-- Высокая: Фурнитура -->
			<a href="/catalog/" style="grid-column:4;grid-row:1/span 2;position:relative;border-radius:14px;overflow:hidden;display:block">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/cat-furn.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Фурнитура">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 48%,rgba(20,17,12,.72) 100%)"></div>
				<div style="position:absolute;left:18px;right:18px;bottom:16px;display:flex;align-items:flex-end;justify-content:space-between">
					<div><div style="font:800 18px 'Manrope';color:#fff">Фурнитура</div><div style="font:600 12px 'Manrope';color:rgba(255,255,255,.72);margin-top:3px">540 позиций</div></div>
					<span style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.92);color:#1b1a17;font-size:15px;display:flex;align-items:center;justify-content:center;flex:none">→</span>
				</div>
			</a>
		</div>
	</div>

	<!-- Коллекции фабрики -->
	<div style="padding:26px 56px 4px">
		<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:6px">
			<h2 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Коллекции фабрики</h2>
			<a href="/collection/" style="font:600 14px;color:#e8820a">Все коллекции →</a>
		</div>
		<div style="font:500 13.5px;color:#8a857b;margin-bottom:18px">Серии дверей с единым дизайном — от полотна до фурнитуры</div>
		<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">

			<a href="/catalog/collections/dorsum/" style="position:relative;border-radius:16px;overflow:hidden;cursor:pointer;height:226px;display:block;text-decoration:none">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-1.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Dorsum">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 38%,rgba(20,17,12,.8) 100%)"></div>
				<div style="position:absolute;left:20px;right:20px;bottom:18px"><div style="font:800 22px 'Manrope';color:#fff;letter-spacing:.01em">Dorsum</div><div style="font:600 12.5px 'Manrope';color:rgba(255,255,255,.78);margin-top:4px">Модерн · экошпон · 14 моделей</div></div>
			</a>

			<a href="/catalog/collections/vilis/" style="position:relative;border-radius:16px;overflow:hidden;cursor:pointer;height:226px;display:block;text-decoration:none">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-2.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Vilis">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 38%,rgba(20,17,12,.8) 100%)"></div>
				<div style="position:absolute;left:20px;right:20px;bottom:18px"><div style="font:800 22px 'Manrope';color:#fff;letter-spacing:.01em">Vilis</div><div style="font:600 12.5px 'Manrope';color:rgba(255,255,255,.78);margin-top:4px">Скандинавский · экошпон · 22 модели</div></div>
			</a>

			<a href="/catalog/collections/actus/" style="position:relative;border-radius:16px;overflow:hidden;cursor:pointer;height:226px;display:block;text-decoration:none">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-5.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Actus">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 38%,rgba(20,17,12,.8) 100%)"></div>
				<div style="position:absolute;left:20px;right:20px;bottom:18px"><div style="font:800 22px 'Manrope';color:#fff;letter-spacing:.01em">Actus</div><div style="font:600 12.5px 'Manrope';color:rgba(255,255,255,.78);margin-top:4px">Классика · эмаль · 9 моделей</div></div>
			</a>

			<a href="/catalog/collections/vitrum/" style="position:relative;border-radius:16px;overflow:hidden;cursor:pointer;height:226px;display:block;text-decoration:none">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-6.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Vitrum">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 38%,rgba(20,17,12,.8) 100%)"></div>
				<div style="position:absolute;left:20px;right:20px;bottom:18px"><div style="font:800 22px 'Manrope';color:#fff;letter-spacing:.01em">Vitrum</div><div style="font:600 12.5px 'Manrope';color:rgba(255,255,255,.78);margin-top:4px">Хай-тек · эмалит · 11 моделей</div></div>
			</a>

			<a href="/catalog/collections/tabula/" style="position:relative;border-radius:16px;overflow:hidden;cursor:pointer;height:226px;display:block;text-decoration:none">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-7.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Tabula">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 38%,rgba(20,17,12,.8) 100%)"></div>
				<div style="position:absolute;left:20px;right:20px;bottom:18px"><div style="font:800 22px 'Manrope';color:#fff;letter-spacing:.01em">Tabula</div><div style="font:600 12.5px 'Manrope';color:rgba(255,255,255,.78);margin-top:4px">Лофт · стекло · 7 моделей</div></div>
			</a>

			<a href="/catalog/collections/lacuna/" style="position:relative;border-radius:16px;overflow:hidden;cursor:pointer;height:226px;display:block;text-decoration:none">
				<img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-8.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Lacuna">
				<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 38%,rgba(20,17,12,.8) 100%)"></div>
				<div style="position:absolute;left:20px;right:20px;bottom:18px"><div style="font:800 22px 'Manrope';color:#fff;letter-spacing:.01em">Lacuna</div><div style="font:600 12.5px 'Manrope';color:rgba(255,255,255,.78);margin-top:4px">Натуральный шпон · 6 моделей</div></div>
			</a>

		</div>
	</div>

	<!-- Популярные запросы -->
	<div style="padding:26px 56px 4px">
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

	<!-- Хиты продаж: реальные данные IBLOCK 19, тег OFFERS = "хит продаж" (Этап 3, Фаза B) -->
	<div style="padding:26px 56px 16px">
		<div class="section-heading">
			<h2>Хиты продаж</h2>
			<a href="/catalog/">Весь каталог →</a>
		</div>
		<?
			// Те же значения свойства OFFERS, что и в боевом dresscode:offers.product на главной.
			global $arrEportaHitsFilter;
			$arrEportaHitsFilter = [
				"IBLOCK_ID" => 19,
				"ACTIVE" => "Y",
				"PROPERTY_OFFERS" => [294, 296, 297],
			];
			$APPLICATION->IncludeComponent(
				"bitrix:catalog.section",
				".default",
				[
					"IBLOCK_TYPE" => "catalog",
					"IBLOCK_ID" => "19",
					"SECTION_ID" => false,
					"SECTION_CODE" => "",
					"SECTION_USER_FIELDS" => [],
					"ELEMENT_SORT_FIELD" => "sort",
					"ELEMENT_SORT_ORDER" => "asc",
					"ELEMENT_SORT_FIELD2" => "id",
					"ELEMENT_SORT_ORDER2" => "desc",
					"FILTER_NAME" => "arrEportaHitsFilter",
					"HIDE_NOT_AVAILABLE" => "N",
					"HIDE_NOT_AVAILABLE_OFFERS" => "N",
					"PAGE_ELEMENT_COUNT" => "8",
					"LINE_ELEMENT_COUNT" => "4",
					"PROPERTY_CODE" => ["STYLE", "COATING_COLOR", "GLAZING", "MAIN_COLOR", "PRODUCT_DAY", "RATING", "VOTE_COUNT", "CML2_ARTICLE"],
					"OFFERS_FIELD_CODE" => [],
					"OFFERS_PROPERTY_CODE" => [],
					"BACKGROUND_IMAGE" => "-",
					"LABEL_PROP" => "-",
					"PRODUCT_SUBSCRIPTION" => "N",
					"SHOW_DISCOUNT_PERCENT" => "Y",
					"SHOW_OLD_PRICE" => "Y",
					"PRICE_CODE" => ["BASE"],
					"USE_PRICE_COUNT" => "N",
					"SHOW_PRICE_COUNT" => "1",
					"PRICE_VAT_INCLUDE" => "Y",
					"CONVERT_CURRENCY" => "N",
					"BASKET_URL" => "/personal/cart/",
					"ACTION_VARIABLE" => "action",
					"PRODUCT_ID_VARIABLE" => "id",
					"PRODUCT_QUANTITY_VARIABLE" => "quantity",
					"ADD_PROPERTIES_TO_BASKET" => "Y",
					"PRODUCT_PROPS_VARIABLE" => "prop",
					"PARTIAL_PRODUCT_PROPERTIES" => "N",
					"USE_PRODUCT_QUANTITY" => "N",
					"CACHE_TYPE" => "A",
					"CACHE_TIME" => "3600",
					"CACHE_GROUPS" => "N",
					"CACHE_FILTER" => "N",
					"DISPLAY_COMPARE" => "N",
					"SET_TITLE" => "N",
					"SET_STATUS_404" => "N",
					"SEF_MODE" => "N",
					"PAGER_TEMPLATE" => "round",
					"DISPLAY_TOP_PAGER" => "N",
					"DISPLAY_BOTTOM_PAGER" => "N",
					"PAGER_TITLE" => "Товары",
					"PAGER_SHOW_ALWAYS" => "N",
					"PAGER_SHOW_ALL" => "N",
					"ADD_SECTIONS_CHAIN" => "N",
					"COMPATIBLE_MODE" => "Y",
					"AJAX_MODE" => "N",
					"TEMPLATE_THEME" => "site",
				],
				false
			);
		?>
	</div>

	<!-- Соцдоказательство -->
	<div class="social-proof">
		<span class="stars">★★★★★ 4.9</span>
		<span class="text">320 отзывов · 40 000+ дверей установлено · 12 лет фабрике</span>
	</div>

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