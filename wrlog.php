<?php

// WRlog - Log debug info to file
// Copyright (C) 2013 Antonio Espinosa
//
// This library is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation; either
// version 3 of the License, or (at your option) any later version.
//
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public
// License along with this library; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA

/**
 * WRlog class
 *
 * @copyright 2013 Antonio Espinosa <aespinosa@teachnova.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class wrlog {
    const FILE_READ_MODE    = 0644;
    const FILE_WRITE_MODE   = 0666;
    const DIR_READ_MODE     = 0755;
    const DIR_WRITE_MODE    = 0777;

    const FOPEN_READ                            = 'rb';
    const FOPEN_READ_WRITE                      = 'r+b';
    const FOPEN_WRITE_CREATE_DESTRUCTIVE        = 'wb';
    const FOPEN_READ_WRITE_CREATE_DESTRUCTIVE   = 'w+b';
    const FOPEN_WRITE_CREATE                    = 'ab';
    const FOPEN_READ_WRITE_CREATE               = 'a+b';
    const FOPEN_WRITE_CREATE_STRICT             = 'xb';
    const FOPEN_READ_WRITE_CREATE_STRICT        = 'x+b';

    /**
     * Enable log to file. Default false
     * @var boolean
     */
    static public   $enabled    = false;

    /**
     * Log path where to create log files. Default /tmp
     * @var string
     */
    static public   $path       = '/tmp';

    /**
     * Extension for logs filename. Default txt
     * @var string
     */
    static public   $extension  = 'txt';

    /**
     * Static instance for procedimental callings
     * @var object
     */
    static private  $global     = null;

    /**
     * Cache IP address within same request
     * @var string
     */
    static private  $ip         = false;

    /**
     * Start time, for time profiling propuse. microtime precision
     * @var float
     */
    private $timestart;

    /**
     * Prefix for logs filename. Default 'log'
     * @var float
     */
    private $prefix;

    /**
     * Create a WRlog object with its own timestart
     */
    public function __construct($prefix = 'log') {
        $this->timestart = microtime(true);
        $this->prefix = $prefix;
    }

    /**
     * Reset time start
     *
     * @return void
     */
    public function reset() {
        $this->timestart = microtime(true);
    }

    /**
     * Write a message to log file, output or just return
     *
     * @param  string  $msg
     * @param  boolean $return
     * @param  boolean $out
     * @return mixed
     */
    public function write($msg, $return = false, $out = false) {
        if ($return ||
            (self::$enabled && self::$path) ) {

            if (! $return && ! $out) {
                if (!is_dir(self::$path)) {
                    @mkdir(self::$path, self::DIR_WRITE_MODE, true);
                }
                $filepath = self::$path . '/' . $this->prefix . '-' . date('Y-m-d') . '.' . self::$extension;
            }
            $message  = '';

            if (! $return && ! $out &&
                ! $fp = @fopen($filepath, self::FOPEN_WRITE_CREATE)) {
                return false;
            }

            $message .= $this->time() . $this->ip_address_read() . ': ' . $msg . "\n";

            if (! $return && ! $out) {
                flock($fp, LOCK_EX);
                fwrite($fp, $message);
                flock($fp, LOCK_UN);
                fclose($fp);

                @chmod($filepath, self::FILE_WRITE_MODE);

                return true;

            } else if (! $return) {
                echo '<pre>' . $message . '</pre>';

                return true;

            } else {
                return $message;
            }
        }

        return false;

    }

    /**
     * Write a variable as JSON to log file, output or just return
     *
     * @param  string  $msg
     * @param  mixed   $var
     * @param  boolean $return
     * @param  boolean $out
     * @return mixed
     */
    public function json($msg, $var, $return = false, $out = false) {
        $json = json_encode($var);
        $msg .= "\n" . self::json_readable($json);

        return $this->write($msg, $return, $out);
    }

    /**
     * Write call backtrace to log file, output or just return
     *
     * @param  array   $trace
     * @param  boolean $return
     * @param  boolean $out
     * @return mixed
     */
    public function btrace($trace = null, $return = false, $out = false) {
        if (empty($trace)) $trace = debug_backtrace();
        $msg = '';
        $number = 0;

        foreach ($trace as $call) {
            $function = (empty($call['function'])) ? '[unknown-function]' : $call['function'];
            $file = (empty($call['file'])) ? 'unknown-file' : $call['file'];
            $line = (empty($call['line'])) ? 'unknown-line' : $call['line'];
            if (($function == 'log_backtrace') || ($function == 'log_exception')) continue;

            $msg .= "\n[$number] $function() called at [$file:$line]\n";

            foreach ($call['args'] as $argc => $argv) {
                $msg .= "   ($argc) [" . gettype($argv) . '] ';
                if (is_numeric($argv)) $msg .= $argv;
                if (is_string($argv)) $msg .= '(' . strlen($argv) . ') ' . "'$argv'";
                if (is_bool($argv)) $msg .= ($argv) ? 'true' : 'false';
                if (is_array($argv)) $msg .= '(' . count($argv) . ') ...';
                $msg .= "\n";
            }
            $number ++;
        }

        return $this->write($msg, $return, $out);
    }

    /**
     * Write request info to log file, output or just return
     *
     * @param  array   $extras
     * @param  boolean $return
     * @param  boolean $out
     * @return mixed
     */
    public function request($extras = array(), $return = false, $out = false) {
        if (!empty($_SERVER['REQUEST_METHOD'])) {
            $method = $_SERVER['REQUEST_METHOD'];
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                $method .= "-AJAX";
            }
            $msg = '------------------------ : [' . $method . '] ' . $_SERVER['REQUEST_URI'];
            if (!empty($extras['cookie'])) $msg .= "\n   - Cookie : " . var_export($_COOKIE, true);
            if (!empty($extras['get']))    $msg .= "\n   - GET    : " . var_export($_GET, true);
            if (!empty($extras['post']))   $msg .= "\n   - POST   : " . var_export($_POST, true);

            return $this->write($msg, $return, $out);
        }

        return false;
    }

    /**
     * Check if $ip is IPv4 valid
     *
     * @param  string $ip
     * @return boolean
     */
    static private function ipv4_is_valid($ip) {
        if (empty($ip)) return false;

        $ip_segments = explode('.', $ip);

        // Always 4 segments needed
        if (count($ip_segments) != 4) {
            return false;
        }
        // IP can not start with 0
        if ($ip_segments[0][0] == '0') {
            return false;
        }
        // Check each segment
        foreach ($ip_segments as $segment) {
            // IP segments must be digits and can not be
            // longer than 3 digits or greater then 255
            if ( ($segment == '') OR
                 (preg_match("/[^0-9]/", $segment)) OR
                 ($segment > 255) OR
                 (strlen($segment) > 3) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get IP address from HTTP headers
     *
     * @return string
     */
    static private function ip_address_read() {
        if (self::$ip !== false) self::$ip;

        $ippublic = '0.0.0.0';

        $xforwarded_for = (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : false;
        $xforwarded     = (isset($_SERVER['HTTP_X_FORWARDED'])) ?     $_SERVER['HTTP_X_FORWARDED'] : false;
        $forwarded_for  = (isset($_SERVER['HTTP_FORWARDED_FOR'])) ?   $_SERVER['HTTP_FORWARDED_FOR'] : false;
        $http_client_ip = (isset($_SERVER['HTTP_CLIENT_IP'])) ?       $_SERVER['HTTP_CLIENT_IP'] : false;
        $http_via       = (isset($_SERVER['HTTP_VIA'])) ?             $_SERVER['HTTP_VIA'] : false;
        $remote_addr    = (isset($_SERVER['REMOTE_ADDR'])) ?          $_SERVER['REMOTE_ADDR'] : false;
        $apache_addr    = (isset($HTTP_SERVER_VARS['REMOTE_ADDR'])) ? $HTTP_SERVER_VARS['REMOTE_ADDR'] : false;

             if (self::ipv4_is_valid($xforwarded_for)) $ippublic = $xforwarded_for;
        else if (self::ipv4_is_valid($xforwarded))     $ippublic = $xforwarded;
        else if (self::ipv4_is_valid($forwarded_for))  $ippublic = $forwarded_for;
        else if (self::ipv4_is_valid($http_client_ip)) $ippublic = $http_client_ip;
        else if (self::ipv4_is_valid($http_via))       $ippublic = $http_via;
        else if (self::ipv4_is_valid($remote_addr))    $ippublic = $remote_addr;
        else if (self::ipv4_is_valid($apache_addr))    $ippublic = $apache_addr;

        self::$ip = '[' . $ippublic . '/' . getmypid() . ']';
        return self::$ip;
    }

    /**
     * Convert JSON flat string into a human readable JSON valid string
     *
     * @param  string $json
     * @return string
     */
    static private function json_readable($json) {
        $tabcount   = 0;
        $result     = '';
        $quotes     = false;
        $tab        = "\t";
        $newline    = "\n";

        for ($i = 0; $i < strlen($json); $i++) {
            $character = $json[$i];
            if ( ($character == '"') &&
                 ($json[$i - 1] != '\\') ) $quotes = !$quotes;
            if ($quotes) {
                $result .= $character;
                continue;
            }
            switch ($character) {
                case '{':
                case '[':
                    $result .= $character . $newline . str_repeat($tab, ++$tabcount);
                    break;
                case '}':
                case ']':
                    $result .= $newline . str_repeat($tab, --$tabcount) . $character;
                    break;
                case ',':
                    $result .= $character;
                    if ( ($json[$i + 1] != '{') &&
                         ($json[$i + 1] != '[') ) $result .= $newline . str_repeat($tab, $tabcount);
                    break;
                case ':':
                    $result .= $character . ' ';
                    break;
                default:
                    $result .= $character;
            }
        }

        return $result;
    }

    /**
     * Return current time and seconds from timestart with precision 3
     *
     * @return string
     */
    private function time() {
        $time = microtime(true) - $this->timestart;
        $time = round($time, 3);

        return date('Y/m/d-H:i:s') . '(' . $time . ')';
    }

    /**
     * Static call to $global->reset
     *
     * @return void
     */
    static public function sreset() {
        if (empty(self::$global)) self::$global = new wrlog('global-log');
        return self::$global->reset();
    }

    /**
     * Static call to $global->write
     *
     * @param  string  $msg
     * @param  boolean $return
     * @param  boolean $out
     * @return mixed
     */
    static public function swrite($msg, $return = false, $out = false) {
        if (empty(self::$global)) self::$global = new wrlog('global-log');
        return self::$global->write($msg, $return, $out);
    }

    /**
     * Static call to $global->json
     *
     * @param  string  $msg
     * @param  mixed   $var
     * @param  boolean $return
     * @param  boolean $out
     * @return mixed
     */
    static public function sjson($msg, $var, $return = false, $out = false) {
        if (empty(self::$global)) self::$global = new wrlog('global-log');
        return self::$global->json($msg, $var, $return, $out);
    }

    /**
     * Static call to $global->btrace
     *
     * @param  array   $trace
     * @param  boolean $return
     * @param  boolean $out
     * @return mixed
     */
    static public function sbtrace($trace = null, $return = false, $out = false) {
        if (empty(self::$global)) self::$global = new wrlog('global-log');
        if (empty($trace)) $trace = debug_backtrace();
        return self::$global->btrace($trace, $return, $out);
    }

    /**
     * Static call to $global->request
     *
     * @param  array   $extras
     * @param  boolean $return
     * @param  boolean $out
     * @return mixed
     */
    static public function srequest($extras = array(), $return = false, $out = false) {
        if (empty(self::$global)) self::$global = new wrlog('global-log');
        return self::$global->request($extras, $return, $out);
    }

}

/**
 * Procedimental call to static wrlog::sreset
 *
 * @return void
 */
function wrlog_timestart() {
    return wrlog::sreset();
}

/**
 * Procedimental call to static wrlog::swrite
 *
 * @param  string  $msg
 * @param  boolean $return
 * @param  boolean $out
 * @return mixed
 */
function wrlog($msg, $return = false, $out = false) {
    return wrlog::swrite($msg, $return, $out);
}

/**
 * Procedimental call to static wrlog::swrite
 * without return and echo to output
 *
 * @param  string  $msg
 * @return boolean
 */
function wrout($msg) {
    return wrlog::swrite($msg, false, true);
}

/**
 * Procedimental call to static wrlog::sjson
 *
 * @param  string  $msg
 * @param  mixed   $var
 * @param  boolean $return
 * @param  boolean $out
 * @return mixed
 */
function wrlog_json($msg, $var, $return = false, $out = false) {
    return wrlog::sjson($msg, $var, $return, $out);
}

/**
 * Procedimental call to static wrlog::sjson
 * without return and echo to output
 *
 * @param  string  $msg
 * @param  mixed   $var
 * @return boolean
 */
function wrout_json($msg, $var) {
    return wrlog::sjson($msg, $var, false, true);
}

/**
 * Procedimental call to static wrlog::sbtrace
 *
 * @param  array   $trace
 * @param  boolean $return
 * @param  boolean $out
 * @return mixed
 */
function wrlog_btrace($trace = null, $return = false, $out = false) {
    if (empty($trace)) $trace = debug_backtrace();
    return wrlog::sbtrace($trace, $return, $out);
}

/**
 * Procedimental call to static wrlog::sbtrace
 * without return and echo to output
 *
 * @param  array   $trace
 * @return mixed
 */
function wrout_btrace($trace = null) {
    if (empty($trace)) $trace = debug_backtrace();
    return wrlog::sbtrace($trace, false, true);
}

/**
 * Procedimental call to static wrlog::srequest
 *
 * @param  array   $extras
 * @return mixed
 */
function wrlog_request($extras = array(), $return = false, $out = false) {
    return wrlog::srequest($extras, $return, $out);
}

/**
 * Procedimental call to static wrlog::srequest
 * without return and echo to output
 *
 * @param  array   $extras
 * @return mixed
 */
function wrout_request($extras = array()) {
    return wrlog::srequest($extras, false, true);
}

// Create global instance and reset time start
wrlog_timestart();
