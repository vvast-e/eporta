<?php
/**
 * EPORTA SEO: сопоставление значения фильтра каталога (цвет/стиль/покрытие) со слагом
 * готового текста в inc/seo/<slug>.php (и записью в _registry_draft.php — H1/TITLE/DESC).
 *
 * Сравнение — по НАЧАЛУ нормализованной строки значения свойства (см. eportaSeoNormalize),
 * а не по точному совпадению: реальные строки в MAIN_COLOR/STYLE/COATING (IBLOCK 19) могут
 * отличаться от кластера ключевых слов родом/числом прилагательного ("Серый" в базе —
 * "Серые" в кластере ключей), а под "экошпон" бы иначе ложно сработал и стем "шпон" —
 * префиксное сравнение "экошпон".startsWith("шпон") = false, страхует от этой коллизии.
 * Ключи — префиксы ПРОВЕРЯЮТСЯ В ПОРЯДКЕ ОБЪЯВЛЕНИЯ, первый подошедший побеждает.
 */

function eportaSeoNormalize(string $value): string
{
	return mb_strtolower(str_replace("ё", "е", trim($value)), "UTF-8");
}

/**
 * @return array<string, array<string, string>> [ "color"|"style"|"coating" => [ префикс => slug ] ]
 */
function eportaSeoFilterMap(): array
{
	static $map = null;
	if ($map !== null) return $map;

	$map = [
		"color" => [
			"бел"    => "white-doors",
			"сер"    => "gray-doors",
			"черн"   => "black-doors",
			"темн"   => "dark-doors",
			"светл"  => "light-doors",
			"орех"   => "nut-doors",
		],
		"style" => [
			"классик"     => "classic-doors",
			"неоклассик"  => "internal-doors-neoclassic",
			"лофт"        => "loft-doors",
			"прованс"     => "provence-doors",
			"скандинав"   => "scandinavian-doors",
			"современ"    => "modern-doors",
			"модерн"      => "doors-modern",
			"дизайнерск"  => "internal-designer-doors",
		],
		"coating" => [
			"эмал"     => "enameled-internal-doors",
			"экошпон"  => "internal-doors-eco",
			"шпон"     => "veneer-doors",
		],
	];

	return $map;
}

/**
 * @param string $groupKey "color"|"style"|"coating"
 * @param string $enumValue Реальное значение свойства из базы (VALUE энума).
 * @return string|null Слаг из inc/seo/, либо null, если совпадений нет.
 */
function eportaMatchSeoSlug(string $groupKey, string $enumValue): ?string
{
	if ($enumValue === "") return null;
	$map = eportaSeoFilterMap();
	if (empty($map[$groupKey])) return null;

	$normalized = eportaSeoNormalize($enumValue);
	foreach ($map[$groupKey] as $prefix => $slug) {
		if (mb_strpos($normalized, $prefix, 0, "UTF-8") === 0) {
			return $slug;
		}
	}
	return null;
}
