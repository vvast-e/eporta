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
// EPORTA: скрипт подключается в <head> с defer (см. header.php) — обёртка в DOMContentLoaded
// обязательна для любого блока, читающего DOM на верхнем уровне (без неё document.querySelector(...)
// вернёт null тихо, без единой ошибки).
document.addEventListener('DOMContentLoaded', function(){
	var nav = document.querySelector('.cat-nav');
	if (!nav) return;

	// Убираем overflow:hidden, чтобы дропдаун не обрезался
	nav.style.overflow = 'visible';
	nav.style.position = 'relative';

	nav.querySelectorAll('.nav-item').forEach(function(s){
		s.addEventListener('click', function(){ window.location.href = '/catalog/'; });
	});

	// Ссылки строятся из window.EPORTA_MEGAMENU (задаётся в header.php: там резолвятся
	// реальные enum-ID свойств STYLE/COATING и ключи категорий — на клиенте их нет).
	// Без фильтра (значение null/отсутствует в конфиге) — fallback на просто "/catalog/".
	var mm = window.EPORTA_MEGAMENU || {};
	function mmCategoryUrl(label) {
		var key = (mm.category || {})[label];
		return key ? '/catalog/?category=' + encodeURIComponent(key) : '/catalog/';
	}
	function mmStyleUrl(label) {
		var id = (mm.style || {})[label];
		return id ? '/catalog/?style[]=' + encodeURIComponent(id) : '/catalog/';
	}
	function mmCoatingUrl(label) {
		var id = (mm.coating || {})[label];
		return id ? '/catalog/?coating[]=' + encodeURIComponent(id) : '/catalog/';
	}

	// Промо-плитки мегаменю (слоты megamenu_sale/megamenu_new, mm.banners — задаётся в
	// header.php из eportaBannersMegamenuBanners(), редактируется в админке local/admin_tools/
	// eporta_banners/). Раньше эти две плитки были захардкожены прямо в HTML ниже (заголовок/
	// подзаголовок/ссылка/цвет фона) — теперь строятся из конфига, картинка (если залита через
	// админку) накладывается как background-image, иначе остаётся фолбэк-цвет fallbackBg (тот же
	// вид, что был раньше, до заливки картинки через админку).
	function mmBannerHtml(key, fallbackBg) {
		var b = (mm.banners || {})[key] || {};
		var overlayGradient = (b.OVERLAY_ENABLED !== false)
			? 'linear-gradient(180deg,rgba(0,0,0,.15),rgba(0,0,0,.55))'
			: 'linear-gradient(180deg,rgba(0,0,0,0),rgba(0,0,0,0))';
		// Объединение градиента и картинки в общий background-image (правка 22.09.2026, см. git
		// blame) не убрало проблему: дело не в рассинхроне двух слоёв, а в антиалиасинге самого
		// border-radius+overflow:hidden в Chrome — скруглённый клип обрезает фон не идеально
		// пиксель-в-пиксель, у самого края (особенно нижних углов) остаётся полупрозрачная
		// кромка в 1px, сквозь которую просвечивает белый фон страницы, а не темнее затенение
		// (баг всё ещё виден на скрине 22.09.2026 после первого фикса). Решение — вынести фон
		// в отдельный слой, залезающий на 1px за границы плитки (inset:-1px): overflow:hidden
		// родителя обрезает именно этот выступ вместе с антиалиасинг-кромкой, так что видимая
		// область до самого скругления остаётся закрыта фоном/градиентом без просветов.
		var bgImage = b.IMAGE ? overlayGradient + ",url('" + String(b.IMAGE).replace(/'/g, "%27") + "')" : overlayGradient;
		var bgLayerStyle = 'position:absolute;inset:-1px;background-image:' + bgImage + ';background-size:cover,cover;' +
			'background-position:center,center;background-repeat:no-repeat,no-repeat;background-color:' + fallbackBg;
		var href = b.LINK || '/catalog/';
		var title = b.NAME || '';
		var subtitle = b.SUBTITLE || '';
		return '<a href="' + htmlAttr(href) + '" style="flex:1;position:relative;border-radius:14px;overflow:hidden;cursor:pointer;min-height:160px;display:block;text-decoration:none">' +
			'<div style="' + bgLayerStyle + '"></div>' +
			'<div style="position:absolute;left:16px;bottom:14px">' +
				(title ? '<div style="font:800 17px \'Manrope\';color:#fff">' + htmlText(title) + '</div>' : '') +
				(subtitle ? '<div style="font:700 12px \'Manrope\';color:#ffd7b0;margin-top:3px">' + htmlText(subtitle) + '</div>' : '') +
			'</div>' +
		'</a>';
	}
	function htmlText(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }
	function htmlAttr(s) { return htmlText(s).replace(/"/g, '&quot;'); }

	var menu = document.createElement('div');
	menu.id = 'megaMenu';
	menu.innerHTML =
		'<div style="position:absolute;left:0;right:0;top:100%;z-index:200;background:#fff;' +
		'border-top:1px solid #efece6;border-bottom:1px solid #efece6;' +
		'box-shadow:0 26px 50px rgba(27,26,23,.13);padding:28px 40px;display:flex;gap:48px;align-items:flex-start">' +

		'<div style="flex:none">' +
			'<div style="font:800 11px \'Manrope\';letter-spacing:.08em;color:#a39e95;margin-bottom:14px">ТИП ДВЕРЕЙ</div>' +
			'<div style="display:flex;flex-direction:column;gap:12px">' +
				'<a href="' + mmCategoryUrl('Межкомнатные') + '" class="mm-link" style="font:700 14px \'Manrope\';color:#1b1a17">Межкомнатные</a>' +
				'<a href="' + mmCategoryUrl('Раздвижные перегородки') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Раздвижные перегородки</a>' +
				'<a href="' + mmCategoryUrl('Входные') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Входные</a>' +
				'<a href="' + mmCategoryUrl('Фурнитура') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Фурнитура</a>' +
			'</div>' +
		'</div>' +

		'<div style="flex:none">' +
			'<div style="font:800 11px \'Manrope\';letter-spacing:.08em;color:#a39e95;margin-bottom:14px">ПО СТИЛЮ</div>' +
			'<div style="display:flex;flex-direction:column;gap:12px">' +
				'<a href="' + mmStyleUrl('Модерн / хай-тек') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Модерн / хай-тек</a>' +
				'<a href="' + mmStyleUrl('Классика') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Классика</a>' +
				'<a href="' + mmStyleUrl('Лофт') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Лофт</a>' +
				'<a href="' + mmStyleUrl('Скандинавский') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Скандинавский</a>' +
			'</div>' +
		'</div>' +

		'<div style="flex:none">' +
			'<div style="font:800 11px \'Manrope\';letter-spacing:.08em;color:#a39e95;margin-bottom:14px">ПО ПОКРЫТИЮ</div>' +
			'<div style="display:flex;flex-direction:column;gap:12px">' +
				'<a href="' + mmCoatingUrl('Экошпон') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Экошпон</a>' +
				'<a href="' + mmCoatingUrl('Эмаль') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Эмаль</a>' +
				'<a href="' + mmCoatingUrl('Эмалит') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Эмалит</a>' +
				'<a href="' + mmCoatingUrl('Натуральный шпон') + '" class="mm-link" style="font:600 14px \'Manrope\';color:#3a3631">Натуральный шпон</a>' +
			'</div>' +
		'</div>' +

		'<div style="flex:1;display:flex;gap:14px;min-width:0">' +
			mmBannerHtml('megamenu_sale', '#2b1512') +
			mmBannerHtml('megamenu_new', '#12251a') +
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
});

// ---- Дропдаун "Покупателю" (Замер/Доставка/Оплата/Монтаж/Гарантия) ----
// Клик-тоггл (а не hover, как у мегаменю каталога) — список плоский и короткий, так удобнее
// на touch-устройствах; закрывается по клику вне блока или повторному клику на кнопку.
document.addEventListener('DOMContentLoaded', function () {
	var buyerNav = document.getElementById('buyerNav');
	var toggle = document.getElementById('buyerNavToggle');
	if (!buyerNav || !toggle) return;

	toggle.addEventListener('click', function (e) {
		e.stopPropagation();
		buyerNav.classList.toggle('open');
	});
	document.addEventListener('click', function (e) {
		if (!buyerNav.contains(e.target)) buyerNav.classList.remove('open');
	});
});

// ---- Бургер-меню (мобильная навигация .cat-nav) ----
// Тот же паттерн click-toggle/click-outside, что у buyerNavToggle выше: класс "open" на
// .cat-nav переключает CSS-drawer (см. .cat-nav-toggle в template_styles.css), плюс класс
// "cat-nav-open" на body — для затемнения фона и блокировки скролла под панелью.
document.addEventListener('DOMContentLoaded', function () {
	var toggle = document.getElementById('catNavToggle');
	var nav = document.getElementById('catNav');
	if (!toggle || !nav) return;

	function closeNav() {
		nav.classList.remove('open');
		toggle.setAttribute('aria-expanded', 'false');
		document.body.classList.remove('cat-nav-open');
	}

	toggle.addEventListener('click', function (e) {
		e.stopPropagation();
		var isOpen = nav.classList.toggle('open');
		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		document.body.classList.toggle('cat-nav-open', isOpen);
	});
	document.addEventListener('click', function (e) {
		if (nav.classList.contains('open') && !nav.contains(e.target)) closeNav();
	});
	// Клик по обычному пункту меню (не по тогглу "Покупателю") — закрыть панель, дать перейти по ссылке.
	nav.querySelectorAll('a').forEach(function (link) {
		link.addEventListener('click', closeNav);
	});
});

// ---- Поиск в шапке (подсказки через search/suggest.php — тот же CSearch, что у страницы
// /search/, вместо вендорского dw.deluxe act=search (substring/LIKE) — движки больше не
// расходятся, один и тот же запрос теперь даёт одинаковый набор результатов подсказке
// и странице поиска). Контракт ответа не менялся, поэтому renderItems() ниже — без правок. ----
document.addEventListener('DOMContentLoaded', function () {
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
		fetch('/search/suggest.php?name=' + encodeURIComponent(q) + '&iblock_id=' + encodeURIComponent(iblockId), { credentials: 'same-origin', signal: controller.signal })
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
});

// ---- Карусель(и) баннеров на главной ----
// Может быть один слайдер (старая раскладка) или несколько независимых
// (мозаика: главный + два боковых) — каждый .home-banner-carousel на странице
// получает свой собственный таймер/точки, не зависящие от соседних.
document.addEventListener('DOMContentLoaded', function () {
	var roots = Array.prototype.slice.call(document.querySelectorAll('.home-banner-carousel'));
	roots.forEach(initHomeBannerCarousel);

	function initHomeBannerCarousel(root) {
		var track = root.querySelector('.hbc-track');
		var slides = Array.prototype.slice.call(root.querySelectorAll('.hbc-slide'));
		if (!track || slides.length < 2) return;

		var dotsBox = root.querySelector('.hbc-dots');
		var dots = dotsBox ? slides.map(function (_, i) {
			var dot = document.createElement('button');
			dot.type = 'button';
			dot.className = 'hbc-dot';
			dot.setAttribute('aria-label', 'Баннер ' + (i + 1));
			dot.addEventListener('click', function () { goTo(i); });
			dotsBox.appendChild(dot);
			return dot;
		}) : [];

		var current = 0;
		var timer = null;

		function render() {
			track.style.transform = 'translateX(-' + (current * 100) + '%)';
			dots.forEach(function (dot, i) { dot.classList.toggle('active', i === current); });
		}

		function goTo(i) {
			current = (i + slides.length) % slides.length;
			render();
		}

		function next() { goTo(current + 1); }
		function prev() { goTo(current - 1); }

		function startAutoplay() {
			stopAutoplay();
			timer = setInterval(next, 5000);
		}
		function stopAutoplay() {
			if (timer) clearInterval(timer);
		}

		var nextBtn = root.querySelector('.hbc-next');
		var prevBtn = root.querySelector('.hbc-prev');
		if (nextBtn) nextBtn.addEventListener('click', function () { next(); startAutoplay(); });
		if (prevBtn) prevBtn.addEventListener('click', function () { prev(); startAutoplay(); });
		root.addEventListener('mouseenter', stopAutoplay);
		root.addEventListener('mouseleave', startAutoplay);

		render();
		startAutoplay();
	}
});

// ---- Каталог: подгрузка по кнопке "Показать ещё" (catalog/index.php, ?eporta_ajax=grid) ----
// Прогрессивное улучшение поверх обычной пейджинации (.bx-pagination) — она остаётся рабочей
// без JS. Раньше это была автоподгрузка по скроллу (IntersectionObserver) с
// history.replaceState на каждую подгруженную страницу — из-за этого обновление страницы или
// переход "назад" открывали последнюю подгруженную страницу, а не ту, на которой был
// пользователь. Кнопка URL/историю не трогает вовсе.
document.addEventListener('DOMContentLoaded', function () {
	var wrap = document.getElementById('eportaCatalogGrid');
	if (!wrap) return;

	var loading = false;

	function currentGrid() {
		return wrap.querySelector('.eporta-product-grid');
	}
	function currentPager() {
		return document.getElementById('eportaCatalogPager');
	}
	function currentLoadMoreBtn() {
		return document.getElementById('eportaCatalogLoadMore');
	}

	function loadNext() {
		var pager = currentPager();
		var nextUrl = pager ? pager.getAttribute('data-next-url') : '';
		if (loading || !nextUrl) return;
		loading = true;
		var btn = currentLoadMoreBtn();
		if (btn) btn.disabled = true;

		var url = nextUrl + (nextUrl.indexOf('?') === -1 ? '?' : '&') + 'eporta_ajax=grid';
		fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.text(); })
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var newGrid = doc.querySelector('.eporta-product-grid');
				var newPager = doc.getElementById('eportaCatalogPager');
				var newLoadMoreBtn = doc.getElementById('eportaCatalogLoadMore');
				var grid = currentGrid();
				if (newGrid && grid) {
					// Дописываем карточки в конец текущей сетки, не заменяя её целиком — так не
					// теряются уже подгруженные ранее страницы.
					while (newGrid.firstChild) {
						grid.appendChild(newGrid.firstChild);
					}
				}
				var oldPager = currentPager();
				if (oldPager) {
					if (newPager) {
						oldPager.replaceWith(newPager);
					} else {
						oldPager.remove();
					}
				}
				btn = currentLoadMoreBtn();
				if (btn) {
					if (newLoadMoreBtn) {
						btn.disabled = false;
					} else {
						// Последняя страница — кнопку убираем.
						btn.remove();
					}
				}
				loading = false;
			})
			.catch(function () {
				loading = false;
				var btnRetry = currentLoadMoreBtn();
				if (btnRetry) btnRetry.disabled = false;
			});
	}

	wrap.addEventListener('click', function (e) {
		if (e.target && e.target.id === 'eportaCatalogLoadMore') loadNext();
	});
});

