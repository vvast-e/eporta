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

	<!-- Хиты продаж -->
	<div style="padding:26px 56px 16px">
		<div class="section-heading">
			<h2>Хиты продаж</h2>
			<a href="/catalog/">Весь каталог →</a>
		</div>
		<div class="products-grid" style="grid-template-columns:repeat(4,1fr)">

			<a href="/catalog/" class="product-card">
				<div class="img-wrap"><img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-1.jpg" alt="Дверь Dorsum 11.1"><span class="badge hit">ХИТ</span></div>
				<div class="info">
					<div class="stars">★★★★★ <span>42</span></div>
					<div class="name">Дверь Dorsum 11.1 экошпон</div>
					<div class="price-row"><div class="price">7 880 ₽</div><button class="btn-cart" onclick="event.preventDefault()">В корзину</button></div>
				</div>
			</a>

			<a href="/catalog/" class="product-card">
				<div class="img-wrap"><img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-2.jpg" alt="Дверь Vilis 21"></div>
				<div class="info">
					<div class="stars">★★★★★ <span>31</span></div>
					<div class="name">Дверь Vilis 21 экошпон, стекло</div>
					<div class="price-row"><div class="price">4 500 ₽</div><button class="btn-cart" onclick="event.preventDefault()">В корзину</button></div>
				</div>
			</a>

			<a href="/catalog/" class="product-card">
				<div class="img-wrap"><img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-3.jpg" alt="Дверь Vilis 18"></div>
				<div class="info">
					<div class="stars">★★★★★ <span>28</span></div>
					<div class="name">Дверь Vilis 18 экошпон беленая</div>
					<div class="price-row"><div class="price">4 770 ₽</div><button class="btn-cart" onclick="event.preventDefault()">В корзину</button></div>
				</div>
			</a>

			<a href="/catalog/" class="product-card">
				<div class="img-wrap"><img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-4.jpg" alt="Дверь Vilis 26"><span class="badge sale">−18%</span></div>
				<div class="info">
					<div class="stars">★★★★★ <span>37</span></div>
					<div class="name">Дверь Vilis 26 экошпон кремовая</div>
					<div class="price-row"><div><span class="price">5 400 ₽</span> <span class="price-old">6 580</span></div><button class="btn-cart" onclick="event.preventDefault()">В корзину</button></div>
				</div>
			</a>

			<a href="/catalog/" class="product-card">
				<div class="img-wrap"><img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-5.jpg" alt="Дверь Actus 6.1"></div>
				<div class="info">
					<div class="stars">★★★★★ <span>19</span></div>
					<div class="name">Дверь Actus 6.1 эмаль, триплекс</div>
					<div class="price-row"><div class="price">34 825 ₽</div><button class="btn-cart" onclick="event.preventDefault()">В корзину</button></div>
				</div>
			</a>

			<a href="/catalog/" class="product-card">
				<div class="img-wrap"><img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-6.jpg" alt="Дверь Dorsum 10.2"><span class="badge hit">ХИТ</span></div>
				<div class="info">
					<div class="stars">★★★★★ <span>24</span></div>
					<div class="name">Дверь Dorsum 10.2 эмалит серый</div>
					<div class="price-row"><div class="price">11 385 ₽</div><button class="btn-cart" onclick="event.preventDefault()">В корзину</button></div>
				</div>
			</a>

			<a href="/catalog/" class="product-card">
				<div class="img-wrap"><img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-7.jpg" alt="Дверь Vilis 34"></div>
				<div class="info">
					<div class="stars">★★★★★ <span>15</span></div>
					<div class="name">Дверь Vilis 34 экошпон, стекло</div>
					<div class="price-row"><div class="price">4 500 ₽</div><button class="btn-cart" onclick="event.preventDefault()">В корзину</button></div>
				</div>
			</a>

			<a href="/catalog/" class="product-card">
				<div class="img-wrap"><img src="<?= SITE_TEMPLATE_PATH ?>/assets/img/hit-8.jpg" alt="Премьер Люкс 229"><span class="badge sale">−12%</span></div>
				<div class="info">
					<div class="stars">★★★★★ <span>22</span></div>
					<div class="name">Премьер Люкс 229 шагрень, зеркало</div>
					<div class="price-row"><div class="price">38 600 ₽</div><button class="btn-cart" onclick="event.preventDefault()">В корзину</button></div>
				</div>
			</a>

		</div>
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
		"CACHE_TIME" => "3600000",
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
		"CACHE_TIME" => "360000",
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