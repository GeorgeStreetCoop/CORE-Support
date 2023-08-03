<?php
/*******************************************************************************

	Copyright 2020 George Street Co-op

	This file is part of IT CORE.

	IT CORE is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	IT CORE is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	in the file license.txt along with IT CORE; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

use COREPOS\pos\plugins\Plugin;

/**
	Plugin class

	Plugins are collections of modules. Each collection should
	contain one module that subclasses 'Plugin'. This module
	provides meta-information about the plugin like settings
	and enable/disable hooks
*/
class InventoryUpdateGSC extends Plugin
{
	/**
		Desired settings. These are automatically exposed
		on the 'Plugins' area of the install page and
		written to ini.php
	*/
	public $plugin_settings = array(
		'InventoryUpdateURL1' => array(
			'label' => 'Primary Base URL',
			'description' => 'A base URL like http://example.com/possale.php. Query parameters will be appended to this URL.',
			'default' => '',
		),
		'InventoryUpdateURL2' => array(
			'label' => 'Backup Base URL',
			'description' => 'A base URL like http://example.com/possale.php. Query parameters will be appended to this URL. Used only when the primary update server fails to respond.',
			'default' => '',
		),
		'LogFileName' => array(
			'label' => 'Logfile name',
			'description' => 'Filename to log to. Default is transaction_reset.log. For security reasons, directory path components will be ignored, and logging will always be to this plugin\'s own directory.',
			'default' => 'transaction_reset.log',
		),
	);

	public $plugin_description = 'Updates George Street Co-op\'s inventory system at the close of each transaction.';

	public function plugin_transaction_reset()
	{
		logToFile('plugin_transaction_reset()');

		$username = CoreLocal::get('localUser');
		$password = CoreLocal::get('localPass');
		if (!$username || !$password) {
			logToFile("Database credentials are unset or unavailable. Giving up.");
			return;
		}

		$primary_url = CoreLocal::get('InventoryUpdateURL1');
		if (!$primary_url) {
			logToFile("Inventory Update Base URL isn't set. Please open the installer page and set this in the Plugins tab.");
		}

		$secondary_url = CoreLocal::get('InventoryUpdateURL2');
		if (!$secondary_url) {
			logToFile("Inventory Update Backup Base URL isn't set. If you want this set, open the installer page and set this in the Plugins tab.");
		}

		$translog_dsn = "mysql:dbname=core_translog;host=localhost;charset=utf8";
		try {
			$translog_db = new PDO($translog_dsn, $username, $password);
			$translog_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
			$password_asterisks = str_repeat('*', mb_strlen($password));
			logToFile("Lane core_translog connected as user '{$username}' with password '{$password_asterisks}'\n");
		}
		catch (PDOException $e) {
			logToFile('Lane core_translog connection failed: '.$e->getMessage());
			return;
		}

		$latest_sale_q = '
			SELECT IF(upc <= 99999, TRIM(LEADING "0" FROM upc), upc) upc, SUM(quantity) sold, description
			FROM localtranstoday
			WHERE
				trans_status != "X"
				AND emp_no != 9999
				AND trans_type = "I"
				AND upc != 0
				AND CONCAT_WS("-", register_no, emp_no, trans_no) = (
					SELECT CONCAT_WS("-", register_no, emp_no, trans_no)
					FROM localtranstoday
					ORDER BY datetime DESC LIMIT 1
				)
			GROUP BY upc';
		logQueryResults($translog_db, $latest_sale_q, $log_filename);
		$latest_sale = $translog_db->query($latest_sale_q);

		$base = 30;
		$url_query = "?base={$base}&add_check_digit=1";
		while ($row = $latest_sale->fetch(PDO::FETCH_ASSOC)) {
			$has_records = true;
			$upc = $row['upc'];
			$sold = $row['sold'];
			logToFile("{$upc} => ".base_convert($upc, 10, $base).": {$sold}"."");
			$url_query .= '&' . base_convert($upc, 10, $base)
					. ($sold == 1? '' : '=' . base_convert_float($sold, 10, $base, 2));
		}
		logToFile($url_query);

		if ($has_records) {

			if ($primary_url) {
				logToFile("\nSending inventory update request to primary update server:");
				$primary_result = file_get_contents($primary_url . $url_query);
				if (is_string($primary_result))
					$primary_result = preg_replace('~^~m', '  |  ', $primary_result);
				logToFile($primary_result);
			}
			else {
				logToFile("No primary update server was configured!");
				$primary_result = null;
			}

			if (!$primary_result) {
				if ($secondary_url) {
					logToFile("\nPrimary update server update failed. Trying now with secondary update server:");
					$secondary_result = file_get_contents($secondary_url . $url_query);
					if (is_string($secondary_result))
						$secondary_result = preg_replace('~^~m', '  |  ', $secondary_result);
					logToFile($secondary_result);
				}
				else {
					logToFile("\nPrimary update server update failed, and no secondary update server was configured - giving up now");
				}
			}

		}
		else {
			logToFile("No inventory update was needed, skipping update server communications");
		}

		logToFile(date('Y-m-d H:i:s')." plugin_transaction_reset() complete\n");
	} // public function plugin_transaction_reset()

} // class InventoryUpdateGSC extends Plugin


// base_convert() doesn't support signs or decimals, so we've got to assemble this!
// should be a drop-in replacement for base_convert()
function base_convert_float($number, $frombase, $tobase, $frac_length=5)
{
	if (preg_match('~^([+-])?([0-9a-z]+)(?:\.([0-9a-z]*))?$~', $number, $matches)) {
		$ret = ($matches[1] == '-'? '-' : '');
		$ret .= base_convert($matches[2], $frombase, $tobase);
		if (count($matches) > 3 && $frac_length > 0) {
			$frac_denom = $frombase ** mb_strlen($matches[3]);
			$new_denom = $tobase ** $frac_length;
			$frac_num = base_convert($matches[3], $frombase, 10);
			$new_num = rtrim(str_pad(base_convert(round($new_denom * $frac_num / $frac_denom), 10, $tobase), $frac_length, '0', STR_PAD_LEFT), '0');
			if ($new_num) $ret .= '.' . $new_num;
		}
		return $ret;
	}

	// fallback: do no worse than original base_convert()
	return base_convert($number, $frombase, $tobase);
}


function logToFile($text)
{
	static $log_filename;

	if (!$log_filename) {
		$log_filename = basename(CoreLocal::get('LogFileName'));
		if (!$log_filename || $log_filename === basename(__FILE__)) // protect from overwriting this plugin!
			$log_filename = 'transaction_reset.log';
		$log_filename = dirname(__FILE__).'/'.$log_filename;
		@unlink($log_filename);
		if (!error_log(date('Y-m-d H:i:s')."\n", 3, $log_filename)) {
			echo __FILE__.": Cannot log to designated logfile '{$log_filename}'".PHP_EOL;
			trigger_error(__FILE__.": Cannot log to designated logfile '{$log_filename}'", E_USER_ERROR);
		}
	}

	if (!is_string($text)) $text = var_export($text, true);

	error_log($text."\n", 3, $log_filename);
}


function logQueryResults($db, $sql, $filepath)
{
	$q = $db->query($sql);
	while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
		if (!$out) $out = join("\t", array_keys($row)) . "\n";
		$out .= join("\t", $row) . "\n";
	}
	logToFile($sql."\n\n".$out."\n");
}
