<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Корзина");

// Дев-превью нового шаблона eporta: статичная вёрстка (Этап 4, Фаза A).
// Боевой dresscode:sale.basket.basket не трогаем — при любом другом активном
// шаблоне страница работает как прежде.
$isEportaTemplate = defined("SITE_TEMPLATE_PATH") && basename(SITE_TEMPLATE_PATH) === "eporta";
?>
<?if ($isEportaTemplate):?>

	<!-- Хлебные крошки -->
	<div class="breadcrumb" style="padding:12px 56px 0"><a href="/">Главная</a> · Корзина</div>

	<!-- Заголовок -->
	<div style="display:flex;align-items:baseline;justify-content:space-between;padding:10px 56px 4px">
		<h1 style="margin:0;font:800 27px 'Manrope';letter-spacing:-0.01em">Корзина <span style="font:700 16px 'Manrope';color:#a39e95">· 2 комплекта, 7 позиций</span></h1>
		<span style="font:600 13px;color:#8a857b;cursor:pointer">Очистить корзину</span>
	</div>

	<!-- Две колонки -->
	<div style="display:flex;gap:22px;padding:14px 56px 30px;align-items:flex-start">

		<!-- Левая: комплекты -->
		<div style="flex:1.62">

			<!-- Комплект 1 -->
			<div id="cart-row-k1" style="border:1px solid #efece6;border-radius:16px;overflow:hidden;margin-bottom:16px;transition:box-shadow .2s">
				<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:linear-gradient(180deg,#faf8f4,#f6f4ef);border-bottom:1px solid #efece6">
					<div style="display:flex;align-items:center;gap:11px">
						<span style="font:800 14.5px 'Manrope'">Комплект 1</span>
						<span style="font:700 11px 'Manrope';color:#c2670a;background:#fbecd9;padding:4px 10px;border-radius:20px">Комплект под ключ</span>
					</div>
					<div id="kit1total" style="font:800 15px 'Manrope'">22 530 ₽</div>
				</div>

				<!-- Дверь -->
				<div style="display:flex;gap:15px;padding:16px 18px;align-items:flex-start">
					<div style="width:78px;height:104px;flex:none;border-radius:12px;border:1px solid #efece6;padding:4px;box-sizing:border-box;background:#fff">
						<img src="<?=SITE_TEMPLATE_PATH?>/assets/img/hit-1.jpg" style="width:100%;height:100%;object-fit:contain;background:#f6f4ef;border-radius:8px" alt="Дверь">
					</div>
					<div style="flex:1">
						<div style="font:700 14.5px 'Manrope'">Дверь Dorsum 11.1 экошпон</div>
						<div style="font:500 12px;color:#8a857b;margin-top:4px">Ширина 800 мм · арт. 1366</div>
						<div style="display:flex;align-items:center;gap:14px;margin-top:12px">
							<div style="display:flex;align-items:center;border:1.5px solid #e7e3db;border-radius:9px;overflow:hidden">
								<span onclick="changeQty(this,-1,'item-d1','7880')" style="width:32px;height:34px;display:flex;align-items:center;justify-content:center;font:700 16px 'Manrope';color:#8a857b;cursor:pointer">−</span>
								<span id="item-d1-qty" style="width:34px;text-align:center;font:700 13.5px 'Manrope'">1</span>
								<span onclick="changeQty(this,1,'item-d1','7880')" style="width:32px;height:34px;display:flex;align-items:center;justify-content:center;font:700 16px 'Manrope';color:#c2670a;cursor:pointer">+</span>
							</div>
							<span style="font:500 12.5px;color:#8a857b;cursor:pointer" onclick="removeItem('cart-row-k1')">Удалить</span>
						</div>
					</div>
					<div style="text-align:right;flex:none">
						<div id="item-d1-price" style="font:800 17px 'Manrope'">7 880 ₽</div>
						<div style="font:500 11.5px;color:#a39e95;text-decoration:line-through">9 850 ₽</div>
					</div>
				</div>

				<!-- Доп-позиции комплекта 1 -->
				<div style="display:flex;gap:13px;padding:11px 18px;align-items:center;border-top:1px dashed #eceae4">
					<span style="width:8px;height:8px;border-radius:50%;background:#e8820a;flex:none"></span>
					<div style="flex:1"><div style="font:600 13px 'Manrope'">Коробка телескопическая</div><div style="font:500 11.5px;color:#a39e95">Экошпон белый, 2050×70 мм</div></div>
					<div style="font:700 14px 'Manrope'">3 200 ₽</div>
				</div>
				<div style="display:flex;gap:13px;padding:11px 18px;align-items:center;border-top:1px dashed #eceae4">
					<span style="width:8px;height:8px;border-radius:50%;background:#e8820a;flex:none"></span>
					<div style="flex:1"><div style="font:600 13px 'Manrope'">Наличник Г-образный <span style="color:#a39e95;font-weight:600">×5</span></div><div style="font:500 11.5px;color:#a39e95">Экошпон белый, 70 мм</div></div>
					<div style="font:700 14px 'Manrope'">1 750 ₽</div>
				</div>
				<div style="display:flex;gap:13px;padding:11px 18px;align-items:center;border-top:1px dashed #eceae4">
					<span style="width:8px;height:8px;border-radius:50%;background:#e8820a;flex:none"></span>
					<div style="flex:1"><div style="font:600 13px 'Manrope'">Замок магнитный + ручка</div><div style="font:500 11.5px;color:#a39e95">MC85BL чёрный, комплект</div></div>
					<div style="font:700 14px 'Manrope'">2 100 ₽</div>
				</div>
				<div style="display:flex;gap:13px;padding:11px 18px;align-items:center;border-top:1px dashed #eceae4">
					<span style="width:8px;height:8px;border-radius:50%;background:#e8820a;flex:none"></span>
					<div style="flex:1"><div style="font:600 13px 'Manrope'">Петли скрытые <span style="color:#a39e95;font-weight:600">×2</span></div><div style="font:500 11.5px;color:#a39e95">Reze 3D, хром матовый</div></div>
					<div style="font:700 14px 'Manrope'">1 850 ₽</div>
				</div>
				<div style="display:flex;gap:13px;padding:11px 18px 14px;align-items:center;border-top:1px dashed #eceae4">
					<span style="width:8px;height:8px;border-radius:50%;background:#e8820a;flex:none"></span>
					<div style="flex:1"><div style="font:600 13px 'Manrope'">Установка «под ключ»</div><div style="font:500 11.5px;color:#a39e95">Демонтаж старой + монтаж новой двери</div></div>
					<div style="font:700 14px 'Manrope'">5 750 ₽</div>
				</div>
			</div>

			<!-- Комплект 2 -->
			<div id="cart-row-k2" style="border:1px solid #efece6;border-radius:16px;overflow:hidden;margin-bottom:16px;transition:box-shadow .2s">
				<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:linear-gradient(180deg,#faf8f4,#f6f4ef);border-bottom:1px solid #efece6">
					<div style="display:flex;align-items:center;gap:11px">
						<span style="font:800 14.5px 'Manrope'">Комплект 2</span>
						<span style="font:700 11px 'Manrope';color:#8a857b;background:#f0ede7;padding:4px 10px;border-radius:20px">Только полотно</span>
					</div>
					<div style="font:800 15px 'Manrope'">4 500 ₽</div>
				</div>
				<div style="display:flex;gap:15px;padding:16px 18px;align-items:flex-start">
					<div style="width:78px;height:104px;flex:none;border-radius:12px;border:1px solid #efece6;padding:4px;box-sizing:border-box;background:#fff">
						<img src="<?=SITE_TEMPLATE_PATH?>/assets/img/hit-2.jpg" style="width:100%;height:100%;object-fit:contain;background:#f6f4ef;border-radius:8px" alt="Дверь">
					</div>
					<div style="flex:1">
						<div style="font:700 14.5px 'Manrope'">Дверь Vilis 21 экошпон, стекло</div>
						<div style="font:500 12px;color:#8a857b;margin-top:4px">Ширина 700 мм · арт. 2104</div>
						<div style="display:flex;align-items:center;gap:14px;margin-top:12px">
							<div style="display:flex;align-items:center;border:1.5px solid #e7e3db;border-radius:9px;overflow:hidden">
								<span style="width:32px;height:34px;display:flex;align-items:center;justify-content:center;font:700 16px 'Manrope';color:#8a857b;cursor:pointer">−</span>
								<span style="width:34px;text-align:center;font:700 13.5px 'Manrope'">1</span>
								<span style="width:32px;height:34px;display:flex;align-items:center;justify-content:center;font:700 16px 'Manrope';color:#c2670a;cursor:pointer">+</span>
							</div>
							<span style="font:500 12.5px;color:#8a857b;cursor:pointer" onclick="removeItem('cart-row-k2')">Удалить</span>
						</div>
					</div>
					<div style="text-align:right;flex:none"><div style="font:800 17px 'Manrope'">4 500 ₽</div></div>
				</div>
			</div>

			<!-- С этими дверями берут -->
			<div style="border-top:1px solid #efece6;padding-top:24px;margin-top:6px">
				<div style="font:800 17px 'Manrope';margin-bottom:14px">С этими дверями берут</div>
				<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
					<div style="display:flex;gap:12px;align-items:center;border:1px solid #efece6;border-radius:13px;padding:12px">
						<img src="<?=SITE_TEMPLATE_PATH?>/assets/img/sim-1.jpg" style="width:56px;height:56px;flex:none;object-fit:contain;background:#f6f4ef;border-radius:9px" alt="">
						<div style="flex:1"><div style="font:600 12.5px 'Manrope'">Порог алюминиевый</div><div style="font:800 14px 'Manrope';margin-top:4px">640 ₽</div></div>
						<span style="width:30px;height:30px;border-radius:8px;background:#fbecd9;color:#c2670a;font:700 17px;display:flex;align-items:center;justify-content:center;cursor:pointer">+</span>
					</div>
					<div style="display:flex;gap:12px;align-items:center;border:1px solid #efece6;border-radius:13px;padding:12px">
						<img src="<?=SITE_TEMPLATE_PATH?>/assets/img/sim-1.jpg" style="width:56px;height:56px;flex:none;object-fit:contain;background:#f6f4ef;border-radius:9px" alt="">
						<div style="flex:1"><div style="font:600 12.5px 'Manrope'">Уплотнитель</div><div style="font:800 14px 'Manrope';margin-top:4px">380 ₽</div></div>
						<span style="width:30px;height:30px;border-radius:8px;background:#fbecd9;color:#c2670a;font:700 17px;display:flex;align-items:center;justify-content:center;cursor:pointer">+</span>
					</div>
					<div style="display:flex;gap:12px;align-items:center;border:1px solid #efece6;border-radius:13px;padding:12px">
						<img src="<?=SITE_TEMPLATE_PATH?>/assets/img/sim-1.jpg" style="width:56px;height:56px;flex:none;object-fit:contain;background:#f6f4ef;border-radius:9px" alt="">
						<div style="flex:1"><div style="font:600 12.5px 'Manrope'">Набор по уходу</div><div style="font:800 14px 'Manrope';margin-top:4px">520 ₽</div></div>
						<span style="width:30px;height:30px;border-radius:8px;background:#fbecd9;color:#c2670a;font:700 17px;display:flex;align-items:center;justify-content:center;cursor:pointer">+</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Правая: итог (sticky) -->
		<div style="flex:1;position:sticky;top:20px;align-self:flex-start">
			<div style="border:1px solid #efece6;border-radius:18px;padding:22px 22px 24px;background:linear-gradient(180deg,#ffffff,#fdfbf7);box-shadow:0 18px 44px rgba(27,26,23,.08)">
				<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
					<div style="width:34px;height:34px;flex:none;border-radius:10px;background:#fbecd9;color:#c2670a;display:flex;align-items:center;justify-content:center;font:800 16px 'Manrope'">₽</div>
					<div style="font:800 18px 'Manrope'">Итого</div>
				</div>
				<div style="display:flex;justify-content:space-between;padding:7px 0;font:500 13.5px 'Manrope';color:#3a3631"><span>Товары, 7 шт.</span><span id="subtotal" style="font-weight:700;color:#1b1a17">27 030 ₽</span></div>
				<div style="display:flex;justify-content:space-between;padding:7px 0;font:500 13.5px 'Manrope';color:#3a3631"><span>Вы экономите</span><span style="font-weight:700;color:#c2670a">−1 970 ₽</span></div>
				<div style="display:flex;justify-content:space-between;padding:7px 0;font:500 13.5px 'Manrope';color:#3a3631"><span>Доставка по Москве</span><span style="font-weight:700;color:#1f8a4c">Бесплатно</span></div>
				<div style="border-top:1px solid #efece6;margin:12px 0;padding-top:14px;display:flex;align-items:baseline;justify-content:space-between">
					<span style="font:700 15px 'Manrope'">К оплате</span><span id="totalPrice" style="font:800 26px 'Manrope';letter-spacing:-0.01em">27 030 ₽</span>
				</div>

				<!-- Промокод (пунктир) -->
				<div style="display:flex;gap:8px;margin:6px 0 16px">
					<div style="flex:1;border:1.5px dashed #d8d2c4;border-radius:10px;padding:11px 13px;font:500 13px 'Manrope';color:#a39e95;display:flex;align-items:center;gap:8px"><span style="color:#c2bdb2">%</span> Промокод</div>
					<span style="font:700 13px 'Manrope';color:#1b1a17;border:1.5px solid #1b1a17;border-radius:10px;padding:11px 16px;cursor:pointer">Применить</span>
				</div>

				<a href="/personal/order/make/" class="btn-primary">Оформить заказ</a>
				<a href="#" class="btn-secondary" style="margin-top:10px;font-size:13px;padding:13px">Купить в 1 клик</a>

				<div style="margin-top:18px;display:flex;flex-direction:column;gap:11px">
					<div style="display:flex;align-items:center;gap:9px;font:600 12.5px 'Manrope';color:#3a3631">
						<span style="width:16px;height:16px;border-radius:5px;background:#e6f2ea;position:relative;flex:none"><span style="position:absolute;left:5.5px;top:2.5px;width:4px;height:7px;border:solid #1f8a4c;border-width:0 2px 2px 0;transform:rotate(45deg)"></span></span>
						Гарантия 2 года на все двери
					</div>
					<div style="display:flex;align-items:center;gap:9px;font:600 12.5px 'Manrope';color:#3a3631">
						<span style="width:16px;height:16px;border-radius:5px;background:#e6f2ea;position:relative;flex:none"><span style="position:absolute;left:5.5px;top:2.5px;width:4px;height:7px;border:solid #1f8a4c;border-width:0 2px 2px 0;transform:rotate(45deg)"></span></span>
						Возврат в течение 14 дней
					</div>
					<div style="display:flex;align-items:center;gap:9px;font:600 12.5px 'Manrope';color:#3a3631">
						<span style="width:16px;height:16px;border-radius:5px;background:#e6f2ea;position:relative;flex:none"><span style="position:absolute;left:5.5px;top:2.5px;width:4px;height:7px;border:solid #1f8a4c;border-width:0 2px 2px 0;transform:rotate(45deg)"></span></span>
						Оплата картой, наличными, в рассрочку
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
	function changeQty(btn, delta, id, priceStr) {
		var qtyEl = document.getElementById(id + '-qty');
		var priceEl = document.getElementById(id + '-price');
		var qty = parseInt(qtyEl.textContent) + delta;
		if (qty < 1) qty = 1;
		qtyEl.textContent = qty;
		var basePrice = parseInt(priceStr);
		priceEl.textContent = (basePrice * qty).toLocaleString('ru-RU') + ' ₽';
	}

	function removeItem(id) {
		var el = document.getElementById(id);
		if (el) { el.style.opacity = '0.3'; el.style.pointerEvents = 'none'; }
	}
	</script>

<?else:?>
	<h1>Корзина</h1><?$APPLICATION->IncludeComponent("dresscode:sale.basket.basket", "standartOrder", array(
			"HIDE_MEASURES" => "N",
			"BASKET_PICTURE_WIDTH" => "220",
			"BASKET_PICTURE_HEIGHT" => "200",
			"HIDE_NOT_AVAILABLE" => "N",
			"PRODUCT_PRICE_CODE" => array(
			),
			"GIFT_CONVERT_CURRENCY" => "N",
			"PATH_TO_PAYMENT" => "/personal/cart/payment/",
			"CACHE_TYPE" => "A",
			"CACHE_TIME" => "36000000",
			"COMPONENT_TEMPLATE" => ".default",
			"PATH_TO_PAYMENT" => "",
			"MIN_SUM_TO_PAYMENT" => "",
			"REGISTER_USER" => "Y",
			"PART_STORES_AVAILABLE" => "",
			"ALL_STORES_AVAILABLE" => "",
			"NO_STORES_AVAILABLE" => ""
		),
		false
	);?><br />
<?endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
