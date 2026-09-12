<?php
// Заводит UF-поле UF_SQUARE_CARDS (Y/N) на секциях IBLOCK 19 — задача 12.09.2026: коллекции с
// интерьерными (квадратными 1200x1200/520x520) фото товара вместо обычных портретных. Флаг
// ставится на секцию-коллекцию (см. local/lib/eporta_collections.php) — все модели внутри такой
// коллекции показываются в квадратной 3-в-ряд раскладке на странице /catalog/collections/<code>/.
// Первая коллекция — Invi (скрытые двери), тем же полем позже помечаются входные двери и
// перегородки (там тот же формат фото). По паттерну scripts/add_section_banner_ufields.php.
// Идемпотентно. Прогнать один раз на проде через SSH ДО деплоя кода, который на это поле
// рассчитывает (catalog/index.php, catalog.section/.default/template.php, local/admin_tools/eporta_collections/).
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;
$entityId = 'IBLOCK_' . $IBLOCK_ID . '_SECTION';

$fieldDef = [
    'USER_TYPE_ID' => 'string',
    'XML_ID' => 'UF_SQUARE_CARDS',
    'FIELD_NAME' => 'UF_SQUARE_CARDS',
    'SORT' => 240,
    'MULTIPLE' => 'N',
    'MANDATORY' => 'N',
    'EDIT_FORM_LABEL' => ['ru' => 'Квадратные карточки товара (Y/N)'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Квадратные карточки'],
];

$existing = CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => 'UF_SQUARE_CARDS'])->Fetch();
if ($existing) {
    echo "Поле UF_SQUARE_CARDS уже существует (ID={$existing['ID']}), пропускаю\n";
} else {
    $utObj = new CUserTypeEntity;
    $id = $utObj->Add($fieldDef + ['ENTITY_ID' => $entityId]);
    echo $id ? "Поле UF_SQUARE_CARDS создано, ID=$id\n" : "Ошибка: {$utObj->LAST_ERROR}\n";
}

echo "Готово.\n";
