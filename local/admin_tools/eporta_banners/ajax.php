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

if ($action !== 'upload') {
    eportaBannersJsonFail('Неизвестное действие');
}

$slots = eportaBannersSlots();
$slotCode = (string)($_POST['slot'] ?? '');
if (!isset($slots[$slotCode])) {
    eportaBannersJsonFail('Неизвестный слот');
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

$fields = [
    'IBLOCK_ID' => EPORTA_BANNERS_IBLOCK_ID,
    'ACTIVE' => 'Y',
    'NAME' => $slots[$slotCode]['label'],
    'DETAIL_PICTURE' => $fileArray,
    'PREVIEW_PICTURE' => $fileArray,
    'PROPERTY_VALUES' => ['PLACEMENT' => $slotCode],
];

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
