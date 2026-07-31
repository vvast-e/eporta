<?
/**
 * EPORTA: единая карта категорий каталога (свойство PROPERTY_CATEGORY, IBLOCK 19).
 *
 * Свойство CATEGORY — строковое (не enum), заполняется при импорте из 1С. Сейчас в проде
 * встречается только "Межкомнатные двери", но 1С со временем будет присылать сокращения
 * (например "МКД") и другие категории — поэтому у каждого ключа список ALIASES: все точные
 * строковые значения PROPERTY_CATEGORY, которые считаются этой категорией. Новые алиасы
 * дописывать сюда по мере появления в выгрузке, дублирования логики по файлам не создавать —
 * этот файл подключают главная (index.php), header.php (мега-меню) и catalog/index.php (фильтр).
 */

if (!defined("EPORTA_CATEGORY_IBLOCK_ID")) {
	define("EPORTA_CATEGORY_IBLOCK_ID", 19);
}

/**
 * key => [
 *   LABEL   — подпись в UI,
 *   ALIASES — точные значения PROPERTY_CATEGORY, которые попадают в эту категорию,
 *   IMG     — код картинки-заглушки для плитки на главной (assets/img/cat-<IMG>.jpg),
 * ]
 */
function eportaGetCategoryMap(): array
{
	static $map = null;
	if ($map !== null) return $map;

	$map = [
		"mkd" => [
			"LABEL" => "Межкомнатные",
			"ALIASES" => ["Межкомнатные двери", "МКД"],
			"IMG" => "mkd",
		],
		"hidden" => [
			"LABEL" => "Скрытые",
			"ALIASES" => ["Скрытые двери", "СД"],
			"IMG" => "hidden",
		],
		"sliding" => [
			"LABEL" => "Раздвижные",
			"ALIASES" => ["Раздвижные двери", "Раздвижные перегородки", "РД"],
			"IMG" => "sliding",
		],
		"entrance" => [
			"LABEL" => "Входные",
			"ALIASES" => ["Входные двери", "ВД"],
			"IMG" => "entrance",
		],
		"arch" => [
			"LABEL" => "Арки и порталы",
			"ALIASES" => ["Арки и порталы", "Арки", "АП"],
			"IMG" => "arch",
		],
		"hardware" => [
			"LABEL" => "Фурнитура",
			"ALIASES" => ["Фурнитура", "ФУР"],
			"IMG" => "hardware",
		],
	];

	return $map;
}

/**
 * Реальное число активных товаров в категории (по IBLOCK_ID=19, PROPERTY_CATEGORY IN ALIASES).
 */
function eportaCategoryCount(string $key): int
{
	if (!\Bitrix\Main\Loader::includeModule("iblock")) return 0;

	$map = eportaGetCategoryMap();
	if (empty($map[$key]["ALIASES"])) return 0;

	return (int)\CIBlockElement::GetList(
		[],
		[
			"IBLOCK_ID" => EPORTA_CATEGORY_IBLOCK_ID,
			"ACTIVE" => "Y",
			"PROPERTY_CATEGORY" => $map[$key]["ALIASES"],
		],
		false,
		false,
		["ID"]
	)->SelectedRowsCount();
}
