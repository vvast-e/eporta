<?php
// Разовый скрипт: пересобирает NAME уже импортированных товаров IBLOCK 19 по новому формату
// "Коллекция Модель, Покрытие Цвет" (см. eportaImportComposeName в
// local/admin_tools/eporta_import/lib.php — та же функция, что теперь использует и веб-, и
// CLI-импорт при создании новых товаров). До этого NAME брался напрямую из колонки
// "Название" (обычно голая модель, например "2").
//
// CODE (SEF URL /catalog/<code>.html) намеренно НЕ трогаем — товары уже могли быть
// проиндексированы/на них могли сослаться, менять CODE задним числом ломает ссылки.
//
// Запуск на сервере:
//   /opt/php83/bin/php scripts/import/recompose_names.php --dry-run   (только показать изменения)
//   /opt/php83/bin/php scripts/import/recompose_names.php             (применить)

define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
require_once($_SERVER['DOCUMENT_ROOT'].'/local/admin_tools/eporta_import/lib.php');

$dryRun = in_array('--dry-run', $argv, true);

$res = CIBlockElement::GetList(
    ['ID' => 'ASC'],
    ['IBLOCK_ID' => EPORTA_IMPORT_IBLOCK_ID, 'ACTIVE' => 'Y'],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_COLLECTION', 'PROPERTY_MODEL', 'PROPERTY_COATING', 'PROPERTY_COATING_COLOR']
);

$total = 0;
$changed = 0;
$updated = 0;

while ($el = $res->Fetch()) {
    $total++;

    $p = [
        'collection'    => $el['PROPERTY_COLLECTION_VALUE'] ?? '',
        'model'         => $el['PROPERTY_MODEL_VALUE'] ?? '',
        'coating'       => $el['PROPERTY_COATING_VALUE'] ?? '',
        'coating_color' => $el['PROPERTY_COATING_COLOR_VALUE'] ?? '',
        'name'          => $el['NAME'],
    ];
    $article = $el['PROPERTY_CML2_ARTICLE_VALUE'] ?? '';

    $newName = eportaImportComposeName($p, $article);
    if ($newName === $el['NAME']) {
        continue;
    }

    $changed++;
    echo "#{$el['ID']}: \"{$el['NAME']}\" -> \"$newName\"\n";

    if (!$dryRun) {
        $elObj = new CIBlockElement;
        if ($elObj->Update((int)$el['ID'], ['NAME' => $newName])) {
            $updated++;
        } else {
            echo "  ОШИБКА обновления #{$el['ID']}: ".$elObj->LAST_ERROR."\n";
        }
    }
}

echo "\n".($dryRun ? "[dry-run] " : "")."Всего товаров: $total. Требуют изменения: $changed."
    .($dryRun ? "" : " Обновлено: $updated.")."\n";
