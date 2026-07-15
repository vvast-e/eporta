/* EPORTA: интерактивные допы в корзине (эталон "EPORTA Прототип" #cart + требование пользователя —
   добавлять/менять/удалять допы прямо в блоке товара корзины через то же мини-окно «Собрать комплект»,
   что на карточке товара). Работает поверх переопределённого шаблона bitrix:sale.basket.basket ("eporta",
   см. local/templates/eporta/components/bitrix/sale.basket.basket/eporta) — сам компонент и его AJAX
   (кол-во/удаление/купон) не трогаем, только читаем DOM после его перерисовок.

   Состояние допов — на клиенте (localStorage), допы НЕ являются реальными товарами Bitrix (нет SKU/услуг
   на проде после текущей выгрузки — см. assets/kit.js) и в реальный заказ не попадают. "Допы
   (предварительно)" в карточке «Итого» — информационная строка, не влияет на "К оплате".

   Когда после выгрузки 1С появятся настоящие допы/услуги — заменить STORAGE_KEY-состояние на реальное
   добавление позиций в корзину Bitrix (аналогично addKitToCart на карточке товара) и убрать пометку
   "предварительно". */
(function () {
	'use strict';

	var STORAGE_KEY = 'eporta_cart_kits';

	function loadState() {
		try {
			var raw = localStorage.getItem(STORAGE_KEY);
			return raw ? JSON.parse(raw) : {};
		} catch (e) {
			return {};
		}
	}
	function saveState(state) {
		try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (e) { /* localStorage недоступен — состояние живёт до перезагрузки */ }
	}

	var state = loadState();

	function parsePriceText(text) {
		if (!text) return 0;
		var digits = text.replace(/[^\d]/g, '');
		return digits ? parseInt(digits, 10) : 0;
	}

	function findRow(itemId) {
		return document.getElementById('basket-item-' + itemId);
	}

	function getDoorUnitPrice(itemId) {
		var byId = document.getElementById('basket-item-price-' + itemId) || document.getElementById('basket-item-sum-price-' + itemId);
		if (byId) return parsePriceText(byId.textContent);
		var row = findRow(itemId);
		if (!row) return 0;
		var el = row.querySelector('.basket-item-price-current-text');
		return el ? parsePriceText(el.textContent) : 0;
	}

	function getDoorName(itemId) {
		var row = findRow(itemId);
		if (!row) return '';
		var el = row.querySelector('[data-entity="basket-item-name"]');
		return el ? el.textContent.trim() : '';
	}

	function renderAddonZone(zoneEl) {
		var itemId = zoneEl.getAttribute('data-kit-item');
		var selection = state[itemId] || {};
		var html = '';
		Object.keys(selection).forEach(function (addonId) {
			var qty = selection[addonId];
			if (!qty) return;
			var addon = KitModal.findAddon(addonId);
			if (!addon) return;
			var qtyLabel = qty > 1 ? '× ' + qty : '';
			html += '<div class="kb-addon-row">' +
				'<span class="kb-addon-dot"></span>' +
				'<div style="flex:1;min-width:0">' +
					'<div class="kb-addon-name">' + addon.name + (qtyLabel ? ' <span class="kb-addon-qty">' + qtyLabel + '</span>' : '') + '</div>' +
					'<div class="kb-addon-sub">' + addon.sub + '</div>' +
				'</div>' +
				'<div class="kb-addon-price">' + KitModal.fmtPrice(addon.price * qty) + '</div>' +
				'<span class="kb-addon-remove" onclick="EportaCartKit.removeAddon(\'' + itemId + '\',\'' + addonId + '\')">Удалить</span>' +
				'</div>';
		});
		html += '<div class="kb-kit-trigger" onclick="EportaCartKit.openFor(\'' + itemId + '\')">' +
			'<span class="kb-kit-trigger-icon">+</span>' +
			'<span>Собрать комплект</span>' +
			'</div>';
		// Идемпотентно: renderAll() вызывается на КАЖДУЮ AJAX-перерисовку корзины (в т.ч. чисто
		// вендорскую — смена количества, где состав допов не менялся). Безусловная запись innerHTML
		// пересоздавала бы DOM-узлы допов/триггера на каждый такой чих — заметный визуальный "дёрг".
		// Пишем, только если контент реально изменился.
		if (zoneEl.innerHTML !== html) zoneEl.innerHTML = html;
	}

	// Цена двери (SUM_PRICE_FORMATED) у вендора рендерится отдельной <td> ("basket-items-list-item-price"),
	// которая у нас идёт блоком уже ПОСЛЕ строки количества — визуально читается как "итог по карточке",
	// хотя это сумма только за полотно (без допов, те входят в шапку группы). Переносим блок цены внутрь
	// той же строки, что фото+название (.basket-items-list-item-descriptions-inner), справа — как в эталоне.
	// CSS-ом (order) это сделать нельзя: цена и фото/название лежат в РАЗНЫХ <td> одного <tr>, order
	// переставляет только прямых детей общего flex/grid-контейнера, а не потомков в разных ветках DOM.
	// Саму табличную структуру (<td>) не трогаем — на ней завязан colspan вендора в состоянии
	// SHOW_RESTORE ("товар удалён/восстановить").
	function relocatePrice(itemId) {
		var row = findRow(itemId);
		if (!row) return;
		var priceTd = row.querySelector('.basket-items-list-item-price');
		if (!priceTd) return;
		var priceBlock = priceTd.querySelector('.basket-item-block-price');
		var descInner = row.querySelector('.basket-items-list-item-descriptions-inner');
		if (priceBlock && descInner && priceBlock.parentElement !== descInner) {
			priceBlock.classList.add('basket-item-price-inline');
			descInner.appendChild(priceBlock);
		}
		if (priceTd.style.display !== 'none') priceTd.style.display = 'none';
	}

	// Степпер количества у вендора — отдельная <td class="basket-items-list-item-amount"> (нужна для
	// табличной структуры/colspan, не трогаем), которая у нас рендерится отдельным блоком в самом низу
	// карточки. Переносим сам степпер (.basket-item-block-amount) сразу под свойства товара
	// (.basket-item-block-properties, там же "Розничная цена") — тем же приёмом, что и relocatePrice.
	function relocateAmount(itemId) {
		var row = findRow(itemId);
		if (!row) return;
		var amountTd = row.querySelector('.basket-items-list-item-amount');
		if (!amountTd) return;
		var amountBlock = amountTd.querySelector('.basket-item-block-amount');
		var propsBlock = row.querySelector('.basket-item-block-properties');
		if (amountBlock && propsBlock && amountBlock.parentElement !== propsBlock.parentNode) {
			amountBlock.classList.add('basket-item-amount-inline');
			propsBlock.parentNode.insertBefore(amountBlock, propsBlock.nextSibling);
		}
		if (amountTd.style.display !== 'none') amountTd.style.display = 'none';
	}

	function updateHeader(itemId) {
		var row = findRow(itemId);
		if (!row) return;
		var header = row.querySelector('.basket-kit-header');
		if (!header) return;
		var selection = state[itemId] || {};
		var addonsTotal = KitModal.calcAddonTotal(selection);
		var cnt = Object.keys(selection).filter(function (id) { return selection[id] > 0; }).length;
		var badge = header.querySelector('[data-entity="basket-kit-badge"]');
		var total = header.querySelector('[data-entity="basket-kit-group-total"]');
		if (badge) {
			if (cnt > 0) {
				badge.textContent = 'Комплект под ключ';
				badge.classList.add('bkh-badge-kit');
			} else {
				badge.textContent = 'Только полотно';
				badge.classList.remove('bkh-badge-kit');
			}
		}
		if (total) total.textContent = KitModal.fmtPrice(getDoorUnitPrice(itemId) + addonsTotal);
	}

	function renderAll() {
		var zones = document.querySelectorAll('.basket-item-addons[data-kit-item]');
		var seenIds = [];
		zones.forEach(function (zoneEl) {
			var itemId = zoneEl.getAttribute('data-kit-item');
			seenIds.push(itemId);
			relocatePrice(itemId);
			relocateAmount(itemId);
			renderAddonZone(zoneEl);
			updateHeader(itemId);
		});
		// Позиция удалена из корзины (AJAX-удаление) — чистим её состояние допов.
		var changed = false;
		Object.keys(state).forEach(function (itemId) {
			if (seenIds.indexOf(itemId) === -1) { delete state[itemId]; changed = true; }
		});
		if (changed) saveState(state);
		updateTotalsInfo();
	}

	function updateTotalsInfo() {
		var container = document.querySelector('[data-entity="basket-checkout-aligner"]');
		if (!container) return;

		var rows = document.querySelectorAll('#basket-item-table tr[data-entity="basket-item"]');
		var itemsCount = 0;
		rows.forEach(function (row) {
			var qtyField = row.querySelector('[data-entity="basket-item-quantity-field"]');
			itemsCount += qtyField ? (parseInt(qtyField.value, 10) || 0) : 1;
		});
		var itemsLabel = container.querySelector('[data-entity="basket-total-items-label"]');
		var itemsValue = container.querySelector('[data-entity="basket-total-items-value"]');
		if (itemsLabel) itemsLabel.textContent = 'Товары, ' + itemsCount + ' ' + KitModal.plural(itemsCount, 'шт.', 'шт.', 'шт.');
		if (itemsValue) {
			var itemsSum = 0;
			rows.forEach(function (row) {
				var sumEl = row.querySelector('.basket-item-price-current-text');
				if (sumEl) itemsSum += parsePriceText(sumEl.textContent);
			});
			itemsValue.textContent = KitModal.fmtPrice(itemsSum);
		}

		var addonsTotal = 0;
		Object.keys(state).forEach(function (itemId) { addonsTotal += KitModal.calcAddonTotal(state[itemId] || {}); });
		var addonsLine = container.querySelector('[data-entity="basket-total-addons-line"]');
		var addonsValue = container.querySelector('[data-entity="basket-total-addons-value"]');
		if (addonsLine) {
			if (addonsTotal > 0) {
				addonsLine.style.display = '';
				if (addonsValue) addonsValue.textContent = '+' + KitModal.fmtPrice(addonsTotal);
			} else {
				addonsLine.style.display = 'none';
			}
		}
	}

	function removeAddon(itemId, addonId) {
		if (!state[itemId]) return;
		delete state[itemId][addonId];
		saveState(state);
		withPausedObservers(function () {
			var zone = document.querySelector('.basket-item-addons[data-kit-item="' + itemId + '"]');
			if (zone) renderAddonZone(zone);
			updateHeader(itemId);
			updateTotalsInfo();
		});
	}

	function openFor(itemId) {
		var zone = document.querySelector('.basket-item-addons[data-kit-item="' + itemId + '"]');
		var doorFit = (zone && zone.getAttribute('data-kit-door-fit')) || 'eco';
		KitModal.open({
			key: itemId,
			doorPrice: getDoorUnitPrice(itemId),
			doorFit: doorFit,
			title: getDoorName(itemId),
			priceLabel: '',
			ctaLabel: 'Применить',
			initialSelection: state[itemId] || {},
			onSubmit: function (selection) {
				state[itemId] = selection;
				saveState(state);
				withPausedObservers(function () {
					if (zone) renderAddonZone(zone);
					updateHeader(itemId);
					updateTotalsInfo();
				});
			}
		});
	}

	// EPORTA: наш собственный рендер (renderAll/renderAddonZone/updateTotalsInfo) пишет innerHTML
	// внутрь #basket-item-table и блока итога — тех же узлов, за которыми следят MutationObserver'ы
	// ниже (чтобы пере-гидратироваться после AJAX-перерисовки родного компонента). Без паузы это
	// зацикливалось: наша перерисовка триггерила observer → снова renderAll → снова мутация → снова
	// observer, до бесконечности (и элементы под курсором постоянно "отваливались" от DOM). Поэтому
	// на время СВОЕЙ мутации отключаем оба observer'а и переподключаем их уже после неё.
	var itemsObserver = null;
	var totalObserver = null;

	function attachObservers() {
		var table = document.getElementById('basket-item-table');
		if (table) {
			itemsObserver = new MutationObserver(scheduleRenderAll);
			itemsObserver.observe(table, { childList: true, subtree: true });
		}
		var totalBlock = document.querySelector('[data-entity="basket-total-block"]');
		if (totalBlock) {
			totalObserver = new MutationObserver(scheduleRenderAll);
			totalObserver.observe(totalBlock, { childList: true, subtree: true });
		}
	}

	function withPausedObservers(fn) {
		if (itemsObserver) itemsObserver.disconnect();
		if (totalObserver) totalObserver.disconnect();
		fn();
		// Переподключаемся на следующем тике, а не сразу — чтобы не поймать в очереди
		// MutationObserver записи о мутациях, вызванных самим fn().
		setTimeout(attachObservers, 0);
	}

	// EPORTA: вендор АНИМИРУЕТ изменение цены (component.js: duration.priceAnimation=300) — при смене
	// количества текст цены проходит через промежуточные кадры на протяжении ~300мс, и только в конце
	// анимации переносит финальное значение в PRICE/PRICE_FORMATED. Раньше здесь был троттлинг (не чаще
	// раза в 30мс) — за время одной анимации renderAll() успевал сработать ~10 раз, каждый раз читая ЕЩЁ
	// НЕ финальную цену и перезаписывая bkh-total — визуальное мерцание, а последний рендер иногда
	// проскакивал ДО того, как анимация осела, оставляя устаревшее значение. Debounce с периодом тишины
	// 400мс (с запасом на 300мс анимации + сетевой джиттер): каждая новая мутация сбрасывает таймер,
	// рендерим один раз, уже после того как всё осело.
	var renderTimer = null;
	function scheduleRenderAll() {
		if (renderTimer) clearTimeout(renderTimer);
		renderTimer = setTimeout(function () { renderTimer = null; withPausedObservers(renderAll); }, 400);
	}

	document.addEventListener('DOMContentLoaded', function () {
		// withPausedObservers само подключит наблюдатели после первого рендера (через attachObservers).
		withPausedObservers(renderAll);
	});

	// EPORTA: подтверждение перед удалением позиции. Раньше вендор при удалении переводил строку в
	// состояние SHOW_RESTORE ("Товар удалён… Восстановить") — теперь оно отключено параметром компонента
	// (SHOW_RESTORE=>"N" в personal/cart/index.php), удаление сразу окончательное, поэтому вместо
	// "undo после" делаем "подтверждение до". Перехватываем клик по кнопке удаления в CAPTURE-фазе — она
	// всегда отрабатывает раньше bubble-фазового делегированного обработчика вендора (component.js),
	// независимо от порядка регистрации листенеров.
	var confirmModalEl = null;
	var confirmPendingCb = null;

	function ensureConfirmModal() {
		if (confirmModalEl) return confirmModalEl;
		var overlay = document.createElement('div');
		overlay.className = 'ek-confirm-overlay';
		overlay.innerHTML =
			'<div class="ek-confirm-modal">' +
				'<div class="ek-confirm-text">Удалить эту дверь из корзины?</div>' +
				'<div class="ek-confirm-actions">' +
					'<button type="button" class="ek-confirm-cancel">Отмена</button>' +
					'<button type="button" class="ek-confirm-delete">Удалить</button>' +
				'</div>' +
			'</div>';
		document.body.appendChild(overlay);
		overlay.addEventListener('click', function (e) { if (e.target === overlay) closeConfirm(); });
		overlay.querySelector('.ek-confirm-cancel').addEventListener('click', closeConfirm);
		overlay.querySelector('.ek-confirm-delete').addEventListener('click', function () {
			var cb = confirmPendingCb;
			closeConfirm();
			if (cb) cb();
		});
		confirmModalEl = overlay;
		return overlay;
	}
	function closeConfirm() {
		confirmPendingCb = null;
		if (confirmModalEl) confirmModalEl.classList.remove('open');
	}
	function showConfirm(onConfirm) {
		var overlay = ensureConfirmModal();
		confirmPendingCb = onConfirm;
		overlay.classList.add('open');
	}

	document.addEventListener('click', function (e) {
		var el = e.target && e.target.closest && e.target.closest('[data-entity="basket-item-delete"]');
		if (!el) return;
		if (el.__eportaConfirmed) { delete el.__eportaConfirmed; return; } // пропускаем один раз — это наш собственный повторный клик после подтверждения
		e.preventDefault();
		e.stopPropagation();
		if (e.stopImmediatePropagation) e.stopImmediatePropagation();
		showConfirm(function () {
			el.__eportaConfirmed = true;
			el.click(); // теперь дойдёт до вендорского обработчика и запустит реальное удаление
		});
	}, true);

	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeConfirm(); });

	window.EportaCartKit = { openFor: openFor, removeAddon: removeAddon };
})();
