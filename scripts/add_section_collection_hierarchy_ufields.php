<?php
// Только консоль: скрипт подставляет права/меняет данные и не должен выполняться по HTTP (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Заводит UF-поля секций IBLOCK 19 под иерархию коллекций и блоки главной:
//   UF_PARENT_COLLECTION (integer) — ID коллекции-родителя; 0/пусто = коллекция верхнего уровня.
//     Подколлекция физически остаётся под разделом 183, как и раньше (URL, слот баннера, подсчёт
//     товаров не меняются) — меняется только логическая связь.
//   UF_HOME_BLOCK (integer) — блок плитки на главной: 1 (левый) или 2 (правый), пусто = 1.
// По паттерну scripts/add_section_square_cards_ufield.php. Идемпотентно. Прогнать один раз на
// проде через SSH ДО деплоя кода, который на эти поля рассчитывает (local/lib/eporta_collections.php,
// local/admin_tools/eporta_collections/).
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;
$entityId = 'IBLOCK_' . $IBLOCK_ID . '_SECTION';

$fields = [
    [
        'FIELD_NAME' => 'UF_PARENT_COLLECTION',
        'SORT' => 250,
        'EDIT_FORM_LABEL' => ['ru' => 'Родительская коллекция (ID)'],
        'LIST_COLUMN_LABEL' => ['ru' => 'Родитель коллекции'],
    ],
    [
        'FIELD_NAME' => 'UF_HOME_BLOCK',
        'SORT' => 260,
        'EDIT_FORM_LABEL' => ['ru' => 'Блок на главной (1 или 2)'],
        'LIST_COLUMN_LABEL' => ['ru' => 'Блок на главной'],
    ],
];

foreach ($fields as $field) {
    $name = $field['FIELD_NAME'];
    $existing = CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $name])->Fetch();
    if ($existing) {
        echo "Поле $name уже существует (ID={$existing['ID']}), пропускаю\n";
        continue;
    }
    $utObj = new CUserTypeEntity;
    $id = $utObj->Add([
        'ENTITY_ID' => $entityId,
        'USER_TYPE_ID' => 'integer',
        'XML_ID' => $name,
        'MULTIPLE' => 'N',
        'MANDATORY' => 'N',
    ] + $field);
    echo $id ? "Поле $name создано, ID=$id\n" : "Ошибка $name: {$utObj->LAST_ERROR}\n";
}

echo "Готово.\n";
