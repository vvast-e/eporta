<?php
// Свойство SHOW_IN_LIST (список Y/N) для IBLOCK 19 — ручное скрытие конкретного варианта
// (цвета/остекления) из общих списков (каталог, "Все товары коллекции"), см. local/admin_tools/
// eporta_showcase/ и catalog/index.php ($eportaScopeFilter). Задача 06.09.2026: у модели может
// быть 6 вариантов (3 цвета × 2 остекления), но показывать в списке хочется не все, а только
// ходовые — остальные остаются доступны по прямой ссылке и через переключение цвета на карточке
// товара. По умолчанию не заполняется ни у одного элемента — отсутствие значения трактуется
// кодом как "показывать" (значение "N" — единственное, что скрывает), чтобы не спрятать разом
// все уже существующие 800+ товаров при первом появлении этого свойства.
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 19;

$res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'SHOW_IN_LIST']);
if ($res->Fetch()) {
    echo "Свойство SHOW_IN_LIST уже существует, пропускаю создание\n";
    exit;
}

$propObj = new CIBlockProperty;
$propId = $propObj->Add([
    'IBLOCK_ID' => $IBLOCK_ID,
    'NAME' => 'Показывать в общем списке',
    'CODE' => 'SHOW_IN_LIST',
    'PROPERTY_TYPE' => 'L',
    'LIST_TYPE' => 'C',
    'ROW_COUNT' => 1,
    'COL_COUNT' => 30,
    'MULTIPLE' => 'N',
    'IS_REQUIRED' => 'N',
    'SORT' => 520,
]);
if (!$propId) {
    die("Ошибка создания свойства SHOW_IN_LIST: {$propObj->LAST_ERROR}\n");
}
echo "Свойство SHOW_IN_LIST создано, ID=$propId\n";

$enumObj = new CIBlockPropertyEnum;
$enumValues = [
    ['VALUE' => 'Да', 'XML_ID' => 'Y', 'DEF' => 'Y', 'SORT' => 100],
    ['VALUE' => 'Нет', 'XML_ID' => 'N', 'SORT' => 200],
];
foreach ($enumValues as $e) {
    $enumId = $enumObj->Add(array_merge(['PROPERTY_ID' => $propId], $e));
    echo "  enum {$e['XML_ID']} -> ID=$enumId\n";
}

echo "Готово.\n";