// ---- Каталог: живая фильтрация сайдбара (catalog/index.php, #eportaFiltersForm) ----
// Клик по чекбоксу или изменение цены сразу обновляет #eportaCatalogGrid через тот же
// AJAX-эндпоинт (?eporta_ajax=grid), что и "Показать ещё" выше — без перезагрузки всей
// страницы. Анимация — "штора": светлая панель полностью закрывает блок ДО подмены карточек,
// затем уезжает вниз (translateY), постепенно открывая уже подставленный под ней новый
// результат сверху вниз, а не просто fade всей сетки разом.
document.addEventListener('DOMContentLoaded', function () {
	var form = document.getElementById('eportaFiltersForm');
	var grid = document.getElementById('eportaCatalogGrid');
	if (!form || !grid) return;

	var submitBtn = document.getElementById('eportaFiltersSubmit');
	// Растущий счётчик запросов — если пользователь быстро щёлкнул несколько чекбоксов подряд,
	// ответ на более раннй запрос, пришедший позже, просто игнорируется.
	var reqSeq = 0;

	function showCurtain() {
		var curtain = grid.querySelector('.eporta-catalog-curtain');
		if (!curtain) {
			curtain = document.createElement('div');
			curtain.className = 'eporta-catalog-curtain';
			grid.appendChild(curtain);
		}
		// Переиспользуем ту же панель (если пользователь щёлкнул фильтр ещё раз, пока предыдущая
		// уезжала) — мгновенно возвращаем её в перекрытое состояние без transition.
		curtain.style.transition = 'none';
		curtain.style.transform = 'translateY(0)';
		curtain.offsetHeight; // reflow — чтобы следующий transition снова сработал
		curtain.style.transition = '';
		return curtain;
	}

	function revealCurtain(curtain) {
		requestAnimationFrame(function () {
			requestAnimationFrame(function () {
				curtain.style.transform = 'translateY(100%)';
			});
		});
		curtain.addEventListener('transitionend', function onEnd(e) {
			if (e.propertyName !== 'transform') return;
			curtain.removeEventListener('transitionend', onEnd);
			if (curtain.parentNode) curtain.parentNode.removeChild(curtain);
		});
	}

	function updateSidebarCounts(meta) {
		if (!meta) return;
		if (meta.counts) {
			Object.keys(meta.counts).forEach(function (groupKey) {
				var groupCounts = meta.counts[groupKey];
				Object.keys(groupCounts).forEach(function (value) {
					var input = form.querySelector('input[name="' + groupKey + '[]"][value="' + CSS.escape(value) + '"]');
					var countEl = input && input.closest('label') ? input.closest('label').querySelector('.eporta-filter-count') : null;
					if (countEl) countEl.textContent = groupCounts[value];
				});
			});
		}
		if (submitBtn && typeof meta.foundCount === 'number') {
			submitBtn.textContent = 'Показать ' + meta.foundCount + ' ' + meta.foundLabel;
		}
	}

	function apply() {
		var seq = ++reqSeq;
		var curtain = showCurtain();

		var queryStr = new URLSearchParams(new FormData(form)).toString();
		var pageUrl = location.pathname + (queryStr ? '?' + queryStr : '');
		var ajaxUrl = location.pathname + '?' + (queryStr ? queryStr + '&' : '') + 'eporta_ajax=grid';

		fetch(ajaxUrl, { credentials: 'same-origin' })
			.then(function (r) { return r.text(); })
			.then(function (html) {
				if (seq !== reqSeq) return;
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var newGrid = doc.querySelector('.eporta-product-grid');
				var newPager = doc.getElementById('eportaCatalogPager');
				var newLoadMoreBtn = doc.getElementById('eportaCatalogLoadMore');
				var metaScript = doc.getElementById('eportaAjaxMeta');
				var meta = null;
				if (metaScript) {
					try { meta = JSON.parse(metaScript.textContent); } catch (e) {}
				}

				var oldGrid = grid.querySelector('.eporta-product-grid');
				if (oldGrid && newGrid) oldGrid.replaceWith(newGrid);
				var oldPager = document.getElementById('eportaCatalogPager');
				if (oldPager) {
					if (newPager) oldPager.replaceWith(newPager); else oldPager.remove();
				}
				var oldLoadMoreBtn = document.getElementById('eportaCatalogLoadMore');
				if (oldLoadMoreBtn) {
					if (newLoadMoreBtn) oldLoadMoreBtn.replaceWith(newLoadMoreBtn); else oldLoadMoreBtn.remove();
				} else if (newLoadMoreBtn) {
					grid.appendChild(newLoadMoreBtn);
				}

				updateSidebarCounts(meta);
				history.pushState({ eportaCatalogFilter: true }, '', pageUrl);
				revealCurtain(curtain);
			})
			.catch(function () {
				if (seq !== reqSeq) return;
				if (curtain.parentNode) curtain.parentNode.removeChild(curtain);
			});
	}

	form.addEventListener('change', function (e) {
		var t = e.target;
		if (t.matches('input[type="checkbox"]') || t.matches('input[type="number"]')) apply();
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		apply();
		document.body.classList.remove('eporta-filters-open');
	});

	// Назад/вперёд по истории (URL менялся через pushState выше) — состояние формы/сайдбара
	// проще и надёжнее просто перезагрузить с сервера, чем восстанавливать на клиенте.
	window.addEventListener('popstate', function (e) {
		if (e.state && e.state.eportaCatalogFilter) location.reload();
	});
});

