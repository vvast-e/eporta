// EPORTA — сравнение товаров (Этап 6, Фаза D)
// AJAX-контракт — существующий рабочий модуль dw.deluxe (bitrix/modules/dw.deluxe/include/api/ajax.php,
// act=addCompare/compDEL/clearCompare/addCart), не наш код, переиспользуется как есть.

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
	fetch('/ajax.php?act=addCompare&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
		.then(function (r) { return r.text(); })
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
	fetch('/ajax.php?act=compDEL&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
		.then(function () { window.location.reload(); })
		.catch(function () {});
}

function clearCompareAll() {
	fetch('/ajax.php?act=clearCompare', { credentials: 'same-origin' })
		.then(function () { window.location.reload(); })
		.catch(function () {});
}

function addCartFromCompare(e, id) {
	e.preventDefault();
	var btn = e.currentTarget;
	var orig = btn.textContent;
	btn.style.pointerEvents = 'none';
	fetch('/ajax.php?act=addCart&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
		.then(function (r) { return r.json(); })
		.then(function (data) {
			btn.style.pointerEvents = '';
			if (!data || data.status !== true) throw new Error();
			btn.textContent = '✓ Добавлено';
			setTimeout(function () { btn.textContent = orig; }, 1500);
		})
		.catch(function () {
			btn.style.pointerEvents = '';
			btn.textContent = 'Ошибка';
			setTimeout(function () { btn.textContent = orig; }, 1500);
		});
}
