<?php
/**
 * Средства для работы с интернет-магазином AdvantShop через php код
 * PHP Version 7.1
 * 
 * @version 1.0.0
 * @author Buturlin Vitaliy (Byurrer), email: byurrer@mail.ru
 * @copyright 2020 Buturlin Vitaliy
 * @license MIT https://opensource.org/licenses/mit-license.php
 */

//##########################################################################

//! режим отладки
define("DEBUG_MODE", true);

//! директория для создания отладочных файлов
define("DEBUG_DATA_PATH", "dbg/");

if(!file_exists(DEBUG_DATA_PATH))
	mkdir(DEBUG_DATA_PATH);

//! файл для мелких отладочных данных
define("DEBUG_FILE_SMALL_EXTRACT", "extract_small.txt");

//##########################################################################

//! класс для работы с интернет-магазинов на AdvantShop
class CAdvantShop
{
	protected $m_hCurl;
  protected $m_sCookie;
	protected $m_sUserAgent;
	protected $m_sURL;
	protected $m_sHost;

	//########################################################################

  //! установка стандартных данных для curl соединения (прокси, куки, юзерагент, возврат ответа через exec)
  protected function stdSetOpt()
  {
		curl_setopt($this->m_hCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this->m_hCurl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($this->m_hCurl, CURLOPT_COOKIE, $this->m_sCookie);
    curl_setopt($this->m_hCurl, CURLOPT_USERAGENT, $this->m_sUserAgent);
    curl_setopt($this->m_hCurl, CURLOPT_RETURNTRANSFER, 1);
  }

  //**********************************************************************
	
	//! инициализация строки кук
	protected function initCookie($sHeaders)
	{
		$sHeaders = str_replace("\r\n", "\n", $sHeaders);
		preg_match_all("/Set-Cookie:\s+(.+?;)\s*(?:.*?)*(?:HttpOnly)*\n/ims", $sHeaders, $aParse);

		foreach($aParse[1] as $key => $value)
		{
			$this->m_sCookie .= " ".$value;

			if(defined("DEBUG_MODE") && DEBUG_MODE)
				file_put_contents(DEBUG_DATA_PATH.DEBUG_FILE_SMALL_EXTRACT, "\nCookie [$key]: ".$value);
		}
	}

	//! извлекает и возвращает длину контента
	protected function extractContentLength($sStr)
	{
		$iContentLength = 0;
		$sStr = str_replace("\r\n", "\n", $sStr);
		if(preg_match("/Content-Length:\s?(.*?)\n/ims", $sStr, $aParse))
			$iContentLength = $aParse[1];

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH.DEBUG_FILE_SMALL_EXTRACT, "\nContent-Length: ".$iContentLength);

		return $iContentLength;
	}

	//! извлечение и декодирование тела сообщения из строки с полным ответом
	protected function extractBody($sStr)
	{
		$aParse = explode("\r\n\r\n", $sStr, 2);
		$sResDecode = gzdecode($aParse[1]);
		return $sResDecode;
	}

	//! извлечение токена на странице
	protected function extractToken($sStr)
	{
		$sToken = "";
		if(preg_match("/<input\s+name\=\"__RequestVerificationToken\"(?:.*?)value\=\"(.*?)\"\s+\/>/ims", $sStr, $aParse))
			$sToken = $aParse[1];

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH.DEBUG_FILE_SMALL_EXTRACT, "\nToken: ".$sToken);
		
