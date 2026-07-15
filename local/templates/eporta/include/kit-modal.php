<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<!-- EPORTA: общая модалка конструктора "Собрать комплект" — статичная разметка без PHP-переменных,
     содержимое (название/цена/список допов) наполняется JS-модулем assets/kit.js через KitModal.open(...).
     Используется и на карточке товара, и в корзине (assets/cart-kit.js) — см. план "корзина + допы". -->
<div class="kit-overlay" id="kitOverlay" onclick="KitModal.close()">
	<div class="kit-modal" onclick="event.stopPropagation()">
		<div class="kit-mhead">
			<div>
				<h2 id="kitModalTitle">Собрать комплект</h2>
				<div class="sub" id="kitSubLabel"></div>
			</div>
			<button class="kit-close" onclick="KitModal.close()">✕</button>
		</div>
		<div class="kit-body">
			<div class="kit-tabs" id="kitTabs"></div>
			<div class="kit-panel">
				<div class="kit-search">
					<input type="text" id="kitSearchInput" placeholder="Поиск по названию…" oninput="KitModal.renderItems()">
					<span class="scnt" id="kitSearchCount"></span>
				</div>
				<div class="kit-items" id="kitItemsList"></div>
			</div>
		</div>
		<div class="kit-foot">
			<div>
				<div class="total-info" id="kitTotalInfo">Полотно · допы не выбраны</div>
				<div class="total-price" id="kitTotalPrice"></div>
			</div>
			<button class="kit-cta" id="kitCtaBtn" onclick="KitModal.submit()">В корзину</button>
		</div>
	</div>
</div>
