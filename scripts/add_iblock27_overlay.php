<?php
// Свойство "Затенение" (OVERLAY) для IBLOCK 27 — общее для карусели главной (main/side1/side2)
// и слотовых баннеров плиток "Категории"/"Коллекции" (eporta_banners). Список Y/N, по умолчанию
// Y — сохраняет текущее поведение (градиент включён) для всех уже существующих элементов.
// Идемпотентно: повторный запуск ничего не ломает.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 27;

$res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'OVERLAY']);
if ($res->Fetch()) {
    echo "Свойство OVERLAY уже существует, пропускаю создание\n";
} else {
    $propObj = new CIBlockProperty;
    $propId = $propObj->Add([
        'IBLOCK_ID' => $IBLOCK_ID,
        'NAME' => 'Затенение поверх фото',
        'CODE' => 'OVERLAY',
        'PROPERTY_TYPE' => 'L',
        'LIST_TYPE' => 'C', // чекбокс-стиль, но список — нужен XML_ID для Y/N
        'ROW_COUNT' => 1,
        'COL_COUNT' => 30,
        'MULTIPLE' => 'N',
        'IS_REQUIRED' => 'N',
        'SORT' => 6,
    ]);
    if (!$propId) {
        die("Ошибка создания свойства OVERLAY: {$propObj->LAST_ERROR}\n");
    }
    echo "Свойство OVERLAY создано, ID=$propId\n";

    $enumObj = new CIBlockPropertyEnum;
    $enumValues = [
        ['VALUE' => 'Да', 'XML_ID' => 'Y', 'DEF' => 'Y', 'SORT' => 100],
        ['VALUE' => 'Нет', 'XML_ID' => 'N', 'SORT' => 200],
    ];
    foreach ($enumValues as $e) {
        $enumId = $enumObj->Add(array_merge(['PROPERTY_ID' => $propId], $e));
        echo "  enum {$e['XML_ID']} -> ID=$enumId\n";
    }
}

// Существующие элементы без явного значения свойства уже ведут себя как "Y" на фронте
// (index.php/lib.php трактуют отсутствие как затенение включено) — принудительно проставлять
// значение не обязательно, но для наглядности в админке проставим "Да" всем, где пусто.
//
// ВАЖНО (инцидент 29.08.2026, см. память project_security_hardening/этот файл в истории git):
// CIBlockElement::Update($id, ['PROPERTY_VALUES' => ['OVERLAY' => 'Y']]) заменяет ВЕСЬ набор
// свойств элемента переданным (кроме файловых F-свойств) — на проде это стёрло PLACEMENT и
// остальные свойства у всех 36 элементов IBLOCK 27, карусель/мозаика главной пропала до
// ручного восстановления. Здесь используется только точечная запись через SetPropertyValuesEx
// с числовым PROPERTY_ID/ENUM_ID — Update()+PROPERTY_VALUES для точечных изменений на элементах
// этого инфоблока применять нельзя.
$overlayProp = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'OVERLAY'])->Fetch();
$overlayPropId = (int)$overlayProp['ID'];
$yEnum = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $overlayPropId, 'XML_ID' => 'Y'])->Fetch();
$yEnumId = (int)$yEnum['ID'];

$res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID', 'PROPERTY_OVERLAY']);
$updated = 0;
while ($el = $res->Fetch()) {
    if (!empty($el['PROPERTY_OVERLAY_VALUE'])) {
        continue;
    }
    CIBlockElement::SetPropertyValuesEx($el['ID'], $IBLOCK_ID, [$overlayPropId => $yEnumId]);
    $updated++;
}
echo "Проставлено значение по умолчанию (Да) для $updated элементов без OVERLAY.\n";

echo "Готово.\n";
