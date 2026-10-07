<?php
// Только консоль: скрипт подставляет права/меняет данные и не должен выполняться по HTTP (каталог scripts/ лежит в webroot).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Проверки чистой логики иерархии коллекций (local/lib/eporta_collections.php) — Bitrix не нужен.
// Запуск: php scripts/tests/collections_hierarchy_test.php  (код возврата 0 = всё прошло)
define('B_PROLOG_INCLUDED', true);
require(__DIR__ . '/../../local/lib/eporta_collections.php');

$failures = 0;
function check(string $name, $actual, $expected): void {
    global $failures;
    if ($actual === $expected) {
        echo "ok   $name\n";
        return;
    }
    $failures++;
    echo "FAIL $name\n  ожидалось: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  получено:  " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
function coll(int $id, int $parent = 0, int $block = 0): array {
    $row = ['ID' => (string)$id, 'NAME' => "C$id", 'CODE' => "c$id"];
    if ($parent) $row['UF_PARENT_COLLECTION'] = (string)$parent;
    if ($block) $row['UF_HOME_BLOCK'] = (string)$block;
    return $row;
}
function ids(array $list): array {
    return array_map(function ($c) { return (int)$c['ID']; }, $list);
}

// Dorsum(1) -> Dorsum F(2), Dorsum ECO(3); Invi(4) без связей
$base = [coll(1), coll(2, 1), coll(3, 1), coll(4)];

check('parentMap: две подколлекции Dorsum', eportaCollectionsParentMap($base), [2 => 1, 3 => 1]);
check('topLevel: подколлекции скрыты', ids(eportaCollectionsTopLevel($base)), [1, 4]);
check('family: со страницы родителя', ids(eportaCollectionFamily($base, 1)), [1, 2, 3]);
check('family: со страницы подколлекции — те же кнопки', ids(eportaCollectionFamily($base, 3)), [1, 2, 3]);
check('family: коллекция без связей — пусто', eportaCollectionFamily($base, 4), []);
check('family: родитель без детей — пусто', eportaCollectionFamily([coll(1), coll(4)], 1), []);

// Пустые данные и отсутствие UF-полей
check('пустой список: topLevel', eportaCollectionsTopLevel([]), []);
check('пустой список: family', eportaCollectionFamily([], 1), []);
check('без UF-полей все верхнего уровня', ids(eportaCollectionsTopLevel([coll(1), coll(2)])), [1, 2]);

// Граничные случаи связей
check('сам себе родитель — верхний уровень', eportaCollectionsParentMap([coll(1, 1)]), []);
check('родитель не найден (скрыт) — верхний уровень', ids(eportaCollectionsTopLevel([coll(2, 99)])), [2]);
check('цикл A<->B — обе верхнего уровня', eportaCollectionsParentMap([coll(1, 2), coll(2, 1)]), []);
check('глубина 3: внук считается верхнего уровня', eportaCollectionsParentMap([coll(1), coll(2, 1), coll(3, 2)]), [2 => 1]);
check('глубина 3: топ-уровень = родитель и внук', ids(eportaCollectionsTopLevel([coll(1), coll(2, 1), coll(3, 2)])), [1, 3]);
check('родитель 0 / нечисловой мусор', eportaCollectionsParentMap([['ID' => '5', 'UF_PARENT_COLLECTION' => 'abc']]), []);

// Порядок подколлекций = порядок входного списка (SORT), родитель всегда первым
check('family: порядок по входному списку, родитель первый', ids(eportaCollectionFamily([coll(3, 1), coll(2, 1), coll(1)], 2)), [1, 3, 2]);

// Блок главной
check('block: по умолчанию 1', eportaCollectionHomeBlock(coll(1)), 1);
check('block: 2', eportaCollectionHomeBlock(coll(1, 0, 2)), 2);
check('block: мусор -> 1', eportaCollectionHomeBlock(['UF_HOME_BLOCK' => '7']), 1);

// Валидация назначения родителя (drag-and-drop в админке)
$v = [coll(1), coll(2, 1), coll(3), coll(4)];
check('validate: нормальный перенос 3 -> 1', eportaCollectionsValidateParent($v, 3, 1), null);
check('validate: снять родителя (0)', eportaCollectionsValidateParent($v, 2, 0), null);
check('validate: на самого себя', is_string(eportaCollectionsValidateParent($v, 3, 3)), true);
check('validate: родитель сам подколлекция (глубина 3)', is_string(eportaCollectionsValidateParent($v, 3, 2)), true);
check('validate: у коллекции есть дети — нельзя вложить', is_string(eportaCollectionsValidateParent($v, 1, 3)), true);
check('validate: несуществующий родитель', is_string(eportaCollectionsValidateParent($v, 3, 99)), true);
check('validate: несуществующая коллекция', is_string(eportaCollectionsValidateParent($v, 99, 1)), true);
check('validate: пустой список', is_string(eportaCollectionsValidateParent([], 1, 0)), true);
check('validate: перенос подколлекции к другому родителю', eportaCollectionsValidateParent([coll(1), coll(2, 1), coll(3)], 2, 3), null);

echo $failures ? "\nПровалено: $failures\n" : "\nВсе проверки прошли\n";
exit($failures ? 1 : 0);
