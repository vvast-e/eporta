<?php
// Заводит UF-поля баннера страницы коллекции на секциях IBLOCK 19 (Этап 5b, 11.09.2026):
// UF_BANNER_OVERLAY (Y/N, затемнение), UF_BANNER_CTA_TEXT (текст кнопки), UF_BANNER_CTA_LINK
// (ссылка кнопки). Заголовок/подзаголовок баннера — уже существующие NAME/DESCRIPTION секции,
// новых полей под них не заводим (см. план Этапа 5b). Идемпотентно — повторный запуск не
// дублирует поля. Прогнать один раз на проде через SSH ДО деплоя кода, который на эти поля
// рассчитывает (catalog/index.php, local/admin_tools/eporta_collections/).
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;
$entityId = 'IBLOCK_' . $IBLOCK_ID . '_SECTION';

$newFields = [
    'UF_BANNER_OVERLAY' => [
        'USER_TYPE_ID' => 'string',
        'XML_ID' => 'UF_BANNER_OVERLAY',
        'FIELD_NAME' => 'UF_BANNER_OVERLAY',
        'SORT' => 210,
        'MULTIPLE' => 'N',
        'MANDATORY' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => 'Затемнение баннера (Y/N)'],
        'LIST_COLUMN_LABEL' => ['ru' => 'Затемнение баннера'],
    ],
    'UF_BANNER_CTA_TEXT' => [
        'USER_TYPE_ID' => 'string',
        'XML_ID' => 'UF_BANNER_CTA_TEXT',
        'FIELD_NAME' => 'UF_BANNER_CTA_TEXT',
        'SORT' => 220,
        'MULTIPLE' => 'N',
        'MANDATORY' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => 'Текст кнопки баннера'],
        'LIST_COLUMN_LABEL' => ['ru' => 'Текст кнопки баннера'],
    ],
    'UF_BANNER_CTA_LINK' => [
        'USER_TYPE_ID' => 'string',
        'XML_ID' => 'UF_BANNER_CTA_LINK',
        'FIELD_NAME' => 'UF_BANNER_CTA_LINK',
        'SORT' => 230,
        'MULTIPLE' => 'N',
        'MANDATORY' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => 'Ссылка кнопки баннера'],
        'LIST_COLUMN_LABEL' => ['ru' => 'Ссылка кнопки баннера'],
    ],
];

$utObj = new CUserTypeEntity;
foreach ($newFields as $code => $fieldDef) {
    $existing = CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $code])->Fetch();
    if ($existing) {
        echo "Поле $code уже существует (ID={$existing['ID']}), пропускаю\n";
        continue;
    }
    $fields = $fieldDef + ['ENTITY_ID' => $entityId];
    $id = $utObj->Add($fields);
    echo $id ? "Поле $code создано, ID=$id\n" : "Ошибка $code: {$utObj->LAST_ERROR}\n";
}

echo "Готово.\n";
