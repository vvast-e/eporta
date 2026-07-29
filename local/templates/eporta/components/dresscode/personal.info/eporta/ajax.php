<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?
	//langs
	\Bitrix\Main\Localization\Loc::loadMessages(dirname(__FILE__)."/ajax.php");
?>
<?
	// EPORTA: в отличие от вендорского .default (который читает $_GET и не
	// проверяет sessid — пароль меняется по обычной кликабельной ссылке,
	// CSRF/раскрытие пароля в URL), наша форма шлёт POST + sessid — сама
	// логика CUser::Update и имена полей ниже не менялись.
	if (
		$_SERVER["REQUEST_METHOD"] !== "POST"
		|| !check_bitrix_sessid()
	){
		echo \Bitrix\Main\Web\Json::encode(array(
			"message" => GetMessage("PERSONAL_SEND_ERROR"),
			"heading" => GetMessage("PERSONAL_ERROR"),
			"reload" => false
		));
		die();
	}
	if(isset($_POST["USER_PASSWORD"]) &&
	   isset($_POST["USER_PASSWORD_CONFIRM"]) &&
	   isset($_POST["USER_STREET"]) &&
	   isset($_POST["USER_MOBILE"]) &&
	   isset($_POST["USER_CITY"]) &&
	   isset($_POST["USER_ZIP"]) &&
	   isset($_POST["EMAIL"]) &&
	   isset($_POST["FIO"])
	){
		global $USER;
		$userID = $USER->GetID();
		if($userID){
			$NAME            = explode(" ", htmlspecialchars($_POST["FIO"]));
			$EMAIL           = htmlspecialchars($_POST["EMAIL"]);
			$PASSWORD        = addslashes($_POST["USER_PASSWORD"]);
			$REPASSWORD      = addslashes($_POST["USER_PASSWORD_CONFIRM"]);
			$PERSONAL_STREET = htmlspecialchars($_POST["USER_STREET"]);
			$PERSONAL_MOBILE = htmlspecialchars($_POST["USER_MOBILE"]);
			$PERSONAL_CITY   = htmlspecialchars($_POST["USER_CITY"]);
			$PERSONAL_ZIP    = htmlspecialchars($_POST["USER_ZIP"]);

			$user = new CUser;
			$fields = Array(
			  "NAME"              => defined("BX_UTF") ? $NAME[1] : iconv("UTF-8","windows-1251//IGNORE", $NAME[1]),
			  "SECOND_NAME"       => defined("BX_UTF") ? $NAME[2] : iconv("UTF-8","windows-1251//IGNORE", $NAME[2]),
			  "LAST_NAME"         => defined("BX_UTF") ? $NAME[0] : iconv("UTF-8","windows-1251//IGNORE", $NAME[0]),
			  "PERSONAL_STREET"   => defined("BX_UTF") ? $PERSONAL_STREET : iconv("UTF-8","windows-1251//IGNORE", $PERSONAL_STREET),
			  "PERSONAL_CITY"	  => defined("BX_UTF") ? $PERSONAL_CITY : iconv("UTF-8","windows-1251//IGNORE", $PERSONAL_CITY),
			  "PERSONAL_ZIP"      => defined("BX_UTF") ? $PERSONAL_ZIP : iconv("UTF-8","windows-1251//IGNORE", $PERSONAL_ZIP),
			  "PERSONAL_MOBILE"   => defined("BX_UTF") ? $PERSONAL_MOBILE : iconv("UTF-8","windows-1251//IGNORE", $PERSONAL_MOBILE),
			  "EMAIL"             => $EMAIL,
			  "PASSWORD"          => $PASSWORD,
			  "CONFIRM_PASSWORD"  => $REPASSWORD
			);

			if(empty($PASSWORD)){
				unset($fields["PASSWORD"]);
				unset($fields["REPASSWORD"]);
			}

			if(!$user->Update($userID, $fields)){
				$result = array(
					"message" => strip_tags($user->LAST_ERROR),
					"heading" => GetMessage("PERSONAL_ERROR"),
					"reload" => false
				);
			}else{
				$result = array(
					"message" => GetMessage("PERSONAL_SUCCESS_SAVED"),
					"heading" => GetMessage("PERSONAL_SAVED"),
					"reload" => true
				);
			}
		}else{
			$result = array(
				"message" => GetMessage("PERSONAL_NEED_AUTH"),
				"heading" => GetMessage("PERSONAL_ERROR"),
				"reload" => false
			);
		}

	}else{
		$result = array(
			"message" => GetMessage("PERSONAL_SEND_ERROR"),
			"heading" => GetMessage("PERSONAL_ERROR"),
			"reload" => false
		);
	}

	echo \Bitrix\Main\Web\Json::encode($result);

?>
