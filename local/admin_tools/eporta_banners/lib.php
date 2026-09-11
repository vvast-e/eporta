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

// Санитизация ссылки, вводимой контент-менеджером в полях "Ссылка"/"Ссылка кнопки" (карусель
// главной, кнопка баннера коллекции) — без неё сохранённое значение вида "javascript:..." в
// href-атрибуте, пропущенное только через htmlspecialcharsbx(), исполнилось бы при клике
// (htmlspecialcharsbx экранирует только HTML-разметку, схему URL — нет; ВАЖНО: не писать здесь
// буквальное закрытие PHP-тега — "?" + ">" в // -комментарии реально закрывает PHP-режим и весь
// остаток файла становится обычным HTML-выводом, см. инцидент 11.09.2026). Разрешены относительные
// пути и http(s); всё остальное
// (javascript:, data:, vbscript: и т.п., включая обфусцированные пробелами/управляющими
// символами вида "java\tscript:") отбрасывается в пустую строку — на выходе кнопка/ссылка
// подставляет дефолт по месту использования. Общая точка для eporta_banners и eporta_collections
// (та require_once уже подключает этот файл).
function eportaSanitizeBannerLink(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $stripped = preg_replace('/[\x00-\x1F\x7F\s]+/', '', $url);
    if (preg_match('~^([a-z][a-z0-9+.\-]*):~i', $stripped, $m)) {
        if (!in_array(strtolower($m[1]), ['http', 'https'], true)) {
            return '';
        }
    }
    return $url;
}

// ВАЖНО (инцидент 29.08.2026): CIBlockElement::Update()/Add() с PROPERTY_VALUES в классическом
// API Bitrix ПОЛНОСТЬЮ ЗАМЕНЯЕТ набор свойств элемента тем, что передано — а не мержит только
// указанные ключи (кроме файловых свойств типа F, их Update() не трогает, если не включить явно).
// Update($id, ['PROPERTY_VALUES' => ['OVERLAY' => 'Y']]) стирает PLACEMENT/SUBTITLE/LINK/CTA_TEXT
// у уже существующего элемента, если их не повторить в том же вызове. Единственный безопасный
// способ обновить ОДНО свойство существующего элемента, не трогая остальные — точечная запись
// через SetPropertyValuesEx с числовыми PROPERTY_ID/ENUM_ID, см. eportaBannersSetListProperty()
// ниже. Все точечные обновления свойств элементов IBLOCK 27 в этом файле и в ajax.php должны
// идти только через неё; Update()+PROPERTY_VALUES допустим только при создании нового элемента
// (Add()) или когда передаётся ПОЛНЫЙ набор свойств, которые должны остаться на элементе.
function eportaBannersSetListProperty(int $elementId, string $propertyCode, string $enumXmlId): bool {
    static $propIdByCode = null;
    if ($propIdByCode === null) {
        $propIdByCode = [];
        $res = CIBlockProperty::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID]);
        while ($p = $res->Fetch()) {
            $propIdByCode[$p['CODE']] = (int)$p['ID'];
        }
    }
    $propId = $propIdByCode[$propertyCode] ?? null;
    if (!$propId) {
        return false;
    }
    $enumRow = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'XML_ID' => $enumXmlId])->Fetch();
    if (!$enumRow) {
        return false;
    }
    CIBlockElement::SetPropertyValuesEx($elementId, EPORTA_BANNERS_IBLOCK_ID, [$propId => (int)$enumRow['ID']]);
    return true;
}
// Право на редактирование баннеров привязано к тому же IBLOCK 19 (каталог), что и импорт —
// это те же контент-менеджеры, отдельной модели прав заводить не требовалось.
const EPORTA_BANNERS_PERMISSION_IBLOCK_ID = 19;
// Раздел ИБ 27, в который заводятся слотовые баннеры плиток (в отличие от слайдов главной
// карусели, которые лежат вне разделов) — заведён scripts/add_iblock27_tiles_section.php.
// Отдельно от главной карусели (index.php), чтобы карусель не подхватывала эти элементы как
// свои слайды. Резолвится по CODE (не хардкодим ID — раздел может отличаться между окружениями).
const EPORTA_BANNERS_TILES_SECTION_CODE = 'home_tiles';

// ID раздела "Плитки главной" — false, если ещё не заведён (см. add_iblock27_tiles_section.php).
// Кэш на запрос: используется и при чтении слотов, и при загрузке новой картинки.
function eportaBannersTilesSectionId() {
    static $sectionId = null;
    if ($sectionId === null) {
        $res = CIBlockSection::GetList(
            [],
            ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => EPORTA_BANNERS_TILES_SECTION_CODE],
            false,
            ['ID']
        );
        $section = $res->Fetch();
        $sectionId = $section ? (int)$section['ID'] : false;
    }
    return $sectionId;
}

