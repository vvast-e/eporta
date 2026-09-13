<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;

// Свойства, которых не хватает под новый формат выгрузки 1С (лист "Тест" /
// "Соответствие" в 2026-07-29-Vilis.xlsx). MODEL/COATING/COATING_COLOR/MAIN_COLOR/
// GLAZING/SIZES/COLLECTION/CML2_ARTICLE/STYLE/RATING уже существуют (см. память
// project-import-table-v2 и предыдущие сессии) — их не трогаем, кроме STYLE
// (см. фикс MULTIPLE ниже).
$toCreate = [
    'MANUFACTURER'    => ['NAME' => 'Производитель',            'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'BRAND'           => ['NAME' => 'Бренд',                    'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'CATEGORY'        => ['NAME' => 'Категория',                'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'DISCOUNT'        => ['NAME' => 'Скидка, %',                'PROPERTY_TYPE' => 'N', 'MULTIPLE' => 'N'],
    'EDGE'            => ['NAME' => 'Кромка',                   'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'INSERT'          => ['NAME' => 'Врезка под петли',         'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'OPEN_TYPE'       => ['NAME' => 'Тип открывания',           'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y'],
    'DOOR_TYPE'       => ['NAME' => 'Вид двери',                'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'CONSTRUCTION'    => ['NAME' => 'Конструкция',               'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'MATERIAL'        => ['NAME' => 'Материал',                 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y'],
    'NOISE_ISOLATION' => ['NAME' => 'Уровень шумоизоляции',     'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'FIRE_RESISTANCE' => ['NAME' => 'Уровень пожароизоляции',   'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'WARRANTY'        => ['NAME' => 'Гарантия',                 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'AVAILABILITY'    => ['NAME' => 'Наличие',                  'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'LEAD_TIME'       => ['NAME' => 'Срок поставки',            'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
    'SHORT_DESC'      => ['NAME' => 'Краткое описание',         'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N'],
];

$existing = [];
$res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID]);
while ($p = $res->Fetch()) {
    $existing[$p['CODE']] = $p;
}

$ibp = new CIBlockProperty;
foreach ($toCreate as $code => $def) {
    if (isset($existing[$code])) {
        echo "Уже есть: $code\n";
        continue;
    }
    $fields = [
        'IBLOCK_ID' => $IBLOCK_ID,
        'CODE' => $code,
        'NAME' => $def['NAME'],
        'PROPERTY_TYPE' => $def['PROPERTY_TYPE'],
        'MULTIPLE' => $def['MULTIPLE'],
        'ACTIVE' => 'Y',
        'SORT' => 500,
    ];
    $id = $ibp->Add($fields);
    echo $id ? "Создано: $code (ID $id)\n" : "Ошибка $code: {$ibp->LAST_ERROR}\n";
}

// Стиль должен быть множественным (в выгрузке 1С — список через запятую),
// а был создан как MULTIPLE=N на предыдущем этапе.
if (isset($existing['STYLE']) && $existing['STYLE']['MULTIPLE'] !== 'Y') {
    $ok = $ibp->Update($existing['STYLE']['ID'], ['MULTIPLE' => 'Y']);
    echo $ok ? "STYLE переключён в MULTIPLE=Y\n" : "Ошибка обновления STYLE: {$ibp->LAST_ERROR}\n";
}

echo "Готово.\n";
