<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

$IBLOCK_ID = 19;
$PRICE_TYPE_ID = 1; // BASE

$jsonPath = $argv[1] ?? null;
if (!$jsonPath || !file_exists($jsonPath)) {
    die("Использование: import_products.php <products.json>\n");
}
$products = json_decode(file_get_contents($jsonPath), true);
if (!is_array($products)) {
    die("Не смог разобрать JSON\n");
}

// XML_ID секции коллекции = нижний регистр латиницей (см. существующее дерево
// "Коллекции" ID 183: vilis/vetus/dorsum/...), сопоставляем по NAME без учёта регистра.
$sectionIdByCollectionName = [];
$res = CIBlockSection::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, '=IBLOCK_SECTION_ID' => 183], false, ['ID', 'NAME']);
while ($s = $res->Fetch()) {
    $sectionIdByCollectionName[mb_strtolower($s['NAME'])] = $s['ID'];
}

function getOrCreateEnumId($iblockId, $propertyCode, $value) {
    static $cache = [];
    if ($value === '' || $value === null) {
        return null;
    }
    $key = $propertyCode;
    if (!isset($cache[$key])) {
        $cache[$key] = [];
        $res = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode]);
        while ($e = $res->Fetch()) {
            $cache[$key][mb_strtolower($e['VALUE'])] = $e['ID'];
        }
    }
    $lower = mb_strtolower($value);
    if (isset($cache[$key][$lower])) {
        return $cache[$key][$lower];
    }
    // значения нет в справочнике — добавляем новый вариант enum'а на лету
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
    $cache[$key][$lower] = $enumId;
    return $enumId;
}

$GLOBALS['__downloadedTmpFiles'] = [];

function downloadImage($url) {
    if (!$url) {
        return false;
    }
    // itphoto.ru (хостинг фото поставщика в 1С-выгрузке) отдаёт неполную цепочку
    // сертификата — file_get_contents/CFile::MakeFileArray($url) рвётся на проверке
    // SSL. Качаем сами через curl без верификации (сторонний CDN с фото, не наш прод)
    // во временный файл и уже из локального файла собираем массив для Bitrix.
    // Расширение нужно ДО MakeFileArray — тип файла Bitrix определяет по имени
    // временного пути, а не по содержимому, иначе получим "не графический файл".
    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
    $tmpBase = tempnam(sys_get_temp_dir(), 'imgimport_');
    $tmpFile = $tmpBase . '.' . $ext;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    @unlink($tmpBase);
    if ($data === false || $httpCode !== 200) {
        echo "  не удалось скачать ($httpCode): $url\n";
        @unlink($tmpFile);
        return false;
    }
    file_put_contents($tmpFile, $data);
    $arFile = CFile::MakeFileArray($tmpFile);
    if (is_array($arFile)) {
        $arFile['name'] = basename(parse_url($url, PHP_URL_PATH));
    }
    if (!is_array($arFile) || empty($arFile['name'])) {
        echo "  не удалось собрать файл: $url\n";
        @unlink($tmpFile);
        return false;
    }
    // tmp_name должен дожить до CIBlockElement::Add()/Update() — удаляем позже, скопом
    $GLOBALS['__downloadedTmpFiles'][] = $tmpFile;
    return $arFile;
}

// Составляет название двери из нескольких полей (см. lib.php eportaImportComposeName —
// тот же формат для веб-загрузки, продублировано здесь т.к. этот CLI-скрипт не подключает
// local/admin_tools/eporta_import/lib.php). "Коллекция Модель, Покрытие Цвет".
function composeName(array $p, string $article): string {
    $collection = trim($p['collection'] ?? '');
    $model = trim($p['model'] ?? '');
    $coating = trim($p['coating'] ?? '');
    $color = trim($p['coating_color'] ?? '');

    // См. eportaImportComposeName в lib.php — "Модель" часто уже содержит название коллекции.
    if ($collection !== '' && $model !== '' && stripos($model, $collection) === 0) {
        $head = $model;
    } else {
        $head = trim($collection.' '.$model);
    }
    $tail = trim(implode(' ', array_filter([$coating, $color])));

    $name = $tail !== '' ? ($head !== '' ? $head.', '.$tail : $tail) : $head;
    if ($name === '') {
        $name = trim($p['name'] ?? '') ?: $article;
    }
    return $name;
}

