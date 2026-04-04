<?php

namespace fronius_modbus_tcp_php\src;

/**
 * Represents the current battery state reported by a Fronius inverter.
 *
 * This class acts as a structured container for battery-related
 * information retrieved via Modbus TCP. It contains operational
 * parameters, limits, and current measurements.
 */
class BatteryStatus
{

    /**
     * Maximum allowed battery charge power.
     *
     * @var int Power in watts
     */
    public int $maxPowerW;

    /**
     * Current battery state of charge.
     *
     * @var float Percentage (0–100)
     */
    public float $stateOfCharge;

    /**
     * Minimum reserve state of charge configured in the inverter.
     *
     * This value represents the percentage of battery capacity
     * reserved for backup or minimum charge protection.
     *
     * @var float Percentage (0–100)
     */
    public float $reservePercent;

    /**
     * Maximum allowed charging rate.
     *
     * Value is expressed as a percentage of the inverter’s
     * maximum battery charge power.
     *
     * @var int Percentage ×100 (e.g. 10000 = 100%)
     */
    public int $chargeLimitPercent;

    /**
     * Maximum allowed discharging rate.
     *
     * Value is expressed as a percentage of the inverter’s
     * maximum battery discharge power.
     *
     * @var int Percentage ×100 (e.g. 10000 = 100%)
     */
    public int $dischargeLimitPercent;

    /**
     * Timeout in seconds before forced battery control
     * automatically reverts to automatic mode.
     *
     * @var int Timeout in seconds
     */
    public int $revertTimeout;

    /**
     * Indicates whether charging the battery from the grid
     * is allowed.
     *
     * @var bool
     */
    public bool $gridChargingEnabled;

    /**
     * Current storage control mode set on the inverter.
     *
     * Typical values:
     * - 0 → Automatic mode
     * - 2 → Forced charging
     * - 3 → Forced discharging
     *
     * @var int
     */
    public int $controlMode;

    /**
     * Current battery power.
     *
     * Positive values typically indicate charging,
     * negative values indicate discharging (device dependent).
     *
     * @var float Power in watts
     */
    public float $batteryPower;

    /**
     * Human-readable battery charge status.
     *
     * Examples:
     * - charging
     * - discharging
     * - full
     * - holding
     *
     * @var string
     */
    public string $chargeStatus;

    /**
     * Converts the battery status object into an associative array.
     *
     * Useful for JSON serialization, APIs, or debugging.
     *
     * @return array<string,mixed> Associative array of all object properties
     */
    public function toArray() {
        return get_object_vars($this);
    }

    /**
     * Converts the numeric charge status value reported by the inverter
     * into a human-readable string.
     *
     * Status codes follow the Fronius Modbus specification.
     *
     * @param int $value Raw status code from the inverter
     *
     * @return string Human-readable status description
     */
    public static function decodeChargeStatus($value) {
        switch ($value) {
            case 1:
                return "off";
            case 2:
                return "empty";
            case 3:
                return "discharging";
            case 4:
                return "charging";
            case 5:
                return "full";
            case 6:
                return "holding";
            case 7:
                return "testing";
            default:
                return "unknown";
        }
    }

    /**
     * Returns a compact, human-readable summary of the battery status.
     *
     * This method formats key battery metrics into a single string,
     * useful for logging, CLI output, or quick diagnostics.
     *
     * Included values:
     * - State of charge (SOC)
     * - Charge limit
     * - Discharge limit
     * - Current battery power
     * - Charge status (human-readable)
     *
     * @return string Formatted summary string (e.g. "SOC: 75.0% | ChargeLimit: 100.00% | ...")
     */
    public function toSummaryString(): string {
        return sprintf(
            "SOC: %.1f%% | ChargeLimit: %.2f%% | DischargeLimit: %.2f%% | Power: %.0f W | Status: %s",
            $this->stateOfCharge,
            $this->chargeLimitPercent,
            $this->dischargeLimitPercent,
            $this->batteryPower,
            $this->chargeStatus
        );
    }
}