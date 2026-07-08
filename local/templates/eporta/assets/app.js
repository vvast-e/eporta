// EPORTA — общий JS шаблона

// ---- Счётчик корзины ----
(function(){
	var count = sessionStorage.getItem('eporta_cart') || '0';
	document.querySelectorAll('.cart-btn .badge').forEach(function(b){ b.textContent = count; });
})();

function updateCartBadge(n){
	sessionStorage.setItem('eporta_cart', n);
	document.querySelectorAll('.cart-btn .badge').forEach(function(b){ b.textContent = n; });
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

// ---- Добавление в корзину (используется в onclick карточек товара) ----
function addToCart(e){
	e.preventDefault();
	e.stopPropagation();
	var count = parseInt(sessionStorage.getItem('eporta_cart') || '0', 10) + 1;
	updateCartBadge(count);
	var btn = e.currentTarget;
	var orig = btn.textContent;
	btn.textContent = '✓ Добавлено';
	btn.style.background = '#1f8a4c';
	setTimeout(function(){ btn.textContent = orig; btn.style.background = '#e8820a'; }, 1500);
}