// ---- Карточка товара в каталоге и карточка модели в коллекции: наведение на кружок цвета
// меняет фото (и, у товарной карточки, цену) на месте ----
// (catalog.section/.default/template.php И блок "Модели коллекции" в catalog/index.php — оба
// используют .product-card/.product-swatches .swatch, поэтому один общий делегированный
// обработчик). Без клика — превью по hover, курсор ушёл со свотчей → карточка возвращается к
// дефолтному фото/цене (data-default-* на самой карточке, тот же HTML, что отрендерен по
// умолчанию). Ссылка остаётся рабочей — клик/ctrl/middle-click как обычно ведут на страницу того
// цвета. Делегирование на document через mouseover/mouseout (в отличие от mouseenter/mouseleave —
// всплывают), а не на сетку — карточки подгружаются AJAX'ом (кнопка "Показать ещё" выше).
// Карточка модели хранит только data-default-picture (без data-default-price) — цена там
// "От X ₽" за всю линейку, а не за конкретный цвет, поэтому data-price на её свотчах не
// рендерится и подмена цены просто не происходит (см. guard ниже). .eporta-model-card в
// селекторе ниже — на случай старой закэшированной разметки, сама карточка теперь рендерится
// как .product-card (задача 08.09.2026).
function eportaApplySwatchPreview(swatch) {
	var card = swatch.closest('.product-card, .eporta-model-card');
	if (!card) return;
	var pictureHtml = swatch.getAttribute('data-picture');
	if (pictureHtml) {
		var imgWrap = card.querySelector('.img-wrap');
		var oldPicture = imgWrap && imgWrap.querySelector('picture, .img-noimg');
		if (oldPicture) oldPicture.outerHTML = pictureHtml;
	}
	var priceHtml = swatch.getAttribute('data-price');
	if (priceHtml) {
		var priceBlock = card.querySelector('.price-block');
		if (priceBlock) priceBlock.innerHTML = priceHtml;
	}
}