		return $sToken;
	}

	//************************************************************************

	//! возвращает строку с куками, для дальнейшего сохранения
  public function getCookie()
  {
    return $this->m_sCookie;
	}

  //######################################################################

  public function __construct(){ $this->m_hCurl = curl_init(); file_put_contents(DEBUG_DATA_PATH.DEBUG_FILE_SMALL_EXTRACT, ""); }
	public function __destruct() { curl_close($this->m_hCurl); }
	
	//**********************************************************************

	/*! авторизация
		"return 0 - не удалось авторизироваться, 1 - авторизация прошла успешно, 2 - авторизация прошла по данным в куках
	*/
	public function auth($sProtocol, $sHost, $sLogin, $sPassword, $sCookie, $sUserAgent)
	{
    $this->m_sCookie = $sCookie;
		$this->m_sUserAgent = $sUserAgent;
		$this->m_sURL = $sProtocol."://".$sHost;
		$this->m_sHost = $sHost;

		// сначала идем на страницу login, и если там ответ 302, значит авторизация на основании кук
		curl_reset($this->m_hCurl);
		curl_setopt($this->m_hCurl, CURLOPT_URL, $this->m_sURL."/login");
		curl_setopt($this->m_hCurl, CURLOPT_HEADER, TRUE);
		curl_setopt($this->m_hCurl, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/");
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
		curl_setopt(
      $this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"Connection: keep-alive"
      ]
    );
		$this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH."auth-1.txt", $sResponse);

		if(curl_getinfo($this->m_hCurl, CURLINFO_HTTP_CODE) == 302)
			return 2;

			
		//если нет 302 ответа, значит надо авторизоваться
		$sBody = $this->extractBody($sResponse);
		$sToken = $this->extractToken($sBody);

		$this->initCookie($sResponse);

    curl_reset($this->m_hCurl);
    curl_setopt($this->m_hCurl, CURLOPT_URL, $this->m_sURL."/user/loginjson");
		curl_setopt($this->m_hCurl, CURLOPT_HEADER, TRUE);
		curl_setopt($this->m_hCurl, CURLOPT_POST, TRUE);
		curl_setopt($this->m_hCurl, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($this->m_hCurl, CURLOPT_POSTFIELDS, ('{"email":"'.$sLogin.'","password":"'.$sPassword.'","captchaSource":null}'));
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/login");
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
    curl_setopt(
      $this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: application/json, text/plain, */*",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"X-Requested-With: XMLHttpRequest",
				"Content-Type: application/json;charset=utf-8",
				"__RequestVerificationToken: ".$sToken,
				"Connection: keep-alive"
      ]
    );
    $this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);
		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH."auth-2.txt", $sResponse);

		$this->initCookie($sResponse);

		$sBody = $this->extractBody($sResponse);
		$aData = json_decode($sBody, true);

		return (strcasecmp($aData["status"], "success") == 0 ? 1 : 0);
	}

	//************************************************************************

	/*! получить список продукции в магазине (на данный момент всей продукции)
		@return json линейный массив с объектами с ключами:
			ProductId -	1998
			ArtNo -	артикула продукта через запятую ("Б-31-38")
			Name - название
			ProductArtNo - артикул продукта ("Б-31")
			PhotoName - название файла изображения ("7342.jpg")
			PhotoSrc	сслка на превью изображения ("/pictures/product/small/7342_small.jpg")
			Price - цена
			PriceString - строкове представление цены ("4 000 руб.")
			Amount - общий остаток товара
			OffersCount	6
			Enabled - активность (true/false)
			CurrencyCode - валюта (" руб.")
			CurrencyIso3 - международный код валюты ("RUB")
			CurrencyValue - ??? (1)
			CurrencyIsCodeBefore - ??? (true/false)
			SortOrder - порядок сортировки (1)
			ColorId - идентификатор цвета
			SizeId - идентификатор размера
	*/
	public function getProducts()
	{
		curl_reset($this->m_hCurl);
		curl_setopt($this->m_hCurl, CURLOPT_URL, $this->m_sURL."/adminv3/catalog/getcatalog?ItemsPerPage=999999&Page=1&categoryId=0&search=&showMethod=AllProducts");
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/adminv3/catalog?showMethod=AllProducts");
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
		curl_setopt(
			$this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: application/json, text/plain, */*",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"X-Requested-With: XMLHttpRequest",
				"Connection: keep-alive"
			]
		);
		$this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);
		$sResponse = gzdecode($sResponse);
		
		$aData = json_decode($sResponse, true);

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH."getProducts.txt", print_r($aData, true));

		return $aData["DataItems"];
	}

	//************************************************************************

	/*! список заказов, в параметрах фильтры
		@param iOnlyPaid статус оплаты (-1 - все, 0 - не оплачено, 1 - оплачено)
		@param iOrderStatus статус (id), -1 - без фильтрации по статусам, идентификатор каждого статуса нужно смотреть на странице adminv3/orders
		@return json линейный массив с объектами с ключами:
			OrderId - идентификатор заказа
			Number - строковое представление OrderId
			StatusName - название статуса ("новый")
			OrderStatusId - ??? 2
			PaymentDate - дата оплаты ("16.12.2019 7:51:20")
			IsPaid - статус оплаты (true/false)
			PaymentMethodName - название метода оплаты ("Онлайн оплата")
			ShippingMethodName - названи еметода оплаты ("Почта России (наземная посылка со страховкой)")
			PaymentMethod - название способа оплаты ("Онлайн оплата")
			ShippingMethod - название способа доставки ("Edost Кукмор")
			Sum - сумма (1544)
			SumFormatted - строковое представление суммы ("1 544 руб.")
			CurrencyCode - международный код валюты ("RUB")
			CurrencyValue - ??? (1)
			CurrencySymbol - текстовое название валюты (" руб.")
			IsCodeBefore - ??? (true/false)
			OrderDate - дата заказа ("2019-12-16T07:44:36")
			OrderDateFormatted - форматирование даты ("16.12.2019 07:44")
			CustomerId - идентификатор клиента ("f4dd35ac-eddc-4937-8362-7b4b2ecb6f7d")
			FirstName - имя заказчика
			LastName - фамилия заказчика
			Organization - название организации
			BuyerName - фамилия и имя заказчика
			ManagerId - идентификатор менеджера
			Color - код цвета заказа ("ffaaaa")
			Rating - рейтинг??? (0)
			ManagerConfirmed - подтверждено ли менеджером
			ManagerCustomerId - ??? ("00000000-0000-0000-0000-000000000000")
			ManagerName -	фамилия и имя менеджера
	*/
	public function getOrders($iOnlyPaid=-1, $iOrderStatus=-1)
	{
		$sIsPaid = "";
		if($iOnlyPaid == 1)
			$sIsPaid = "IsPaid=true";
		else if($iOnlyPaid == 0)
			$sIsPaid = "IsPaid=false";

		$sOrderStatusId = "";
		if($iOrderStatus)
			$sOrderStatusId = "OrderStatusId=".$iOrderStatus;

		$sURL = $this->m_sURL."/adminv3/orders/getorders?filterby=none";
		if(strlen($sIsPaid) > 0)
			$sURL .= "&$sIsPaid";

		if(strlen($sOrderStatusId) > 0)
			$sURL .= "&$sOrderStatusId";

		//$sURL .= "&ItemsPerPage=$iItemsPerPage&Page=$iCurrPage";

		curl_reset($this->m_hCurl);
		curl_setopt($this->m_hCurl, CURLOPT_URL, $sURL);
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/adminv3");
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
		curl_setopt(
			$this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: application/json, text/plain, */*",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"X-Requested-With: XMLHttpRequest",
				"Connection: keep-alive"
			]
		);
		$this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);
		$sResponse = gzdecode($sResponse);
		
		$aData = json_decode($sResponse, true);

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH."getOrders.txt", print_r($aData, true));

		return $aData["DataItems"];
	}

	//************************************************************************

	/*! сводка данных по заказу
		@param idOrder идентификатор заказа
		@return 
			OrderCurrency - валюта заказа
				CurrencyCode - международный код валюты ("RUB")
				CurrencyNumCode - международный код валюты (643)
				CurrencyValue - ??? 1
				CurrencySymbol - обозначение валюты на сайте " руб."
				IsCodeBefore - ??? false
				RoundNumbers - ??? 1
				EnablePriceRounding - ??? true
			BonusCard - ??? null
			BonusCardPurchase - ??? null
			BonusCost - ??? 0
			BonusesAvailable - ??? 0
			CanChangeBonusAmount - ??? false
			ProductsCost - ценапродукта (1144)
			ProductsCostStr - строкове представление цены ("1 144 руб.")
			ProductsDiscountPrice - сумма скидки
			ProductsDiscountPriceStr - строквое значение суммы скидки ("0 руб.")
			OrderDiscount - ??? 0
			CouponPrice - итоговая цена с учетом скидок?
			CouponPriceStr - строковое представление  итоговой суммы с учетом скидок "0 руб."
			Coupon - ??? null
			CertificatePrice - ??? 0
			CertificatePriceStr - ??? "0 руб."
			Certificate - ??? null
			ShippingName - название доставки ("Почта России (наземная посылка со страховкой)")
			ShippingType - тип доставки ("edost")
			ShippingCost - цена доставки (400)
			OrderPickPoint - ??? null
			ShippingCostStr - строковое предстваление цены доставки ("400 руб.")
			DeliveryDate - ??? null
			DeliveryTime - ??? ""
			PaymentName - название оплаты ("Онлайн оплата")
			PaymentCost - ??? 0
			PaymentCostStr - ??? "0 руб."
			PaymentDetails - ??? null
			PaymentKey - ??? "robokassa"
			ShowSendBillingLink - ??? true
			ShowPrintPaymentDetails - ??? false
			PrintPaymentDetailsText - ??? null
			PrintPaymentDetailsLink - ??? null
			Taxes - налоги
				0	
					Key - название ("В том числе НДС 20%")
					Value - зщначение ("190,67 руб.")
			Sum - сумма к оплате (1544)
			SumStr - строковое представление суммы ("1 544 руб.")
			OrderDiscountValue - 0
			Paid - оплачено ли (true/false)
			TotalWeight - итоговый вес (0.85)
			TotalDemensions - итоговый объем ("0 x 0 x 0 мм")
			CustomerComment - комментарий заказчика
	*/
	public function getOrderItemsSummary($idOrder)
	{
		curl_reset($this->m_hCurl);
		curl_setopt($this->m_hCurl, CURLOPT_URL, $this->m_sURL."/adminv3/orders/getOrderItemsSummary?orderId=".$idOrder);
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/adminv3");
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
		curl_setopt(
			$this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: application/json, text/plain, */*",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"X-Requested-With: XMLHttpRequest",
				"Connection: keep-alive"
			]
		);
		$this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);
		$sResponse = gzdecode($sResponse);
		
		$aData = json_decode($sResponse, true);

		return $aData;
	}

	//************************************************************************

	/*! возвращает данные контрагента по id заказа
		@param idOrder идентификатор заказа
		@return тоже самое что #ParseOrderCustomer
	*/
	public function getСontractor($idOrder)
	{
		curl_reset($this->m_hCurl);
		curl_setopt($this->m_hCurl, CURLOPT_URL, $this->m_sURL."/adminv3/orders/popupOrderCustomer?orderid=$idOrder"/*&rnd=0,".rand(0, 9999999999)*/);
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/adminv3");
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
		curl_setopt(
			$this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"Cache-Control: max-age=0",
				"Upgrade-Insecure-Requests: 1",
				"Connection: keep-alive"
			]
		);
		$this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);
		$sResponse = gzdecode($sResponse);

		$aContractor = ParseOrderCustomer($sResponse);

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH."getСontractor.txt", print_r($aContractor, true));

		return $aContractor;
	}

	//************************************************************************

	/*! список офферов продукта
		@param idProduct идентификатор продукта
		@return json линейный массив с объектами с ключами:
			OfferId -	id оффера (связка данных по предложению в продукте, например по размеру), у каждого оффера свой id
			ProductId	- id проудкта, у каждого оффера продукта это поле одинаковое
			ArtNo	- актикул оффера
			BasePrice	4000
			SupplyPrice	- закупочная цена оффера
			Amount - количество
			ColorId	- id цвета
			Color	- название цвета
			SizeId - id размера
			Size - название размера
			Main - является ли основным (true/false)
			Weight - вес в кг
			Width	- ширина
			Height - высота
			Length - длина
		@note несмотря на то, что здесь есть возможность указать вес и габариты для каждого оффера, в интерфейсе админки такого не нашел
	*/
	public function getOffers($idProduct)
	{
		curl_reset($this->m_hCurl);
		curl_setopt($this->m_hCurl, CURLOPT_URL, $this->m_sURL."/adminv3/product/getOffers?ItemsPerPage=999&Page=1&ProductId=".$idProduct);
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/adminv3/catalog?showMethod=AllProducts");
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
		curl_setopt(
			$this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: application/json, text/plain, */*",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"X-Requested-With: XMLHttpRequest",
				"Connection: keep-alive"
			]
		);
		$this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);
		$sResponse = gzdecode($sResponse);
		$aData = json_decode($sResponse, true);

		$aOffers = $aData["DataItems"];

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH."getOffers.txt", print_r($aOffers, true));

		return $aOffers;
	}

	//************************************************************************

	/*! обновление оффера продукта
		@param idProduct идентификатор продукта
		@param sJSON json объект с данными оффера (можно посмотреть в методе #getOffers)
		@note происходит в 2 этапа: 
		 - нужно зайти на страницу редактирования продукта чтобы получить токен с этой страницы
		 - отправить запрос на обновление оффера с этим токеном (без токена не пройдет)
		 @return ["result" => true/false, "errors" => [массив со строками ошибок]], ключ errors присутсвует если ключ result == false
	*/
	public function updateOffer($idProduct, $sJSON)
	{
		curl_reset($this->m_hCurl);
		curl_setopt($this->m_hCurl, CURLOPT_URL, $this->m_sURL."/adminv3/product/edit/".$idProduct);
		curl_setopt($this->m_hCurl, CURLOPT_HEADER, TRUE);
		curl_setopt($this->m_hCurl, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/adminv3/catalog?showMethod=AllProducts");
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
		curl_setopt(
      $this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"Connection: keep-alive"
      ]
    );
		$this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH."updateOffer_1.txt", $sResponse);

		$sBody = $this->extractBody($sResponse);
		$sToken = $this->extractToken($sBody);

		curl_reset($this->m_hCurl);
		curl_setopt($this->m_hCurl, CURLOPT_URL, $this->m_sURL."/adminv3/product/updateOffer");
		curl_setopt($this->m_hCurl, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($this->m_hCurl, CURLOPT_POST, TRUE);
		curl_setopt($this->m_hCurl, CURLOPT_POSTFIELDS, ($sJSON));
		curl_setopt($this->m_hCurl, CURLOPT_REFERER, "{$this->m_sURL}/adminv3/product/edit/".$idProduct);
		curl_setopt($this->m_hCurl, CURLOPT_TIMEOUT, 10);
		curl_setopt(
			$this->m_hCurl, 
      CURLOPT_HTTPHEADER, 
      [
        "Host: ".$this->m_sHost,
				"Accept: application/json, text/plain, */*",
				"Accept-Language: ru,en-US;q=0.7,en;q=0.3",
				"Accept-Encoding: gzip, deflate, br",
				"X-Requested-With: XMLHttpRequest",
				"Content-Type: application/json;charset=utf-8",
				"__RequestVerificationToken: ".$sToken,
				"Connection: keep-alive"
			]
		);
		$this->stdSetOpt();

		$sResponse = curl_exec($this->m_hCurl);
		$sResponse = gzdecode($sResponse);

		if(defined("DEBUG_MODE") && DEBUG_MODE)
			file_put_contents(DEBUG_DATA_PATH."updateOffer_2.txt", $sResponse);

		return json_decode($sResponse, true);
	}
}

//##########################################################################
//##########################################################################
//##########################################################################

//! извлечение определенного значения на странице контрагента (для функции ParseOrderCustomer)
function ParseOrderCustomerValue($sId, $sText)
{
	preg_match("/id\=\"$sId\"(.[^>]*)/ims", $sText, $aMatches);
	preg_match("/value\=\"(.*?)\"/ims", $aMatches[1], $aMatches);
	return $aMatches[1];
}

/*! парсинг страницы с данными контрагента
	@param sText html текст страницы с данными
	@return ассоциативный массив:
		last_name - фамилия
		first_name - имя
		patronymic - отчество
		email - адрес электронной почты
		phone -  телефон
		country - страна
		region - регион
		city - город
		street - улица
		zip - индекс
		passport - серия и номер паспорта
		terminal - терминал
		house - дом
		structure - строение
		apartment - квартира
		entrance - подъезд
		floor - этаж
*/
function ParseOrderCustomer($sText)
{
	$aFields = [
		"last_name" 	=> "Order_OrderCustomer_LastName",
		"first_name" 	=> "Order_OrderCustomer_FirstName",
		"patronymic" 	=> "Order_OrderCustomer_Patronymic",
		"email" 			=> "Order_OrderCustomer_Email",	
		"phone" 			=> "Order_OrderCustomer_Phone",
		"country" 		=> "Order_OrderCustomer_Country",
		"region" 			=> "Order_OrderCustomer_Region",
		"city" 				=> "Order_OrderCustomer_City",
		"street" 			=> "Order_OrderCustomer_Street",
		"zip" 				=> "Order_OrderCustomer_Zip",
		"passport" 		=> "Order_OrderCustomer_CustomField1",
		"terminal" 		=> "Order_OrderCustomer_CustomField3",
		"house"				=> "Order_OrderCustomer_House",
		"structure" 	=> "Order_OrderCustomer_Structure", //строение
		"apartment" 	=> "Order_OrderCustomer_Apartment", //квартира
		"entrance" 		=> "Order_OrderCustomer_Entrance", //подъезд
		"floor"				=> "Order_OrderCustomer_Floor", //этаж
	];

	$aData = [];

	foreach($aFields as $key => $value)
		$aData[$key] = ParseOrderCustomerValue($value, $sText);

	return $aData;
}

//##########################################################################
//##########################################################################
//##########################################################################
