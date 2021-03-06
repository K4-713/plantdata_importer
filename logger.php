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

/**
 * Using this function instantiates echologger on the first use, and passes the 
 * message through to the say function.
 * @staticvar echologger $log The echologger class instance
 * @param string|array $logme The statement to log
 * @param boolean $display_last_error True if you want to also display the last error. Otherwise false.
 */
function echolog( $logme, $display_last_error = false ){
	//TODO: might want to implement a verbose mode at some point...
	static $log = null;
	if (is_null($log)){
		$log = new echologger();
	}
	$log->say( $logme );
	if ($display_last_error === true){
		$log->lastError();
	}
	
}


function stopwatch($timer_name, $text = ''){
	if( !getConfig('stopwatch_output') ){
		return;
	}
	
	//I want this to behave like a toggle. Don't need to get too fancy, I just need 
	//to know where my bottlenecks are.
	static $timers = array();
	if (array_key_exists($timer_name, $timers)){
		$elapsed = microtime(true) - $timers[$timer_name];
		echolog("Stopwatch '$timer_name' = $elapsed $text");
		unset($timers[$timer_name]);
	} else {
		$timers[$timer_name] = microtime(true);
	}
}


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
			if ($this->is_3d_array($logme)){
				fwrite( $this->file, $this->getTime() . ": " . print_r( $logme, true ));
				print_r( $logme );
				return;
				
			} else { //just 2d. Phew.
				foreach ($logme as $ind=>$line) {
					//looks dumb with the time. Otherwise I'd recurse here...
					$writeme = '';
					if (is_numeric($ind)) {	//swallow it
						$writeme = "\t$line\n";
					} else {
						$writeme = "\t$ind = $line\n";
					}
					fwrite($this->file, $writeme);
					echo $writeme;
				}	
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
	
	
	public function is_3d_array( $checkme ){
		foreach ( $checkme as $thing => $stuff ){
			if (is_array($stuff)) {
				return true;
			}			
		}
		return false;
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
