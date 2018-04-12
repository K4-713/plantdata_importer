<?php

/* 
 * Copyright (C) 2018 Katie Horn (katie@katiehorn.com)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

class echologger {

	private $file = ''; //file handle, opened with fopen

	public function __construct() {
		//make and open the file, save reference to class variable
		$time = $this->getTime(true);
		$this->file = fopen(__DIR__ . "/logs/$time.txt", 'w');
	}

	/**
	 * Log and echo some string
	 * @param string $logme the seting you want to log and echo
	 */
	public function say($logme) {
		if (is_array($logme)) {
			foreach ($logme as $line) {
				//looks dumb with the time. Otherwise I'd recurse here...
				fwrite($this->file, "\t$line\n");
			}
			return;
		}

		echo "$logme\n";
		$time = $this->getTime();
		fwrite($this->file, "$time: $logme\n");
	}

	/*
	 * Return the current date and time in the format we're using for the logs.
	 */

	public function getTime($filename = false) {
		$microtime = microtime();
		$bits = explode(' ', $microtime);
		//I want exactly three digits of utime
		$utime = round($bits[0], 3) * 1000;

		if (!$filename) {
			$dateformat = 'H:i:s';
			$time = date($dateformat, $bits[1]);
			$time = $time . '.' . round($bits[0], 3);
		} else {
			$dateformat = 'Ymd-H:i:s';
			$time = date($dateformat, $bits[1]);
		}
		return $time;
	}

	public function lastError() {
		$this->say('Last error output:');
		$last_error = error_get_last();

		$out[0] = "Type: " . $last_error['type']; //nice to have: String output on that type instead of numeric.
		$out[1] = "Message: " . $last_error['message'];
		$out[2] = $last_error['file'] . ", line " . $last_error['line'];
		$this->say($out);
	}

	/*
	 * Close the log file
	 */

	public function __destruct() {
		$time = $this->getTime();
		fwrite($this->file, "$time: Exiting.");
		fclose($this->file);
	}

}
