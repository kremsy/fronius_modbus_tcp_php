<?php

namespace fronius_modbus_tcp_php\src;

/**
 * Modbus client implementation for controlling and monitoring
 * a Fronius inverter with battery storage via Modbus TCP.
 *
 * This class extends ModbusTCPClient and provides higher level
 * operations for retrieving battery status and controlling
 * charging/discharging behaviour.
 */
class ModbusTCPClientInverter extends ModbusTCPClient
{
    /** Base register for storage/battery information block */
    const REG_STORAGE = 40344;

    /** Register containing maximum battery charge power (W) */
    const REG_WCHAMAX = 40346;

    /** Register containing DC battery power (Gen24 devices) */
    const REG_DC_POWER_3 = 40315;

    /** Register containing scaling factor for DC power */
    const REG_DCW_SF = 40258;

    /** Register enabling grid charging */
    const REG_CHAGRISET = 40352;

    /** Register controlling discharge rate */
    const REG_OUTWRTE = 40356;

    /** Register controlling charge rate */
    const REG_INWRTE = 40357;

    /** Register controlling storage control mode */
    const REG_STORCTL_MOD = 40349;


    /**
     * Reads the current battery status from the inverter.
     *
     * This method reads the full storage register block and converts
     * the values into a structured BatteryStatus object.
     *
     * Scaling factors defined by the inverter are applied to obtain
     * human readable values (percent, watts, etc.).
     *
     * Currently optimized for Fronius GEN24 devices.
     *
     * @return BatteryStatus Populated battery status object
     */
    public function getBatteryStatus(): BatteryStatus {
        // base address of storage - 1 to align indices according to documentation
        $regs = $this->read(self::REG_STORAGE - 1, 27);

        $status = new BatteryStatus();

        // Index numbers matching the documentation
        $wchamax = $regs[3]; // Maximum Charge
        $storCtl = $regs[6]; // CHARGE / DISCHARGE control mode
        $minRsv = $regs[8]; // Reserve % (unscaled)
        $chaState = $regs[9]; // State of charge
        $chaSt = $regs[12]; // Charge status
        $outWrte = self::signed16($regs[13]); // Max discharge %
        $inWrte = self::signed16($regs[14]); // Max charge %
        $rvrtTms = $regs[16]; // Timeout for revert to auto mode
        $grid = $regs[18]; // Allow grid charging

        $sfReserve = self::signed16($regs[22]); // Scale factor for reserve
        $sfSoc = self::signed16($regs[26]); // Scale factor for state of charge
        $sfRate = self::signed16($regs[26]); // Scale factor for charge/discharge rate

        $scaleReserve = pow(10, $sfReserve);
        $scaleSoc = pow(10, $sfSoc);
        $scaleRate = pow(10, $sfRate);

        $status->maxPowerW = $wchamax;

        $status->reservePercent = $minRsv * $scaleReserve;
        $status->stateOfCharge = $chaState * $scaleSoc;

        $status->chargeLimitPercent = $inWrte * $scaleRate;
        $status->dischargeLimitPercent = $outWrte * $scaleRate;

        $status->revertTimeout = $rvrtTms;
        $status->gridChargingEnabled = ($grid == 1);

        $status->controlMode = $storCtl;

        $status->chargeStatus = BatteryStatus::decodeChargeStatus($chaSt);

        // Works only for GEN24 for now, can be improved to support all devices
        $batPower = $this->readSingleRegister(self::REG_DC_POWER_3);
        $dcwSf = $this->readSingleRegister(self::REG_DCW_SF);
        $scaleBat = pow(10, self::signed16($dcwSf));

        $status->batteryPower = $batPower * $scaleBat;

        return $status;
    }


    /**
     * Forces the battery to charge with a specified power.
     *
     * The method converts the requested watt value into a percentage
     * of the inverter's maximum battery charge power and applies the
     * necessary Modbus register changes to enable forced charging.
     *
     * Steps performed:
     * 1. Enable grid charging
     * 2. Limit discharge
     * 3. Allow charging
     * 4. Set control mode to forced charge
     *
     * @param int|float $watts Desired charging power in watts
     *
     * @return void
     */
    public function forceBatteryCharge($watts) {

        $max = $this->readSingleRegister(self::REG_WCHAMAX);

        $pct = self::wattsToPct($watts, $max);

        $this->write(self::REG_CHAGRISET, 1);
        usleep(200000);

        $this->write(self::REG_OUTWRTE, 65536 - $pct);
        usleep(200000);

        $this->write(self::REG_INWRTE, 10000);
        usleep(200000);

        $this->write(self::REG_STORCTL_MOD, 2);
    }

    /**
     * Forces the battery to discharge with a specified power.
     *
     * The requested watt value is converted to a percentage of
     * the inverter's maximum battery charge/discharge capacity.
     *
     * The inverter is then switched into forced discharge mode.
     *
     * @param int|float $watts Desired discharge power in watts
     *
     * @return void
     */
    public function forceBatteryDischarge($watts) {

        $max = $this->readSingleRegister(self::REG_WCHAMAX);

        $pct = self::wattsToPct($watts, $max);

        $this->write(self::REG_INWRTE, 65536 - $pct);
        usleep(200000);

        $this->write(self::REG_OUTWRTE, $pct);
        usleep(200000);

        $this->write(self::REG_STORCTL_MOD, 3);
    }

    /**
     * Restores the inverter's automatic battery control mode.
     *
     * In this mode the inverter manages charging and discharging
     * automatically based on system configuration and energy flows.
     *
     * @return void
     */
    public function autoMode() {

        $this->write(self::REG_STORCTL_MOD, 0);
    }

}