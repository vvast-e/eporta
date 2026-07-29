<?php
// Общая логика страницы самостоятельной загрузки товаров (IBLOCK 19).
// Требует уже подключенный prolog_before.php (модули main/iblock/catalog).
// Ничего не подключает из bitrix/ помимо стандартного ядра — сам bitrix/
// это общий с dverimarket.ru код, самостоятельных файлов туда не кладём.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die('Прямой доступ запрещён');
}

const EPORTA_IMPORT_IBLOCK_ID = 19;
const EPORTA_IMPORT_COLLECTIONS_SECTION_ID = 183;
const EPORTA_IMPORT_PRICE_TYPE_ID = 1; // BASE

// 1С-заголовок (лист "Тест") -> внутренний ключ. Совпадает с xlsx_to_json.py.
const EPORTA_IMPORT_FIELD_MAP = [
    'Артикул' => 'article',
    'Модель' => 'model',
    'Название' => 'name',
    'Фабрика' => 'manufacturer',
    'Бренд' => 'brand',
    'Категория' => 'category',
    'Коллекция' => 'collection',
    'Цена' => 'price',
    'Скидка' => 'discount',
    'Покрытие' => 'coating',
    'Цвет' => 'coating_color',
    'Оттенок' => 'main_color',
    'Остекление' => 'glazing',
    'Кромка' => 'edge',
    'Стиль' => 'style',
    'Открывание' => 'open_type',
    'Вид двери' => 'door_type',
    'Конструкция' => 'construction',
    'Материал' => 'material',
    'Шумоизоляция' => 'noise_isolation',
    'Огнестойкость' => 'fire_resistance',
    'Гарантия' => 'warranty',
    'Размеры' => 'sizes',
    'Наличие' => 'availability',
    'Срок поставки' => 'lead_time',
    'Краткое описание' => 'short_desc',
    'Полное описание' => 'full_desc',
    'Фото-Big' => 'photo_big',
    'Фото-Preview' => 'photo_preview',
    'Галеррея' => 'gallery',
    'Рейтинг' => 'rating',
];

const EPORTA_IMPORT_MULTI_FIELDS = ['style', 'open_type', 'material'];
const EPORTA_IMPORT_NUM_FIELDS = ['price', 'discount', 'rating'];
const EPORTA_IMPORT_REQUIRED_HEADERS = ['Артикул'];

function eportaImportTmpDir(): string {
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/local/tmp/eporta_import';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
    return $dir;
}

// Текущий пользователь имеет право на запись в IBLOCK 19 (стандартная модель прав Bitrix).
function eportaImportUserHasAccess(): bool {
    global $USER;
    if (!$USER->IsAuthorized()) {
        return false;
    }
    if ($USER->IsAdmin()) {
        return true;
    }
    $permission = CIBlock::GetPermission(EPORTA_IMPORT_IBLOCK_ID);
    return $permission >= 'W';
}

// --- Минимальный XLSX-парсер (zip + xml), т.к. на проде нет composer/PhpSpreadsheet ---

function eportaImportColLetterToIndex(string $ref): int {
    // "C7" -> "C" -> 2 (0-based)
    preg_match('/^([A-Z]+)/', $ref, $m);
    $letters = $m[1] ?? 'A';
    $idx = 0;
    foreach (str_split($letters) as $ch) {
        $idx = $idx * 26 + (ord($ch) - ord('A') + 1);
    }
    return $idx - 1;
}

function eportaImportParseSheetXml(string $xml, array $sharedStrings): array {
    libxml_use_internal_errors(true);
    $sx = simplexml_load_string($xml);
    if ($sx === false) {
        return [];
    }
    $rows = [];
    foreach ($sx->sheetData->row as $rowNode) {
        $rowIdx = (int)$rowNode['r'] - 1;
        $cells = [];
        foreach ($rowNode->c as $cellNode) {
            $ref = (string)$cellNode['r'];
            $colIdx = eportaImportColLetterToIndex($ref);
            $type = (string)$cellNode['t'];
            $value = '';
            if ($type === 's') {
                $sIdx = (int)$cellNode->v;
                $value = $sharedStrings[$sIdx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)($cellNode->is->t ?? '');
            } elseif ($type === 'str' || $type === 'b') {
                $value = (string)$cellNode->v;
            } else {
                $value = (string)$cellNode->v;
            }
            $cells[$colIdx] = $value;
        }
        $rows[$rowIdx] = $cells;
    }
    return $rows;
}

