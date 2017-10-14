<?php
	$is_cron = (php_sapi_name() == 'cli');
	$lf = ($is_cron? "\n" : "<br>\n");
	$hr = ($is_cron? '' : "<br>\n");
	$line_length = ($is_cron? 75 : 250);

	ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_WARNING);
	ini_set('display_errors', 1);
	ini_set('log_errors', 0);
	ini_set('error_log', '/dev/null');

	set_time_limit(60);

	$OFFICE_SERVER_URL_BASE = null;
	$OFFICE_SERVER = null;
	$OFFICE_SERVER_USER = null;
	$OFFICE_SERVER_PW = null;
	$OFFICE_OP_DB = null;
	$coop_host = null;
	$coop_user = null;
	$coop_pw = null;
	$coop_member_db = null;
	$coop_product_db = null;

	if ($is_cron) {
		echo "Running as cron!";
		echo $lf.$hr.$lf;
		ob_start();
	}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Update Office Data</title>
	<style>
		* { font-family: Helvetica,Arial,sans; }
		h3 { margin: 0; }
		table { margin-bottom: 20px; }
		td { padding: 0; }
		input { margin: 0; }
	</style>
</head>
<body>

	<form method="post">
		<table>
			<tr>
				<td colspan="3"><h3>Source Databases: Co-op Members and Products</h3></td>
			</tr>
			<tr>
				<td colspan="2">Host</td>
				<td><?=installTextField('coop_host', $coop_host, 'georgestreetcoop.com')?></td>
			</tr>
			<tr>
				<td colspan="2">Username</td>
				<td><?=installTextField('coop_user', $coop_user, 'geor5702_backup')?></td>
			</tr>
			<tr>
				<td colspan="2">Password</td>
				<td><?=installTextField('coop_pw', $coop_pw, '', true, array('type'=>'password'))?></td>
			</tr>
			<tr>
				<td>Member Database</td>
				<td><input type="checkbox" name="xfer_members"></td>
				<td><?=installTextField('coop_member_db', $coop_member_db, 'geor5702_members')?></td>
			</tr>
			<tr>
				<td>Product Database</td>
				<td><input type="checkbox" name="xfer_products"></td>
				<td><?=installTextField('coop_product_db', $coop_product_db, 'geor5702_products')?></td>
			</tr>
		</table>
		<table>
			<tr>
				<td colspan="2"><h3>Destination Database: CORE-POS</h3></td>
			</tr>
			<tr>
				<td>Host</td>
				<td><?=installTextField('OFFICE_SERVER', $OFFICE_SERVER, '192.168.1.50')?></td>
			</tr>
			<tr>
				<td>Username</td>
				<td><?=installTextField('OFFICE_SERVER_USER', $OFFICE_SERVER_USER, 'office')?></td>
			</tr>
			<tr>
				<td>Password</td>
				<td><?=installTextField('OFFICE_SERVER_PW', $OFFICE_SERVER_PW, '', true, array('type'=>'password'))?></td>
			</tr>
			<tr>
				<td>Database</td>
				<td><?=installTextField('OFFICE_OP_DB', $OFFICE_OP_DB, 'office_opdata')?></td>
			</tr>
			<tr>
				<td>Office URL Base</td>
				<td><?=installTextField('OFFICE_SERVER_URL_BASE', $OFFICE_SERVER_URL_BASE, 'office')?></td>
			</tr>
		</table>
		<button type="submit">Update Now!</button>
		<br>
	</form>
	<br>

