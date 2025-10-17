<?php
	$is_cron = (php_sapi_name() == 'cli');
	$lf = ($is_cron? "\n" : "<br>\n");
	$hr = ($is_cron? "\n\n=====\n\n" : "\n<hr>\n");
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
	$OFFICE_OP_DBNAME = null;
	$coop_host = null;
	$coop_user = null;
	$coop_pw = null;
	$coop_member_dbname = null;
	$coop_products_dbname = null;

	if ($is_cron) {
		echo "Running as command line or cron";
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
				<td colspan="2"><h3>Office Database: CORE-POS</h3></td>
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
				<td><?=installTextField('OFFICE_OP_DBNAME', $OFFICE_OP_DBNAME, 'office_opdata')?></td>
			</tr>
			<tr>
				<td>Office URL Base</td>
				<td><?=installTextField('OFFICE_SERVER_URL_BASE', $OFFICE_SERVER_URL_BASE, 'office')?></td>
			</tr>
		</table>

		<table>
			<tr>
				<td colspan="3"><h3>Web Databases: Co-op Members and Products</h3></td>
			</tr>
			<tr>
				<td>Host</td>
				<td><?=installTextField('coop_host', $coop_host, 'georgestreetcoop.com')?></td>
			</tr>
			<tr>
				<td>Username</td>
				<td><?=installTextField('coop_user', $coop_user, 'geor5702_COREPOS')?></td>
			</tr>
			<tr>
				<td>Password</td>
				<td><?=installTextField('coop_pw', $coop_pw, '', true, array('type'=>'password'))?></td>
			</tr>
			<tr>
				<td>Member Database</td>
				<td><?=installTextField('coop_member_dbname', $coop_member_dbname, 'geor5702_members')?></td>
				<td><input type="checkbox" name="xfer_members"> Members</td>
			</tr>
			<tr>
				<td>Product Database</td>
				<td><?=installTextField('coop_product_dbname', $coop_products_dbname, 'geor5702_products')?></td>
				<td><input type="checkbox" name="xfer_products"> Products</td>
				<td><input type="checkbox" name="xfer_sales"> Sales</td>
			</tr>
		</table>

		<table>
			<tr>
				<td colspan="2"><h3>Date Range (for Sales Stats)</h3></td>
			</tr>
			<tr>
				<td>Start Date</td>
				<td><?=installTextField('start_date', $start_date, date('Y-m-d', strtotime($is_cron? '-2 day' : '-21 day')), array('type'=>'date'))?><!-- <small><i>(this date will be included)</i></small>--></td>
			</tr>
			<tr>
				<td>End Date</td>
				<td><?=installTextField('end_date', $end_date, date('Y-m-d'), array('type'=>'date'))?><!-- <small><i>(this date will <u>not</u> be included)</i></small>--></td>
			</tr>
		</table>

		<button type="submit">Update Now!</button>
		<br>
	</form>
	<br>

<?php
	if ($is_cron) {
		ob_end_clean();
	}
	else {
		flush();
	}

	$allowed_params = [
		'OFFICE_SERVER_URL_BASE' => null,
		'OFFICE_SERVER' => null,
		'OFFICE_SERVER_USER' => null,
		'OFFICE_SERVER_PW' => null,
		'OFFICE_OP_DBNAME' => null,
		'coop_host' => null,
		'coop_user' => null,
		'coop_pw' => null,
		'coop_member_dbname' => null,
		'coop_product_dbname' => null,
		'xfer_members' => null,
		'xfer_products' => null,
		'xfer_sales' => null,
		'start_date' => null,
		'end_date' => null,
	];
	$invoke_params = [];
	foreach ($allowed_params as $param => $__) {
		$value = getenv($param);
		if ($value !== false)
			$invoke_params[$param] = $value;
	}

	if ($_POST) {
		$invoke_params = array_merge($invoke_params, $_POST);
	}
	elseif (isset($_SERVER['argv'])) {
		foreach ($argv as $idx => $arg) {
			if ($idx == 0 && $arg == $_SERVER['PHP_SELF']) continue;

			if (in_array($arg, ['xfer_members', 'xfer_products', 'xfer_sales']))
				$arg_parsed[$arg] = true; // boolean arg; mere presence = true
			else
				parse_str($arg, $arg_parsed);
			$invoke_params = array_merge($invoke_params, $arg_parsed);
		}
	} // if ($_POST) elseif (isset($_SERVER['argv']))

	$invoke_params = array_intersect_key($invoke_params, $allowed_params);

	if ($invoke_params) {
		extract($invoke_params);


		// *** SET UP TRANSFER(S) ***
		if ($xfer_members || $xfer_products || $xfer_sales) {
			echo $hr;

			$office_server_sync_url_base = "//{$OFFICE_SERVER}/{$OFFICE_SERVER_URL_BASE}/sync/TableSyncPage.php";
			$time = time();
			$asof_date = 'as of '.date('M j Y g:ia', $time);
			$asof_hash = date('Y-m-d_His', $time);

			echo "Connecting with {$OFFICE_SERVER} `{$OFFICE_OP_DBNAME}`";
			$office_dsn = "mysql:dbname={$OFFICE_OP_DBNAME};host={$OFFICE_SERVER};charset=utf8";
			try {
				$office_db = new PDO($office_dsn, $OFFICE_SERVER_USER, $OFFICE_SERVER_PW, array(PDO::ATTR_TIMEOUT => 10));
				$office_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
				echo ' — success!';
			} catch (PDOException $e) {
				echo ' — FAILED: ' . $e->getMessage();
			}
			flush();
		} // if ($xfer_members || $xfer_products || $xfer_sales)


		// *** MEMBER DATA TRANSFER ***
		if ($xfer_members) {
			echo $hr;
			echo "Sending member data from {$coop_host} to `{$OFFICE_OP_DBNAME}`:{$lf}";
			flush();

			$coop_members_hash_pass = substr(sha1(date('Ymd').'members'.$coop_pw), -12);
			$coop_members_json = file_get_contents('https://'.$coop_host.'/members/_pos_members.php?hash='.$coop_members_hash_pass);
			if ($coop_members_json) {
				$coop_members = json_decode($coop_members_json, $associative = true);
				$coop_member_keys = $coop_members['keys'];
				$coop_members = $coop_members['data'];

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
				foreach ($coop_members as $coop_member) {
					$coop_member = array_combine($coop_member_keys, $coop_member);
					set_time_limit(60);

					$member_details = $office_custdata_params = $office_meminfo_params = $office_memdates_params = array();
					foreach ($coop_member as $column => $value) {
						$member_details[':'.$column] = $value;
					}

					// Make member name safe for current CORE-POS charset limitations
					$member_details[':last_name'] = textASCII($member_details[':last_name']);
					$member_details[':first_name'] = textASCII($member_details[':first_name']);

					// Prepend SEN and EXP prefixes for Senior and Expired status
					if ($coop_member['is_senior']) $member_details[':first_name'] = 'SEN '.$member_details[':first_name'];
					if ($coop_member['is_expired']) $member_details[':first_name'] = 'EXP '.$member_details[':first_name'];

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
				} // foreach ($coop_members as $coop_member)

				// Add non-member POS lookups
				$office_nonmembers = array(
						array(':card_no' => 999, ':discount' => 0, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Non-member', ':first_name' => '', ':modified' => '2016-02-02'),
						array(':card_no' => 62, ':discount' => 5, ':is_staff' => 0, ':is_senior' => 1, ':last_name' => 'Senior Non-member', ':first_name' => '', ':modified' => '2016-02-02'),
						array(':card_no' => 33, ':discount' => 67, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Too Good To Go', ':first_name' => '', ':modified' => '2016-02-02'),
						array(':card_no' => 111, ':discount' => 5, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => '5% for a Day discount', ':first_name' => '', ':modified' => '2016-02-02'),
						array(':card_no' => 555, ':discount' => 5, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'New (or newly renewed) member', ':first_name' => '', ':modified' => '2016-02-02'),
						array(':card_no' => 888, ':discount' => 5, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Member of another co-op', ':first_name' => '', ':modified' => '2016-02-02'),
						array(':card_no' => 1766, ':discount' => 15, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Rutgers strike solidarity', ':first_name' => '', ':modified' => '2016-02-02'),
						array(':card_no' => 91111, ':discount' => 0, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => $asof_date, ':first_name' => '', ':modified' => '2016-02-02'),
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
				} // foreach ($office_nonmembers as $office_nonmember)

				$member_sync_urls = array(
						'custdata' => 'Synchronize Members to Lanes',
						'memberCards' => 'Synchronize Member Cards to Lanes',
						'memtype' => 'Synchronize Member Types to Lanes',
					);
				foreach ($member_sync_urls as $tablename => $label) {
					$url = "{$office_server_sync_url_base}?tablename={$tablename}#{$asof_hash}";
					if ($sync_lanes) {
						$data = file_get_contents('http:' . $url);
						$checkbox = strlen($data)? ' <b style="color:green">√</b>' : '';
						if ($is_cron) {
							echo $lf . (strlen($data)? "Synced table `{$tablename}`" : "Table `{$tablename}` sync failed!");
						}
					}
					elseif (!$is_cron) {
						echo "{$lf}<a href=\"{$url}\" target=\"{$tablename}\">{$label}</a>{$synced}";
					}
				} // foreach ($member_sync_urls as $tablename => $label)
			}
			else {
				echo "<b style=\"color:red\">Failed to fetch {$coop_host} member data!</b>{$lf}";				
			} // if ($coop_members_json)
			flush();
		} // if ($xfer_members)


		// *** PRODUCT DATA TRANSFER ***
		if ($xfer_products) {
			echo $hr;
			echo "Sending product data from {$coop_host} to `{$OFFICE_OP_DBNAME}`:{$lf}";
			flush();

			$coop_products_hash_pass = substr(sha1(date('Ymd').'products'.$coop_pw), -12);
			$coop_products_json = file_get_contents('https://'.$coop_host.'/products/_pos_products.php?hash='.$coop_products_hash_pass);
			if ($coop_products_json) {
				$coop_products = json_decode($coop_products_json, $associative = true);
				$coop_product_keys = $coop_products['keys'];
				$coop_products = $coop_products['data'];

				$office_db->exec('UPDATE products SET inUse = 0 WHERE upc < 100000');

				flush();
				foreach ($coop_products as $coop_product) {
					$coop_product = array_combine($coop_product_keys, $coop_product);
					set_time_limit(60);

					$coop_products_copy = $coop_product;
					$coop_products_copy['brand'] = textASCII($coop_product['brand']);
					$coop_products_copy['description'] = textASCII($coop_product['description']);
					$coop_products_copy['discount'] = 1;

					$r = pdoBulkInsertOp('REPLACE', $office_db, 'products',
							'upc, description, brand, normal_price, department, tax, foodstamp, scale, wicable, qttyEnforced, cost, inUse, deposit, default_vendor_id, id, discount',
							$coop_products_copy
						);
					if ($r !== false)
						echo '.';
				} // foreach ($coop_products as $coop_product)

				$office_nonproduct = [
						'upc' => '0000000091111',
						'description' => $asof_date,
						'brand' => '',
						'normal_price' => 0,
						'department' => 0,
						'tax' => 0,
						'foodstamp' => 0,
						'scale' => 0,
						'wicable' => 0,
						'qttyEnforced' => 0,
						'cost' => 0,
						'inUse' => 1,
						'deposit' => NULL,
						'default_vendor_id' => NULL,
						'id' => 91111,
						'discount' => 0,
					];
				$r = pdoBulkInsertOp('REPLACE', $office_db, 'products',
						'upc, description, brand, normal_price, department, tax, foodstamp, scale, wicable, qttyEnforced, cost, inUse, deposit, default_vendor_id, id, discount',
						$office_nonproduct,
						true
					);
				if ($r !== false)
					echo ';';
				echo $lf;
				flush();

				$product_sync_urls = array(
						'products' => 'Synchronize Products to Lanes',
					);
				foreach ($product_sync_urls as $tablename => $label) {
					$url = "{$office_server_sync_url_base}?tablename={$tablename}#{$asof_hash}";
					if ($sync_lanes) {
						$data = file_get_contents('http:' . $url);
						$checkbox = strlen($data)? ' <b style="color:green">√</b>' : '';
						if ($is_cron) {
							echo $lf . (strlen($data)? "Synced table `{$tablename}`" : "Table `{$tablename}` sync failed!");
						}
					}
					elseif (!$is_cron) {
						echo "{$lf}<a href=\"{$url}\" target=\"{$tablename}\">{$label}</a>{$synced}";
					}
				}
			}
			else {
				echo "<b style=\"color:red\">Failed to fetch {$coop_host} product data!</b>{$lf}";				
			} // if ($coop_products_json)
			flush();
		} // if ($xfer_products)


		// *** SALES DATA TRANSFER ***
		if ($xfer_sales) {
			echo $hr;
			echo "Fetching sales data from `{$OFFICE_OP_DBNAME}`:";
			flush();

			$sales_fetch_sqls = [

					'SELECT
						DATE_FORMAT(datetime, "%Y-%m-%d") SaleDate,
						DATE_FORMAT(datetime, "%Y-%m-%d %a") SaleDateNice,
						IF(upc REGEXP "^-?[0-9.]+DP+[0-9]+$",
								CONCAT("9999999999", department), -- open rings
								upc -- regular items
						) UPC,
						department Department,
						SUM(quantity) ItemCount,
						SUM(total) GrossPrice,
						SUM(total * IF(discountable = 1, (percentDiscount - IF(memtype >= 10, 5, 0)) * 0.01, 0)) MemberDiscount,
						SUM(total * IF(memtype >= 10 AND discountable = 1, 0.05, 0)) SeniorDiscount
					FROM office_trans_archive.bigArchive
					WHERE register_no != 99
						AND emp_no != 9999
						AND trans_status NOT IN ("D", "X", "Z")
						AND (( -- regular items
							upc REGEXP "^[0-9]+$"
							AND upc > 0
							AND department > 0
						) OR ( -- open rings; UPC will be made up of 9999999999 prepended to dept ID
							upc REGEXP "^-?[0-9.]+DP+[0-9]+$"
						))
						AND datetime
							BETWEEN :start_date
							AND (:end_date + INTERVAL 1 DAY) -- expand endpoint to end-of-day
					GROUP BY
						DATE_FORMAT(datetime, "%Y-%m-%d"),
						IF(upc REGEXP "^-?[0-9.]+DP+[0-9]+$",
								CONCAT("9999999999", department), -- open rings
								upc -- regular items
						)',

					'SELECT
						DATE_FORMAT(datetime, "%Y-%m-%d") SaleDate,
						DATE_FORMAT(datetime, "%Y-%m-%d %a") SaleDateNice,
						IF(upc REGEXP "^-?[0-9.]+DP+[0-9]+$",
								CONCAT("9999999999", department),
								upc
						) UPC,
						department Department,
						SUM(quantity) ItemCount,
						SUM(total) GrossPrice,
						SUM(total * IF(discountable = 1, (percentDiscount - IF(memtype >= 10, 5, 0)) * 0.01, 0)) MemberDiscount,
						SUM(total * IF(memtype >= 10 AND discountable = 1, 0.05, 0)) SeniorDiscount
					FROM office_trans.dtransactions
					WHERE register_no != 99
						AND emp_no != 9999
						AND trans_status NOT IN ("D", "X", "Z")
						AND (( -- regular items
							upc REGEXP "^[0-9]+$"
							AND upc > 0
							AND department > 0
						) OR ( -- open rings; UPC will be made up of 9999999999 prepended to dept ID
							upc REGEXP "^-?[0-9.]+DP+[0-9]+$"
						))
						AND datetime
							BETWEEN :start_date
							AND (:end_date + INTERVAL 1 DAY) -- expand endpoint to end-of-day
					GROUP BY
						DATE_FORMAT(datetime, "%Y-%m-%d"),
						IF(upc REGEXP "^-?[0-9.]+DP+[0-9]+$",
								CONCAT("9999999999", department),
								upc
						)',

				]; // $sales_fetch_sqls

			$dated_sales_rows = [];
			$date_records = $date_gross = $date_net = $date_reported_gross = $date_reported_net = [];

			foreach ($sales_fetch_sqls as $sales_fetch_sql) {
				$sales_fetch_q = $office_db->prepare($sales_fetch_sql);
				$date_range_p = [
					':start_date' => $start_date,
					':end_date' => $end_date,
				];
				$r = $sales_fetch_q->execute($date_range_p);

				if (!$r) {
					echo "{$lf}— error querying CORE-POS: " . $sales_fetch_q->errorInfo()[2] . $lf;
				}
				else {
					// do bindings
					$sales_fetch_q->bindColumn('SaleDate', $sale_date);
					$sales_fetch_q->bindColumn('SaleDateNice', $sale_date_nice);
					$sales_fetch_q->bindColumn('UPC', $upc);
					$sales_fetch_q->bindColumn('Department', $department);
					$sales_fetch_q->bindColumn('ItemCount', $item_count);
					$sales_fetch_q->bindColumn('GrossPrice', $gross_price);
					$sales_fetch_q->bindColumn('MemberDiscount', $member_discount);
					$sales_fetch_q->bindColumn('SeniorDiscount', $senior_discount);

					while ($f = $sales_fetch_q->fetch(PDO::FETCH_BOUND)) {

						// time to start a new day's records?
						if ($sale_date !== $last_sale_date) {
							echo "{$lf}Fetching {$sale_date_nice}";
							flush();
							$last_sale_date = $sale_date;
							$date_records[$sale_date] = 0;
						}

						if ($date_records[$sale_date]++ % 5 === 0) {
							echo '.';
							flush();
						}
						$total_records++;

						$upc_corrected = $upc . getCheckDigit($upc);
						$upcs_changed += ($upc_corrected === $upc? 0 : 1);

						$date_gross[$sale_date] += $gross_price;
						$total_gross += $gross_price;
						$date_net[$sale_date] += $gross_price - $member_discount - $senior_discount;
						$total_net += $gross_price - $member_discount - $senior_discount;

						// “reported” totals are only for certain departments
						switch ($department) {
							case 101:
							case 102:
							case 103:
							case 105:
							case 106:
							case 108:
							case 112:
							case 113:
							case 114:
								$date_reported_gross[$sale_date] += $gross_price;
								$total_reported_gross += $gross_price;
								$date_reported_net[$sale_date] += $gross_price - $member_discount - $senior_discount;
								$total_reported_net += $gross_price - $member_discount - $senior_discount;
						} // switch ($department)

						// formatting tweaks applied here save about 9% on transmitted data size
						$sales_row = [
							'CORE-POS',
							$upc_corrected <= 99999999? (int)$upc_corrected : str_pad(ltrim($upc_corrected, 0), 12, 0, STR_PAD_LEFT),
							$sale_date,
							(int)$department,
							ctype_digit($item_count)? (int)$item_count : $item_count,
							$gross_price,
							$member_discount == 0? 0 : $member_discount,
							$senior_discount == 0? 0 : $senior_discount,
						];
						// echo "<pre style='background-color:#ffd;font:8px Courier'>".join(', ', $sales_row)."</pre>";
						$dated_sales_rows[$sale_date][] = $sales_row;

					} // while ($f = $sales_fetch_q->fetch(PDO::FETCH_BOUND))
				} // else if (!$r)
			} // foreach ($sales_fetch_sqls as $sales_fetch_sql)

// 			echo "<pre style='background-color:#fdd;font:8px Courier'>".htmlspecialchars(var_export($dated_sales_rows, true))."</pre>";

			// show totals for reconciliation purposes
			echo "{$lf}{$lf}Daily totals:";
			foreach ($dated_sales_rows as $sale_date => $rows_for_date) {
				$date_gross[$sale_date] = '$'.number_format($date_gross[$sale_date], 2);
				$date_net[$sale_date] = '$'.number_format($date_net[$sale_date], 2);
				$date_reported_gross[$sale_date] = '$'.number_format($date_reported_gross[$sale_date], 2);
				$date_reported_net[$sale_date] = '$'.number_format($date_reported_net[$sale_date], 2);
				echo "{$lf}{$sale_date}: {$date_records[$sale_date]} records; {$date_gross[$sale_date]} gross, {$date_net[$sale_date]} net, {$date_reported_gross[$sale_date]} reported gross, {$date_reported_net[$sale_date]} reported net";
			}
			$total_gross = '$'.number_format($total_gross, 2);
			$total_net = '$'.number_format($total_net, 2);
			$total_reported_gross = '$'.number_format($total_reported_gross, 2);
			$total_reported_net = '$'.number_format($total_reported_net, 2);
			echo "{$lf}Total: {$total_records} records; {$total_gross} gross, {$total_net} net, {$total_reported_gross} reported gross, {$total_reported_net} reported net{$lf}";

			echo "{$lf}Saving dated sales data to {$coop_host}:";
			flush();

			$coop_sales_hash_pass = substr(sha1(date('Ymd').'sales'.$coop_pw), -12);
			$sales_headers = [
				'Source',
				'UPC',
				'SaleDate',
				'Department',
				'ItemCount',
				'GrossPrice',
				'MemberDiscount',
				'SeniorDiscount',
			];
			foreach ($dated_sales_rows as $sale_date => $rows_for_date) {
				echo "{$lf}Saving {$sale_date}";

				$postdata = [
					'hash' => $coop_sales_hash_pass,
					'replace' => $sale_date,
					'header' => $sales_headers,
					'data' => $rows_for_date,
				];
// 				echo "<pre style='background-color:#fdd;font:8px Courier'>".htmlspecialchars(var_export($postdata, true))."</pre>";
				$result = httpPost("https://{$coop_host}/products/_pos_sales.php", $postdata, $json = true);
// 				echo "<pre style='background-color:#fdd;font:8px Courier'>".htmlspecialchars($result)."</pre>";

				if (strpos($result, 'SUCCESS') === false)
					echo " — error inserting data: " . htmlspecialchars($result);
				else
					echo ' — success!';
				flush();
			} // foreach ($dated_sales_rows as $sale_date => $rows_for_date)
			flush();
		} // if ($xfer_sales)


		// *** LANE STATUSES ***
		if ($xfer_members || $xfer_products) {
			echo $hr;

			for ($i = 1; $i <= 3; $i++) { // iterate lanes
				$lane_ip = "192.168.1.5{$i}";
				$lane_ping = shell_exec("ping -c3 -i.2 -t2 -q {$lane_ip}"); // 3 pings .2sec apart, TTL=2, quiet
				$lane_loss = preg_match('~ ([0-9.]+)% packet loss~', $lane_ping, $matches)? floatval($matches[1]) : 100;
				$lane_up = $lane_loss < 50;

				$lane_status = $lane_up? 'UP' : 'DOWN';
				if ($lane_up && strlen($OFFICE_SERVER_PW)) {
					$lane_dsn = "mysql:dbname=core_opdata;host={$lane_ip};charset=utf8";
					try {
						$lane_db = new PDO($lane_dsn, $OFFICE_SERVER_USER, $OFFICE_SERVER_PW, array(PDO::ATTR_TIMEOUT => 1));
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
						$lane_status = 'ERROR: '.$e->getMessage();
						$lane_up = false;
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
			} // for ($i = 1; $i <= 3; $i++) // iterate lanes
		} // if ($xfer_members || $xfer_products)
	} // if ($invoke_params)

	if ($is_cron) {
		echo $lf.$lf;
// 		@file_put_contents('.update_office.done', date('Y-m-d H:i:s'));
	}
	else {
		echo "</body>\n";
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

function getCheckDigit($upc)
{
	if (!is_string($upc)) { echo '$'; return; };
	if (!ctype_digit($upc)) { echo '#'; return; };
	if (substr($upc, 0, 9) === '999999999') { echo ''; return; };
	if ($upc <= 99999) { echo ''; return; };

	$upc = str_pad($upc, 13, '0', STR_PAD_LEFT);

	for ($i = 0; $i < strlen($upc); $i++) {
		$sum += $upc[$i] * ($i % 2? 1 : 3);
	}
	return (400 - $sum) % 10;
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
			'a' => 'áâåäāà',
			'c' => 'çćč',
			'd' => 'ď',
			'e' => 'éêëèě',
			'i' => 'íîï',
			'n' => 'ñň',
			'o' => 'óôøöō',
			'r' => 'ř',
			's' => 'š',
			't' => 'ť',
			'u' => 'úûüùů',
			'y' => 'ý',
			'z' => 'ž',
			'2' => '₂', // used for CO₂
			"'" => '‘’′', // these may disappear anyway due to CORE-POS filtering apostrophes
			'"' => '“”″',
		);
	static $map;

	if (empty($map)) {
		foreach ($map_alphabetics as $to => $froms) {
			foreach (preg_split('//u', $froms, -1, PREG_SPLIT_NO_EMPTY) as $from) {
				$map[$from] = $to;
				$map[mb_convert_case($from, MB_CASE_UPPER, "UTF-8")] = mb_convert_case($to, MB_CASE_UPPER, "UTF-8");
			}
		}
	}

	$text_ascii = iconv('UTF-8', 'ASCII//TRANSLIT', strtr($text_utf8, $map));
	if ($text_ascii === $text_utf8)
		return $text_ascii;

// 	echo "{$lf}<span style=\"color:red\">“".($text_utf8)."”</span> → ";
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
// 	echo "<span style=\"color:green\">“".($text_ascii)."”</span>{$lf}";
	return $text_ascii;
}


function httpPost($url, $data, $json = false)
{
	if (!is_string($data)) {
		$data = $json?
				json_encode($data)
				:
				http_build_query($data)
			;
	}

	$options = [
		'http' => [ // use key 'http' even if you send the request to https://...
			'method' => 'POST',
			'header' => 'Content-type: '.($json? 'application/json' : 'application/x-www-form-urlencoded'),
			'content' => $data,
		],
	];

	$context = stream_context_create($options);
	if (!$context) return '(couldn’t create POST stream context!)';

	$result = file_get_contents($url, false, $context);
	if ($result === false) return "(couldn’t connect to {$url}!)";

	return $result;
}



function pdoBulkInsertOp($operation, $db, $tablename, $fieldnames, $values, $trigger=100)
{
	switch (strtoupper($operation)) {
		case 'INSERT':
		case 'INSERT IGNORE':
		case 'REPLACE':
			break;
		default:
			$operation = 'INSERT';
	}

	static $inserts = [];

	if ($values) {
		$insert = [];
		foreach ($values as $value) {
			switch (gettype($value)) {
				case 'NULL':
				case 'boolean':
				case 'integer':
					$insert[] = var_export($value, true);
					break;

				case 'double':
					$insert[] = var_export($value, true);
// 					$insert[] = round($value, 6);
					break;

				case 'string':
					$insert[] = $db->quote($value);
					break;

				case 'array':
				case 'object':
				case 'resource':
				case 'unknown type':
				default:
					$insert[] = 'NULL';
			}
		}
		$inserts[] = '						('.join(', ', $insert).')';
	}

	if (
			!$values // can trigger query by providing empty $values array
			||
			(
				is_bool($trigger)? $trigger : // can use explicit boolean $trigger
				(
					is_numeric($trigger) || ctype_digit($trigger)
					 	? count($inserts) >= $trigger // can give numeric threshold $trigger
						: true // for safety's sake, any other value always triggers
				)
			)
		) {

		if (count($inserts)) {
			$insert_q = '
					'.$operation.' `'.$tablename.'`
						('.(is_array($fieldnames)? '`'.join('`, `', $fieldnames).'`' : $fieldnames).')
					VALUES'."\n"
					. join(",\n", $inserts)
				;
// 			echo '<pre>'.$insert_q.'</pre>';
			$insert_count = $db->exec($insert_q);

			$inserts = [];
		}
		else {
			$insert_count = 0;
		}
		return $insert_count;
	}

	return false;
}
