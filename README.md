## Краткое писание
**Advantshop-PHP-API** – неофициальное API на php для автоматизации работы с интернет-магазином на движке Advantsop. Тесты проводились на Advantshop 8.

**Используемые библиотеки**: curl, gzip

## Возможности
API представлено классом, реализующим низкоуровневые операции (advantshop.class.php).
После авторизации (через логин/пароль или на основании кук) доступны следующие функции:
* список продукции в магазине $oShop->getProducts()
* список заказов с фильтрацией: $oShop->getOrders()
* сводка данных по заказу: $oShop->getOrderItemsSummary($idOrder)
* данные контрагента по id заказа: $oShop->getСontractor($idOrder)
* список офферов продукта: $oShop->getOffers($idProduct)
* обновление оффера продукта: $oShop->updateOffer($idProduct, $sJSON)

Для осуществления конкретных действий есть скрипты примеров:
* Получение списка контрагентов по статусу (get_contractors.sample.php), относительный адрес запроса: /get_contractors.sample.php?token=TOKEN из config.inc.php
* Обновление остатков товаров (updating_stocks.sample.php) /updating_stocks.sample.php?token=TOKEN из config.inc.php

## Использование
Самостоятельное использование класса API:
	
	include("advantshop.php");

	$sCookie = "";
	//загрузка куки
	if(file_exists("cookie.txt"))
		$sCookie = file_get_contents("cookie.txt");

	//авторизация в интернет-магазине
	$oShop = new CAdvantShop();
	$iResult = $oShop->auth(PROTOCOL, SITE, LOGIN, PASSWORD, $sCookie, USERAGENT);

	//сохраняем куки
	file_put_contents("cookie.txt", $oShop->getCookie());
  
Файлы config.inc.php и common.inc.php облегчают использование API:
* config.inc.php содержит данные для авторизации
* common.inc.php содержит подключение всего необходимого, создание объекта класса $oShop, загрузку и создание файла кук

Для упрощенного использования можно просто подключить:
	
	include("common.inc.php");

## Лицензия
**MIT**

## Автор
Buturlin Vitaliy (Byurrer), email: byurrer@mail.ru
