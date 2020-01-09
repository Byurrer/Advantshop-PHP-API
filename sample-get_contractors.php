<?php
/**
 * Обновление остатков товаров
 * Данные GET:
 * status - номер статуса выбираемых заказов
 * 
 * @author Buturlin Vitaliy (Byurrer), email: byurrer@mail.ru
 * @copyright 2020 Buturlin Vitaliy
 * @license MIT https://opensource.org/licenses/mit-license.php
*/

//##########################################################################

header('Content-Type: application/json; charset=utf-8');
ob_start();

$iStatus = (array_key_exists("status", $_GET) ? $_GET["status"] : 0);

$fTime = microtime(true);

include("common.php");

//название модуля (это же название директории, куда будут сохраняться отладочные данные)
define("DEBUG_MODULE_PATH", "get_contractors");

if(!file_exists(DEBUG_MODULE_PATH))
	mkdir(DEBUG_MODULE_PATH);

//загружаем список всех оплченных заказов где есть контрагенты
$aOrders = $oShop->getOrders(true, $iStatus);

$aContractors = [];
foreach($aOrders as $value)
{
	$sContractor = $oShop->getСontractor($value["OrderId"]);
	$aContractors[] = $sContractor;
}

$sBuffer = ob_get_contents();
ob_end_clean();

$aResponse = ["contractors" => $aContractors, "echo" => $sBuffer];
$sJSON = json_encode($aResponse, JSON_UNESCAPED_UNICODE);
echo $sJSON;
