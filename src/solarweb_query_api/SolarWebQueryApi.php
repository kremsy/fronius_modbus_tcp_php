<?php

namespace fronius_modbus_tcp_php\src\solarweb_query_api;

use DateTime;
use DateTimeZone;
use Exception;
/**
 * This is just a first draft with some useful methods, will be improved in the future
 */
class SolarWebQueryApi
{
    public function __construct() {
        $config = require __DIR__ . '/credentials.php';

        if (!isset($config['access_key_id'], $config['access_key_value'])) {
            throw new Exception("Missing API credentials, check if credentials.php is existing");
        }

        $this->pvSystemId = $config['pv_system_id'];
        $this->access_key_id = $config['access_key_id'];
        $this->access_key_value = $config['access_key_value'];
    }

    public function solarWebApiGet($endPoint) {
        $ch = curl_init();
        $headers = array(
            "AccessKeyId: " . $this->access_key_id,
            "AccessKeyValue: " . $this->access_key_value
        );

        $url = "https://api.solarweb.com/swqapi/" . $endPoint;
        var_dump($url);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        return json_decode(curl_exec($ch));
    }

    public function getNightConsumptionForecast() {
        $api = new SolarWebQueryApi();
        $tz = new DateTimeZone('Europe/Vienna');

        $nightSums = [];

        // Loop over last 10 nights
        for ($i = 1; $i <= 10; $i++) {

            $start = new DateTime("-{$i} day 17:00:00", $tz);
            $end = new DateTime("-" . ($i - 1) . " day 08:00:00", $tz);

            $from = $start->format('Y-m-d\TH:i:s');
            $to = $end->format('Y-m-d\TH:i:s');
            $endPoint = "pvsystems/{$this->pvSystemId}/histdata?from={$from}&to={$to}&limit=500";

            //$endPoint = "pvsystems/{$this->pvSystemId}/histdata?channel=EnergyConsumptionTotal&from={$from}&to={$to}&limit=500";
            $energyData = $api->solarWebApiGet($endPoint);

            // Skip invalid responses
            if (!$energyData || !property_exists($energyData, "data")) {
                continue;
            }

            $sum = 0;
            $productionSum = 0;

            foreach ($energyData->data as $entry) {
                foreach ($entry->channels as $channel) {
                    if ($channel->channelName === 'EnergyConsumptionTotal') {
                        $sum += $channel->value;
                    }
                    if ($channel->channelName === 'EnergySelfConsumptionTotal') {
                        $productionSum += $channel->value;
                    }
                }
            }
            #var_dump($sum,$productionSum);

            $nightSums[] = $sum - $productionSum;
        }

        // If no valid data at all
        if (count($nightSums) === 0) {
            return -1;
        }

        // Calculate average
        $average = array_sum($nightSums) / count($nightSums);

        //var_dump($energyData);
        return $average;
    }

    public function getEnergyForecastFullDay() {
        $api = new SolarWebQueryApi();


        $endPoint = "pvsystems/{$this->pvSystemId}/weather/energyforecast?duration=24"; # TODO limit data by parameters

        $energyData = $api->solarWebApiGet($endPoint);

        //tomorrowStart = new DateTime('tomorrow 00:00:00', new DateTimeZone('UTC'));
        //$tomorrowEnd = new DateTime('tomorrow 23:59:59', new DateTimeZone('UTC'));

        if (!$energyData or !property_exists($energyData, "data")) {
            return -1;
        }

        $sum = 0;
        foreach ($energyData->data as $entry) {
            #$time = new DateTime($entry->logDateTime, new DateTimeZone('UTC'));

            #if ($time >= $tomorrowStart && $time <= $tomorrowEnd) {
            foreach ($entry->channels as $channel) {
                if ($channel->channelName === 'EnergyExpected') {
                    $sum += $channel->value;
                }
            }
            #}
        }
        return round($sum / 1000, 2);
    }

    public function getEnergyForecastAfternoon() {
        $api = new SolarWebQueryApi();

        $tz = new DateTimeZone('Europe/Vienna');

        $start = new DateTime('today 13:00:00', $tz);
        $end = new DateTime('today 18:00:00', $tz);

        $from = $start->format('Y-m-d\TH:i:s');
        $to = $end->format('Y-m-d\TH:i:s');

        $endPoint = "pvsystems/{$this->pvSystemId}/weather/energyforecast?from={$from}&to={$to}";

        $energyData = $api->solarWebApiGet($endPoint);

        //If error, return -1
        if (!$energyData || !property_exists($energyData, "data")) {
            return -1;
        }
        $sum = 0;
        foreach ($energyData->data as $entry) {
            foreach ($entry->channels as $channel) {
                if ($channel->channelName === 'EnergyExpected') {
                    $sum += $channel->value;
                }
            }
        }

        return round($sum / 1000, 2);
    }

    public function getEnergyForecastBeforeMidday() {
        $api = new SolarWebQueryApi();

        $tz = new DateTimeZone('Europe/Vienna');

        //$start = new DateTime('today 12:00:00', $tz);
        $end = new DateTime('today 13:00:00', $tz);

        //$from = $start->format('Y-m-d\TH:i:s');
        $to = $end->format('Y-m-d\TH:i:s');

        $endPoint = "pvsystems/{$this->pvSystemId}/weather/energyforecast?to={$to}";

        $energyData = $api->solarWebApiGet($endPoint);

        //If error, return -1
        if (!$energyData || !property_exists($energyData, "data")) {
            return -1;
        }
        $sum = 0;
        foreach ($energyData->data as $entry) {
            foreach ($entry->channels as $channel) {
                if ($channel->channelName === 'EnergyExpected') {
                    $sum += $channel->value;
                }
            }
        }

        return round($sum / 1000, 2);
    }
}