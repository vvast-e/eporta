<?php
// Общая логика админки "Коллекции фабрики" — редактирование названия/описания/порядка/
// активности существующих коллекций и добавление новых. Требует уже подключенный
// prolog_before.php (модули main/iblock). По паттерну local/admin_tools/eporta_banners/lib.php —
// та же модель прав (право записи в IBLOCK 19), тот же общий IBLOCK 27 для авто-заведения слота
// баннера новой коллекции.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/eporta_collections.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_banners/lib.php');
// Модели/варианты коллекции (витринный вариант + видимость в общем списке) — задача 06.09.2026:
// раньше отдельная страница local/admin_tools/eporta_showcase/, перенесена сюда единым разделом
// на каждую коллекцию, чтобы всё управление коллекцией было в одном месте. Логика (свойства
// SHOWCASE/SHOW_IN_LIST IBLOCK 19) осталась в eporta_showcase/lib.php без изменений — переиспользуем
// функции оттуда, а не дублируем.
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/admin_tools/eporta_showcase/lib.php');

function eportaCollectionsUserHasAccess(): bool {
    return eportaBannersUserHasAccess();
}

// Временная папка для загрузки баннера страницы коллекции — своя (не eportaBannersTmpDir()),
// та под eporta_banners/ концептуально про слотовые баннеры плиток IBLOCK 27.
function eportaCollectionsTmpDir(): string {
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/local/tmp/eporta_collections';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
    return $dir;
}

function eportaCollectionsGenerateCode(string $name): string {
    $code = \CUtil::translit($name, 'ru', [
        'max_len' => 100,
        'change_case' => 'L',
        'replace_space' => '-',
        'replace_other' => '-',
        'delete_repeat_replace' => true,
    ]);
    return $code !== '' ? $code : 'collection';
}

// Следующий свободный CODE — если сгенерированный уже занят (в т.ч. в другом разделе того же
// инфоблока), добавляет числовой суффикс.
function eportaCollectionsUniqueCode(string $baseCode): string {
    $code = $baseCode;
    $i = 2;
    while (CIBlockSection::GetList([], ['IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID, 'CODE' => $code])->Fetch()) {
        $code = $baseCode . '-' . $i;
        $i++;
    }
    return $code;
}

// Заводит в IBLOCK 27 enum-значение PLACEMENT для слота новой коллекции (coll_<code>), если
// его ещё нет — без этого eporta_banners/ajax.php отбивает загрузку картинки как "Неизвестный
// слот" (проверка идёт по eportaBannersSlots(), которая уже строится динамически по
// eportaCollections(), но само PLACEMENT-значение свойства всё равно должно физически
// существовать в IBLOCK 27, иначе элемент баннера сохранить нельзя). Идемпотентно.
function eportaCollectionsEnsurePlacementEnum(string $collectionCode, string $collectionName): bool {
    CModule::IncludeModule('iblock');
    $slotCode = eportaCollectionSlotCode($collectionCode);
    $propRes = CIBlockProperty::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => 'PLACEMENT']);
    $prop = $propRes->Fetch();
    if (!$prop) {
        return false;
    }
    $existing = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID'], 'XML_ID' => $slotCode])->Fetch();
    if ($existing) {
        return true;
    }
    $maxSortRes = CIBlockPropertyEnum::GetList(['SORT' => 'DESC'], ['PROPERTY_ID' => $prop['ID']]);
    $maxSortRow = $maxSortRes->Fetch();
    $nextSort = $maxSortRow ? ((int)$maxSortRow['SORT'] + 10) : 1000;
    $enumObj = new CIBlockPropertyEnum;
    $enumId = $enumObj->Add([
        'PROPERTY_ID' => $prop['ID'],
        'VALUE' => 'Коллекция: ' . $collectionName,
        'XML_ID' => $slotCode,
        'SORT' => $nextSort,
    ]);
    return (bool)$enumId;
}

// Следующий свободный SORT — новая коллекция по умолчанию встаёт в конец списка.
function eportaCollectionsNextSort(): int {
    $maxSort = 0;
    foreach (eportaCollections() as $coll) {
        $maxSort = max($maxSort, (int)$coll['SORT']);
    }
    return $maxSort + 100;
}

