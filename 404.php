<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/urlrewrite.php');

CHTTP::SetStatus("404 Not Found");
@define("ERROR_404","Y");
const HIDE_SIDEBAR = true;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

/** @global CMain $APPLICATION */

$APPLICATION->SetTitle("Страница не найдена");

// Собственное оформление 404 под дизайн-систему eporta (акцент/шрифт из template_styles.css,
// уже подключённого в header.php шаблона) — раньше здесь всегда была битриксовая заглушка
// с деревом каталога и картой сайта, не совпадающая по стилю с остальным сайтом (найдено
// 20.09.2026 вместе с фиксом soft-404 в catalog/index.php). Старое оформление оставлено как
// fallback для любого другого активного шаблона (переключение — b_site_template/cookie
// dev_preview, см. project_template_switch_mechanism), чтобы не сломать его страницы.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>

<?if ($isEportaTemplate):?>
	<style>
		.eporta-404 {
			max-width: 640px;
			margin: 0 auto;
			padding: clamp(48px, 8vw, 96px) var(--pad-x) clamp(56px, 10vw, 120px);
			text-align: center;
		}
		.eporta-404 .code {
			font: 800 clamp(72px, 14vw, 128px)/1 var(--font);
			color: var(--accent);
			letter-spacing: -0.02em;
		}
		.eporta-404 h1 {
			margin: 8px 0 12px;
			font: 800 clamp(22px, 3vw, 30px)/1.25 var(--font);
			color: var(--dark);
		}
		.eporta-404 p {
			margin: 0 0 32px;
			font: 400 15px/1.6 var(--font);
			color: var(--text-mid);
		}
		.eporta-404 form.header-search {
			max-width: 420px;
			margin: 0 auto 40px;
		}
		.eporta-404 .actions {
			display: flex;
			flex-wrap: wrap;
			justify-content: center;
			gap: 12px;
			margin-bottom: 48px;
		}
		.eporta-404 .actions a {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 13px 28px;
			border-radius: 12px;
			font: 700 14px/1 var(--font);
			transition: background .15s, color .15s, border-color .15s;
		}
		.eporta-404 .actions a.primary {
			background: var(--accent);
			color: #fff;
		}
		.eporta-404 .actions a.primary:hover { background: var(--accent-dark); }
		.eporta-404 .actions a.secondary {
			background: #fff;
			color: var(--dark);
			border: 1px solid var(--border);
		}
		.eporta-404 .actions a.secondary:hover { border-color: var(--accent); color: var(--accent-dark); }
		.eporta-404 .quick-links {
			display: flex;
			flex-wrap: wrap;
			justify-content: center;
			gap: 8px 20px;
			padding-top: 32px;
			border-top: 1px solid var(--border);
		}
		.eporta-404 .quick-links a {
			font: 600 13px/1 var(--font);
			color: var(--text-mid);
		}
		.eporta-404 .quick-links a:hover { color: var(--accent-dark); }
	</style>

	<div class="eporta-404">
		<div class="code">404</div>
		<h1>Такой страницы не существует</h1>
		<p>Возможно, товар сняли с продажи или адрес был набран с ошибкой. Попробуйте поиск или перейдите в каталог.</p>

		<form class="header-search" action="/search/" method="get" autocomplete="off">
			<span class="ico">⌕</span>
			<input type="text" name="q" placeholder="Поиск двери по названию или артикулу">
		</form>

		<div class="actions">
			<a class="primary" href="/catalog/">Перейти в каталог</a>
			<a class="secondary" href="/">На главную</a>
		</div>

		<nav class="quick-links">
			<a href="/catalog/?category=mkd">Межкомнатные</a>
			<a href="/catalog/?category=sliding">Раздвижные</a>
			<a href="/catalog/?category=entrance">Входные</a>
			<a href="/collection/">Коллекции</a>
			<a href="/about/contacts/">Контакты</a>
		</nav>
	</div>
<?else:?>
	<div class="bx-404-container">
		<div class="bx-404-block"><img src="<?=SITE_DIR?>images/404.png" alt=""></div>
		<div class="bx-404-text-block">Неправильно набран адрес, <br>или такой страницы на сайте больше не существует.</div>
		<div class="">Вернитесь на <a href="<?=SITE_DIR?>">главную</a> или воспользуйтесь картой сайта.</div>
	</div>
	<div class="map-columns row">
		<div class="col-sm-10 col-sm-offset-1">
			<div class="bx-maps-title">Карта сайта:</div>
		</div>
	</div>

	<div class="col-sm-offset-2 col-sm-4">
		<div class="bx-map-title"><i class="fa fa-leanpub"></i> Каталог</div>
		<?php
		$APPLICATION->IncludeComponent(
			"bitrix:catalog.section.list",
			"tree",
			array(
				"COMPONENT_TEMPLATE" => "tree",
				"IBLOCK_TYPE" => "catalog",
				"IBLOCK_ID" => "2",
				"SECTION_ID" => $_REQUEST["SECTION_ID"],
				"SECTION_CODE" => "",
				"COUNT_ELEMENTS" => "Y",
				"TOP_DEPTH" => "2",
				"SECTION_FIELDS" => array(
					0 => "",
					1 => "",
				),
				"SECTION_USER_FIELDS" => array(
					0 => "",
					1 => "",
				),
				"SECTION_URL" => "",
				"CACHE_TYPE" => "A",
				"CACHE_TIME" => "36000000",
				"CACHE_GROUPS" => "Y",
				"ADD_SECTIONS_CHAIN" => "Y"
			),
			false
		);
		?>
	</div>

	<div class="col-sm-offset-1 col-sm-4">
		<div class="bx-map-title"><i class="fa fa-info-circle"></i> О магазине</div>
		<?php
		$APPLICATION->IncludeComponent(
			"bitrix:main.map",
			".default",
			array(
				"CACHE_TYPE" => "A",
				"CACHE_TIME" => "36000000",
				"SET_TITLE" => "N",
				"LEVEL" => "3",
				"COL_NUM" => "2",
				"SHOW_DESCRIPTION" => "Y",
				"COMPONENT_TEMPLATE" => ".default"
			),
			false
		);?>
	</div>
<?endif;?>
<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
