<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Доставка");
?><h1>Доставка дверей по Москве и области от 3000 руб.</h1>
 <?$APPLICATION->IncludeComponent(
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
<div class="global-block-container">
	<div class="global-content-block">
		<div class="blockquote-wrap">
			<h3>Время доставки с 10:00 до 17:00</h3>
 <b>Ваши заказы доставляются с понедельника по пятницу. За день до доставки мы свяжемся с вами и сообщим интервал времени. В день доставки экспедитор свяжется с вами за час до прибытия. Доставка так же возможна по желанию покупателя в субботу (+ 1400 руб.)</b>
			<p>
			</p>
		</div>
		<div class="questions-answers-list">
			<div class="question-answer-wrap">
				<div class="question">
					 Доставка межкомнатных дверей по городу Москва в пределах МКАД и городам Мытищи, Королев, Щелково, Фрязино. <br>
					 Комплект: полотно (до 2000 мм) + 2,5 коробки + 5 наличников.
					<div class="open-answer">
 <span class="hide-answer-text">Скрыть</span><span class="open-answer-text">Подробнее</span>
						<div class="open-answer-btn">
						</div>
					</div>
				</div>
				<div class="answer" style="display: none;">
					<li>До 5 комплектов: 3000 руб.</li>
					<li>От 6 до 10 комплектов: 3250 руб.</li>
					<li>Более 10 комплектов: согласовывается индивидуально</li>
					<li>Доставка с ограничениями по въезду (подземный паркинг): + 1400 руб.</li>
					<li>Доставка с ограничениями по времени но, с учетом 3-х часового «Тайм слота»: + 1400 руб.</li>
				</div>
			</div>
			<div class="question-answer-wrap">
				<div class="question">
					 За пределы МКАД: 3000 + 50 руб. / км
					<div class="open-answer">
 <span class="hide-answer-text">Скрыть</span><span class="open-answer-text">Подробнее</span>
						<div class="open-answer-btn">
						</div>
					</div>
				</div>
				<div class="answer" style="display: none;">
					<li>Доставка за пределы МКАД в ближайшие регионы: 3750 руб. + 50 руб. / км</li>
				</div>
			</div>
			<div class="question-answer-wrap">
				<div class="question">
					 Межкомнатные двери — подъем до квартиры
					<div class="open-answer">
 <span class="hide-answer-text">Скрыть</span><span class="open-answer-text">Подробнее</span>
						<div class="open-answer-btn">
						</div>
					</div>
				</div>
				<div class="answer" style="display: none;">
					<li>400 руб. за стандартный комплект на грузовом лифте (не менее 2,2 м)</li>
					<li>400 руб. / этаж за стандартный комплект пешком</li>
					<li>500 руб. за нестандартный комплект на грузовом лифте (не менее 2,2 м)</li>
					<li>500 руб. / этаж за нестандартный комплект пешком</li>
				</div>
			</div>
			<div class="question-answer-wrap">
				<div class="question">
					 Подъём погонажной продукции
					<div class="open-answer">
 <span class="hide-answer-text">Скрыть</span><span class="open-answer-text">Подробнее</span>
						<div class="open-answer-btn">
						</div>
					</div>
				</div>
				<div class="answer">
					<li>В том случае, если количество превышает стандартный комплект 2,5 коробки и 5 наличников на одно полотно:</li>
					<li>170 руб. / этаж погонаж за одну упаковку (5 наличников, 3 коробки, 3 доборы)</li>
				</div>
			</div>
			<div class="question-answer-wrap">
				<div class="question">
					 Входные двери — подъем до квартиры
					<div class="open-answer">
 <span class="hide-answer-text">Скрыть</span><span class="open-answer-text">Подробнее</span>
						<div class="open-answer-btn">
						</div>
					</div>
				</div>
				<div class="answer">
					<li>500 руб. на грузовом лифте</li>
					<li>500 руб. / этаж пешком</li>
				</div>
			</div>
		</div>
	</div>
	<div class="global-information-block">
			 <?$APPLICATION->IncludeComponent(
	"bitrix:main.include",
	".default",
	Array(
		"AREA_FILE_RECURSIVE" => "Y",
		"AREA_FILE_SHOW" => "sect",
		"AREA_FILE_SUFFIX" => "information_block",
		"COMPONENT_TEMPLATE" => ".default",
		"EDIT_TEMPLATE" => ""
	)
);?>
	</div>
</div><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>