<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?php
// Общая таб-навигация ЛК (Этап 6). Подключается через
// `$active = 'profile'; require $_SERVER["DOCUMENT_ROOT"] . SITE_TEMPLATE_PATH . '/include/lk-tabs.php';`
// Пункт "Профили покупателя" сознательно не включён — убран из навигации по решению
// пользователя (см. память project_lk_redesign_source), сама страница
// bitrix:sale.personal.profile при этом не удаляется, просто больше никуда не ведёт.
$eportaLkTabs = [
	'profile'   => ['/personal/', 'Персональные данные'],
	'orders'    => ['/personal/order/', 'История заказов'],
	'bill'      => ['/personal/bill/', 'Личный счёт'],
	'cart'      => ['/personal/cart/', 'Моя корзина'],
	'subscribe' => ['/personal/subscribe/', 'Подписка на рассылку'],
	'compare'   => ['/compare/', 'Сравнение'],
];
$eportaLkActive = $active ?? '';
?>
<div class="lk-tabs">
<?php foreach ($eportaLkTabs as $key => [$url, $label]): ?>
	<?php if ($key === $eportaLkActive): ?>
		<span class="lk-tab active"><?= htmlspecialcharsbx($label) ?></span>
	<?php else: ?>
		<a href="<?= $url ?>" class="lk-tab"><?= htmlspecialcharsbx($label) ?></a>
	<?php endif; ?>
<?php endforeach; ?>
</div>
