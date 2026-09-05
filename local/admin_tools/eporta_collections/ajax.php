<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaCollectionsJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaCollectionsUserHasAccess()) {
    eportaCollectionsJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaCollectionsJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $error = null;
    $sectionId = eportaCollectionsCreate($name, $description, $error);
    if (!$sectionId) {
        eportaCollectionsJsonFail($error ?: 'Ошибка создания коллекции', 500);
    }
    echo json_encode(['ok' => true, 'id' => $sectionId], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'update') {
    $sectionId = (int)($_POST['id'] ?? 0);
    if ($sectionId <= 0) {
        eportaCollectionsJsonFail('Некорректный ID коллекции');
    }
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $sort = (int)($_POST['sort'] ?? 500);
    $active = ($_POST['active'] ?? 'Y') !== 'N';
    $error = null;
    $ok = eportaCollectionsUpdate($sectionId, $name, $description, $sort, $active, $error);
    if (!$ok) {
        eportaCollectionsJsonFail($error ?: 'Ошибка сохранения коллекции', 500);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'upload_banner') {
    $sectionId = (int)($_POST['id'] ?? 0);
    if ($sectionId <= 0) {
        eportaCollectionsJsonFail('Некорректный ID коллекции');
    }
    if (empty($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
        eportaCollectionsJsonFail('Файл не загружен');
    }

    $allowedExt = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        eportaCollectionsJsonFail('Допустимые форматы: JPG, PNG');
    }
    // Полноразмерный баннер шапки страницы коллекции (не плитка) — лимит выше, чем у плиток
    // в eporta_banners/ajax.php (8 МБ).
    if ($_FILES['banner']['size'] > 15 * 1024 * 1024) {
        eportaCollectionsJsonFail('Файл слишком большой (максимум 15 МБ)');
    }

    $tmpDir = eportaCollectionsTmpDir();
    $tmpPath = $tmpDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['banner']['tmp_name'], $tmpPath)) {
        eportaCollectionsJsonFail('Не удалось сохранить загруженный файл');
    }
    if (!@getimagesize($tmpPath)) {
        @unlink($tmpPath);
        eportaCollectionsJsonFail('Файл повреждён или не является изображением');
    }

    $fileArray = CFile::MakeFileArray($tmpPath);
    if (!$fileArray) {
        @unlink($tmpPath);
        eportaCollectionsJsonFail('Не удалось подготовить файл для сохранения');
    }

    $error = null;
    $ok = eportaCollectionsUpdateBanner($sectionId, $fileArray, $error);
    @unlink($tmpPath);
    if (!$ok) {
        eportaCollectionsJsonFail($error ?: 'Ошибка сохранения баннера', 500);
    }

    $section = CIBlockSection::GetByID($sectionId)->GetNext();
    $imgPath = $section && $section['DETAIL_PICTURE'] ? CFile::GetPath($section['DETAIL_PICTURE']) : '';
    echo json_encode(['ok' => true, 'image' => $imgPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

eportaCollectionsJsonFail('Неизвестное действие');
