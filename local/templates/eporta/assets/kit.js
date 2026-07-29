/* EPORTA: общий JS-модуль конструктора «Собрать комплект» (мок-допы, единый ADDON_POOL).
   Используется в двух контекстах:
     - карточка товара (components/bitrix/catalog.element/.default/template.php) — выбор допов
       для добавляемой в корзину двери;
     - корзина (components/bitrix/sale.basket.basket/eporta) — изменение допов уже лежащей
       в корзине позиции (см. assets/cart-kit.js).
   ВАЖНО: ADDON_POOL — статичный мок. RELATED_PRODUCT/SIMILAR_PRODUCT и SKU (инфоблок 54) на проде
   пока не заполнены (см. карту данных проекта, снэпшот 2026-07-09). Когда появятся реальные
   допы/услуги после выгрузки 1С — заменить ADDON_POOL реальными товарами и передавать в
   ctx.onSubmit настоящее добавление в корзину вместо мок-колбэка вызывающей стороны. */
(function (global) {
	'use strict';

	var ADDON_CATEGORIES = [
		{ key: 'frame',  name: 'Коробка',              mode: 'multi',  hint: 'по числу проёмов' },
		{ key: 'casing', name: 'Наличники',            mode: 'multi',  hint: 'комплект на проём' },
		{ key: 'ext',    name: 'Доборы',               mode: 'multi',  hint: 'для широких стен' },
		{ key: 'hinge',  name: 'Петли',                mode: 'multi',  hint: '' },
		{ key: 'lock',   name: 'Замки',                mode: 'single', hint: 'один на полотно' },
		{ key: 'handle', name: 'Ручки',                mode: 'single', hint: 'один комплект' },
		{ key: 'stop',   name: 'Упоры и ограничители', mode: 'single', hint: '' }
	];
	var ADDON_POOL = [
		{ id: 'fr-eco',    cat: 'frame',  fit: 'eco',  name: 'Коробка телескопическая, экошпон в тон', sub: 'МДФ · кромка ПВХ', price: 1980 },
		{ id: 'fr-eco2',   cat: 'frame',  fit: 'eco',  name: 'Коробка прямая, экошпон в тон', sub: 'фиксированная ширина', price: 1490 },
		{ id: 'fr-emal',   cat: 'frame',  fit: 'emal', name: 'Коробка эмаль, под покраску', sub: 'массив сосны + МДФ', price: 2680 },
		{ id: 'fr-steel',  cat: 'frame',  fit: 'dark', name: 'Коробка стальная усиленная', sub: 'для входной группы', price: 5900 },
		{ id: 'cs-tele',   cat: 'casing', fit: 'eco',  name: 'Наличник телескопический, экошпон', sub: 'комплект 5 шт · 2.2 м', price: 1240 },
		{ id: 'cs-flat',   cat: 'casing', fit: 'eco',  name: 'Наличник плоский 70 мм, экошпон', sub: 'комплект 5 шт · 2.2 м', price: 960 },
		{ id: 'cs-emal',   cat: 'casing', fit: 'emal', name: 'Наличник фигурный, эмаль', sub: 'комплект 5 шт · 2.2 м', price: 1680 },
		{ id: 'ex-100',    cat: 'ext',    fit: 'eco',  name: 'Добор 100 мм, экошпон в тон', sub: '2 шт · стены до 120 мм', price: 1120 },
		{ id: 'ex-150',    cat: 'ext',    fit: 'eco',  name: 'Добор 150 мм, экошпон в тон', sub: '2 шт · стены до 180 мм', price: 1460 },
		{ id: 'ex-emal',   cat: 'ext',    fit: 'emal', name: 'Добор 100 мм, эмаль', sub: '2 шт · под покраску', price: 1590 },
		{ id: 'hg-univ',   cat: 'hinge',  fit: 'all',  name: 'Петли универсальные, чёрные', sub: 'пара · врезные 100 мм', price: 640 },
		{ id: 'hg-hidden', cat: 'hinge',  fit: 'all',  name: 'Петли скрытые AGB', sub: 'пара · регулировка 3D', price: 2340 },
		{ id: 'hg-brass',  cat: 'hinge',  fit: 'all',  name: 'Петли латунь, бронза', sub: 'пара · декоративные', price: 1180 },
		{ id: 'lk-mag',    cat: 'lock',   fit: 'all',  name: 'Замок магнитный Morelli, чёрный', sub: 'бесшумный', price: 1890 },
		{ id: 'lk-cyl',    cat: 'lock',   fit: 'all',  name: 'Замок под цилиндр MC85BL', sub: 'с ключом', price: 2100 },
		{ id: 'lk-wc',     cat: 'lock',   fit: 'all',  name: 'Защёлка WC для санузла', sub: 'с фиксатором', price: 820 },
		{ id: 'hn-black',  cat: 'handle', fit: 'all',  name: 'Ручка Fimet, чёрный матовый', sub: 'на розетке', price: 2340 },
		{ id: 'hn-nickel', cat: 'handle', fit: 'all',  name: 'Ручка Morelli, никель', sub: 'на планке', price: 1980 },
		{ id: 'hn-brass',  cat: 'handle', fit: 'all',  name: 'Ручка Colombo, бронза', sub: 'премиум', price: 4650 },
		{ id: 'hn-chrome', cat: 'handle', fit: 'all',  name: 'Ручка Fuaro, хром глянец', sub: 'на розетке', price: 1460 },
		{ id: 'st-floor',  cat: 'stop',   fit: 'all',  name: 'Ограничитель напольный, чёрный', sub: 'магнитный', price: 480 },
		{ id: 'st-wall',   cat: 'stop',   fit: 'all',  name: 'Упор настенный, чёрный', sub: 'силиконовый', price: 290 }
	];
	var ADDON_FIT = { eco: ['eco', 'all'], emal: ['emal', 'all'], dark: ['dark', 'eco', 'all'] };

	function fmtPrice(n) { return n.toLocaleString('ru-RU') + ' ₽'; }
	function plural(n, a, b, c) {
		var m = Math.abs(n) % 100, m1 = m % 10;
		if (m > 10 && m < 20) return c;
		if (m1 > 1 && m1 < 5) return b;
		if (m1 === 1) return a;
		return c;
	}
	function findAddon(id) { return ADDON_POOL.filter(function (a) { return a.id === id; })[0]; }
	function calcAddonTotal(selection) {
		var sum = 0;
		Object.keys(selection || {}).forEach(function (id) {
			var item = findAddon(id);
			if (item) sum += item.price * selection[id];
		});
		return sum;
	}

	var ctx = null;       // текущий открытый контекст: {key, doorPrice, fit, selection, ctaLabel, onChange, onSubmit}
	var activeCat = 'frame';

	function getCompatible() {
		var fits = ADDON_FIT[ctx.fit] || ['all'];
		return ADDON_POOL.filter(function (a) { return fits.indexOf(a.fit) >= 0; });
	}
	function getCatItems(catKey) { return getCompatible().filter(function (a) { return a.cat === catKey; }); }
	function countCatSelected(catKey) {
		var cat = ADDON_CATEGORIES.filter(function (c) { return c.key === catKey; })[0];
		if (!cat) return 0;
		if (cat.mode === 'single') return getCatItems(catKey).some(function (a) { return ctx.selection[a.id]; }) ? 1 : 0;
		return getCatItems(catKey).reduce(function (s, a) { return s + (ctx.selection[a.id] || 0); }, 0);
	}

	function renderTabs() {
		var html = '';
		ADDON_CATEGORIES.forEach(function (cat) {
			var cnt = countCatSelected(cat.key);
			var isActive = cat.key === activeCat;
			html += '<div class="kit-tab' + (isActive ? ' active' : '') + '" onclick="KitModal.switchCat(\'' + cat.key + '\')">' +
				'<div><div class="tname">' + cat.name + '</div>' +
				(cat.hint ? '<div class="thint">' + cat.hint + '</div>' : '') + '</div>' +
				(cnt > 0 ? '<div class="tbadge">' + cnt + '</div>' : '') +
				'</div>';
		});
		document.getElementById('kitTabs').innerHTML = html;
	}

	function renderItems() {
		var cat = ADDON_CATEGORIES.filter(function (c) { return c.key === activeCat; })[0];
		var items = getCatItems(activeCat);
		var q = (document.getElementById('kitSearchInput').value || '').toLowerCase().trim();
		if (q) items = items.filter(function (a) { return (a.name + a.sub).toLowerCase().indexOf(q) >= 0; });
		document.getElementById('kitSearchCount').textContent = items.length + ' ' + plural(items.length, 'вариант', 'варианта', 'вариантов');
		if (!items.length) {
			document.getElementById('kitItemsList').innerHTML = '<div class="kit-empty">По запросу ничего не найдено<br><span style="font-size:12px;margin-top:6px;display:block">Попробуйте другой запрос</span></div>';
			return;
		}
		var html = '';
		items.forEach(function (item) {
			var qty = ctx.selection[item.id] || 0;
			var sel = qty > 0;
			html += '<div class="kit-row' + (sel ? ' selected' : '') + '" id="row-' + item.id + '">';
			if (cat.mode === 'single') {
				html += '<span class="rmark">' + (sel ? '✓' : '') + '</span>';
				html += '<div style="flex:1;min-width:0" onclick="KitModal.toggleSingle(\'' + item.id + '\')">' +
					'<div class="rname">' + item.name + '</div>' +
					'<div class="rsub">' + item.sub + '</div></div>';
				html += '<div class="rprice" onclick="KitModal.toggleSingle(\'' + item.id + '\')">+' + fmtPrice(item.price) + '</div>';
			} else {
				html += '<div style="flex:1;min-width:0;cursor:pointer" onclick="KitModal.toggleMulti(\'' + item.id + '\')">' +
					'<div class="rname">' + item.name + '</div>' +
					'<div class="rsub">' + item.sub + '</div></div>';
				html += '<div class="rprice" style="margin-right:10px">+' + fmtPrice(item.price) + '</div>';
				if (sel) {
					html += '<div class="kit-stepper">' +
						'<button onclick="KitModal.changeQtyAddon(\'' + item.id + '\',-1)">−</button>' +
						'<span>' + qty + '</span>' +
						'<button onclick="KitModal.changeQtyAddon(\'' + item.id + '\',1)">+</button>' +
						'</div>';
				} else {
					html += '<div style="width:34px;height:34px;border-radius:9px;background:#f4f1ea;color:#8a857b;display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer" onclick="KitModal.toggleMulti(\'' + item.id + '\')">+</div>';
				}
			}
			html += '</div>';
		});
		document.getElementById('kitItemsList').innerHTML = html;
	}

	function updateFooter() {
		var addons = calcAddonTotal(ctx.selection);
		var total = ctx.doorPrice + addons;
		var cnt = Object.keys(ctx.selection).filter(function (id) { return ctx.selection[id] > 0; }).length;
		var info = cnt > 0 ? 'Полотно + ' + cnt + ' ' + plural(cnt, 'доп.', 'доп.', 'доп.') : 'Полотно · допы не выбраны';
		document.getElementById('kitTotalInfo').textContent = info;
		document.getElementById('kitTotalPrice').textContent = fmtPrice(total);
		var ctaBtn = document.getElementById('kitCtaBtn');
		ctaBtn.textContent = (ctx.ctaLabel || 'В корзину') + ' · ' + fmtPrice(total);
		if (typeof ctx.onChange === 'function') ctx.onChange(ctx.selection, addons, total);
	}

	function toggleSingle(id) {
		getCatItems(activeCat).forEach(function (a) { delete ctx.selection[a.id]; });
		if (!ctx.selection[id]) ctx.selection[id] = 1;
		renderItems(); renderTabs(); updateFooter();
	}
	function toggleMulti(id) {
		if (ctx.selection[id]) delete ctx.selection[id]; else ctx.selection[id] = 1;
		renderItems(); renderTabs(); updateFooter();
	}
	function changeQtyAddon(id, delta) {
		ctx.selection[id] = (ctx.selection[id] || 0) + delta;
		if (ctx.selection[id] <= 0) delete ctx.selection[id];
		renderItems(); renderTabs(); updateFooter();
	}
	function switchCat(key) {
		activeCat = key;
		document.getElementById('kitSearchInput').value = '';
		renderTabs(); renderItems();
	}

	function open(options) {
		ctx = {
			key: options.key,
			doorPrice: options.doorPrice || 0,
			fit: options.doorFit || 'eco',
			selection: options.initialSelection ? JSON.parse(JSON.stringify(options.initialSelection)) : {},
			ctaLabel: options.ctaLabel,
			onChange: options.onChange,
			onSubmit: options.onSubmit
		};
		activeCat = 'frame';
		document.getElementById('kitModalTitle').textContent = options.modalTitle || 'Собрать комплект';
		document.getElementById('kitSubLabel').textContent =
			(options.title || '') + (options.priceLabel ? ' · ' + options.priceLabel : '') + ' · выберите нужные позиции';
		document.getElementById('kitSearchInput').value = '';
		document.getElementById('kitOverlay').classList.add('open');
		document.body.style.overflow = 'hidden';
		renderTabs(); renderItems(); updateFooter();
	}
	function close() {
		var overlay = document.getElementById('kitOverlay');
		if (overlay) overlay.classList.remove('open');
		document.body.style.overflow = '';
	}
	function submit() {
		if (ctx && typeof ctx.onSubmit === 'function') {
			var addons = calcAddonTotal(ctx.selection);
			ctx.onSubmit(ctx.selection, addons, ctx.doorPrice + addons);
		}
		close();
	}

	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

	global.KitModal = {
		open: open,
		close: close,
		submit: submit,
		toggleSingle: toggleSingle,
		toggleMulti: toggleMulti,
		changeQtyAddon: changeQtyAddon,
		switchCat: switchCat,
		renderItems: renderItems,
		fmtPrice: fmtPrice,
		plural: plural,
		calcAddonTotal: calcAddonTotal,
		findAddon: findAddon,
		ADDON_POOL: ADDON_POOL,
		ADDON_CATEGORIES: ADDON_CATEGORIES
	};
})(window);