// Создаёт новую коллекцию — секцию под родителем 183 плюс слот баннера (enum PLACEMENT).
// Возвращает ID секции или false с текстом ошибки в $error.
function eportaCollectionsCreate(string $name, string $description, ?string &$error = null) {
    $name = trim($name);
    if ($name === '') {
        $error = 'Название не может быть пустым';
        return false;
    }
    $code = eportaCollectionsUniqueCode(eportaCollectionsGenerateCode($name));
    $sectionObj = new CIBlockSection;
    $sectionId = $sectionObj->Add([
        'IBLOCK_ID' => EPORTA_COLLECTIONS_IBLOCK_ID,
        'IBLOCK_SECTION_ID' => EPORTA_COLLECTIONS_PARENT_SECTION_ID,
        'NAME' => $name,
        'CODE' => $code,
        'DESCRIPTION' => $description,
        'SORT' => eportaCollectionsNextSort(),
        'ACTIVE' => 'Y',
    ]);
    if (!$sectionId) {
        $error = $sectionObj->LAST_ERROR ?: 'Не удалось создать коллекцию';
        return false;
    }
    eportaCollectionsEnsurePlacementEnum($code, $name);
    return (int)$sectionId;
}

// Обновляет название/описание/порядок/активность существующей коллекции. CODE и родительский
// раздел не меняются — смена CODE сломала бы уже залитый баннер (слот привязан к CODE) и
// действующие ссылки /catalog/collections/<code>/.
function eportaCollectionsUpdate(int $sectionId, string $name, string $description, int $sort, bool $active, bool $squareCards = false, ?string &$error = null): bool {
    $name = trim($name);
    if ($name === '') {
        $error = 'Название не может быть пустым';
        return false;
    }
    $sectionObj = new CIBlockSection;
    $ok = $sectionObj->Update($sectionId, [
        'NAME' => $name,
        'DESCRIPTION' => $description,
        'SORT' => $sort,
        'ACTIVE' => $active ? 'Y' : 'N',
        'UF_SQUARE_CARDS' => $squareCards ? 'Y' : 'N',
    ]);
    if (!$ok) {
        $error = $sectionObj->LAST_ERROR ?: 'Не удалось сохранить коллекцию';
    }
    return $ok;
}

// Баннер страницы САМОЙ коллекции (/catalog/collections/<code>/ — шапка с фото и описанием,
// catalog/index.php: $eportaCollectionSection["DETAIL_PICTURE"] ?: ["PICTURE"]). НЕ то же самое,
// что слотовый баннер плитки коллекции на главной/на /collection/ — тот отдельный элемент
// IBLOCK 27, редактируется в eporta_banners/. Это — обычное файловое поле DETAIL_PICTURE прямо
// на разделе IBLOCK 19, Update() с ним не трогает остальные поля секции (в отличие от
// CIBlockElement::Update()+PROPERTY_VALUES, см. предупреждение в eporta_banners/lib.php — та
// ловушка про свойства элементов, полей разделов не касается).
function eportaCollectionsUpdateBanner(int $sectionId, array $fileArray, ?string &$error = null): bool {
    $sectionObj = new CIBlockSection;
    $ok = $sectionObj->Update($sectionId, ['DETAIL_PICTURE' => $fileArray]);
    if (!$ok) {
        $error = $sectionObj->LAST_ERROR ?: 'Не удалось сохранить баннер';
    }
    return $ok;
}

// Затемнение/кнопка баннера страницы коллекции (Этап 5b, 11.09.2026) — UF-поля секции
// (scripts/add_section_banner_ufields.php), заголовок/подзаголовок баннера берутся из уже
// существующих NAME/DESCRIPTION секции, новых полей под них не заводим. В отличие от свойств
// элементов IBLOCK 27 (см. предупреждение в eporta_banners/lib.php), Update() поля секции не
// затрагивает остальные поля секции, если их не передавать — точечное обновление безопасно
// обычным вызовом.
function eportaCollectionsUpdateBannerMeta(int $sectionId, bool $overlay, string $ctaText, string $ctaLink, ?string &$error = null): bool {
    $sectionObj = new CIBlockSection;
    $ok = $sectionObj->Update($sectionId, [
        'UF_BANNER_OVERLAY' => $overlay ? 'Y' : 'N',
        'UF_BANNER_CTA_TEXT' => $ctaText,
        'UF_BANNER_CTA_LINK' => $ctaLink,
    ]);
    if (!$ok) {
        $error = $sectionObj->LAST_ERROR ?: 'Не удалось сохранить настройки баннера';
    }
    return $ok;
}
