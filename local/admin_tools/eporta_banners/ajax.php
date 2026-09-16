<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaBannersJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaBannersUserHasAccess()) {
    eportaBannersJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaBannersJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if (!in_array($action, ['upload', 'set_overlay', 'save_meta', 'save_slot_meta'], true)) {
    eportaBannersJsonFail('Неизвестное действие');
}

// save_slot_meta — заголовок/подзаголовок/ссылка промо-плиток мегаменю "Каталог" (слоты
// megamenu_sale/megamenu_new, см. eportaBannersSlots()/eportaBannersMegamenuBanners() в lib.php).
// Адресуется по коду слота, не по ID элемента — в отличие от save_meta (карусель, там несколько
// элементов на один PLACEMENT), у этих слотов элемент один, как у плиток cat_*/coll_*, и может
// ещё не существовать (плитка на дефолтном тексте/подложке) — тогда создаём его, как и upload/
// set_overlay ниже для тех же слотов.
if ($action === 'save_slot_meta') {
    $slots = eportaBannersSlots();
    $slotCode = (string)($_POST['slot'] ?? '');
    if (!isset($slots[$slotCode]) || empty($slots[$slotCode]['has_text'])) {
        eportaBannersJsonFail('Неизвестный слот');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        eportaBannersJsonFail('Заголовок не может быть пустым');
    }
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $link = eportaSanitizeBannerLink((string)($_POST['link'] ?? ''));

    $existing = eportaBannersGetSlotElements()[$slotCode] ?? null;
    if ($existing) {
        $elObj = new CIBlockElement;
        if (!$elObj->Update((int)$existing['ID'], ['NAME' => $name])) {
            eportaBannersJsonFail('Ошибка сохранения заголовка: ' . $elObj->LAST_ERROR, 500);
        }
        eportaBannersSetStringProperty((int)$existing['ID'], 'SUBTITLE', $subtitle);
        eportaBannersSetStringProperty((int)$existing['ID'], 'LINK', $link);
    } else {
        $fields = [
            'IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID,
            'ACTIVE' => 'Y',
            'NAME' => $name,
            'PROPERTY_VALUES' => ['PLACEMENT' => $slotCode, 'OVERLAY' => 'Y', 'SUBTITLE' => $subtitle, 'LINK' => $link],
        ];
        $tilesSectionId = eportaBannersTilesSectionId();
        if ($tilesSectionId) {
            $fields['IBLOCK_SECTION_ID'] = $tilesSectionId;
        }
        $elObj = new CIBlockElement;
        if (!$elObj->Add($fields)) {
            eportaBannersJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
        }
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// save_meta адресуется по ID элемента карусели (их несколько на один PLACEMENT), а не по коду
// слота, как upload/set_overlay для слотовых плиток (там элемент один на слот) — свой блок ниже.
if ($action === 'save_meta') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    $existsCheck = $elementId > 0
        ? CIBlockElement::GetList([], ['IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID, 'ID' => $elementId], false, false, ['ID'])->Fetch()
        : null;
    if (!$existsCheck) {
        eportaBannersJsonFail('Слайд не найден');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        eportaBannersJsonFail('Заголовок не может быть пустым');
    }
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $link = eportaSanitizeBannerLink((string)($_POST['link'] ?? ''));
    $showCta = ($_POST['show_cta'] ?? 'N') === 'Y';
    $ctaText = $showCta ? trim((string)($_POST['cta_text'] ?? '')) : '';
    $overlayValue = ($_POST['overlay'] ?? 'Y') === 'N' ? 'N' : 'Y';

    // NAME без ключа PROPERTY_VALUES в $arFields — Update() в этом случае свойства элемента
    // вообще не трогает (см. предупреждение в lib.php про инцидент 29.08.2026); отдельные
    // свойства пишем точечно через SetPropertyValuesEx ниже.
    $elObj = new CIBlockElement;
    if (!$elObj->Update($elementId, ['NAME' => $name])) {
        eportaBannersJsonFail('Ошибка сохранения заголовка: ' . $elObj->LAST_ERROR, 500);
    }
    eportaBannersSetStringProperty($elementId, 'SUBTITLE', $subtitle);
    eportaBannersSetStringProperty($elementId, 'LINK', $link);
    eportaBannersSetStringProperty($elementId, 'CTA_TEXT', $ctaText);
    eportaBannersSetListProperty($elementId, 'OVERLAY', $overlayValue);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$slots = eportaBannersSlots();
$slotCode = (string)($_POST['slot'] ?? '');
if (!isset($slots[$slotCode])) {
    eportaBannersJsonFail('Неизвестный слот');
}

// Переключение затенения — отдельное лёгкое действие, картинку не трогает. Если элемента для
// слота ещё нет (плитка ещё на фолбэке), затенение сохранить некуда — создаём элемент без
// картинки, eportaBannersResolveImage() всё равно продолжит отдавать фолбэк для DETAIL_PICTURE.
if ($action === 'set_overlay') {
    $overlayValue = ($_POST['overlay'] ?? 'Y') === 'N' ? 'N' : 'Y';
    $existingForOverlay = eportaBannersGetSlotElements()[$slotCode] ?? null;
    $elObj = new CIBlockElement;
    if ($existingForOverlay) {
        // SetPropertyValuesEx, не Update() — элемент уже существует и несёт PLACEMENT/картинку,
        // Update()+PROPERTY_VALUES с одним ключом стёр бы всё остальное (см. комментарий в lib.php).
        $ok = eportaBannersSetListProperty((int)$existingForOverlay['ID'], 'OVERLAY', $overlayValue);
    } else {
        // Для слотов с текстовыми полями (megamenu_sale/megamenu_new) заголовок/подзаголовок/
        // ссылка при первом создании элемента (до того, как save_slot_meta их сохранит) берём
        // из text_defaults, а не из технического 'label' админки — иначе на фронте до первого
        // сохранения текста показался бы служебный лейбл вместо реального заголовка баннера.
        $textDefaults = $slots[$slotCode]['text_defaults'] ?? null;
        $fields = [
            'IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID,
            'ACTIVE' => 'Y',
            'NAME' => $textDefaults['NAME'] ?? $slots[$slotCode]['label'],
            'PROPERTY_VALUES' => ['PLACEMENT' => $slotCode, 'OVERLAY' => $overlayValue]
                + ($textDefaults ? ['SUBTITLE' => $textDefaults['SUBTITLE'], 'LINK' => $textDefaults['LINK']] : []),
        ];
        $tilesSectionId = eportaBannersTilesSectionId();
        if ($tilesSectionId) {
            $fields['IBLOCK_SECTION_ID'] = $tilesSectionId;
        }
        $ok = (bool)$elObj->Add($fields);
    }
    if (!$ok) {
        eportaBannersJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    eportaBannersJsonFail('Файл не загружен');
}

$allowedExt = ['jpg', 'jpeg', 'png'];
$origName = $_FILES['image']['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    eportaBannersJsonFail('Допустимые форматы: JPG, PNG');
}

// Простая защита от слишком тяжёлых загрузок — плитки главной, не полноразмерные баннеры.
if ($_FILES['image']['size'] > 8 * 1024 * 1024) {
    eportaBannersJsonFail('Файл слишком большой (максимум 8 МБ)');
}

$tmpDir = eportaBannersTmpDir();
$tmpPath = $tmpDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $tmpPath)) {
    eportaBannersJsonFail('Не удалось сохранить загруженный файл');
}

if (!@getimagesize($tmpPath)) {
    @unlink($tmpPath);
    eportaBannersJsonFail('Файл повреждён или не является изображением');
}

$fileArray = CFile::MakeFileArray($tmpPath);
if (!$fileArray) {
    @unlink($tmpPath);
    eportaBannersJsonFail('Не удалось подготовить файл для сохранения');
}

$existing = eportaBannersGetSlotElements()[$slotCode] ?? null;
$elObj = new CIBlockElement;

// Update()+PROPERTY_VALUES заменяет ВЕСЬ набор свойств элемента (см. lib.php) — при повторной
// загрузке фото для уже существующей плитки нужно явно повторить текущее значение OVERLAY,
// иначе выбор "затенения" контент-менеджера молча слетит на значение по умолчанию.
$currentOverlay = $existing ? (($existing['OVERLAY_ENABLED'] ?? true) ? 'Y' : 'N') : 'Y';

// Слоты с текстовыми полями (megamenu_sale/megamenu_new) — Update()+PROPERTY_VALUES ниже
// заменяет ВЕСЬ набор свойств элемента (см. предупреждение в lib.php), поэтому при повторной
// загрузке картинки для уже существующего элемента нужно явно повторить текущие SUBTITLE/LINK,
// иначе они молча стёрлись бы; для ещё не существующего элемента — взять text_defaults, как и
// в блоке set_overlay выше.
$textDefaults = $slots[$slotCode]['text_defaults'] ?? null;
$currentName = $slots[$slotCode]['label'];
$textPropertyValues = [];
if ($textDefaults) {
    $currentName = $existing ? (string)$existing['NAME'] : $textDefaults['NAME'];
    $textPropertyValues = [
        'SUBTITLE' => $existing ? (string)($existing['PROPERTY_SUBTITLE_VALUE'] ?? '') : $textDefaults['SUBTITLE'],
        'LINK' => $existing ? (string)($existing['PROPERTY_LINK_VALUE'] ?? '') : $textDefaults['LINK'],
    ];
}

$fields = [
    'IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID,
    'ACTIVE' => 'Y',
    'NAME' => $currentName,
    'DETAIL_PICTURE' => $fileArray,
    'PREVIEW_PICTURE' => $fileArray,
    'PROPERTY_VALUES' => ['PLACEMENT' => $slotCode, 'OVERLAY' => $currentOverlay] + $textPropertyValues,
];
// Кладём в раздел "Плитки главной", если он уже заведён (add_iblock27_tiles_section.php) — иначе
// этот элемент неотличим в общем списке ИБ 27 от слайдов главной карусели и попадает туда как
// слайд (см. index.php: пропуск слайдов с чужим PLACEMENT). На разбор PLACEMENT самой плиткой
// (eportaBannersResolveImage) раздел не влияет — тот фильтрует по PLACEMENT, не по разделу.
$tilesSectionId = eportaBannersTilesSectionId();
if ($tilesSectionId) {
    $fields['IBLOCK_SECTION_ID'] = $tilesSectionId;
}

if ($existing) {
    $ok = $elObj->Update((int)$existing['ID'], $fields);
    $elementId = $ok ? (int)$existing['ID'] : false;
} else {
    $elementId = $elObj->Add($fields);
}

@unlink($tmpPath);

if (!$elementId) {
    eportaBannersJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
}

$el = CIBlockElement::GetByID($elementId)->GetNext();
$imgPath = $el && $el['DETAIL_PICTURE'] ? CFile::GetPath($el['DETAIL_PICTURE']) : '';

echo json_encode(['ok' => true, 'image' => $imgPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
