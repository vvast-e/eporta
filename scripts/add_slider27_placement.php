<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/www-root/data/www/eporta.ru';
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('iblock');

$IBLOCK_ID = 27;

$res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => 'PLACEMENT']);
if ($res->Fetch()) {
    echo "Свойство PLACEMENT уже существует, пропускаю создание\n";
} else {
    $propObj = new CIBlockProperty;
    $propId = $propObj->Add([
        'IBLOCK_ID' => $IBLOCK_ID,
        'NAME' => 'Место показа',
        'CODE' => 'PLACEMENT',
        'PROPERTY_TYPE' => 'L',
        'LIST_TYPE' => 'L',
        'ROW_COUNT' => 1,
        'COL_COUNT' => 30,
        'MULTIPLE' => 'N',
        'IS_REQUIRED' => 'N',
        'SORT' => 5,
    ]);
    if (!$propId) {
        die("Ошибка создания свойства PLACEMENT: {$propObj->LAST_ERROR}\n");
    }
    echo "Свойство PLACEMENT создано, ID=$propId\n";

    $enumObj = new CIBlockPropertyEnum;
    $enumValues = [
        ['VALUE' => 'Главный (большой)', 'XML_ID' => 'main', 'DEF' => 'Y', 'SORT' => 100],
        ['VALUE' => 'Боковой верхний', 'XML_ID' => 'side1', 'SORT' => 200],
        ['VALUE' => 'Боковой нижний', 'XML_ID' => 'side2', 'SORT' => 300],
    ];
    foreach ($enumValues as $e) {
        $enumId = $enumObj->Add(array_merge(['PROPERTY_ID' => $propId], $e));
        echo "  enum {$e['XML_ID']} -> ID=$enumId\n";
    }
}

// --- Распределяем существующие 3 демо-слайда по местам ---
$placementByName = [
    'Eporta - dveri na zakaz' => 'main',
    'Novaya kollekciya Vilis' => 'side1',
    'Skidki do 20%' => 'side2',
];

$elObj = new CIBlockElement;
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID', 'NAME']);
while ($el = $res->Fetch()) {
    if (!isset($placementByName[$el['NAME']])) {
        continue;
    }
    $ok = $elObj->Update($el['ID'], [
        'PROPERTY_VALUES' => ['PLACEMENT' => $placementByName[$el['NAME']]],
    ]);
    echo $ok
        ? "Элемент {$el['ID']} ({$el['NAME']}) -> {$placementByName[$el['NAME']]}\n"
        : "Ошибка обновления {$el['ID']}: {$elObj->LAST_ERROR}\n";
}

// --- Добавляем по второму слайду в каждое место, чтобы карусели реально листались ---
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
$extraSlides = [
    [
        'text' => 'Rassrochka 0% na 12 mesyacev',
        'bg' => [45, 35, 25],
        'sort' => 110,
        'placement' => 'main',
        'subtitle' => 'Без переплат и справок',
        'link' => '/catalog/',
        'cta' => 'Узнать условия →',
    ],
    [
        'text' => 'Garantiya 5 let',
        'bg' => [35, 45, 40],
        'sort' => 210,
        'placement' => 'side1',
        'subtitle' => 'На фурнитуру и полотно',
        'link' => '/catalog/',
        'cta' => 'Подробнее →',
    ],
    [
        'text' => 'Dostavka za 1-3 dnya',
        'bg' => [50, 40, 55],
        'sort' => 310,
        'placement' => 'side2',
        'subtitle' => 'По Москве и области',
        'link' => '/catalog/',
        'cta' => 'Подробнее →',
    ],
];

foreach ($extraSlides as $i => $slide) {
    $checkRes = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'NAME' => $slide['text']], false, false, ['ID']);
    if ($checkRes->Fetch()) {
        echo "Слайд '{$slide['text']}' уже существует, пропускаю\n";
        continue;
    }
    $file = $tmpDir . "/slider27_extra_$i.jpg";
    makePlaceholderJpg($file, $slide['text'], $slide['bg']);
    $fileArray = CFile::MakeFileArray($file);

    $elId = $elObj->Add([
        'IBLOCK_ID' => $IBLOCK_ID,
        'ACTIVE' => 'Y',
        'NAME' => $slide['text'],
        'SORT' => $slide['sort'],
        'DETAIL_PICTURE' => $fileArray,
        'PREVIEW_PICTURE' => $fileArray,
        'DETAIL_TEXT' => '<div class="bigText whiteColor noMargin">' . htmlspecialchars($slide['text']) . '</div>',
        'DETAIL_TEXT_TYPE' => 'html',
        'PROPERTY_VALUES' => [
            'POSITION' => 'center',
            'PLACEMENT' => $slide['placement'],
            'SUBTITLE' => $slide['subtitle'],
            'LINK' => $slide['link'],
            'CTA_TEXT' => $slide['cta'],
        ],
    ]);
    echo $elId ? "Слайд добавлен, ID=$elId ({$slide['text']}, {$slide['placement']})\n" : "Ошибка: {$elObj->LAST_ERROR}\n";
    @unlink($file);
}

echo "Готово.\n";
