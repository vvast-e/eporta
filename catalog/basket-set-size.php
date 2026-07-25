<?php
// Изолированный аддитивный эндпоинт (Этап 2, фикс "размер в корзине").
// Не трогает вендорский compatible-mode add-to-basket и не требует глобального
// фиче-флага IN_BASKET на свойстве SIZES в IBLOCK 19 (см. план "transient-swimming-ullman" —
// флаг общий с dverimarket.ru, риск для чужого сайта не исключён). Вместо этого: карточка
// товара сначала добавляет дверь в корзину как обычно (~ADD_URL_TEMPLATE), затем фоновым
// вторым запросом сюда патчит PROPS уже добавленной строки через Sale API напрямую.
//
// Bitrix сливает повторные добавления одного PRODUCT_ID в одну строку корзины, только если
// у них совпадают PROPS. Пока PROPS пустой у всех — единственная строка этого товара БЕЗ
// PROPS гарантированно та, что добавлена только что (любая ранее патченная строка того же
// товара уже имеет непустой PROPS и не сольётся с новой).
$_SERVER["DOCUMENT_ROOT"] = $_SERVER["DOCUMENT_ROOT"] ?: dirname(__DIR__);
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

header("Content-Type: application/json; charset=UTF-8");

function eportaBasketSetSizeFail($message) {
	echo json_encode(["STATUS" => "ERROR", "MESSAGE" => $message], JSON_UNESCAPED_UNICODE);
	die();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
	eportaBasketSetSizeFail("Method not allowed");
}

// CSRF: эндпоинт меняет содержимое корзины по POST — обязательна проверка сессионного
// токена Bitrix (тот же механизм, что bitrix_sessid_post() вставляет в формы компонентов).
if (!check_bitrix_sessid()) {
	eportaBasketSetSizeFail("Bad sessid");
}

$productId = (int)($_POST["product_id"] ?? 0);
$size = trim((string)($_POST["size"] ?? ""));

if ($productId <= 0 || $size === "") {
	eportaBasketSetSizeFail("Bad request");
}

if (!\Bitrix\Main\Loader::includeModule("iblock") || !\Bitrix\Main\Loader::includeModule("sale")) {
	eportaBasketSetSizeFail("Module unavailable");
}

// $size обязан быть одним из реальных значений PROPERTY_SIZES этого товара, а не
// произвольной строкой из запроса — иначе в PROPS корзины можно записать что угодно.
$eportaValidSizes = [];
$eportaSizeRes = \CIBlockElement::GetList(
	[],
	["ID" => $productId, "IBLOCK_ID" => 19, "ACTIVE" => "Y"],
	false,
	false,
	["ID", "PROPERTY_SIZES"]
);
if ($eportaSizeRow = $eportaSizeRes->GetNext()) {
	$eportaSizesRaw = $eportaSizeRow["PROPERTY_SIZES_VALUE"] ?? [];
	if (!is_array($eportaSizesRaw)) {
		$eportaSizesRaw = $eportaSizesRaw !== "" ? [$eportaSizesRaw] : [];
	}
	foreach ($eportaSizesRaw as $eportaSizeRaw) {
		$eportaValidSizes[] = explode(":", (string)$eportaSizeRaw, 2)[0];
	}
}
if (!in_array($size, $eportaValidSizes, true)) {
	eportaBasketSetSizeFail("Unknown size for this product");
}

try {
	$fuserId = \Bitrix\Sale\Fuser::getId();
	$basket = \Bitrix\Sale\Basket::loadItemsForFUser($fuserId, SITE_ID);

	$targetItem = null;
	foreach ($basket as $basketItem) {
		if ((int)$basketItem->getProductId() !== $productId) {
			continue;
		}
		// getPropertyCollection() всегда включает служебные CATALOG.XML_ID/PRODUCT.XML_ID —
		// "нет размера" значит "нет свойства с CODE=SIZE", а не "коллекция пуста".
		$hasSize = false;
		foreach ($basketItem->getPropertyCollection() as $propItem) {
			if ($propItem->getField("CODE") === "SIZE") {
				$hasSize = true;
				break;
			}
		}
		if (!$hasSize) {
			$targetItem = $basketItem;
			break;
		}
	}

	if ($targetItem === null) {
		eportaBasketSetSizeFail("Basket item not found");
	}

	$targetItem->getPropertyCollection()->redefine([
		["NAME" => "Размер", "CODE" => "SIZE", "VALUE" => $size, "SORT" => 100],
	]);

	$result = $basket->save();
	if (!$result->isSuccess()) {
		eportaBasketSetSizeFail(implode("; ", $result->getErrorMessages()));
	}

	echo json_encode(["STATUS" => "OK"], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
	eportaBasketSetSizeFail($e->getMessage());
}
