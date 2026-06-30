<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Задайте вопрос");
?><h1>Контактная информация</h1>
 <?
	//include module
	\Bitrix\Main\Loader::includeModule("dw.deluxe");
	//get template settings
	$arTemplateSettings = DwSettings::getInstance()->getCurrentSettings();
?> <?$APPLICATION->IncludeComponent(
	"bitrix:menu",
	"personal",
	Array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "",
		"COMPONENT_TEMPLATE" => "personal",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(),
		"MENU_CACHE_TIME" => "3600000",
		"MENU_CACHE_TYPE" => "A",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "about",
		"USE_EXT" => "N"
	)
);?>
<ul class="contactList">
	 <?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"]) || !empty($arTemplateSettings["TEMPLATE_TELEPHONE_2"])):?>
	<li>
	<table>
	<tbody>
	<tr>
		<td>
 <img alt="cont1.png" src="/bitrix/templates/dresscodeV2/images/cont1.png" title="cont1.png">
		</td>
		<td>
 <b>Розничный отдел<br>
 </b><?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_1"])):?><?=$arTemplateSettings["TEMPLATE_TELEPHONE_1"]?> <?endif;?> <br>
 <b>Оптовый отдел<br>
 </b><?if(!empty($arTemplateSettings["TEMPLATE_TELEPHONE_2"])):?><?=$arTemplateSettings["TEMPLATE_TELEPHONE_2"]?><br>
			 <?endif;?>
		</td>
	</tr>
	</tbody>
	</table>
 </li>
	 <?endif;?> <?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_1"]) || !empty($arTemplateSettings["TEMPLATE_EMAIL_2"])):?>
	<li>
	<table>
	<tbody>
	<tr>
		<td>
 <img alt="cont2.png" src="/bitrix/templates/dresscodeV2/images/cont2.png" title="cont2.png">
		</td>
		<td>
 <b>Розничный отдел<br>
 </b><?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_1"])):?><a href="mailto:info@eporta.ru" template_email_1"]?=""><?=$arTemplateSettings["TEMPLATE_EMAIL_1"]?></a><br>
 <b>Оптовый отдел</b><br>
			 <?endif;?> <?if(!empty($arTemplateSettings["TEMPLATE_EMAIL_2"])):?><a href="mailto:sale@eporta.ru" template_email_2"]?=""><?=$arTemplateSettings["TEMPLATE_EMAIL_2"]?></a><br>
			 <?endif;?>
		</td>
	</tr>
	</tbody>
	</table>
 </li>
	 <?endif;?> <?if(!empty($arTemplateSettings["TEMPLATE_FULL_ADDRESS"])):?>
	<li>
	<table>
	<tbody>
	<tr>
		<td>
 <img alt="cont3.png" src="/bitrix/templates/dresscodeV2/images/cont3.png" title="cont3.png">
		</td>
		<td>
			<div class="contactAddress">
				 <?=$arTemplateSettings["TEMPLATE_FULL_ADDRESS"]?>
			</div>
		</td>
	</tr>
	</tbody>
	</table>
 </li>
	 <?endif;?> <?if(!empty($arTemplateSettings["TEMPLATE_WORKING_TIME"])):?>
	<li>
	<table>
	<tbody>
	<tr>
		<td>
 <img alt="cont4.png" src="/bitrix/templates/dresscodeV2/images/cont4.png" title="cont4.png">
		</td>
		<td>
			 <?=$arTemplateSettings["TEMPLATE_WORKING_TIME"]?>
		</td>
	</tr>
	</tbody>
	</table>
 </li>
	 <?endif;?>
</ul>
 <?$APPLICATION->IncludeComponent(
	"bitrix:map.yandex.view", 
	".default", 
	[
		"API_KEY" => "",
		"COMPONENT_TEMPLATE" => ".default",
		"COMPOSITE_FRAME_MODE" => "A",
		"COMPOSITE_FRAME_TYPE" => "AUTO",
		"CONTROLS" => [
			0 => "TYPECONTROL",
			1 => "SCALELINE",
		],
		"INIT_MAP_TYPE" => "MAP",
		"MAP_DATA" => "a:4:{s:10:\"yandex_lat\";d:55.7630661801997;s:10:\"yandex_lon\";d:37.58975959785898;s:12:\"yandex_scale\";i:10;s:10:\"PLACEMARKS\";a:8:{i:0;a:3:{s:3:\"LON\";d:37.744779761268;s:3:\"LAT\";d:55.969529325621;s:4:\"TEXT\";s:10:\"Склад\";}i:1;a:3:{s:3:\"LON\";d:37.489535172321;s:3:\"LAT\";d:55.828035667564;s:4:\"TEXT\";s:77:\"ТЦ «Family Room», Москва, Ленинградское шоссе, 25\";}i:2;a:3:{s:3:\"LON\";d:37.750719186288;s:3:\"LAT\";d:55.929844046305;s:4:\"TEXT\";s:83:\"ТЦ «ФОРМАТ», Мытищи, Олимпийский проспект, 29с1\";}i:3;a:3:{s:3:\"LON\";d:38.001141392611;s:3:\"LAT\";d:55.92397163652;s:4:\"TEXT\";s:80:\"ТЦ «КЭМП», Щёлково, Пролетарский проспект, 10\";}i:4;a:3:{s:3:\"LON\";d:37.986955123861;s:3:\"LAT\";d:55.724908450908;s:4:\"TEXT\";s:86:\"ТЦ «СтройТракт», Балашиха, Пригородная улица, 92\";}i:5;a:3:{s:3:\"LON\";d:37.782178585737;s:3:\"LAT\";d:55.678125814432;s:4:\"TEXT\";s:97:\"ТЦ «Люблинское поле», Москва, Тихорецкий бульвар, 1с2А\";}i:6;a:3:{s:3:\"LON\";d:37.564978249676;s:3:\"LAT\";d:55.57405719593;s:4:\"TEXT\";s:67:\"ТЦ «Алфавит», Куликовская ул. 6, 4 этаж\";}i:7;a:3:{s:3:\"LON\";d:37.429398861604376;s:3:\"LAT\";d:55.659506259741796;s:4:\"TEXT\";s:97:\"СTK «Галерея ремонта», Москва, МКАД, 47-й километр, вл31с1\";}}}",
		"MAP_HEIGHT" => "700",
		"MAP_ID" => "",
		"MAP_WIDTH" => "100%",
		"OPTIONS" => [
			0 => "ENABLE_DBLCLICK_ZOOM",
			1 => "ENABLE_DRAGGING",
		]
	],
	false
);?><br>
 <br>
