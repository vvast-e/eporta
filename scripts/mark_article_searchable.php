<?php
// Разово помечает свойство CML2_ARTICLE (артикул) в IBLOCK 19 как SEARCHABLE и переиндексирует
// элементы каталога, чтобы артикул попадал и в общий полнотекстовый индекс CSearch (не только
// в точный/частичный поиск по свойству из eportaFindArticleMatches() — local/php_interface/init.php,
// который и так покрывает задачу "искать по артикулу" без ожидания переиндексации; этот скрипт —
// подстраховка для морфологии/опечаток через штатный движок поиска).
// Запуск на сервере: php scripts/mark_article_searchable.php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('search');

$IBLOCK_ID = 19;

$propRes = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'CML2_ARTICLE']);
$prop = $propRes->Fetch();
if (!$prop) {
    die("Свойство CML2_ARTICLE не найдено в IBLOCK $IBLOCK_ID\n");
}

if ($prop['SEARCHABLE'] === 'Y') {
    echo "Свойство CML2_ARTICLE (ID={$prop['ID']}) уже SEARCHABLE=Y, переиндексация всё равно выполнится.\n";
} else {
    $propObj = new CIBlockProperty;
    $ok = $propObj->Update($prop['ID'], ['SEARCHABLE' => 'Y']);
    echo $ok
        ? "Свойство CML2_ARTICLE (ID={$prop['ID']}) помечено SEARCHABLE=Y\n"
        : "Ошибка обновления свойства: {$propObj->LAST_ERROR}\n";
    if (!$ok) {
        exit(1);
    }
}

echo "Переиндексация элементов IBLOCK $IBLOCK_ID...\n";
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'ACTIVE' => 'Y'], false, false, ['ID']);
$count = 0;
while ($el = $res->Fetch()) {
    CIBlockElement::UpdateSearch($el['ID']);
    $count++;
    if ($count % 200 === 0) {
        echo "  ...$count\n";
    }
}
echo "Готово, переиндексировано элементов: $count\n";
