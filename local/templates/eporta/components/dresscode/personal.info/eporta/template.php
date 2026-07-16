<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<!-- EPORTA: оверрайд dresscode:personal.info — поля формы (name-атрибуты) байт-в-байт
     как у вендорского .default (FIO/EMAIL/USER_MOBILE/USER_CITY/USER_ZIP/USER_STREET/
     USER_PASSWORD/USER_PASSWORD_CONFIRM), иначе сломается ajax.php (CUser::Update).
     Значения полей не экранируем дополнительно (как и вендор) — CUser::GetByID отдаёт уже
     сохранённые htmlspecialchars()-значения из ajax.php, повторное экранирование задвоило бы &. -->
<form method="GET" action="" id="lkProfileForm" novalidate>
	<div class="lk-body">
		<div class="lk-col">
			<div class="lk-field-label" style="margin-top:0">Фамилия Имя Отчество</div>
			<input class="epi" type="text" name="FIO" value="<?=$arResult["USER"]["LAST_NAME"]?> <?=$arResult["USER"]["NAME"]?> <?=$arResult["USER"]["SECOND_NAME"]?>">
			<div class="lk-field-label">E-mail</div>
			<input class="epi" type="text" name="EMAIL" value="<?=$arResult["USER"]["EMAIL"]?>">
			<div class="lk-field-label">Мобильный телефон</div>
			<input class="epi" type="text" name="USER_MOBILE" value="<?=$arResult["USER"]["PERSONAL_MOBILE"]?>" placeholder="+7 (___) ___-__-__">
			<div class="lk-field-label">Город</div>
			<input class="epi" type="text" name="USER_CITY" value="<?=$arResult["USER"]["PERSONAL_CITY"]?>" placeholder="Москва">
			<div class="lk-field-label">Почтовый индекс</div>
			<input class="epi" type="text" name="USER_ZIP" value="<?=$arResult["USER"]["PERSONAL_ZIP"]?>" placeholder="123456">
		</div>
		<div class="lk-col">
			<div class="lk-field-label" style="margin-top:0">Адрес</div>
			<textarea class="epi" name="USER_STREET" rows="4" placeholder="Москва, ул. Лесная 12"><?if(!empty($arResult["USER"]["PERSONAL_STREET"])):?><?=$arResult["USER"]["PERSONAL_STREET"]?><?else:?><?=$arResult["USER"]["CITY_NAME"]?><?endif;?></textarea>
			<div class="lk-subheading">Изменить пароль</div>
			<div class="lk-field-label" style="margin-top:0">Новый пароль</div>
			<input class="epi" type="password" name="USER_PASSWORD" value="" placeholder="••••••••" autocomplete="new-password">
			<div class="lk-field-label">Подтверждение пароля</div>
			<input class="epi" type="password" name="USER_PASSWORD_CONFIRM" value="" placeholder="••••••••" autocomplete="new-password">
			<button type="submit" class="lk-btn-primary" style="margin-top:22px">Сохранить изменения</button>
		</div>
	</div>
</form>

<div id="lkProfileModal" class="lk-profile-modal" hidden>
	<div class="lk-profile-modal-box">
		<div class="lk-profile-modal-heading"></div>
		<p class="lk-profile-modal-message"></p>
		<button type="button" class="lk-btn-primary lk-profile-modal-close">Закрыть окно</button>
	</div>
</div>

<script>
(function(){
	var ajaxDir = <?=\Bitrix\Main\Web\Json::encode($this->GetFolder())?>;
	var form = document.getElementById("lkProfileForm");
	var modal = document.getElementById("lkProfileModal");
	var heading = modal.querySelector(".lk-profile-modal-heading");
	var message = modal.querySelector(".lk-profile-modal-message");
	var shouldReload = false;

	form.addEventListener("submit", function (event) {
		event.preventDefault();
		var params = new URLSearchParams(new FormData(form));
		fetch(ajaxDir + "/ajax.php?" + params.toString(), {credentials: "same-origin"})
			.then(function (response) { return response.json(); })
			.then(function (data) {
				heading.textContent = data.heading || "";
				message.textContent = data.message || "";
				shouldReload = !!data.reload;
				modal.hidden = false;
			});
	});

	modal.querySelector(".lk-profile-modal-close").addEventListener("click", function () {
		if (shouldReload) {
			document.location.reload();
		} else {
			modal.hidden = true;
		}
	});
})();
</script>
