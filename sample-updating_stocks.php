<?php
/**
 * Обновление остатков товаров
 * Данные POST:
 * [{артикул:{артикул_размера:количество, ...}, ...}]
 * 
 * @author Buturlin Vitaliy (Byurrer), email: byurrer@mail.ru
 * @copyright 2020 Buturlin Vitaliy
 * @license MIT https://opensource.org/licenses/mit-license.php
*/

//##########################################################################

//выводим данные как плоский текст
header("Content-Type: text/plain; charset=utf-8");
ob_start();

$fTime = microtime(true);

include("common.php");

if(strcasecmp("post", $_SERVER["REQUEST_METHOD"]) != 0)
	return http_response_code(400);

//название модуля (это же название директории, куда будут сохраняться отладочные данные)
define("DEBUG_MODULE_PATH", "update_stocks");

if(!file_exists(DEBUG_MODULE_PATH))
	mkdir(DEBUG_MODULE_PATH);

$sPostDataRaw = file_get_contents('php://input');
$sPostDataRaw = str_ireplace(" ", "", $sPostDataRaw);
$sPostDataRaw = mb_strtoupper($sPostDataRaw);
$_POST = json_decode($sPostDataRaw, true);

file_put_contents(DEBUG_MODULE_PATH."/post_data.txt", $sPostDataRaw);
file_put_contents(DEBUG_MODULE_PATH."/post_data2.txt", print_r($_POST, true));

//**************************************************************************

//загружаем список всех товаров
$aProducts = $oShop->getProducts();

//сюда сложим все данные до их изменения, чтобы в случае ошибки можно было откатить изменения
$aOldProducts = [];

//количество размеров, которые изменили
$iCountUpdate = 0;

//сюда запишем данные об изменения, котоыре вносим
$aLogs = [];

//проход по всему списку товаров
foreach($aProducts as $key => $value)
{
	$idProduct = $value["ProductId"];
	$sArtProduct = trim($value["ProductArtNo"]);
	$aProduct = $oShop->getOffers($idProduct);
	$aOldProducts[$sArtProduct] = [];
	
	foreach($aProduct as $key2 => $value2)
	{
		$aOldProducts[$sArtProduct][$value2["ArtNo"]] = $value2["Amount"];
		$sArtSize = trim($value2["ArtNo"]);
		$sArtProductClear = $sArtSize;
		$sArtProductClear = substr($sArtProductClear, 0, strlen($sArtProductClear)-3);
		
		//если не существует артикул в пост данных, или нет такого размера (артикул-размер)
		if(!(isset($_POST[$sArtProductClear]) && isset($_POST[$sArtProductClear][$sArtSize])))
			continue;

		$iNewAmount = $_POST[$sArtProductClear][$sArtSize];
		if($value2["Amount"] == $iNewAmount)
			continue;

		if(!array_key_exists($sArtProductClear, $aLogs))
			$aLogs[$sArtProductClear] = "";
		else 
			$aLogs[$sArtProductClear] .= ", ";

		$aLogs[$sArtProductClear] .= $value2["Size"] . "(" . $value2["Amount"]. ", " . $iNewAmount . ")";

		$value2["Amount"] = $iNewAmount;
		++$iCountUpdate;

		$oShop->updateOffer($idProduct, json_encode($value2));
	}
}

echo "count updates bundles ".$iCountUpdate."\n";

if(!file_exists(DEBUG_MODULE_PATH."/backup"))
	mkdir(DEBUG_MODULE_PATH."/backup");

//если были изменения, тогда делаем бэкап предыдущих данных
if($iCountUpdate > 0)
{
	$sFileBackup = DEBUG_MODULE_PATH."/backup/".date("Y-m-d-H:i:s").".txt";
	$sJSON = json_encode($aOldProducts, JSON_UNESCAPED_UNICODE);
	file_put_contents($sFileBackup, $sJSON);

	echo "backup file ".$sFileBackup."\n";

	$sLog = "[".date("Y-m-d-H:i:s")."]\n";

	foreach($aLogs as $key => $value)
		$sLog .= $key." => ".$value . "\n";

	$sLog .= "######################################################################\n";

	if(!file_exists(DEBUG_MODULE_PATH."/logs/"))
		mkdir(DEBUG_MODULE_PATH."/logs/");

	file_put_contents(DEBUG_MODULE_PATH."/logs/".date("Y-m-d").".txt", $sLog, FILE_APPEND);
}

echo "count time ".(microtime(true) - $fTime)."\n";

$g_sBuffer = ob_get_contents();
ob_end_clean();

echo $g_sBuffer;

file_put_contents(DEBUG_MODULE_PATH."/output.txt", $g_sBuffer);
file_put_contents(DEBUG_MODULE_PATH."/alogs.txt", print_r($aLogs, true));
