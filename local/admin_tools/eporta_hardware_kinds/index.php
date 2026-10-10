<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}
if (!eportaHardwareKindsUserHasAccess()) {
    ?>
    <!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Нет доступа</title></head>
    <body style="font-family: sans-serif; padding: 40px;">
        <h1>Нет доступа</h1>
        <p>У вашей учётной записи нет прав на запись в каталог (IBLOCK 19). Обратитесь к администратору сайта.</p>
    </body></html>
    <?php
    exit;
}

$sessid = bitrix_sessid();
$kinds = eportaHardwareKindsAdminList();
$parentMissing = eportaHardwareKindsParentId() <= 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Типы фурнитуры (eporta.ru)</title>
<style>
    table { border-collapse: collapse; background: #fff; }
    th, td { border: 1px solid #ddd; padding: 6px 10px; font-size: 13.5px; text-align: left; vertical-align: middle; }
    th { background: #f6f6f6; }
    input[type=text] { width: 220px; font-size: 14px; padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; }
    input[type=number] { width: 70px; padding: 5px 6px; border: 1px solid #ccc; border-radius: 4px; }
    button { font-size: 13px; padding: 5px 12px; border: 1px solid #bbb; border-radius: 4px; background: #f6f6f6; cursor: pointer; }
    button.primary { background: #2f9e44; border-color: #2f9e44; color: #fff; }
    .muted { color: #888; font-size: 12px; }
    .status { font-size: 13px; min-height: 18px; margin: 10px 0; }
    .status.ok { color: #2f9e44; } .status.err { color: #c0392b; }
    .add { margin-top: 18px; display: flex; gap: 10px; align-items: center; }
</style>
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_storefront/nav.php'; eportaStorefrontNav('hardware_kinds'); ?>
<h1>Типы фурнитуры</h1>
<p class="hint">
    Типы изделий раздела «Фурнитура» (Ручки, Петли…): кнопки над списком фурнитуры на сайте и колонка «Коллекция» в
    <a href="/local/admin_tools/eporta_import/">импорте</a> — товар попадает в тип по названию. Здесь можно переименовать тип, изменить
    порядок кнопок, скрыть тип или добавить новый. Тип без товаров на сайте не показывается. Адрес типа (код) не меняется.
    Коллекции дверей — в <a href="/local/admin_tools/eporta_collections/">админке коллекций</a>.
</p>
<?php if ($parentMissing): ?>
<p style="color:#c0392b"><b>Раздел «Фурнитура» ещё не создан.</b> Запустите на сервере scripts/add_hardware_kinds_sections.php.</p>
<?php else: ?>
<table id="tbl">
    <tr><th>Название</th><th>Порядок</th><th>Показывать</th><th>Товаров</th><th>Код</th><th></th></tr>
    <?php foreach ($kinds as $kind): ?>
    <tr data-id="<?= (int)$kind['ID'] ?>">
        <td><input type="text" class="f-name" maxlength="100" value="<?= htmlspecialchars($kind['NAME'], ENT_QUOTES) ?>"></td>
        <td><input type="number" class="f-sort" value="<?= (int)$kind['SORT'] ?>"></td>
        <td><input type="checkbox" class="f-active"<?= $kind['ACTIVE'] === 'Y' ? ' checked' : '' ?>></td>
        <td><?= (int)$kind['COUNT'] ?></td>
        <td class="muted"><a href="/catalog/?category=hardware&amp;type=<?= htmlspecialchars(rawurlencode($kind['CODE']), ENT_QUOTES) ?>" target="_blank"><?= htmlspecialchars($kind['CODE'], ENT_QUOTES) ?></a></td>
        <td><button type="button" class="f-save">Сохранить</button></td>
    </tr>
    <?php endforeach; ?>
</table>
<div class="add">
    <input type="text" id="newName" maxlength="100" placeholder="Название нового типа">
    <button type="button" class="primary" id="add">+ Добавить тип</button>
</div>
<div class="status" id="status"></div>
<script>
(function () {
    var SESSID = <?= json_encode($sessid) ?>;
    var statusEl = document.getElementById('status');
    function setStatus(text, cls) { statusEl.textContent = text; statusEl.className = 'status' + (cls ? ' ' + cls : ''); }
    async function post(fields) {
        var fd = new FormData();
        fd.append('sessid', SESSID);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        var r = await fetch('ajax.php', { method: 'POST', body: fd });
        return r.json();
    }
    document.querySelectorAll('#tbl tr[data-id]').forEach(function (tr) {
        tr.querySelector('.f-save').onclick = async function () {
            setStatus('Сохраняю…');
            try {
                var data = await post({
                    action: 'update', id: tr.dataset.id,
                    name: tr.querySelector('.f-name').value, sort: tr.querySelector('.f-sort').value,
                    active: tr.querySelector('.f-active').checked ? 'Y' : 'N'
                });
                setStatus(data.ok ? 'Сохранено' : (data.error || 'Ошибка'), data.ok ? 'ok' : 'err');
            } catch (e) { setStatus('Ошибка сети: ' + e.message, 'err'); }
        };
    });
    document.getElementById('add').onclick = async function () {
        var name = document.getElementById('newName').value;
        setStatus('Создаю…');
        try {
            var data = await post({ action: 'create', name: name });
            if (data.ok) { location.reload(); } else { setStatus(data.error || 'Ошибка', 'err'); }
        } catch (e) { setStatus('Ошибка сети: ' + e.message, 'err'); }
    };
})();
</script>
<?php endif; ?>
</body>
</html>