function eportaImportReadSharedStrings(ZipArchive $zip): array {
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }
    libxml_use_internal_errors(true);
    $sx = simplexml_load_string($xml);
    if ($sx === false) {
        return [];
    }
    $strings = [];
    foreach ($sx->si as $si) {
        if (isset($si->t)) {
            $strings[] = (string)$si->t;
        } else {
            // rich text: несколько <r><t>...</t></r> подряд
            $text = '';
            foreach ($si->r as $r) {
                $text .= (string)$r->t;
            }
            $strings[] = $text;
        }
    }
    return $strings;
}

// Открывает xlsx, находит лист $sheetName, возвращает грид [rowIdx => [colIdx => value]]
function eportaImportReadSheet(string $filePath, string $sheetName): ?array {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return null;
    }
    $sharedStrings = eportaImportReadSharedStrings($zip);

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false || $relsXml === false) {
        $zip->close();
        return null;
    }

    libxml_use_internal_errors(true);
    $wb = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);
    if ($wb === false || $rels === false) {
        $zip->close();
        return null;
    }

    $rIdToTarget = [];
    foreach ($rels->Relationship as $rel) {
        $rIdToTarget[(string)$rel['Id']] = (string)$rel['Target'];
    }

    $sheetTarget = null;
    $wbNs = $wb->getNamespaces(true);
    $rNs = $wbNs['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    foreach ($wb->sheets->sheet as $sheet) {
        if ((string)$sheet['name'] === $sheetName) {
            $attrs = $sheet->attributes($rNs);
            $rId = (string)$attrs['id'];
            $sheetTarget = $rIdToTarget[$rId] ?? null;
            break;
        }
    }

    if ($sheetTarget === null) {
        $zip->close();
        return null;
    }

    $sheetPath = 'xl/' . ltrim($sheetTarget, '/');
    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) {
        return null;
    }

    return eportaImportParseSheetXml($sheetXml, $sharedStrings);
}

function eportaImportNormalizeSizes(string $value): array {
    if ($value === '') {
        return [];
    }
    $value = str_replace(['х', 'Х'], 'x', $value); // кириллическая "х" -> латинская
    return array_values(array_filter(array_map('trim', explode(',', $value)), fn($s) => $s !== ''));
}

function eportaImportNormalizeMulti(string $value): array {
    if ($value === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $value)), fn($s) => $s !== ''));
}

function eportaImportNormalizeGallery(string $value): array {
    if ($value === '') {
        return [];
    }
    $parts = preg_split('/[;,]/', $value);
    return array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
}

// Разбирает грид листа на заголовки + строки-товары. Возвращает:
// ['headers' => [...], 'unmapped' => [...], 'missing_required' => [...], 'products' => [...]]
function eportaImportParseGrid(array $grid): array {
    // ищем строку заголовков — ту, где встречается "Артикул"
    $headerRowIdx = null;
    $headerCols = [];
    foreach ($grid as $rowIdx => $cells) {
        if (in_array('Артикул', $cells, true)) {
            $headerRowIdx = $rowIdx;
            $headerCols = $cells;
            break;
        }
    }

    if ($headerRowIdx === null) {
        return [
            'headers' => [],
            'unmapped' => [],
            'missing_required' => EPORTA_IMPORT_REQUIRED_HEADERS,
            'products' => [],
            'error' => 'Не найдена строка заголовков (нет колонки "Артикул")',
        ];
    }

    $colToKey = []; // colIdx => internal key
    $headers = [];
    foreach ($headerCols as $colIdx => $header) {
        $header = trim((string)$header);
        if ($header === '') {
            continue;
        }
        $headers[] = $header;
        if (isset(EPORTA_IMPORT_FIELD_MAP[$header])) {
            $colToKey[$colIdx] = EPORTA_IMPORT_FIELD_MAP[$header];
        }
    }

    $unmapped = array_values(array_diff($headers, array_keys(EPORTA_IMPORT_FIELD_MAP)));
    $missingRequired = array_values(array_diff(EPORTA_IMPORT_REQUIRED_HEADERS, $headers));

    $products = [];
    foreach ($grid as $rowIdx => $cells) {
        if ($rowIdx <= $headerRowIdx) {
            continue;
        }
        $articleColIdx = array_search('article', $colToKey, true);
        $article = $articleColIdx !== false ? trim((string)($cells[$articleColIdx] ?? '')) : '';
        if ($article === '') {
            continue;
        }

        $item = ['_row' => $rowIdx + 1];
        foreach ($colToKey as $colIdx => $key) {
            $raw = trim((string)($cells[$colIdx] ?? ''));
            if ($key === 'sizes') {
                $item[$key] = eportaImportNormalizeSizes($raw);
            } elseif ($key === 'gallery') {
                $item[$key] = eportaImportNormalizeGallery($raw);
            } elseif (in_array($key, EPORTA_IMPORT_MULTI_FIELDS, true)) {
                $item[$key] = eportaImportNormalizeMulti($raw);
            } elseif (in_array($key, EPORTA_IMPORT_NUM_FIELDS, true)) {
                $item[$key] = $raw !== '' ? (float)str_replace(',', '.', $raw) : 0;
            } else {
                $item[$key] = $raw;
            }
        }
        $products[] = $item;
    }

    return [
        'headers' => $headers,
        'unmapped' => $unmapped,
        'missing_required' => $missingRequired,
        'products' => $products,
    ];
}

