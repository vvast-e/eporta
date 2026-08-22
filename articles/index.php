<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
	// Раздел "Статьи" — список + деталь в одном файле, парсинг REQUEST_URI вручную (тот же
	// паттерн самостоятельного роутинга, что и в catalog/index.php для коллекций/детали товара):
	// /articles/ — список, /articles/<code>.html — статья. Требует urlrewrite-правило
	// #^/articles/# -> /articles/index.php (см. urlrewrite.php), иначе Bitrix не резолвит
	// произвольный *.html под этой директорией.
	$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
	if ($isEportaTemplate) {
		\Bitrix\Main\Loader::includeModule("iblock");
		require_once($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/include/eporta_articles_common.php");

		$eportaReqPath = parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);
		$eportaIsDetail = preg_match('~/([^/]+)\.html~', $eportaReqPath, $eportaUrlMatch);
	}
?>
<?if ($isEportaTemplate && $eportaIsDetail):
	// ---- Деталь статьи ----
	$eportaArticleCode = $eportaUrlMatch[1];
	$eportaArticle = \CIBlockElement::GetList(
		[], ["IBLOCK_ID" => EPORTA_ARTICLES_IBLOCK_ID, "CODE" => $eportaArticleCode, "ACTIVE" => "Y"], false, false,
		["ID", "NAME", "DETAIL_TEXT", "DETAIL_TEXT_TYPE", "DETAIL_PICTURE", "DATE_ACTIVE_FROM"]
	)->Fetch();
	if (!$eportaArticle):
		\CHTTP::SetStatus("404 Not Found");
		$APPLICATION->SetTitle("Статья не найдена");
	?>
	<div style="padding:60px var(--pad-x);text-align:center">
		<h1 style="font:800 24px 'Manrope'">Статья не найдена</h1>
		<p style="color:#8a857b;margin:10px 0 20px">Возможно, она была удалена или адрес указан неверно.</p>
		<a href="/articles/" style="color:#e8820a;font-weight:700;text-decoration:none">← Ко всем статьям</a>
	</div>
	<?
	else:
		$APPLICATION->SetPageProperty("title", $eportaArticle["NAME"]);
		$APPLICATION->SetTitle($eportaArticle["NAME"]);
		$eportaArticleHasPhoto = !empty($eportaArticle["DETAIL_PICTURE"]);
		$eportaArticlePhotoSrc = $eportaArticleHasPhoto ? \CFile::GetPath($eportaArticle["DETAIL_PICTURE"]) : "";
	?>
	<div style="padding:12px var(--pad-x) 0"><div style="font:500 13px;color:#726c62">Главная · <a href="/articles/" style="color:inherit">Статьи</a> · <?=htmlspecialcharsbx($eportaArticle["NAME"])?></div></div>

	<div style="max-width:760px;margin:0 auto;padding:20px var(--pad-x) 60px">
		<?if ($eportaArticleHasPhoto):?>
		<?php eportaPicture($eportaArticlePhotoSrc, $eportaArticle["NAME"], [
			"style" => "width:100%;max-height:420px;object-fit:cover;border-radius:16px;margin-bottom:24px",
			"loading" => "eager",
		]); ?>
		<?endif;?>
		<h1 style="margin:0 0 20px;font:800 30px 'Manrope';letter-spacing:-0.01em"><?=htmlspecialcharsbx($eportaArticle["NAME"])?></h1>
		<div style="font:400 15px/1.7 'Manrope';color:#3a3631"><?php
			// DETAIL_TEXT сохранён как HTML из встроенного WYSIWYG-редактора админки статей
			// (см. local/admin_tools/eporta_articles) — доверенный источник, пишет только
			// авторизованный админ через собственный интерфейс, поэтому выводится как есть.
			echo $eportaArticle["DETAIL_TEXT"] !== "" ? $eportaArticle["DETAIL_TEXT"] : "<p>Текст статьи пока не заполнен.</p>";
		?></div>
		<div style="margin-top:36px"><a href="/articles/" style="color:#e8820a;font-weight:700;text-decoration:none">← Ко всем статьям</a></div>
	</div>
<?endif; ?>

<?elseif ($isEportaTemplate):
	// ---- Список статей ----
	$APPLICATION->SetPageProperty("title", "Статьи");
	$APPLICATION->SetTitle("Статьи");

	$eportaArticlesRes = \CIBlockElement::GetList(
		["SORT" => "ASC", "ACTIVE_FROM" => "DESC", "ID" => "DESC"],
		["IBLOCK_ID" => EPORTA_ARTICLES_IBLOCK_ID, "ACTIVE" => "Y"], false, false,
		["ID", "NAME", "CODE", "PREVIEW_TEXT", "PREVIEW_PICTURE"]
	);
	$eportaArticlesList = [];
	while ($eportaArticleRow = $eportaArticlesRes->Fetch()) {
		$eportaArticlesList[] = $eportaArticleRow;
	}
?>
	<div style="padding:12px var(--pad-x) 0"><div style="font:500 13px;color:#726c62">Главная · Статьи</div></div>

	<div style="padding:14px var(--pad-x) 6px">
		<h1 style="margin:0;font:800 28px 'Manrope';letter-spacing:-0.01em">Статьи</h1>
		<p style="margin:6px 0 0;font:500 14px/1.5 'Manrope';color:#8a857b;max-width:640px">Материалы о дверях фабрики EPORTA: выбор материала, ухода и подбора под интерьер.</p>
	</div>

	<?if ($eportaArticlesList):?>
	<div class="eporta-tile-grid" style="padding:18px var(--pad-x) 40px;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
		<?foreach ($eportaArticlesList as $eportaArticleItem):
			$eportaHasPhoto = !empty($eportaArticleItem["PREVIEW_PICTURE"]);
			$eportaPhotoSrc = $eportaHasPhoto ? \CFile::GetPath($eportaArticleItem["PREVIEW_PICTURE"]) : "";
			$eportaArticleUrl = "/articles/".$eportaArticleItem["CODE"].".html";
		?>
		<a href="<?=htmlspecialcharsbx($eportaArticleUrl)?>" style="display:block;border-radius:16px;overflow:hidden;background:#fff;border:1px solid #efece6;text-decoration:none;color:inherit">
			<?if ($eportaHasPhoto):?>
			<?php eportaPicture($eportaPhotoSrc, $eportaArticleItem["NAME"], [
				"style" => "width:100%;height:170px;object-fit:cover;display:block",
				"loading" => "lazy",
			]); ?>
			<?else:?>
			<div class="img-noimg" style="height:170px">Нет фото</div>
			<?endif;?>
			<div style="padding:16px 18px">
				<div style="font:800 16px 'Manrope';letter-spacing:-0.01em;margin-bottom:6px"><?=htmlspecialcharsbx($eportaArticleItem["NAME"])?></div>
				<?if ($eportaArticleItem["PREVIEW_TEXT"]):?>
				<div style="font:500 13px/1.5 'Manrope';color:#8a857b"><?=htmlspecialcharsbx(mb_substr(strip_tags($eportaArticleItem["PREVIEW_TEXT"]), 0, 140))?><?=mb_strlen($eportaArticleItem["PREVIEW_TEXT"]) > 140 ? "…" : ""?></div>
				<?endif;?>
			</div>
		</a>
		<?endforeach;?>
	</div>
	<?else:?>
	<div style="padding:40px var(--pad-x) 60px;color:#8a857b">Пока нет опубликованных статей.</div>
	<?endif;?>

<?else:?>
	<div style="padding:40px">Раздел недоступен.</div>
<?endif;?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
