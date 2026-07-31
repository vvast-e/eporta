// EPORTA — сравнение товаров (Этап 6, Фаза D)
// AJAX-контракт — существующий рабочий модуль dw.deluxe (bitrix/modules/dw.deluxe/include/api/ajax.php,
// act=addCompare/compDEL/clearCompare/addCart), не наш код, переиспользуется как есть.
//
// Все четыре action защищены check_bitrix_sessid() и читают id строго через POST-тело
// ($request->getPost(), не $_REQUEST) — обычный GET-запрос без токена не выполнял действие
// (не 403/400, а мимо всех веток, до реального 404 роутинга Bitrix). window.BX_SESSID
// задаётся глобально в header.php (bitrix_sessid()).

function eportaAjaxPost(act, params) {
	var body = 'sessid=' + encodeURIComponent(window.BX_SESSID || '');
	for (var key in params) {
		body += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
	}
	return fetch('/ajax.php?act=' + encodeURIComponent(act), {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: body,
	});
}

function eportaCompareBadge(n) {
	document.querySelectorAll('.compare-btn .badge').forEach(function (b) { b.textContent = n; });
}

function eportaCompareCount() {
	var badge = document.querySelector('.compare-btn .badge');
	return badge ? (parseInt(badge.textContent, 10) || 0) : 0;
}

function addCompare(e, id) {
	e.preventDefault();
	e.stopPropagation();
	var btn = e.currentTarget;
	eportaAjaxPost('addCompare', { id: id })
		.then(function (r) { return r.json(); })
		.then(function () {
			eportaCompareBadge(eportaCompareCount() + 1);
			var orig = btn.textContent;
			btn.textContent = '✓';
			btn.classList.add('added');
			setTimeout(function () { btn.textContent = orig; btn.classList.remove('added'); }, 1500);
		})
		.catch(function () {});
}

function removeCompareItem(id) {
	eportaAjaxPost('compDEL', { id: id })
		.then(function () { window.location.reload(); })
		.catch(function () {});
}

function clearCompareAll() {
	eportaAjaxPost('clearCompare', {})
		.then(function () { window.location.reload(); })
		.catch(function () {});
}

// Общий обработчик "В корзину" через тот же act=addCart, что уже используется на /compare/.
// stopPropagation обязателен на карточках каталога — кнопка лежит внутри <a class="product-card">,
// без него клик дополнительно уводил бы на страницу товара.
function addCartAjax(e, id) {
	e.preventDefault();
	e.stopPropagation();
	var btn = e.currentTarget;
	var orig = btn.textContent;
	btn.style.pointerEvents = 'none';
	eportaAjaxPost('addCart', { id: id })
		.then(function (r) { return r.json(); })
		.then(function (data) {
			btn.style.pointerEvents = '';
			if (!data || data.status !== true) throw new Error();
			if (typeof eportaCartBadge === 'function') eportaCartBadge(eportaCartCount() + 1);
			btn.textContent = '✓ Добавлено';
			setTimeout(function () { btn.textContent = orig; }, 1500);
		})
		.catch(function () {
			btn.style.pointerEvents = '';
			btn.textContent = 'Ошибка';
			setTimeout(function () { btn.textContent = orig; }, 1500);
		});
}
// Алиас под старым именем — используется в шаблоне /compare/.
function addCartFromCompare(e, id) { return addCartAjax(e, id); }
