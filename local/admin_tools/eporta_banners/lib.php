<?php
// Общая логика админки баннеров "Категории"/"Коллекции" главной страницы.
// Требует уже подключенный prolog_before.php (модули main/iblock).
// По паттерну local/admin_tools/eporta_import/lib.php (см. eportaImportUserHasAccess и т.п.) —
// та же модель прав (право записи в IBLOCK 19), тот же общий IBLOCK 27, что и карусель баннеров.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

// WebP-варианты для DETAIL_PICTURE генерируются автоматически общим хуком
// eportaOnIBlockElementSaveGenerateWebp (local/php_interface/init.php, срабатывает и на
// IBLOCK 27) при CIBlockElement::Add/Update ниже — отдельно вызывать не нужно.

const EPORTA_BANNERS_IBLOCK_ID = 27;
// Право на редактирование баннеров привязано к тому же IBLOCK 19 (каталог), что и импорт —
// это те же контент-менеджеры, отдельной модели прав заводить не требовалось.
const EPORTA_BANNERS_PERMISSION_IBLOCK_ID = 19;

// Слоты плиток главной — код (=XML_ID enum PLACEMENT, см. scripts/add_slider27_home_slots.php),
// человекочитаемая подпись и путь к файлу-фолбэку (текущий хардкод в index.php), который
// используется, пока для слота не залита картинка через эту админку.
function eportaBannersSlots(): array {
    return [
        'cat_mkd' => ['label' => 'Категория: Межкомнатные', 'fallback' => 'cat-mezh.jpg'],
        'cat_hidden' => ['label' => 'Категория: Скрытые', 'fallback' => 'cat-skryt.jpg'],
        'cat_sliding' => ['label' => 'Категория: Раздвижные', 'fallback' => 'cat-razdv.jpg'],
        'cat_entrance' => ['label' => 'Категория: Входные', 'fallback' => 'cat-vhod.jpg'],
        'cat_arch' => ['label' => 'Категория: Арки и порталы', 'fallback' => 'cat-arki.jpg'],
        'cat_hardware' => ['label' => 'Категория: Фурнитура', 'fallback' => 'cat-furn.jpg'],
        'coll_dorsum' => ['label' => 'Коллекция: Dorsum', 'fallback' => 'hit-1.jpg'],
        'coll_vilis' => ['label' => 'Коллекция: Vilis', 'fallback' => 'hit-2.jpg'],
        'coll_actus' => ['label' => 'Коллекция: Actus', 'fallback' => 'hit-5.jpg'],
        'coll_vitrum' => ['label' => 'Коллекция: Vitrum', 'fallback' => 'hit-6.jpg'],
        'coll_tabula' => ['label' => 'Коллекция: Tabula', 'fallback' => 'hit-7.jpg'],
        'coll_lacuna' => ['label' => 'Коллекция: Lacuna', 'fallback' => 'hit-8.jpg'],
    ];
}

function eportaBannersUserHasAccess(): bool {
    global $USER;
    if (!$USER->IsAuthorized()) {
        return false;
    }
    if ($USER->IsAdmin()) {
        return true;
    }
    return CIBlock::GetPermission(EPORTA_BANNERS_PERMISSION_IBLOCK_ID) >= 'W';
}

function eportaBannersTmpDir(): string {
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/local/tmp/eporta_banners';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
    return $dir;
}

// Текущее состояние всех слотов: элемент IBLOCK 27 (если залит), по коду слота (XML_ID enum
// PLACEMENT). Один запрос на все слоты (не по одному на плитку) — используется и здесь в
// админке, и на главной через eportaBannersResolveImage() ниже (там статически кэшируется).
function eportaBannersGetSlotElements(): array {
    $bySlot = [];
    // classic API не отдаёт XML_ID enum'а напрямую через PROPERTY_CODE — берём ENUM_ID и
    // сопоставляем с картой enum ID -> XML_ID (один лёгкий доп.запрос, не по одному на элемент).
    $enumRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => 'PLACEMENT']);
    $enumIdToXmlId = [];
    while ($enumRow = $enumRes->Fetch()) {
        $enumIdToXmlId[$enumRow['ID']] = $enumRow['XML_ID'];
    }

    $res = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'ACTIVE' => 'Y'],
        false,
        false,
        ['ID', 'NAME', 'DETAIL_PICTURE', 'PROPERTY_PLACEMENT']
    );
    while ($el = $res->Fetch()) {
        // ...ENUM_ID, не ...VALUE — тот же паттерн, что и на главной (index.php, разбор
        // карусели баннеров того же IBLOCK 27): GetList() для list-свойства отдаёт номер
        // enum-значения в ключе PROPERTY_<CODE>_ENUM_ID.
        $enumId = $el['PROPERTY_PLACEMENT_ENUM_ID'] ?? null;
        $xmlId = $enumId !== null ? ($enumIdToXmlId[$enumId] ?? null) : null;
        if ($xmlId === null) {
            continue;
        }
        // На случай дублей (несколько элементов с одним PLACEMENT) — берём последний по ID.
        if (!isset($bySlot[$xmlId]) || (int)$el['ID'] > (int)$bySlot[$xmlId]['ID']) {
            $bySlot[$xmlId] = $el;
        }
    }
    return $bySlot;
}

// Публичный хелпер для index.php главной: код слота -> веб-путь к картинке (залитая через
// админку либо фолбэк-файл шаблона) — именно веб-путь ожидает eportaPicture() (inc/webp.php),
// не абсолютный путь на диске. Кэш на запрос — вызывается по разу на каждую из 12 плиток.
function eportaBannersResolveImage(string $slotCode, string $fallbackWebPath): string {
    static $slotElements = null;
    if ($slotElements === null) {
        $slotElements = eportaBannersGetSlotElements();
    }
    $el = $slotElements[$slotCode] ?? null;
    if ($el && !empty($el['DETAIL_PICTURE'])) {
        $path = CFile::GetPath($el['DETAIL_PICTURE']);
        if ($path) {
            return $path;
        }
    }
    return $fallbackWebPath;
}
