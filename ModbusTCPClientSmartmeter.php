<?php

namespace battery\fronius_modbus_tcp_php;

/**
 * Modbus client for reading data from a Fronius Smart Meter via Modbus TCP.
 *
 * This class extends ModbusTCPClient and provides convenience methods
 * for retrieving smart meter measurements such as real power.
 *
 * The smart meter is typically accessed through the inverter using
 * Modbus TCP with slave ID 200.
 */
class ModbusTCPClientSmartmeter extends ModbusTCPClient
{
    /**
     * Base register address for the smart meter measurement block.
     *
     * This register block contains various electrical measurements
     * such as voltage, current, power, and scaling factors.
     */
    const METER_BASE = 40070;

    /**
     * Constructor.
     *
     * Initializes a Modbus client configured for a Fronius smart meter.
     * The default slave ID is 200, which is typically used by Fronius
     * for the integrated smart meter device.
     *
     * @param string $host  Hostname or IP address of the inverter/smart meter
     * @param int    $port  Modbus TCP port (default: 502)
     * @param int    $slave Modbus slave/unit ID (default: 200)
     */
    public function __construct($host, $port = 502, $slave = 200) {
        parent::__construct($host, $port, $slave);
    }

    /**
     * Reads the current total real power measured by the smart meter.
     *
     * The value is retrieved from the smart meter register block and
     * scaled using the provided scale factor.
     *
     * Interpretation of the result:
     * - Positive value → importing power from the grid
     * - Negative value → exporting power to the grid
     *
     * The returned value is expressed in watts.
     *
     * @return int Total real power in watts
     */
    function getRealPower(): int {
        $meter = $this->read(self::METER_BASE - 1, 29);

        // Total real power value
        $totalPower = $meter[19];

        // Scale factor for power values
        $wScale = pow(10, self::signed16($meter[23]));

        return $totalPower * $wScale;
    }
}