<?
	if ($is_cron) {
		ob_end_clean();
	}
	else {
		flush();
	}

	$allowed_params = array(
			'OFFICE_SERVER_URL_BASE' => null,
			'OFFICE_SERVER' => null,
			'OFFICE_SERVER_USER' => null,
			'OFFICE_SERVER_PW' => null,
			'OFFICE_OP_DB' => null,
			'coop_host' => null,
			'coop_user' => null,
			'coop_pw' => null,
			'coop_member_db' => null,
			'coop_product_db' => null,
			'xfer_members' => null,
			'xfer_products' => null,
		);
	if (count($_POST))
		$invoke_params = $_POST;
	elseif (in_array($_SERVER['PHP_SELF'], $_SERVER['argv'])) {
		$allowed_params['sync_lanes'] = null;
		$invoke_params = array_flip($_SERVER['argv']);
		unset($invoke_params[$_SERVER['PHP_SELF']]);
	}

	if (isset($invoke_params)) {
		$invoke_params = array_intersect_key($invoke_params, $allowed_params);
		extract($invoke_params);
		$office_server_sync_url_base = "//{$OFFICE_SERVER}/{$OFFICE_SERVER_URL_BASE}/sync/TableSyncPage.php";
		$asof_date = 'as of '.date('M j Y g:ia');

		echo "Connecting with `{$OFFICE_OP_DB}`...{$lf}";
		$office_dsn = "mysql:dbname={$OFFICE_OP_DB};host={$OFFICE_SERVER};charset=utf8";
		try {
			$office_db = new PDO($office_dsn, $OFFICE_SERVER_USER, $OFFICE_SERVER_PW);
			$office_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
		} catch (PDOException $e) {
			echo 'Office connection failed: ' . $e->getMessage() . $lf;
		}
		echo $hr.$lf;

		if ($xfer_members) {
			echo "Connecting with `{$coop_member_db}`...{$lf}";
			$coop_members_dsn = "mysql:dbname={$coop_member_db};host={$coop_host};charset=utf8";
			try {
				$coop_members_db = new PDO($coop_members_dsn, $coop_user, $coop_pw);
				$coop_members_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
			} catch (PDOException $e) {
				echo "Co-op connection ({$coop_members_dsn}) failed: " . $e->getMessage() . $lf;
			}

			$coop_members_q = $coop_members_db->query('SELECT * FROM MembersForIS4C');

			$office_custdata_q = $office_db->prepare('
					INSERT custdata
					SET
						CardNo = :card_no,
						personNum = 1,
						LastName = :last_name,
						FirstName = :first_name,
						CashBack = 20.00,
						Balance = 0.00,
						Discount = :discount,
						MemDiscountLimit = 0.00,
						ChargeLimit = 0.00,
						ChargeOk = 0,
						WriteChecks = IF(:discount, 1, 0),
						StoreCoupons = 1,
						Type = "PC",
						memType = CONCAT(IF(:is_senior, "1", ""), IF(:is_staff, "9", FIELD(:discount, "5", "8", "15", "25"))),
						staff = :is_staff,
						SSI = :is_senior,
						Purchases = 0.00,
						NumberOfChecks = 0,
						memCoupons = 0,
						blueLine = CONCAT_WS(" ", :card_no, :first_name, :last_name),
						Shown = 1,
						LastChange = :modified,
						id = :card_no
					ON DUPLICATE KEY UPDATE
						CardNo = :card_no,
						personNum = 1,
						LastName = :last_name,
						FirstName = :first_name,
						CashBack = 20.00,
					--	Balance = 0.00,
						Discount = :discount,
					--	MemDiscountLimit = 0.00,
					--	ChargeLimit = 0.00,
					--	ChargeOk = 0,
						WriteChecks = IF(:discount, 1, 0),
					--	StoreCoupons = 1,
						Type = "PC", 
						memType = CONCAT(IF(:is_senior, "1", ""), IF(:is_staff, "9", FIELD(:discount, "5", "8", "15", "25"))),
						staff = :is_staff,
						SSI = :is_senior,
					--	Purchases = 0.00,
					--	NumberOfChecks = 0,
					--	memCoupons = 0,
						blueLine = CONCAT_WS(" ", :card_no, :first_name, :last_name),
						Shown = 1,
						LastChange = :modified
				');

			$office_custdata_paramlist = array(
					':card_no' => 0,
					':discount' => 0,
					':is_staff' => 0,
					':is_senior' => 0,
					':last_name' => 0,
					':first_name' => 0,
					':modified' => 0,
				);
			$office_meminfo_q = $office_db->prepare('
					INSERT meminfo
					SET
						card_no = :card_no,
						last_name = :last_name,
						first_name = :first_name,
						street = :street,
						city = :city,
						state = :state,
						zip = :zip,
						phone = :phone,
						email_1 = :email_1,
						ads_OK = :ads_OK,
						modified = :modified
					ON DUPLICATE KEY UPDATE
						last_name = :last_name,
						first_name = :first_name,
						street = :street,
						city = :city,
						state = :state,
						zip = :zip,
						phone = :phone,
						email_1 = :email_1,
						ads_OK = :ads_OK,
						modified = :modified
				');
			$office_meminfo_paramlist = array(
					':card_no' => 0,
					':last_name' => 0,
					':first_name' => 0,
					':street' => 0,
					':city' => 0,
					':state' => 0,
					':zip' => 0,
					':phone' => 0,
					':email_1' => 0,
					':ads_OK' => 0,
					':modified' => 0,
				);
			$office_memdates_q = $office_db->prepare('
					INSERT IGNORE memDates
					SET
						card_no = :card_no,
						start_date = NULL,
						end_date = NULL
				');
			$office_memdates_paramlist = array(
					':card_no' => 0,
				);

			flush();
			while ($coop_member = $coop_members_q->fetch(PDO::FETCH_ASSOC)) {
				set_time_limit(60);

				$member_details = $office_custdata_params = $office_meminfo_params = $office_memdates_params = array();
				foreach ($coop_member as $column => $value) {
					$member_details[':'.$column] = $value;
				}

				// Make member name safe for current CORE-POS charset limitations
				$member_details[':last_name'] = textASCII($member_details[':last_name']);
				$member_details[':first_name'] = textASCII($member_details[':first_name']);

				$office_custdata_params = array_intersect_key($member_details, $office_custdata_paramlist);
				$office_meminfo_params = array_intersect_key($member_details, $office_meminfo_paramlist);
				$office_memdates_params = array_intersect_key($member_details, $office_memdates_paramlist);

				if (!($r = $office_custdata_q->execute($office_custdata_params)))
					reportInsertError($office_custdata_q, $office_custdata_params);
				if (!($s = $office_meminfo_q->execute($office_meminfo_params)))
					reportInsertError($office_meminfo_q, $office_meminfo_params);
				if (!($t = $office_memdates_q->execute($office_memdates_params)))
					reportInsertError($office_memdates_q, $office_memdates_params);

				if ($r && $s && $t) {
					echo '.';
					if (++$i % $line_length === 0) {
						echo $lf;
						flush();
					}
				}
				elseif ((++$e >= 5) && ($e > $i * 5))
					die;
			}

			// Add non-member POS lookups
			$office_nonmembers = array(
					array(':card_no' => 999, ':discount' => 0, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Non-member', ':first_name' => '', ':modified' => 0),
					array(':card_no' => 62, ':discount' => 5, ':is_staff' => 0, ':is_senior' => 1, ':last_name' => 'Senior Non-member', ':first_name' => '', ':modified' => 0),
					array(':card_no' => 555, ':discount' => 5, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'New (or newly renewed) member', ':first_name' => '', ':modified' => 0),
					array(':card_no' => 888, ':discount' => 5, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Member of another co-op', ':first_name' => '', ':modified' => 0),
					array(':card_no' => 91111, ':discount' => 0, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => $asof_date, ':first_name' => '', ':modified' => 0),
				);
			foreach ($office_nonmembers as $office_nonmember) {
				if (!($r = $office_custdata_q->execute($office_nonmember)))
					reportInsertError($office_custdata_q, $office_nonmember);
				if ($r) {
					echo ',';
					if (++$i % $line_length === 0) {
						echo $lf;
						flush();
					}
				}
			}

			$member_sync_urls = array(
					'custdata' => 'Synchronize Members to Lanes',
					'memberCards' => 'Synchronize Member Cards to Lanes',
					'memtype' => 'Synchronize Member Types to Lanes',
				);
			foreach ($member_sync_urls as $tablename => $label) {
				$url = "{$office_server_sync_url_base}?tablename={$tablename}";
				if ($sync_lanes) {
					$data = file_get_contents('http:' . $url);
					$checkbox = strlen($data)? ' <b style="color:green">√</b>' : '';
					if ($is_cron) {
						echo $lf . (strlen($data)? "Synced table `{$tablename}`" : "Table `{$tablename}` sync failed!");
					}
				}
				elseif (!$is_cron) {
?>
				<br>
				<a href="<?=$url?>" target="<?=$tablename?>"><?=$label?></a><?=$synced?>
<?				}
			}
			echo $lf.$hr.$lf;
			flush();
		}

		if ($xfer_products) {
			echo "Connecting with `{$coop_product_db}`...{$lf}";
			$coop_products_dsn = "mysql:dbname={$coop_product_db};host={$coop_host};charset=utf8";
			try {
				$coop_products_db = new PDO($coop_products_dsn, $coop_user, $coop_pw);
				$coop_products_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
			} catch (PDOException $e) {
				echo "Co-op connection ({$coop_products_dsn}) failed: " . $e->getMessage() . $lf;
			}

			$coop_products_q = $coop_products_db->query('SELECT * FROM ProductsForIS4C');

			$office_db->exec('UPDATE products SET inUse = 0 WHERE upc < 100000');
			$office_products_q = $office_db->prepare('
					INSERT products
					SET
						upc = :upc,
						description = :description,
						brand = :brand,
						normal_price = :normal_price,
						department = :department,
						tax = :tax,
						foodstamp = :foodstamp,
						scale = :scale,
						discount = 1,
						wicable = :wicable,
						qttyEnforced = :qttyEnforced,
						cost = :cost,
						inUse = :inUse,
						deposit = :deposit,
						default_vendor_id = :default_vendor_id,
						id = :id
					ON DUPLICATE KEY UPDATE
						upc = :upc,
						description = :description,
						brand = :brand,
						normal_price = :normal_price,
						department = :department,
						tax = :tax,
						foodstamp = :foodstamp,
						scale = :scale,
	--					discount = 1,
	--					wicable = :wicable,
						qttyEnforced = :qttyEnforced,
						cost = :cost,
						inUse = :inUse,
						deposit = :deposit,
						default_vendor_id = :default_vendor_id,
						id = :id
				');

			flush();
			while ($coop_product = $coop_products_q->fetch(PDO::FETCH_ASSOC)) {
				set_time_limit(60);

				$coop_products_params = array();
				foreach ($coop_product as $column => $value) {
					$coop_products_params[':'.$column] = $value;
				}

				// Make brand and description safe for current CORE-POS charset limitations
				$coop_products_params[':brand'] = textASCII($coop_products_params[':brand']);
				$coop_products_params[':description'] = textASCII($coop_products_params[':description']);

				if (!($r = $office_products_q->execute($coop_products_params)))
					reportInsertError($office_products_q, $coop_products_params);

				if ($r) {
					echo '.';
					if (++$i % $line_length === 0) {
						echo $lf;
						flush();
					}
				}
				elseif ((++$e >= 5) && ($e > $i * 5))
					die;
			}

			// Add non-product POS lookups
			$office_nonproducts = array(
					array(':upc' => '0000000091111', ':description' => $asof_date, ':brand' => '', ':normal_price' => 0, ':department' => 0, ':tax' => 0, ':foodstamp' => 0, ':scale' => 0, ':wicable' => 0, ':qttyEnforced' => 0, ':cost' => 0, ':inUse' => 1, ':deposit' => NULL, ':default_vendor_id' => NULL, ':id' => 91111),
				);
			foreach ($office_nonproducts as $office_nonproduct) {
				if (!($r = $office_products_q->execute($office_nonproduct)))
					reportInsertError($office_products_q, $office_nonproduct);
				if ($r) {
					echo ',';
					if (++$i % $line_length === 0) {
						echo $lf;
						flush();
					}
				}
			}

			$product_sync_urls = array(
					'products' => 'Synchronize Products to Lanes',
				);
			foreach ($product_sync_urls as $tablename => $label) {
				$url = "{$office_server_sync_url_base}?tablename={$tablename}";
				if ($sync_lanes) {
					$data = file_get_contents('http:' . $url);
					$checkbox = strlen($data)? ' <b style="color:green">√</b>' : '';
					if ($is_cron) {
						echo $lf . (strlen($data)? "Synced table `{$tablename}`" : "Table `{$tablename}` sync failed!");
					}
				}
				elseif (!$is_cron) {
?>
				<br>
				<a href="<?=$url?>" target="<?=$tablename?>"><?=$label?></a><?=$synced?>
<?				}
			}
			echo $lf.$hr.$lf;
			flush();
		}
	}

	for ($i = 1; $i <= 3; $i++) {
		$lane_ip = "192.168.1.5{$i}";
		$lane_ping = shell_exec("ping -q -t2 -c3 {$lane_ip}");
		$lane_loss = preg_match('~ ([0-9.]+)% packet loss~', $lane_ping, $matches)? floatval($matches[1]) : 100;
		$lane_up = $lane_loss < 50;

		$lane_status = $lane_up? 'UP' : 'DOWN';
		if ($lane_up && strlen($OFFICE_SERVER_PW)) {
			$lane_dsn = "mysql:dbname=core_opdata;host={$lane_ip};charset=utf8";
			try {
				$lane_db = new PDO($lane_dsn, $OFFICE_SERVER_USER, $OFFICE_SERVER_PW);
				$lane_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
				$lane_login = $lane_db->query('SELECT Cashier, LoggedIn FROM core_opdata.globalvalues')->fetch(PDO::FETCH_ASSOC);
				if ($lane_login['LoggedIn']) {
					$lane_status = trim($lane_login['Cashier'], ' .');
					$lane_transcount = $lane_db->query('SELECT COUNT(*) FROM core_translog.localtemptrans');
					if (is_object($lane_transcount)) {
						$lane_transcount = $lane_transcount->fetch(PDO::FETCH_NUM);
						if ($lane_transcount[0])
							$lane_status .= ' in transaction';
						else
							$lane_status .= ' logged in';
					}
					else {
						$lane_status .= ' (couldn’t determine transaction status)';
					}
				}
				else {
					$lane_status = 'Logged out';
				}
				$lane_db = null;
			} catch (PDOException $e) {
				echo "Co-op connection ({$lane_db}) failed: " . $e->getMessage() . $lf;
				$lane_status = '(ERROR)';
			}
		}
		if ($is_cron)
			$lane_status_tag = '';
		elseif ($lane_up)
			$lane_status_tag = '<b style="color:green">';
		else
			$lane_status_tag = '<b style="color:red">';
		$lane_status_tag_end = (strlen($lane_status_tag)? '</b>' : '');
		echo "Lane {$i} ({$lane_ip}): {$lane_status_tag}{$lane_status}{$lane_status_tag_end}{$lf}";
		flush();
	}
	if ($is_cron) {
		@file_put_contents('.update_office.done', date('Y-m-d H:i:s'));
	}
	else {
?>
</body>
<?
	}


function installTextField($name, &$current_val, $default='', $bool=false, $html_vals=array())
{
	static $ini;
	if (!isset($ini)) {
		$ini_path = '/etc/gsc_pos.ini';
		if (file_exists($ini_path)) {
			$ini = parse_ini_file('/etc/gsc_pos.ini');
		}
		else {
			$ini = array();
		}
	}

	if (is_null($current_val)) {
		$current_val = @$ini[$name] ?: $default;
	}

	$html_vals['type'] = @$html_vals['type']?: 'text';
	$html_vals['name'] = @$html_vals['name']?: $name;
	$html_vals['value'] = @$html_vals['value']?: @$_POST[$name]?: $current_val;

	return '<input type="'.$html_vals['type'].'" name="'.$html_vals['name'].'" value="'.$html_vals['value'].'" />';
}

function reportInsertError($query, $params)
{
	echo '<code><b><pre>'.$query->queryString.'</pre></b></code>'
			. "<br>\n"
			. var_export($params, 1)
			. "<br>\n"
			. var_export($query->errorInfo(), 1)
			. "<br>\n"
		;
}

function textASCII($text_utf8)
{
	static $map_alphabetics = array(
			'a' => 'áâåäā',
			'c' => 'ç',
			'e' => 'éêë',
			'i' => 'íîï',
			'n' => 'ñ',
			'o' => 'óôøö',
			'u' => 'úûü',
			"'" => '‘’′', // these may disappear anyway due to CORE-POS filtering apostrophes
			'"' => '“”″',
		);
	static $map;

	if (empty($map)) {
		foreach ($map_alphabetics as $to => $froms) {
			foreach (preg_split('//u', $froms, null, PREG_SPLIT_NO_EMPTY) as $from) {
				$map[$from] = $to;
				$map[mb_convert_case($from, MB_CASE_UPPER, "UTF-8")] = mb_convert_case($to, MB_CASE_UPPER, "UTF-8");
			}
		}
	}

	$text_ascii = iconv('UTF-8', 'ASCII//TRANSLIT', strtr($text_utf8, $map));
	if ($text_ascii === $text_utf8)
		return $text_ascii;

// 	echo "<br>\n<span style=\"color:red\">“".($text_utf8)."”</span> → ";
	if ($ret === false) {
		// Should be extremely rare to reach this point — possibly due to collation issues.
		// Split the string, process each character we can recognize.
		$text_ascii = '';
		$chars_utf8 = str_split($text_utf8);
		foreach ($chars_utf8 as $char_utf8) {
			$char_ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $char_utf8);
			if ($char_ascii !== false)
				$text_ascii .= $char_ascii;
		}
	}
// 	echo "<span style=\"color:green\">“".($text_ascii)."”</span><br>\n";
	return $text_ascii;
}
