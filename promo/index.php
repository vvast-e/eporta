<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
	// Раздел "Акции" — список + деталь в одном файле, полное зеркало articles/index.php под
	// отдельный инфоблок (см. eporta_promo_common.php). Тот же самописный роутинг по REQUEST_URI:
	// /promo/ — список, /promo/<code>.html — акция. Требует urlrewrite-правило #^/promo/#
	// -> /promo/index.php (см. urlrewrite.php), иначе Bitrix не резолвит произвольный *.html
	// под этой директорией.
	$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
	if ($isEportaTemplate) {
		\Bitrix\Main\Loader::includeModule("iblock");
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/eporta_promo_common.php");

		$eportaReqPath = parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);
		$eportaIsDetail = preg_match('~/([^/]+)\.html~', $eportaReqPath, $eportaUrlMatch);
	}
?>
<?if ($isEportaTemplate && $eportaIsDetail):
	// ---- Деталь акции ----
	$eportaPromoCode = $eportaUrlMatch[1];
	$eportaPromo = \CIBlockElement::GetList(
		[], ["IBLOCK_ID" => EPORTA_PROMO_IBLOCK_ID, "CODE" => $eportaPromoCode, "ACTIVE" => "Y"], false, false,
		["ID", "NAME", "DETAIL_TEXT", "DETAIL_TEXT_TYPE", "DETAIL_PICTURE", "DATE_ACTIVE_FROM"]
	)->Fetch();
	if (!$eportaPromo):
		\CHTTP::SetStatus("404 Not Found");
		$APPLICATION->SetTitle("Акция не найдена");
	?>
	<div style="padding:60px var(--pad-x);text-align:center">
		<h1 style="font:800 24px 'Manrope'">Акция не найдена</h1>
		<p style="color:#8a857b;margin:10px 0 20px">Возможно, она была удалена или адрес указан неверно.</p>
		<a href="/promo/" style="color:#e8820a;font-weight:700;text-decoration:none">← Ко всем акциям</a>
	</div>
	<?
	else:
		$APPLICATION->SetPageProperty("title", $eportaPromo["NAME"]);
		$APPLICATION->SetTitle($eportaPromo["NAME"]);
		$eportaPromoHasPhoto = !empty($eportaPromo["DETAIL_PICTURE"]);
		$eportaPromoPhotoSrc = $eportaPromoHasPhoto ? \CFile::GetPath($eportaPromo["DETAIL_PICTURE"]) : "";
	?>
	<div style="padding:12px var(--pad-x) 0"><div style="font:500 13px;color:#726c62">Главная · <a href="/promo/" style="color:inherit">Акции</a> · <?=htmlspecialcharsbx($eportaPromo["NAME"])?></div></div>

	<div style="max-width:760px;margin:0 auto;padding:20px var(--pad-x) 60px">
		<?if ($eportaPromoHasPhoto):?>
		<?php eportaPicture($eportaPromoPhotoSrc, $eportaPromo["NAME"], [
			"style" => "width:100%;max-height:420px;object-fit:cover;border-radius:16px;margin-bottom:24px",
			"loading" => "eager",
		]); ?>
		<?endif;?>
		<h1 style="margin:0 0 20px;font:800 30px 'Manrope';letter-spacing:-0.01em"><?=htmlspecialcharsbx($eportaPromo["NAME"])?></h1>
		<div class="article-content"><?php
			// DETAIL_TEXT сохранён как HTML из встроенного WYSIWYG-редактора админки акций
			// (см. local/admin_tools/eporta_promo) — доверенный источник, пишет только
			// авторизованный админ через собственный интерфейс, поэтому выводится как есть.
			// Класс .article-content переиспользован из раздела "Статьи" (template_styles.css) —
			// та же вёрстка абзацев/списков/выравнивания картинок, дублировать незачем.
			echo $eportaPromo["DETAIL_TEXT"] !== "" ? $eportaPromo["DETAIL_TEXT"] : "<p>Текст акции пока не заполнен.</p>";
		?></div>
		<div style="margin-top:36px"><a href="/promo/" style="color:#e8820a;font-weight:700;text-decoration:none">← Ко всем акциям</a></div>
	</div>
<?endif; ?>

<?elseif ($isEportaTemplate):
	// ---- Список акций ----
	$APPLICATION->SetPageProperty("title", "Акции");
	$APPLICATION->SetTitle("Акции");

	$eportaPromoRes = \CIBlockElement::GetList(
		["SORT" => "ASC", "ACTIVE_FROM" => "DESC", "ID" => "DESC"],
		["IBLOCK_ID" => EPORTA_PROMO_IBLOCK_ID, "ACTIVE" => "Y"], false, false,
		["ID", "NAME", "CODE", "PREVIEW_TEXT", "PREVIEW_PICTURE"]
	);
	$eportaPromoList = [];
	while ($eportaPromoRow = $eportaPromoRes->Fetch()) {
		$eportaPromoList[] = $eportaPromoRow;
	}
?>
	<div style="padding:12px var(--pad-x) 0"><div style="font:500 13px;color:#726c62">Главная · Акции</div></div>

	<div style="padding:14px var(--pad-x) 6px">
		<h1 style="margin:0;font:800 28px 'Manrope';letter-spacing:-0.01em">Акции</h1>
		<p style="margin:6px 0 0;font:500 14px/1.5 'Manrope';color:#8a857b;max-width:640px">Актуальные акции и специальные предложения фабрики EPORTA.</p>
	</div>

	<?if ($eportaPromoList):?>
	<div class="eporta-tile-grid" style="padding:18px var(--pad-x) 40px;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
		<?foreach ($eportaPromoList as $eportaPromoItem):
			$eportaHasPhoto = !empty($eportaPromoItem["PREVIEW_PICTURE"]);
			$eportaPhotoSrc = $eportaHasPhoto ? \CFile::GetPath($eportaPromoItem["PREVIEW_PICTURE"]) : "";
			$eportaPromoUrl = "/promo/".$eportaPromoItem["CODE"].".html";
		?>
		<a href="<?=htmlspecialcharsbx($eportaPromoUrl)?>" style="display:block;border-radius:16px;overflow:hidden;background:#fff;border:1px solid #efece6;text-decoration:none;color:inherit">
			<?if ($eportaHasPhoto):?>
			<?php eportaPicture($eportaPhotoSrc, $eportaPromoItem["NAME"], [
				"style" => "width:100%;height:170px;object-fit:cover;display:block",
				"loading" => "lazy",
			]); ?>
			<?else:?>
			<div class="img-noimg" style="height:170px">Нет фото</div>
			<?endif;?>
			<div style="padding:16px 18px">
				<div style="font:800 16px 'Manrope';letter-spacing:-0.01em;margin-bottom:6px"><?=htmlspecialcharsbx($eportaPromoItem["NAME"])?></div>
				<?if ($eportaPromoItem["PREVIEW_TEXT"]):?>
				<div style="font:500 13px/1.5 'Manrope';color:#8a857b"><?=htmlspecialcharsbx(mb_substr(strip_tags($eportaPromoItem["PREVIEW_TEXT"]), 0, 140))?><?=mb_strlen($eportaPromoItem["PREVIEW_TEXT"]) > 140 ? "…" : ""?></div>
				<?endif;?>
			</div>
		</a>
		<?endforeach;?>
	</div>
	<?else:?>
	<div style="padding:40px var(--pad-x) 60px;color:#8a857b">Пока нет активных акций.</div>
	<?endif;?>

<?else:?>
	<div style="padding:40px">Раздел недоступен.</div>
<?endif;?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
