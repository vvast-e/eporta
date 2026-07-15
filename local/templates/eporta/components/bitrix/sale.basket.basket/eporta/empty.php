<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// EPORTA: пустое состояние корзины по эталону «EPORTA Прототип» (#cart, ветка cartEmpty) —
// см. local/templates/eporta/template_styles.css, блок "Корзина (bx-basket)".
$emptyHintPath = !empty($arParams['EMPTY_BASKET_HINT_PATH']) ? $arParams['EMPTY_BASKET_HINT_PATH'] : '/catalog/';
?>

<div class="basket-empty">
	<div class="basket-empty-title">Корзина пуста</div>
	<div class="basket-empty-desc">Добавьте двери из каталога, чтобы оформить заказ</div>
	<a href="<?=htmlspecialcharsbx($emptyHintPath)?>" class="basket-empty-cta">Перейти в каталог</a>
</div>