foreach ($products as $p) {
    $article = $p['article'] ?? '';
    if ($article === '') {
        echo "Пропуск: нет артикула\n";
        continue;
    }

    $sectionId = $sectionIdByCollectionName[mb_strtolower($p['collection'] ?? '')] ?? false;

    $propertyValues = [
        'CML2_ARTICLE'    => $article,
        'MODEL'           => $p['model'] ?? '',
        'MANUFACTURER'    => $p['manufacturer'] ?? '',
        'BRAND'           => $p['brand'] ?? '',
        'CATEGORY'        => $p['category'] ?? '',
        'COLLECTION'      => $p['collection'] ?? '',
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
        $enumId = getOrCreateEnumId($IBLOCK_ID, $code, $p[$key] ?? '');
        if ($enumId) {
            $propertyValues[$code] = $enumId;
        }
    }

    $styleEnumIds = [];
    foreach ($p['style'] ?? [] as $styleValue) {
        $enumId = getOrCreateEnumId($IBLOCK_ID, 'STYLE', $styleValue);
        if ($enumId) {
            $styleEnumIds[] = $enumId;
        }
    }
    $propertyValues['STYLE'] = $styleEnumIds;

    $galleryFiles = [];
    foreach (array_unique($p['gallery'] ?? []) as $galleryUrl) {
        $f = downloadImage($galleryUrl);
        if ($f) {
            $galleryFiles[] = $f;
        }
    }
    if ($galleryFiles) {
        $propertyValues['MORE_PHOTO'] = $galleryFiles;
    }

    $elFields = [
        'IBLOCK_ID' => $IBLOCK_ID,
        'NAME' => composeName($p, $article),
        'ACTIVE' => 'Y',
        'IBLOCK_SECTION_ID' => $sectionId,
        // full_desc/short_desc из 1С — реальная HTML-разметка (<p>...</p> и т.п.), а не
        // обычный текст. Без явного TEXT_TYPE=>"html" Bitrix хранит поле как "text" и на
        // выводе экранирует теги (видны буквально как &lt;p&gt;) — см. project_audit_issues,
        // фикс двойного экранирования описания в карточке товара.
        'PREVIEW_TEXT' => $p['short_desc'] ?? '',
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $p['full_desc'] ?? '',
        'DETAIL_TEXT_TYPE' => 'html',
        'PROPERTY_VALUES' => $propertyValues,
    ];

    $detailPicture = downloadImage($p['photo_big'] ?? null);
    if ($detailPicture) {
        $elFields['DETAIL_PICTURE'] = $detailPicture;
    }
    $previewPicture = downloadImage($p['photo_preview'] ?? null);
    if ($previewPicture) {
        $elFields['PREVIEW_PICTURE'] = $previewPicture;
    }

    $elObj = new CIBlockElement;
    $existingId = false;
    $res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'PROPERTY_CML2_ARTICLE' => $article], false, false, ['ID']);
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

    if (!$ok) {
        echo "Ошибка артикул $article: {$elObj->LAST_ERROR}\n";
        continue;
    }

    echo "Элемент $elementId (артикул $article) " . ($existingId ? 'обновлён' : 'создан') . "\n";

    if (!empty($p['price'])) {
        $priceFields = [
            'PRODUCT_ID' => $elementId,
            'CATALOG_GROUP_ID' => $PRICE_TYPE_ID,
            'PRICE' => $p['price'],
            'CURRENCY' => 'RUB',
        ];
        $existingPrice = CPrice::GetList([], ['PRODUCT_ID' => $elementId, 'CATALOG_GROUP_ID' => $PRICE_TYPE_ID])->Fetch();
        if ($existingPrice) {
            CPrice::Update($existingPrice['ID'], $priceFields);
        } else {
            CPrice::Add($priceFields);
        }
        echo "  цена: {$p['price']} руб.\n";
    }

    foreach ($GLOBALS['__downloadedTmpFiles'] as $tmpFile) {
        @unlink($tmpFile);
    }
    $GLOBALS['__downloadedTmpFiles'] = [];
}

echo "Готово.\n";
