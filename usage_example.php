<?php
/**
 * ----------------------------------------------------
 * Example usage of the Fronius Modbus TCP PHP library
 * ----------------------------------------------------
 *
 * This example demonstrates how to:
 *  - Connect to a Fronius inverter via Modbus TCP
 *  - Read battery status information
 *  - Control battery operation mode
 *  - Read real power from primary and secondary smart meters
 *
 * Requirements:
 *  - Fronius inverter with Modbus TCP enabled
 *  - Correct IP address of the inverter
 *  - Smart meter configured (optional)
 */

use battery\fronius_modbus_tcp_php\ModbusTCPClientInverter;
use battery\fronius_modbus_tcp_php\ModbusTCPClientSmartmeter;

include_once("ModbusTCPClient.php");
include_once("ModbusTCPClientSmartmeter.php");
include_once("ModbusTCPClientInverter.php");
include_once("BatteryStatus.php");

/**
 * IP address or hostname of the Fronius inverter.
 *
 * The inverter exposes the Modbus TCP service that also
 * proxies smart meter data.
 */
$host = "192.168.0.65";

/**
 * ----------------------------------------------------
 * Inverter connection
 * ----------------------------------------------------
 *
 * Create a Modbus client for the inverter and retrieve
 * the current battery status.
 */
$client = new ModbusTCPClientInverter($host);

$client->connect();

/**
 * Retrieve structured battery information such as:
 *  - state of charge
 *  - charge/discharge limits
 *  - battery power
 *  - reserve percentage
 *  - operating mode
 */
$status = $client->getBatteryStatus();

/**
 * Convert the BatteryStatus object into an array
 * and print it for debugging or logging.
 */
print_r($status->toArray());

/**
 * Optional: Force the battery to charge at a specific power.
 *
 * Example:
 * $client->forceBatteryCharge(4000); // Charge with ~4kW
 */

/**
 * Restore automatic battery control mode.
 *
 * In this mode the inverter decides when to
 * charge or discharge the battery.
 */
$client->autoMode();

/**
 * Close inverter connection.
 */
$client->close();


/**
 * ----------------------------------------------------
 * Primary smart meter
 * ----------------------------------------------------
 *
 * The primary smart meter usually has slave ID 200
 * and provides information about total grid power.
 */
$client = new ModbusTCPClientSmartmeter($host);

$client->connect();

/**
 * Retrieve current real power measured by the meter.
 *
 * Interpretation:
 *  - Positive value  → importing power from the grid
 *  - Negative value  → exporting power to the grid
 */
$power = $client->getRealPower();

var_dump($power);

$client->close();


/**
 * ----------------------------------------------------
 * Secondary smart meter
 * ----------------------------------------------------
 *
 * Some systems have an additional smart meter,
 * typically using slave ID 201.
 */
$client = new ModbusTCPClientSmartmeter($host, 502, 201);

$client->connect();

/**
 * Read real power from the secondary meter.
 */
$power = $client->getRealPower();

var_dump($power);

$client->close();