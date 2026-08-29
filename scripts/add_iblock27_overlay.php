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
$elObj = new CIBlockElement;
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID', 'PROPERTY_OVERLAY']);
$updated = 0;
while ($el = $res->Fetch()) {
    if (!empty($el['PROPERTY_OVERLAY_VALUE'])) {
        continue;
    }
    $ok = $elObj->Update($el['ID'], ['PROPERTY_VALUES' => ['OVERLAY' => 'Y']]);
    if ($ok) {
        $updated++;
    } else {
        echo "Ошибка обновления {$el['ID']}: {$elObj->LAST_ERROR}\n";
    }
}
echo "Проставлено значение по умолчанию (Да) для $updated элементов без OVERLAY.\n";

echo "Готово.\n";