// --- Импорт в IBLOCK 19 (логика 1:1 с scripts/import/import_products.php) ---

function eportaImportGetOrCreateEnumId(int $iblockId, string $propertyCode, string $value): ?int {
    static $cache = [];
    if ($value === '') {
        return null;
    }
    if (!isset($cache[$propertyCode])) {
        $cache[$propertyCode] = [];
        $res = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode]);
        while ($e = $res->Fetch()) {
            $cache[$propertyCode][mb_strtolower($e['VALUE'])] = (int)$e['ID'];
        }
    }
    $lower = mb_strtolower($value);
    if (isset($cache[$propertyCode][$lower])) {
        return $cache[$propertyCode][$lower];
    }
    $propRes = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode]);
    $prop = $propRes->Fetch();
    if (!$prop) {
        return null;
    }
    $enumId = CIBlockPropertyEnum::Add([
        'PROPERTY_ID' => $prop['ID'],
        'VALUE' => $value,
        'DEF' => 'N',
        'SORT' => 500,
    ]);
    $cache[$propertyCode][$lower] = $enumId;
    return $enumId;
}

function eportaImportSectionMap(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    $res = CIBlockSection::GetList([], [
        'IBLOCK_ID' => EPORTA_IMPORT_IBLOCK_ID,
        '=IBLOCK_SECTION_ID' => EPORTA_IMPORT_COLLECTIONS_SECTION_ID,
    ], false, ['ID', 'NAME']);
    while ($s = $res->Fetch()) {
        $map[mb_strtolower($s['NAME'])] = $s['ID'];
    }
    return $map;
}