<h2>Адреса салонов:</h2>
<table border="0" cellpadding="10" cellspacing="10">
<tbody>
<tr>
	<td>
		 &nbsp;<img alt="cont3.png" src="/bitrix/templates/dresscodeV2/images/cont3.png" title="cont3.png">
	</td>
	<td>
		 Ленинградское шоссе 25, 2 этаж,<br>
		 ТЦ «Family Room»<br>
		 Ежедневно: 10:00 — 21:00
	</td>
	<td>
		 &nbsp;<img alt="cont3.png" src="/bitrix/templates/dresscodeV2/images/cont3.png" title="cont3.png">
	</td>
	<td>
		 Тихорецкий бульвар, 1к5, цоколь, А-040,<br>
		 ТЦ «Люблинское поле»<br>
		 Ежедневно: 10:00 — 20:00
	</td>
</tr>
<tr>
	<td>
		 &nbsp;<img alt="cont3.png" src="/bitrix/templates/dresscodeV2/images/cont3.png" title="cont3.png">
	</td>
	<td>
		 МКАД, 47 километр, 31с1, цоколь,<br>
		 СTK «Галерея ремонта»<br>
		 Ежедневно: 10:00 — 21:00
	</td>
	<td>
		 &nbsp;<img alt="cont3.png" src="/bitrix/templates/dresscodeV2/images/cont3.png" title="cont3.png">
	</td>
	<td>
		 Куликовская ул. 6, 4 этаж,<br>
		 ТЦ «Алфавит»<br>
		 Ежедневно: 10:00 — 20:00
	</td>
</tr>
<tr>
	<td>
		 &nbsp;<img alt="cont3.png" src="/bitrix/templates/dresscodeV2/images/cont3.png" title="cont3.png">
	</td>
	<td>
		 Олимпийский проспект, 29с1, ТЦ «Формат»<br>
		 Ежедневно: 10:00 — 21:00
	</td>
	<td>
		 &nbsp;<img alt="cont3.png" src="/bitrix/templates/dresscodeV2/images/cont3.png" title="cont3.png"><br>
	</td>
	<td>
		 Пролетарский проспект, 10, ТЦ «Кэмп»<br>
		 Ежедневно: 10:00 — 21:00
	</td>
</tr>
<tr>
	<td>
		 &nbsp;<img alt="cont3.png" src="/bitrix/templates/dresscodeV2/images/cont3.png" title="cont3.png">
	</td>
	<td>
		Железнодорожный мкр., Пригородная 92, 1 этаж,<br>
		 ТЦ «СтройТракт»<br>
		 Ежедневно: 10:00 — 21:00
	</td>
	<td>
		 &nbsp;
	</td>
	<td>
		 &nbsp;
	</td>
</tr>
</tbody>
</table>
 <br>
<br>
<br>
 <br>
 &nbsp;&nbsp;<br>
 <br>
 <br>
 <?$APPLICATION->IncludeComponent(
	"bitrix:form.result.new",
	"twoColumns",
	Array(
		"CACHE_TIME" => "360000",
		"CACHE_TYPE" => "Y",
		"CHAIN_ITEM_LINK" => "",
		"CHAIN_ITEM_TEXT" => "",
		"COMPONENT_TEMPLATE" => ".default",
		"EDIT_URL" => "",
		"IGNORE_CUSTOM_TEMPLATE" => "N",
		"LIST_URL" => "",
		"SEF_MODE" => "N",
		"SUCCESS_URL" => "",
		"USE_EXTENDED_ERRORS" => "Y",
		"VARIABLE_ALIASES" => array("WEB_FORM_ID"=>"WEB_FORM_ID","RESULT_ID"=>"RESULT_ID",),
		"WEB_FORM_ID" => "2"
	)
);?><br><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php")?>