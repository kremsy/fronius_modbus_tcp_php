<?php

use fronius_modbus_tcp_php\src\ModbusTCPClientInverter;
use fronius_modbus_tcp_php\src\solarweb_query_api\SolarWebQueryApi;

include_once __DIR__ . '/../src/solarweb_query_api/SolarWebQueryApi.php';
include_once __DIR__ . '/../src/ModbusTCPClient.php';
include_once __DIR__ . '/../src/ModbusTCPClientInverter.php';
include_once __DIR__ . '/../src/ModbusTCPClientSmartmeter.php';
include_once __DIR__ . '/../src/BatteryStatus.php';

/**
 * This is just a first draft, if forecast is bad, it loads battery full-day, if forecast is good it loads at lunch time
 */
class BatteryManager
{

    const PRE_NOON_CHARGE_LIMIT_PERCENT = 1;#5;

    private $energyForeCast = 0.0;

    public function __construct() {
    }

    public function run() {
        $api = new SolarWebQueryApi();
        $this->energyForeCast = $api->getEnergyForecastFullDay();


        echo "Forecast next 24 hours: " . $this->energyForeCast . "kWh\n";

        $lastForecastCall = time();
        $foreCastAfternoon = $api->getEnergyForecastAfternoon();
        echo "Forecast next >13:00: " . $foreCastAfternoon . "kWh\n";


        # TODO set forecast only before a certain time

        $host = "192.168.0.65";
        $client = new ModbusTCPClientInverter($host);
        $autoModeSet = false;
        $foreCastToMidday = 0;
        while (true) {
            $client->connect();
            //var_dump($client->getBatteryStatus());
            $batteryStatus = $client->getBatteryStatus();

            $now = new DateTime('now', new DateTimeZone('Europe/Vienna'));
            $noon = new DateTime('today 13:00:00', new DateTimeZone('Europe/Vienna'));

            if ($now < $noon) {
                //Check every 30min forecast for > 12:00
                if (time() - $lastForecastCall >= 30 * 60) { // 30 min = 1800s
                    $foreCast = $api->getEnergyForecastAfternoon();
                    $foreCastToMidday = $api->getEnergyForecastBeforeMidday();

                    if ($foreCast != -1) {//-1 in case something goes wrong
                        $foreCastAfternoon = $foreCast;
                    }
                    $lastForecastCall = time();
                }
                echo "Forecast next >13:00: " . $foreCastAfternoon . "kWh\n";

                echo "Forecast until 13:00: " . $foreCastToMidday . "kWh\n";


                if ($foreCastAfternoon > 25) {
                    # Enforce minimum SOC
                    if ($batteryStatus->stateOfCharge < 12 && !$autoModeSet) {
                        echo "SOC is below 12, stay in auto-mode \n";
                        $client->autoMode(); //TODO call only once
                        $autoModeSet = true;
                    } else if ($batteryStatus->stateOfCharge < 20) {
                        // Limit Battery Charge until 12:00 if enough sun is here
                        if ($batteryStatus->chargeLimitPercent != self::PRE_NOON_CHARGE_LIMIT_PERCENT) {
                            $client->limitChargePower(self::PRE_NOON_CHARGE_LIMIT_PERCENT);
                            $autoModeSet = false;
                        }
                    } else {
                        // Block Battery Charge until 12:00 if enough sun is here and SOC is high enough
                        if ($batteryStatus->chargeLimitPercent != 0) {
                            $client->limitChargePower(0);
                            $autoModeSet = false;
                        }
                    }
                } else {
                    if (!$autoModeSet) {
                        echo "Set back auto-mode ";
                        $client->autoMode(); //TODO call only once
                        $autoModeSet = true;
                    }
                }
            } else {
                if (!$autoModeSet) {
                    echo "Set back auto-mode ";
                    $client->autoMode(); //TODO call only once
                    $autoModeSet = true;
                }
            }
            //$client->forceBatteryCharge(500);

            //echo "Charge Limit:  " . $client->getBatteryStatus()->chargeLimitPercent . "% \n";

            echo $client->getBatteryStatus()->toSummaryString() . "\n";
            //var_dump($client->getBatteryStatus());
            $client->close();

            sleep(60);
        }
    }
}