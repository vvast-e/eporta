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

if (!in_array($action, ['upload', 'set_overlay', 'save_meta'], true)) {
    eportaBannersJsonFail('Неизвестное действие');
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
    $link = trim((string)($_POST['link'] ?? ''));
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
        $fields = [
            'IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID,
            'ACTIVE' => 'Y',
            'NAME' => $slots[$slotCode]['label'],
            'PROPERTY_VALUES' => ['PLACEMENT' => $slotCode, 'OVERLAY' => $overlayValue],
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

$fields = [
    'IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID,
    'ACTIVE' => 'Y',
    'NAME' => $slots[$slotCode]['label'],
    'DETAIL_PICTURE' => $fileArray,
    'PREVIEW_PICTURE' => $fileArray,
    'PROPERTY_VALUES' => ['PLACEMENT' => $slotCode, 'OVERLAY' => $currentOverlay],
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
