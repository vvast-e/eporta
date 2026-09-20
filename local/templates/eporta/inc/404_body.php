<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Общий блок содержимого 404 под дизайн eporta — используется и общесайтовым /404.php, и
// коротким замыканием в catalog/index.php (товар не найден по ELEMENT_CODE из URL), чтобы
// не дублировать разметку/стили в двух местах. $eporta404Heading/$eporta404Text — опциональные
// переопределения текста под конкретный случай (по умолчанию — общая формулировка).
$eporta404Heading = $eporta404Heading ?? "Такой страницы не существует";
$eporta404Text = $eporta404Text ?? "Возможно, товар сняли с продажи или адрес был набран с ошибкой. Попробуйте поиск или перейдите в каталог.";
?>
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
	<h1><?=htmlspecialcharsbx($eporta404Heading)?></h1>
	<p><?=htmlspecialcharsbx($eporta404Text)?></p>

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
