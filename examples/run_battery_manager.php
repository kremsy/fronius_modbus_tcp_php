<?php
/**
 * This is just a first draft
 */

include_once __DIR__ . '/BatteryManager.php';


//$api = new SolarWebQueryApi();
//$foreCast = $api->getEnergyForecastFullDay();
$batteryManager = new BatteryManager();
$batteryManager->run();