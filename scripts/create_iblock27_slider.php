<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

global $DB;

$TARGET_ID = 27;

$res = CIBlock::GetList([], ['ID' => $TARGET_ID]);
if ($res->Fetch()) {
    die("IBLOCK $TARGET_ID уже существует, прерываю.\n");
}

$maxId = $DB->Query("SELECT MAX(ID) AS M FROM b_iblock")->Fetch()['M'];
if ($maxId >= $TARGET_ID) {
    die("Текущий максимум b_iblock ($maxId) >= $TARGET_ID, форсировать AUTO_INCREMENT нельзя (InnoDB не разрешит). Прерываю.\n");
}
$DB->Query("ALTER TABLE b_iblock AUTO_INCREMENT = $TARGET_ID");

$ib = new CIBlock;
$fields = [
    'ACTIVE' => 'Y',
    'NAME' => 'Слайдер для верхнего меню',
    'CODE' => 'slider',
    'IBLOCK_TYPE_ID' => 'slider',
    'SITE_ID' => ['s1'],
    'SORT' => 500,
    'LIST_PAGE_URL' => '/sliders/index.php?ID=#IBLOCK_ID#',
    'DETAIL_PAGE_URL' => '/sliders/detail.php?ID=#ELEMENT_ID#',
    'SECTION_PAGE_URL' => '/sliders/list.php?SECTION_ID=#SECTION_ID#',
    'CANONICAL_PAGE_URL' => '',
    'PICTURE' => '',
    'INDEX_ELEMENT' => 'Y',
    'INDEX_SECTION' => 'Y',
    'WORKFLOW' => 'N',
    'BIZPROC' => 'N',
    'SECTION_CHOOSER' => 'L',
    'LIST_MODE' => '',
    'RIGHTS_MODE' => 'S',
    'SECTION_PROPERTY' => 'N',
    'PROPERTY_INDEX' => 'N',
    'VERSION' => 2,
    'LAST_CONV_ELEMENT' => 0,
    'SOCNET_GROUP_ID' => false,
    'EDIT_FILE_BEFORE' => '',
    'EDIT_FILE_AFTER' => '',
    'SECTIONS_NAME' => 'Разделы',
    'SECTION_NAME' => 'Раздел',
    'ELEMENTS_NAME' => 'Элементы',
    'ELEMENT_NAME' => 'Элемент',
];
$ibId = $ib->Add($fields);
if (!$ibId) {
    die("Ошибка создания инфоблока: " . $ib->LAST_ERROR . "\n");
}
if ((int)$ibId !== $TARGET_ID) {
    die("Инфоблок создан с ID=$ibId вместо $TARGET_ID! Проверьте вручную.\n");
}
echo "IBLOCK создан с ID=$ibId\n";

$propObj = new CIBlockProperty;

$properties = [
    'POSITION' => [
        'NAME' => 'С какой стороны поставить текст',
        'PROPERTY_TYPE' => 'L',
        'LIST_TYPE' => 'L',
        'ENUM' => [
            ['VALUE' => 'По центру', 'XML_ID' => 'center', 'DEF' => 'Y'],
            ['VALUE' => 'Слева', 'XML_ID' => 'left'],
            ['VALUE' => 'Справа', 'XML_ID' => 'right'],
        ],
    ],
    'VIDEO_SLIDE' => [
        'NAME' => 'Видео',
        'PROPERTY_TYPE' => 'F',
        'FILE_TYPE' => 'mp4',
    ],
    'VIDEO_POSTER' => [
        'NAME' => 'Картинка-заглушка видео для мобильных',
        'PROPERTY_TYPE' => 'F',
        'FILE_TYPE' => 'jpg, gif, bmp, png, jpeg',
    ],
    'VIDEO_COLOR' => [
        'NAME' => 'Цвет накладываемый на видео',
        'PROPERTY_TYPE' => 'S',
    ],
    'VIDEO_OPACITY' => [
        'NAME' => 'Прозрачность цвета (0.1 - 1)',
        'PROPERTY_TYPE' => 'S',
    ],
];