// Слоты плиток главной — код (=XML_ID enum PLACEMENT, см. scripts/add_slider27_home_slots.php),
// человекочитаемая подпись и путь к файлу-фолбэку (текущий хардкод в index.php), который
// используется, пока для слота не залита картинка через эту админку.
// 'size' — рекомендация по размеру для конкретной формы плитки на главной (см. index.php:
// cat_mkd/cat_hardware — высокие плитки на 2 строки грида, cat_hidden/cat_sliding/cat_entrance/
// cat_arch — низкие широкие плитки-пары, coll_* — широкие плитки фикс. высоты 226px). Вывод
// везде через object-fit:cover, поэтому важнее пропорция, чем точный пиксельный размер.
//
// Слоты coll_* строятся динамически по eportaCollections() (local/lib/eporta_collections.php) —
// раньше здесь был хардкод ровно 6 записей, из-за чего часть коллекций не могла получить свою
// картинку через админку в принципе. Теперь слот заводится на каждую коллекцию автоматически;
// enum-значение PLACEMENT для новой коллекции заводит eportaCollectionsEnsurePlacementEnum()
// (local/admin_tools/eporta_collections/lib.php) при её создании.
function eportaBannersSlots(): array {
    $slots = [
        'cat_mkd' => ['label' => 'Категория: Межкомнатные', 'fallback' => 'cat-mezh.jpg', 'size' => 'высокая плитка, портрет — рекомендуется ~800×1100 px'],
        'cat_hidden' => ['label' => 'Категория: Скрытые', 'fallback' => 'cat-skryt.jpg', 'size' => 'широкая низкая плитка — рекомендуется ~900×550 px'],
        'cat_sliding' => ['label' => 'Категория: Раздвижные', 'fallback' => 'cat-razdv.jpg', 'size' => 'широкая низкая плитка — рекомендуется ~900×550 px'],
        'cat_entrance' => ['label' => 'Категория: Входные', 'fallback' => 'cat-vhod.jpg', 'size' => 'широкая низкая плитка — рекомендуется ~900×550 px'],
        'cat_arch' => ['label' => 'Категория: Арки и порталы', 'fallback' => 'cat-arki.jpg', 'size' => 'широкая низкая плитка — рекомендуется ~900×550 px'],
        'cat_hardware' => ['label' => 'Категория: Фурнитура', 'fallback' => 'cat-furn.jpg', 'size' => 'высокая плитка, портрет — рекомендуется ~800×1100 px'],
    ];
    require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/eporta_collections.php');
    // Квадратная плитка 1:1, картинка вписывается целиком (object-fit:contain) — не обрезается,
    // независимо от пропорций исходного фото. Точный размер не критичен, важна не слишком
    // маленькая сторона (упирается в подложку #f2efe9 при несовпадении пропорций). Историческая
    // fallback-картинка сохранена только для коллекций, у которых она уже была; для новых
    // коллекций fallback пуст — плитка остаётся на нейтральной подложке, пока фото не залито.
    $legacyFallback = [
        'dorsum' => 'hit-1.jpg',
        'vilis' => 'hit-2.jpg',
        'actus' => 'hit-5.jpg',
        'vitrum' => 'hit-6.jpg',
        'tabula' => 'hit-7.jpg',
        'lacuna' => 'hit-8.jpg',
    ];
    foreach (eportaCollections() as $coll) {
        $slotCode = eportaCollectionSlotCode($coll['CODE']);
        $slots[$slotCode] = [
            'label' => 'Коллекция: ' . $coll['NAME'],
            'fallback' => $legacyFallback[$coll['CODE']] ?? '',
            'size' => 'квадратная плитка — рекомендуется ~900×900 px',
        ];
    }
    return $slots;
}

// Точечное обновление СТРОКОВОГО (не списочного) свойства элемента — тот же принцип, что и
// eportaBannersSetListProperty() выше (SetPropertyValuesEx с одним ключом не трогает остальные
// свойства элемента), но без резолва enum ID: для свойств типа S значение пишется как есть.
function eportaBannersSetStringProperty(int $elementId, string $propertyCode, string $value): void {
    CIBlockElement::SetPropertyValuesEx($elementId, EPORTA_BANNERS_IBLOCK_ID, [$propertyCode => $value]);
}

