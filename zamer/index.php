<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Вызвать замерщика");

$eportaZamerSent = false;
$eportaZamerError = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["zamer_phone"])) {
	if (!check_bitrix_sessid()) {
		$eportaZamerError = "Сессия устарела, обновите страницу и попробуйте снова.";
	} elseif (trim($_POST["zamer_phone"]) === "") {
		$eportaZamerError = "Пожалуйста, укажите номер телефона.";
	} else {
		$phone = trim($_POST["zamer_phone"]);
		$name = trim($_POST["zamer_name"] ?? "");
		$comment = trim($_POST["zamer_comment"] ?? "");
		$body = "Заявка на вызов замерщика с сайта eporta.ru\n\n"
			."Телефон: {$phone}\n"
			."Имя: {$name}\n"
			."Адрес/комментарий: {$comment}\n";
		$headers = "Content-Type: text/plain; charset=utf-8\r\nFrom: no-reply@eporta.ru";
		@mail("info@eporta.ru", "Заявка: вызов замерщика", $body, $headers);
		$eportaZamerSent = true;
	}
}
?>
<div class="info-page">
	<div class="info-tabs">
		<?php foreach (eportaBuyerInfoPages() as $eportaBuyerPage): ?>
			<a href="<?= htmlspecialcharsbx($eportaBuyerPage["href"]) ?>"<?= $eportaBuyerPage["href"] === "/zamer/" ? ' class="active"' : "" ?>><?= htmlspecialcharsbx($eportaBuyerPage["label"]) ?></a>
		<?php endforeach; ?>
	</div>

	<h1>Вызвать замерщика</h1>

	<div class="info-callout info-callout--accent">
		<h4>Бесплатно при покупке и установке от 3-х дверей</h4>
		<p>Выезд специалиста на замер — <strong>1500 ₽</strong> по Москве (в пределах МКАД), за пределы МКАД — плюс 40 ₽ за километр. Эта сумма учитывается как предоплата и вычитается из стоимости заказа при оформлении договора. При заказе одной-двух дверей без услуги монтажа сумма не компенсируется.</p>
	</div>

	<?php if ($eportaZamerSent): ?>
		<div class="info-callout">
			<h4>Заявка отправлена</h4>
			<p>Спасибо! Мы свяжемся с вами в ближайшее время, чтобы согласовать удобное время выезда замерщика.</p>
		</div>
	<?php else: ?>
		<form class="info-form" method="post" action="">
			<?= bitrix_sessid_post() ?>
			<?php if ($eportaZamerError): ?>
				<p style="color:var(--sale);font-weight:700;margin-bottom:16px;"><?= htmlspecialcharsbx($eportaZamerError) ?></p>
			<?php endif; ?>
			<label for="zamerPhone">Ваш телефон (обязательно)</label>
			<input type="tel" id="zamerPhone" name="zamer_phone" placeholder="+7 (___) ___-__-__" value="<?= htmlspecialcharsbx($_POST["zamer_phone"] ?? "") ?>">
			<label for="zamerName">Ваше имя</label>
			<input type="text" id="zamerName" name="zamer_name" value="<?= htmlspecialcharsbx($_POST["zamer_name"] ?? "") ?>">
			<label for="zamerComment">Адрес, комментарий</label>
			<textarea id="zamerComment" name="zamer_comment" rows="4"><?= htmlspecialcharsbx($_POST["zamer_comment"] ?? "") ?></textarea>
			<button type="submit">Отправить заявку</button>
			<p class="info-form-note">Нажимая «Отправить заявку», вы соглашаетесь на обработку персональных данных и принимаете условия <a href="/privacy-policy/" class="info-link">Пользовательского соглашения</a>.</p>
		</form>
	<?php endif; ?>
</div>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>