$sort = 100;
foreach ($properties as $code => $p) {
    $arFields = [
        'IBLOCK_ID' => $TARGET_ID,
        'NAME' => $p['NAME'],
        'CODE' => $code,
        'PROPERTY_TYPE' => $p['PROPERTY_TYPE'],
        'ROW_COUNT' => 1,
        'COL_COUNT' => 30,
        'MULTIPLE' => 'N',
        'IS_REQUIRED' => 'N',
        'SORT' => $sort,
    ];
    if (isset($p['FILE_TYPE'])) {
        $arFields['FILE_TYPE'] = $p['FILE_TYPE'];
    }
    if (isset($p['LIST_TYPE'])) {
        $arFields['LIST_TYPE'] = $p['LIST_TYPE'];
    }
    $propId = $propObj->Add($arFields);
    if (!$propId) {
        echo "Ошибка создания свойства $code: " . $propObj->LAST_ERROR . "\n";
        continue;
    }
    echo "Свойство $code создано, ID=$propId\n";
    if (isset($p['ENUM'])) {
        foreach ($p['ENUM'] as $i => $enumVal) {
            $enumObj = new CIBlockPropertyEnum;
            $enumId = $enumObj->Add([
                'PROPERTY_ID' => $propId,
                'VALUE' => $enumVal['VALUE'],
                'XML_ID' => $enumVal['XML_ID'],
                'DEF' => $enumVal['DEF'] ?? 'N',
                'SORT' => ($i + 1) * 100,
            ]);
            echo "  enum {$enumVal['XML_ID']} -> ID=$enumId\n";
        }
    }
    $sort += 100;
}

// --- Демо-элементы с плейсхолдер-картинками ---
function makePlaceholderJpg($path, $text, $rgb) {
    $w = 1920; $h = 1080;
    $im = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    $white = imagecolorallocate($im, 255, 255, 255);
    $fontSize = 5;
    $tw = imagefontwidth($fontSize) * strlen($text);
    $th = imagefontheight($fontSize);
    imagestring($im, $fontSize, (int)(($w - $tw) / 2), (int)(($h - $th) / 2), $text, $white);
    imagejpeg($im, $path, 85);
    imagedestroy($im);
}

$tmpDir = sys_get_temp_dir();
$demoSlides = [
    ['text' => 'Eporta - dveri na zakaz',   'bg' => [30, 40, 60], 'sort' => 100],
    ['text' => 'Novaya kollekciya Vilis',   'bg' => [60, 40, 30], 'sort' => 200],
    ['text' => 'Skidki do 20%',             'bg' => [40, 55, 40], 'sort' => 300],
];

$elObj = new CIBlockElement;
foreach ($demoSlides as $i => $slide) {
    $file = $tmpDir . "/slider27_demo_$i.jpg";
    makePlaceholderJpg($file, $slide['text'], $slide['bg']);
    $fileArray = CFile::MakeFileArray($file);

    $arLoadProductArray = [
        'IBLOCK_ID' => $TARGET_ID,
        'ACTIVE' => 'Y',
        'NAME' => $slide['text'],
        'SORT' => $slide['sort'],
        'DETAIL_PICTURE' => $fileArray,
        'PREVIEW_PICTURE' => $fileArray,
        'DETAIL_TEXT' => '<div class="bigText whiteColor noMargin">' . htmlspecialchars($slide['text']) . '</div>',
        'DETAIL_TEXT_TYPE' => 'html',
        'PROPERTY_VALUES' => [
            'POSITION' => 'center',
        ],
    ];
    $elId = $elObj->Add($arLoadProductArray);
    if ($elId) {
        echo "Демо-элемент добавлен, ID=$elId ({$slide['text']})\n";
    } else {
        echo "Ошибка добавления демо-элемента: " . $elObj->LAST_ERROR . "\n";
    }
    @unlink($file);
}

echo "Готово.\n";
