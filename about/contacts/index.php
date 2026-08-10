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
<div class="lk-page-wrap">
	<div class="lk-card" itemscope itemtype="http://schema.org/Organization">
		<h1 style="margin:0 0 18px;font:800 27px 'Manrope';letter-spacing:-0.01em">Контакты</h1>

		<div style="margin-bottom:22px" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
			<div style="font:700 14px 'Manrope';color:#3a3631;margin-bottom:4px">Магазин и склад <span itemprop="name">EPORTA</span></div>
			<div style="font:600 15px 'Manrope';color:#3a3631" itemprop="streetAddress"><?=htmlspecialcharsbx($eportaContactsAddress)?></div>
			<div style="font:600 13px 'Manrope';color:#a39e95;margin-top:4px">Будние дни: 09:00 — 18:00</div>
		</div>

		<div style="display:flex;flex-wrap:wrap;gap:28px;margin-bottom:24px">
			<div>
				<div style="font:700 14px 'Manrope';color:#3a3631;margin-bottom:8px">Розничный отдел</div>
				<div style="font:600 14px 'Manrope';color:#3a3631">Телефон: <a href="tel:+74951201138" style="color:inherit;text-decoration:none" itemprop="telephone">+7 (495) 120-11-38</a></div>
				<div style="font:600 14px 'Manrope';color:#3a3631">Мобильный: <a href="tel:+79037249335" style="color:inherit;text-decoration:none">+7 (903) 724-93-35</a></div>
				<div style="font:600 14px 'Manrope';color:#3a3631">Эл. почта: <a href="mailto:info@eporta.ru" style="color:inherit;text-decoration:none" itemprop="email">info@eporta.ru</a></div>
			</div>
			<div>
				<div style="font:700 14px 'Manrope';color:#3a3631;margin-bottom:8px">Оптовый отдел</div>
				<div style="font:600 14px 'Manrope';color:#3a3631">Телефон: <a href="tel:+74951208829" style="color:inherit;text-decoration:none">+7 (495) 120-88-29</a></div>
				<div style="font:600 14px 'Manrope';color:#3a3631">Эл. почта: <a href="mailto:sale@eporta.ru" style="color:inherit;text-decoration:none">sale@eporta.ru</a></div>
			</div>
		</div>

		<div style="border-radius:14px;overflow:hidden;border:1.5px solid var(--border-soft, #e7e3db)">
			<iframe
				src="https://yandex.ru/map-widget/v1/?text=<?=urlencode($eportaContactsAddress)?>"
				width="100%" height="400" frameborder="0" loading="lazy" title="Карта проезда"></iframe>
		</div>
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
