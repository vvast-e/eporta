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
	<?require($_SERVER["DOCUMENT_ROOT"]."/local/templates/eporta/inc/404_body.php");?>
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
