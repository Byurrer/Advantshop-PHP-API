<?php

//включаем вывод ошибок
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include("config.php");

//проверка токена
if($_GET["token"] != TOKEN)
	return http_response_code(400);

//создание функции array_key_first
if (!function_exists('array_key_first'))
{
	function array_key_first(array $arr) 
	{
		foreach($arr as $key => $unused)
			return $key;
		return NULL;
	}
}

include("advantshop.php");

$sCookie = "";
//загрузка куки
if(file_exists("cookie.txt"))
	$sCookie = file_get_contents("cookie.txt");

//авторизация в интернет-магазине
$oShop = new CAdvantShop();
$iResult = $oShop->auth(PROTOCOL, SITE, LOGIN, PASSWORD, $sCookie, USERAGENT);

//проверка статуса авторизации
if($iResult == 1)
	echo "autorized\n";
else if($iResult == 2)
	echo "already autorized\n";
else
	exit("error autorization ...");

//сохраняем куки
file_put_contents("cookie.txt", $oShop->getCookie());
