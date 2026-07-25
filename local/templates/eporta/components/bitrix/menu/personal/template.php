<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
if (empty($arResult)) return;
?>
<div id="personalMenuWrap">
	<ul id="personalMenu">
		<?php foreach ($arResult as $arItem):
			if ($arParams["MAX_LEVEL"] == 1 && $arItem["DEPTH_LEVEL"] > 1) continue;
		?>
			<li><a href="<?= htmlspecialcharsbx($arItem["LINK"]) ?>"<?= $arItem["SELECTED"] ? ' class="selected"' : '' ?>><?= htmlspecialcharsbx($arItem["TEXT"]) ?></a></li>
		<?php endforeach; ?>
	</ul>
</div>