// Слайды главной карусели (PLACEMENT main/side1/side2, тот же IBLOCK 27, что и слотовые баннеры
// плиток выше) — для редактирования текстовых полей (заголовок/подзаголовок/ссылка/кнопка/
// затемнение) через эту же админку вместо штатной формы Bitrix (см. index.php: тот же разбор
// PLACEMENT для вывода карусели на главной — держать оба места в синхроне при правках). Этот
// хелпер только ЧИТАЕТ и редактирует поля уже существующих слайдов; создание/удаление/порядок
// слайдов по-прежнему через штатную админку Bitrix (раздел "Слайдер" / IBLOCK 27) — вне объёма
// этой доработки.
function eportaBannersCarouselSlides(): array {
    $enumRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => 'PLACEMENT']);
    $enumIdToXmlId = [];
    while ($enumRow = $enumRes->Fetch()) {
        $enumIdToXmlId[$enumRow['ID']] = $enumRow['XML_ID'];
    }
    $overlayEnumRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => 'OVERLAY']);
    $overlayEnumIdToXmlId = [];
    while ($enumRow = $overlayEnumRes->Fetch()) {
        $overlayEnumIdToXmlId[$enumRow['ID']] = $enumRow['XML_ID'];
    }

    $slides = ['main' => [], 'side1' => [], 'side2' => []];
    $res = CIBlockElement::GetList(
        ['SORT' => 'ASC'],
        ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'ACTIVE' => 'Y'],
        false,
        false,
        ['ID', 'NAME', 'DETAIL_PICTURE', 'PROPERTY_PLACEMENT', 'PROPERTY_OVERLAY', 'PROPERTY_SUBTITLE', 'PROPERTY_LINK', 'PROPERTY_CTA_TEXT']
    );
    while ($el = $res->Fetch()) {
        $enumId = $el['PROPERTY_PLACEMENT_ENUM_ID'] ?? null;
        $xmlId = $enumId !== null ? ($enumIdToXmlId[$enumId] ?? '') : '';
        if (!isset($slides[$xmlId])) {
            // Слотовые баннеры плиток (cat_*/coll_*) — не наши, сюда не подмешиваем. Пустой/
            // незнакомый PLACEMENT (старый слайд без PLACEMENT) на главной трактуется как "main" —
            // здесь для редактирования тоже кладём его в "main".
            if ($xmlId !== '' && (str_starts_with($xmlId, 'cat_') || str_starts_with($xmlId, 'coll_'))) {
                continue;
            }
            $xmlId = 'main';
        }
        $overlayEnumId = $el['PROPERTY_OVERLAY_ENUM_ID'] ?? null;
        $overlayXmlId = $overlayEnumId ? ($overlayEnumIdToXmlId[$overlayEnumId] ?? '') : '';
        $slides[$xmlId][] = [
            'ID' => (int)$el['ID'],
            'NAME' => (string)$el['NAME'],
            'SUBTITLE' => (string)($el['PROPERTY_SUBTITLE_VALUE'] ?? ''),
            'LINK' => (string)($el['PROPERTY_LINK_VALUE'] ?? ''),
            'CTA_TEXT' => (string)($el['PROPERTY_CTA_TEXT_VALUE'] ?? ''),
            'OVERLAY_ENABLED' => ($overlayXmlId === 'Y'),
            'preview' => $el['DETAIL_PICTURE'] ? CFile::GetPath($el['DETAIL_PICTURE']) : '',
        ];
    }
    return $slides;
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
    // Та же карта для затенения (см. scripts/add_iblock27_overlay.php) — Y/N.
    $overlayEnumRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'CODE' => 'OVERLAY']);
    $overlayEnumIdToXmlId = [];
    while ($enumRow = $overlayEnumRes->Fetch()) {
        $overlayEnumIdToXmlId[$enumRow['ID']] = $enumRow['XML_ID'];
    }

    $res = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'ACTIVE' => 'Y'],
        false,
        false,
        ['ID', 'NAME', 'DETAIL_PICTURE', 'PROPERTY_PLACEMENT', 'PROPERTY_OVERLAY']
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
        $overlayEnumId = $el['PROPERTY_OVERLAY_ENUM_ID'] ?? null;
        $overlayXmlId = $overlayEnumId ? ($overlayEnumIdToXmlId[$overlayEnumId] ?? '') : '';
        // Пусто (свойство не заполнено — в т.ч. значение "(нет)" в штатной форме Битрикса,
        // у которого нет XML_ID) трактуется как затенение ВЫКЛЮЧЕНО, а не включено, как было
        // раньше. Иначе в штатной форме элемента IBLOCK 27 три положения "(нет)/Да/Нет" давали
        // только два разных результата и непонятно было, что выбрать, чтобы отключить (заявка
        // заказчика 05.09.2026). Существующие баннеры при миграции (scripts/setup_collections_
        // order.php) получают явное "Да", чтобы не потерять затенение молча.
        $el['OVERLAY_ENABLED'] = ($overlayXmlId === 'Y');
        // На случай дублей (несколько элементов с одним PLACEMENT) — берём последний по ID.
        if (!isset($bySlot[$xmlId]) || (int)$el['ID'] > (int)$bySlot[$xmlId]['ID']) {
            $bySlot[$xmlId] = $el;
        }
    }
    return $bySlot;
}

// Затенение слотового баннера включено? true, если свойства ещё нет на элементе (по умолчанию
// как раньше — включено) или явно не выставлено "Нет".
function eportaBannersSlotOverlayEnabled(string $slotCode): bool {
    static $slotElements = null;
    if ($slotElements === null) {
        $slotElements = eportaBannersGetSlotElements();
    }
    $el = $slotElements[$slotCode] ?? null;
    return $el ? ($el['OVERLAY_ENABLED'] ?? true) : true;
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