// URL приходит из загруженного сотрудником xlsx — не доверенный источник.
// Не даём серверу ходить в приватные/локальные сети (SSRF) под видом "скачивания фото".
function eportaImportIsPublicImageUrl(string $url): bool {
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
        return false;
    }
    $ips = @gethostbynamel($parts['host']);
    if (!$ips) {
        return false;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

function eportaImportDownloadImage(?string $url) {
    if (!$url || !eportaImportIsPublicImageUrl($url)) {
        return false;
    }
    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
    $tmpBase = tempnam(sys_get_temp_dir(), 'imgimport_');
    $tmpFile = $tmpBase . '.' . $ext;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        // itphoto.ru (хостинг фото поставщика в 1С-выгрузке) отдаёт неполную цепочку
        // сертификата — сторонний CDN с фото, не наш прод, поэтому проверку отключаем,
        // но сам хост уже проверен выше на публичность (не приватная сеть).
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    @unlink($tmpBase);
    if ($data === false || $httpCode !== 200) {
        @unlink($tmpFile);
        return false;
    }
    file_put_contents($tmpFile, $data);
    $arFile = CFile::MakeFileArray($tmpFile);
    if (is_array($arFile)) {
        $arFile['name'] = basename(parse_url($url, PHP_URL_PATH));
    }
    if (!is_array($arFile) || empty($arFile['name'])) {
        @unlink($tmpFile);
        return false;
    }
    $GLOBALS['__eportaImportTmpFiles'][] = $tmpFile;
    return $arFile;
}

// Импортирует один товар. Возвращает ['article'=>, 'status'=>created|updated|error, 'message'=>]
function eportaImportOneProduct(array $p): array {
    $article = $p['article'] ?? '';
    $GLOBALS['__eportaImportTmpFiles'] = [];

    $sectionMap = eportaImportSectionMap();
    $collection = $p['collection'] ?? '';
    $sectionId = $sectionMap[mb_strtolower($collection)] ?? false;
    if ($collection !== '' && $sectionId === false) {
        return ['article' => $article, 'status' => 'error', 'message' => "Не найдена коллекция \"$collection\" в разделе Коллекции"];
    }

    $propertyValues = [
        'CML2_ARTICLE'    => $article,
        'MODEL'           => $p['model'] ?? '',
        'MANUFACTURER'    => $p['manufacturer'] ?? '',
        'BRAND'           => $p['brand'] ?? '',
        'CATEGORY'        => $p['category'] ?? '',
        'COLLECTION'      => $collection,
        'DISCOUNT'        => $p['discount'] ?? 0,
        'EDGE'            => $p['edge'] ?? '',
        'OPEN_TYPE'       => $p['open_type'] ?? [],
        'DOOR_TYPE'       => $p['door_type'] ?? '',
        'CONSTRUCTION'    => $p['construction'] ?? '',
        'MATERIAL'        => $p['material'] ?? [],
        'NOISE_ISOLATION' => $p['noise_isolation'] ?? '',
        'FIRE_RESISTANCE' => $p['fire_resistance'] ?? '',
        'WARRANTY'        => $p['warranty'] ?? '',
        'SIZES'           => implode(',', $p['sizes'] ?? []),
        'AVAILABILITY'    => $p['availability'] ?? '',
        'LEAD_TIME'       => $p['lead_time'] ?? '',
        'SHORT_DESC'      => $p['short_desc'] ?? '',
        'RATING'          => $p['rating'] ?? 0,
    ];

    foreach (['COATING' => 'coating', 'COATING_COLOR' => 'coating_color', 'MAIN_COLOR' => 'main_color', 'GLAZING' => 'glazing'] as $code => $key) {
        $enumId = eportaImportGetOrCreateEnumId(EPORTA_IMPORT_IBLOCK_ID, $code, $p[$key] ?? '');
        if ($enumId) {
            $propertyValues[$code] = $enumId;
        }
    }

    $styleEnumIds = [];
    foreach ($p['style'] ?? [] as $styleValue) {
        $enumId = eportaImportGetOrCreateEnumId(EPORTA_IMPORT_IBLOCK_ID, 'STYLE', $styleValue);
        if ($enumId) {
            $styleEnumIds[] = $enumId;
        }
    }
    $propertyValues['STYLE'] = $styleEnumIds;

    $galleryFiles = [];
    foreach (array_unique($p['gallery'] ?? []) as $galleryUrl) {
        $f = eportaImportDownloadImage($galleryUrl);
        if ($f) {
            $galleryFiles[] = $f;
        }
    }
    if ($galleryFiles) {
        $propertyValues['MORE_PHOTO'] = $galleryFiles;
    }

    $elFields = [
        'IBLOCK_ID' => EPORTA_IMPORT_IBLOCK_ID,
        'NAME' => $p['name'] ?? $article,
        'ACTIVE' => 'Y',
        'IBLOCK_SECTION_ID' => $sectionId,
        'PREVIEW_TEXT' => $p['short_desc'] ?? '',
        'DETAIL_TEXT' => $p['full_desc'] ?? '',
        'PROPERTY_VALUES' => $propertyValues,
    ];

    $detailPicture = eportaImportDownloadImage($p['photo_big'] ?? null);
    if ($detailPicture) {
        $elFields['DETAIL_PICTURE'] = $detailPicture;
    }
    $previewPicture = eportaImportDownloadImage($p['photo_preview'] ?? null);
    if ($previewPicture) {
        $elFields['PREVIEW_PICTURE'] = $previewPicture;
    }

    $elObj = new CIBlockElement;
    $existingId = false;
    $res = CIBlockElement::GetList([], ['IBLOCK_ID' => EPORTA_IMPORT_IBLOCK_ID, 'PROPERTY_CML2_ARTICLE' => $article], false, false, ['ID']);
    if ($el = $res->Fetch()) {
        $existingId = $el['ID'];
    }

    if ($existingId) {
        $ok = $elObj->Update($existingId, $elFields);
        $elementId = $existingId;
    } else {
        $elementId = $elObj->Add($elFields);
        $ok = (bool)$elementId;
    }

    foreach ($GLOBALS['__eportaImportTmpFiles'] as $tmpFile) {
        @unlink($tmpFile);
    }
    $GLOBALS['__eportaImportTmpFiles'] = [];

    if (!$ok) {
        return ['article' => $article, 'status' => 'error', 'message' => $elObj->LAST_ERROR];
    }

    if (!empty($p['price'])) {
        $priceFields = [
            'PRODUCT_ID' => $elementId,
            'CATALOG_GROUP_ID' => EPORTA_IMPORT_PRICE_TYPE_ID,
            'PRICE' => $p['price'],
            'CURRENCY' => 'RUB',
        ];
        $existingPrice = CPrice::GetList([], ['PRODUCT_ID' => $elementId, 'CATALOG_GROUP_ID' => EPORTA_IMPORT_PRICE_TYPE_ID])->Fetch();
        if ($existingPrice) {
            CPrice::Update($existingPrice['ID'], $priceFields);
        } else {
            CPrice::Add($priceFields);
        }
    }

    return [
        'article' => $article,
        'status' => $existingId ? 'updated' : 'created',
        'message' => "Элемент #$elementId",
    ];
}
