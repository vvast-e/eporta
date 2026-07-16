// EPORTA — общий JS шаблона

// ---- Счётчик корзины ----
// Источник истины при загрузке страницы — реальный подсчёт на сервере (header.php,
// CSaleBasket по текущему FUSER). Эти функции только обновляют бейдж мгновенно между
// AJAX-добавлением и следующей перезагрузкой (см. addMainToCart/addKitToCart в
// catalog.element/.default/template.php, addCartFromCompare в assets/compare.js).
function eportaCartBadge(n) {
	document.querySelectorAll('.cart-btn .badge').forEach(function (b) { b.textContent = n; });
}

function eportaCartCount() {
	var badge = document.querySelector('.cart-btn .badge');
	return badge ? (parseInt(badge.textContent, 10) || 0) : 0;
}

// ---- Мегаменю каталога ----
(function(){
	var nav = document.querySelector('.cat-nav');
	if (!nav) return;

	// Убираем overflow:hidden, чтобы дропдаун не обрезался
	nav.style.overflow = 'visible';
	nav.style.position = 'relative';

	nav.querySelectorAll('.nav-item').forEach(function(s){
		s.addEventListener('click', function(){ window.location.href = '/catalog/'; });
	});

	var menu = document.createElement('div');
	menu.id = 'megaMenu';
	menu.innerHTML =
		'<div style="position:absolute;left:0;right:0;top:100%;z-index:200;background:#fff;' +
		'border-top:1px solid #efece6;border-bottom:1px solid #efece6;' +
		'box-shadow:0 26px 50px rgba(27,26,23,.13);padding:28px 40px;display:flex;gap:48px;align-items:flex-start">' +

		'<div style="flex:none">' +
			'<div style="font:800 11px \'Manrope\';letter-spacing:.08em;color:#a39e95;margin-bottom:14px">ТИП ДВЕРЕЙ</div>' +
			'<div style="display:flex;flex-direction:column;gap:12px">' +
				'<a href="/catalog/" class="mm-link" style="font:700 14px \'Manrope\';color:#1b1a17">Межкомнатные</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Раздвижные перегородки</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Входные</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Фурнитура</a>' +
			'</div>' +
		'</div>' +

		'<div style="flex:none">' +
			'<div style="font:800 11px \'Manrope\';letter-spacing:.08em;color:#a39e95;margin-bottom:14px">ПО СТИЛЮ</div>' +
			'<div style="display:flex;flex-direction:column;gap:12px">' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Модерн / хай-тек</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Классика</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Лофт</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Скандинавский</a>' +
			'</div>' +
		'</div>' +

		'<div style="flex:none">' +
			'<div style="font:800 11px \'Manrope\';letter-spacing:.08em;color:#a39e95;margin-bottom:14px">ПО ПОКРЫТИЮ</div>' +
			'<div style="display:flex;flex-direction:column;gap:12px">' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Экошпон</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Эмаль</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Эмалит</a>' +
				'<a href="/catalog/" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Натуральный шпон</a>' +
			'</div>' +
		'</div>' +

		'<div style="flex:1;display:flex;gap:14px;min-width:0">' +
			'<a href="/discount/" style="flex:1;position:relative;border-radius:14px;overflow:hidden;cursor:pointer;min-height:160px;background:#2b1512;display:block;text-decoration:none">' +
				'<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.15),rgba(0,0,0,.55))"></div>' +
				'<div style="position:absolute;left:16px;bottom:14px">' +
					'<div style="font:800 17px \'Manrope\';color:#fff">Распродажа</div>' +
					'<div style="font:700 12px \'Manrope\';color:#ffd7b0;margin-top:3px">скидки до −25%</div>' +
				'</div>' +
			'</a>' +
			'<a href="/catalog/" style="flex:1;position:relative;border-radius:14px;overflow:hidden;cursor:pointer;min-height:160px;background:#12251a;display:block;text-decoration:none">' +
				'<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.15),rgba(0,0,0,.55))"></div>' +
				'<div style="position:absolute;left:16px;bottom:14px">' +
					'<div style="font:800 17px \'Manrope\';color:#fff">Новинки</div>' +
					'<div style="font:700 12px \'Manrope\';color:#b7e6c6;margin-top:3px">новые коллекции 2026</div>' +
				'</div>' +
			'</a>' +
		'</div>' +

		'</div>';

	menu.style.display = 'none';
	nav.appendChild(menu);

	var style = document.createElement('style');
	style.textContent = '.mm-link{text-decoration:none;transition:color .12s}.mm-link:hover{color:#c2670a!important}';
	document.head.appendChild(style);

	var catLink = nav.querySelector('a[href="/catalog/"]');
	var closeTimer;
	function openMenu(){
		clearTimeout(closeTimer);
		menu.style.display = 'block';
	}
	function closeMenu(){
		closeTimer = setTimeout(function(){ menu.style.display = 'none'; }, 80);
	}

	if (catLink) {
		catLink.addEventListener('mouseenter', openMenu);
	}
	nav.addEventListener('mouseleave', closeMenu);
	menu.addEventListener('mouseenter', function(){ clearTimeout(closeTimer); });
	menu.addEventListener('mouseleave', closeMenu);
})();

// ---- Поиск в шапке (подсказки через существующий AJAX dw.deluxe, act=search) ----
(function () {
	var form = document.querySelector('.header-search');
	if (!form) return;
	var input = document.getElementById('headerSearchInput');
	var box = document.getElementById('headerSearchSuggest');
	var iblockId = form.getAttribute('data-iblock') || '';
	if (!input || !box || !iblockId) return;

	var debounceTimer = null;
	var currentController = null;

	function closeSuggest() {
		box.classList.remove('open');
		box.textContent = '';
	}

	function renderItems(items) {
		box.textContent = '';
		if (!items.length) {
			var empty = document.createElement('div');
			empty.className = 'hs-empty';
			empty.textContent = 'Ничего не найдено';
			box.appendChild(empty);
			box.classList.add('open');
			return;
		}
		items.forEach(function (item) {
			var a = document.createElement('a');
			a.className = 'hs-item';
			a.href = item.DETAIL_PAGE_URL || '#';

			var img = document.createElement('img');
			img.src = item.DETAIL_PICTURE || '';
			img.alt = '';
			a.appendChild(img);

			var name = document.createElement('span');
			name.className = 'hs-name';
			name.textContent = item.NAME || '';
			a.appendChild(name);

			if (item.PRICE) {
				var price = document.createElement('span');
				price.className = 'hs-price';
				price.textContent = item.PRICE;
				a.appendChild(price);
			}

			box.appendChild(a);
		});
		box.classList.add('open');
	}

	function fetchSuggest(q) {
		if (currentController) currentController.abort();
		var controller = new AbortController();
		currentController = controller;
		fetch('/ajax.php?act=search&name=' + encodeURIComponent(q) + '&iblock_id=' + encodeURIComponent(iblockId), { credentials: 'same-origin', signal: controller.signal })
			.then(function (r) { return r.json(); })
			.then(function (data) { renderItems(Array.isArray(data) ? data : []); })
			.catch(function () {});
	}

	input.addEventListener('input', function () {
		var q = input.value.trim();
		clearTimeout(debounceTimer);
		if (q.length < 2) { closeSuggest(); return; }
		debounceTimer = setTimeout(function () { fetchSuggest(q); }, 300);
	});

	document.addEventListener('click', function (e) {
		if (!form.contains(e.target)) closeSuggest();
	});

	input.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') closeSuggest();
	});
})();
