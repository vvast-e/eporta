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

if ($action === 'update_banner_meta') {
    $sectionId = (int)($_POST['id'] ?? 0);
    if ($sectionId <= 0) {
        eportaCollectionsJsonFail('Некорректный ID коллекции');
    }
    $overlay = ($_POST['overlay'] ?? 'Y') !== 'N';
    $ctaText = trim((string)($_POST['cta_text'] ?? ''));
    $ctaLink = eportaSanitizeBannerLink((string)($_POST['cta_link'] ?? ''));
    $error = null;
    $ok = eportaCollectionsUpdateBannerMeta($sectionId, $overlay, $ctaText, $ctaLink, $error);
    if (!$ok) {
        eportaCollectionsJsonFail($error ?: 'Ошибка сохранения настроек баннера', 500);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// Модели/варианты коллекции (перенесено из local/admin_tools/eporta_showcase/, задача
// 06.09.2026) — логика в eporta_showcase/lib.php (require_once в lib.php этой админки),
// здесь только HTTP-обвязка и проверка принадлежности ID к IBLOCK 19.

if ($action === 'get_models') {
    $sectionId = (int)($_POST['section_id'] ?? 0);
    if ($sectionId <= 0) {
        eportaCollectionsJsonFail('Некорректный ID коллекции');
    }
    $models = eportaShowcaseGetModels($sectionId);
    echo json_encode(['ok' => true, 'models' => $models], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'set_showcase') {
    $selectedId = (int)($_POST['id'] ?? 0);
    $allIdsRaw = $_POST['all_ids'] ?? '';
    $allIds = array_filter(array_map('intval', explode(',', (string)$allIdsRaw)));

    if (!$selectedId || !in_array($selectedId, $allIds, true)) {
        eportaCollectionsJsonFail('Некорректные данные варианта');
    }

    // Все ID должны реально принадлежать IBLOCK 19 — защита от произвольной записи по чужим ID.
    $verifyRes = CIBlockElement::GetList([], ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID, 'ID' => $allIds], false, false, ['ID']);
    $verifiedIds = [];
    while ($row = $verifyRes->Fetch()) {
        $verifiedIds[] = (int)$row['ID'];
    }
    if (count($verifiedIds) !== count($allIds) || !in_array($selectedId, $verifiedIds, true)) {
        eportaCollectionsJsonFail('Некорректные данные варианта');
    }

    $ok = eportaShowcaseSetVariant($selectedId, $verifiedIds);
    if (!$ok) {
        eportaCollectionsJsonFail('Ошибка сохранения', 500);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'set_show_in_list') {
    $variantId = (int)($_POST['id'] ?? 0);
    $show = ($_POST['show'] ?? '') === '1';

    if (!$variantId) {
        eportaCollectionsJsonFail('Некорректные данные варианта');
    }

    // ID должен реально принадлежать IBLOCK 19 — защита от произвольной записи по чужим ID.
    $verifyRow = CIBlockElement::GetList([], ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID, 'ID' => $variantId], false, false, ['ID'])->Fetch();
    if (!$verifyRow) {
        eportaCollectionsJsonFail('Некорректные данные варианта');
    }

    $ok = eportaShowcaseSetShowInList($variantId, $show);
    if (!$ok) {
        eportaCollectionsJsonFail('Ошибка сохранения', 500);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaCollectionsJsonFail('Неизвестное действие');
