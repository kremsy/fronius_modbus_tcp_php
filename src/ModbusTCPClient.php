<?php

namespace battery\fronius_modbus_tcp_php\src;

use Exception;

class ModbusTCPClient
{

    /** @var string Modbus server hostname or IP */
    private $host;

    /** @var int TCP port of the Modbus server (default 502) */
    private $port;

    /** @var int Modbus slave/unit ID */
    private $slave;

    /** @var resource|null Socket connection handle */
    private $socket;

    /** @var int Current Modbus transaction identifier */
    private $transaction = 0;

    /**
     * Constructor.
     *
     * Initializes a Modbus TCP client instance.
     *
     * @param string $host  Hostname or IP address of the Modbus TCP server
     * @param int    $port  TCP port (default: 502)
     * @param int    $slave Modbus slave/unit ID (default: 1)
     */
    public function __construct($host, $port = 502, $slave = 1) {
        $this->host = $host;
        $this->port = $port;
        $this->slave = $slave;
    }

    /**
     * Opens the TCP connection to the Modbus server.
     *
     * @throws Exception If the connection cannot be established
     *
     * @return void
     */
    public function connect() {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (!$this->socket) {
            throw new Exception("Connection failed: $errstr");
        }
    }

    /**
     * Closes the TCP connection if it is open.
     *
     * @return void
     */
    public function close() {
        if ($this->socket) fclose($this->socket);
    }

    /**
     * Generates the next Modbus transaction identifier.
     *
     * The transaction ID is incremented and wrapped to 16 bits.
     *
     * @return int Next transaction ID
     */
    private function nextTransaction() {
        $this->transaction = ($this->transaction + 1) & 0xffff;
        return $this->transaction;
    }

    /**
     * Sends a raw Modbus PDU request and reads the response.
     *
     * This method builds the MBAP header, sends the request
     * to the Modbus TCP server, and returns the response PDU.
     *
     * @param string $pdu Protocol Data Unit to send
     *
     * @throws Exception If the server returns a Modbus exception
     *
     * @return string Response PDU
     */
    private function request($pdu) {

        $tid = $this->nextTransaction();

        $header = pack("nnnC", $tid, 0, strlen($pdu) + 1, $this->slave);
        fwrite($this->socket, $header . $pdu);

        $h = fread($this->socket, 7);
        $resp = unpack("ntid/nproto/nlen/Cunit", $h);

        $pdu = fread($this->socket, $resp["len"] - 1);

        if (ord($pdu[0]) & 0x80) {
            throw new Exception("Modbus exception");
        }

        return $pdu;
    }

    /**
     * Reads one or more holding registers (Function Code 3).
     *
     * The returned values are converted to signed 16-bit integers.
     *
     * @param int $addr  Starting register address (1-based)
     * @param int $count Number of registers to read
     *
     * @return int[] Array of signed register values
     */
    public function read($addr, $count) {

        $pdu = pack("Cnn", 3, $addr - 1, $count);
        $resp = $this->request($pdu);

        $values = [];

        for ($i = 0; $i < $count; $i++) {

            $v = unpack("n", substr($resp, 2 + $i * 2, 2))[1];

            // convert to signed
            if ($v >= 0x8000) {
                $v -= 0x10000;
            }

            $values[] = $v;
        }

        return $values;
    }

    /**
     * Reads a single holding register.
     *
     * Convenience wrapper around read().
     *
     * @param int $addr Register address (1-based)
     *
     * @return int Signed 16-bit register value
     */
    public function readSingleRegister($addr) {
        return $this->read($addr, 1)[0];
    }

    /**
     * Writes a single holding register (Function Code 6).
     *
     * @param int $addr  Register address (1-based)
     * @param int $value Value to write (16-bit)
     *
     * @return void
     */
    public function write($addr, $value) {
        // -1 to align index with documentation
        $pdu = pack("Cnn", 6, $addr - 1, $value & 0xffff);
        $this->request($pdu);
    }

    # ----------------------------
    # helper methods
    # ----------------------------

    /**
     * Converts an unsigned 16-bit integer to signed.
     *
     * @param int $v Unsigned 16-bit value
     *
     * @return int Signed 16-bit value
     */
    public static function signed16($v) {
        return ($v >= 32768) ? $v - 65536 : $v;
    }

    /**
     * Converts a power value in watts to a Modbus percentage value.
     *
     * The result is scaled by 100 (e.g. 50% → 5000).
     *
     * @param float|int $watts Current power value in watts
     * @param float|int $max   Maximum power in watts
     *
     * @return int Percentage * 100 (e.g. 100% = 10000)
     */
    public static function wattsToPct($watts, $max) {

        if ($max <= 0) return 10000;

        $pct = ($watts / $max) * 100;
        return intval($pct * 100);
    }
}