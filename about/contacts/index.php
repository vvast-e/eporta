<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Контакты");
?>
<?
	// Реальные контакты Eporta (были демо-данные вендора: Москва, ул. 2-я Хуторская, д. 38,
	// EMAIL_TO формы — sale@nyuta.bx). Адрес склада — новый (переезд из Мытищи, Олимпийский
	// проспект, 33). Адрес в settings.php ($TEMPLATE_ADDRESS, Мытищи, Коргашино, Пироговское ш.,
	// 2Т) — отдельный действующий адрес, не трогаем.
	$eportaContactsAddress = "г. Щёлково, ул. Хотовская, стр. 43, Индустриальный парк Щёлково";
	$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>
<div class="info-page" itemscope itemtype="http://schema.org/Organization">
	<div class="info-tabs">
		<?php foreach (eportaBuyerInfoPages() as $eportaBuyerPage): ?>
			<a href="<?= htmlspecialcharsbx($eportaBuyerPage["href"]) ?>"><?= htmlspecialcharsbx($eportaBuyerPage["label"]) ?></a>
		<?php endforeach; ?>
		<a href="/about/contacts/" class="active">Контакты</a>
	</div>

	<h1>Контакты</h1>

	<div class="info-callout" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
		<h4>Магазин и склад <span itemprop="name">EPORTA</span></h4>
		<p itemprop="streetAddress"><?=htmlspecialcharsbx($eportaContactsAddress)?><br>Будние дни: 09:00 — 18:00</p>
	</div>

	<h2>Розничный отдел</h2>
	<p>
		Телефон: <a href="tel:+74951201138" class="info-link" itemprop="telephone">+7 (495) 120-11-38</a><br>
		Мобильный: <a href="tel:+79037249335" class="info-link">+7 (903) 724-93-35</a><br>
		Эл. почта: <a href="mailto:info@eporta.ru" class="info-link" itemprop="email">info@eporta.ru</a>
	</p>

	<h2>Оптовый отдел</h2>
	<p>
		Телефон: <a href="tel:+74951208829" class="info-link">+7 (495) 120-88-29</a><br>
		Эл. почта: <a href="mailto:sale@eporta.ru" class="info-link">sale@eporta.ru</a>
	</p>

	<div style="border-radius:14px;overflow:hidden;border:1.5px solid var(--border-soft, #e7e3db);margin-top:16px">
		<iframe
			src="https://yandex.ru/map-widget/v1/?text=<?=urlencode($eportaContactsAddress)?>"
			width="100%" height="400" frameborder="0" loading="lazy" title="Карта проезда"></iframe>
	</div>
</div>
<?else:?>
	<p>
		<b>Магазин и склад EPORTA</b><br>
		<?=htmlspecialcharsbx($eportaContactsAddress)?><br>
		Будние дни: 09:00 — 18:00
	</p>
	<h2>Розничный отдел</h2>
	<p>
		Телефон: <b>+7 (495) 120-11-38</b><br>
		Мобильный: +7 (903) 724-93-35<br>
		Электропочта: <a href="mailto:info@eporta.ru">info@eporta.ru</a>
	</p>
	<h2>Оптовый отдел</h2>
	<p>
		Телефон: <b>+7 (495) 120-88-29</b><br>
		Электропочта: <a href="mailto:sale@eporta.ru">sale@eporta.ru</a>
	</p>
	<div class="mb-2 embed-responsive embed-responsive-16by9">
		<iframe class="embed-responsive-item" width="100%" height="400" frameborder="0" loading="lazy"
			src="https://yandex.ru/map-widget/v1/?text=<?=urlencode($eportaContactsAddress)?>"></iframe>
	</div>
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php")?>