function eportaRevertCardDefault(card) {
	if (!card) return;
	var imgWrap = card.querySelector('.img-wrap');
	var oldPicture = imgWrap && imgWrap.querySelector('picture, .img-noimg');
	var defaultPicture = card.getAttribute('data-default-picture');
	if (oldPicture && defaultPicture) oldPicture.outerHTML = defaultPicture;
	var priceBlock = card.querySelector('.price-block');
	var defaultPrice = card.getAttribute('data-default-price');
	if (priceBlock && defaultPrice) priceBlock.innerHTML = defaultPrice;
}

document.addEventListener('mouseover', function (e) {
	var swatch = e.target.closest('.product-swatches .swatch');
	if (!swatch) return;
	eportaApplySwatchPreview(swatch);
});

document.addEventListener('mouseout', function (e) {
	var swatchesWrap = e.target.closest('.product-swatches');
	if (!swatchesWrap) return;
	// Курсор всё ещё внутри той же строки свотчей (перешёл на соседний кружок) — ничего не
	// возвращаем, его mouseover сам подставит превью следующего цвета без мигания дефолтом.
	if (e.relatedTarget && swatchesWrap.contains(e.relatedTarget)) return;
	eportaRevertCardDefault(swatchesWrap.closest('.product-card, .eporta-model-card'));
});
