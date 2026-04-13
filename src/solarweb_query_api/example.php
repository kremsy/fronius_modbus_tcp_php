<?php

use fronius_modbus_tcp_php\src\solarweb_query_api\SolarWebQueryApi;

include_once __DIR__ . '/SolarWebQueryApi.php';

# Notice only available on Solar-Web premium
$api = new SolarWebQueryApi();
var_dump($api->getEnergyForecastFullDay());
