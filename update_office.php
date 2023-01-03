<?php
	$use_prepared = false;

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
	$OFFICE_OP_DBNAME = null;
	$coop_host = null;
	$coop_user = null;
	$coop_pw = null;
	$coop_member_dbname = null;
	$coop_products_dbname = null;

	if ($is_cron) {
		echo "Running as command line or cron";
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
				<td><?=installTextField('start_date', $start_date, date('Y-m-d', strtotime('-21 day')), array('type'=>'date'))?><!-- <small><i>(this date will be included)</i></small>--></td>
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

	$allowed_params = array(
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
		);
	if (count($_POST))
		$invoke_params = $_POST;
	elseif ($_SERVER['argv']) {
		$invoke_params = $arg_parsed = [];
		foreach ($argv as $idx => $arg) {
			if ($idx == 0 && $arg == $_SERVER['PHP_SELF']) continue;

			parse_str($arg, $arg_parsed);
			$invoke_params += $arg_parsed;
		}
	}

	if (isset($invoke_params)) {
		$invoke_params = array_intersect_key($invoke_params, $allowed_params);
		extract($invoke_params);
		$office_server_sync_url_base = "//{$OFFICE_SERVER}/{$OFFICE_SERVER_URL_BASE}/sync/TableSyncPage.php";
		$time = time();
		$asof_date = 'as of '.date('M j Y g:ia', $time);
		$asof_hash = date('Y-m-d_His', $time);

		if ($xfer_members || $xfer_products || $xfer_sales) {
			echo "Connecting with `{$OFFICE_OP_DBNAME}`...{$lf}";
			$office_dsn = "mysql:dbname={$OFFICE_OP_DBNAME};host={$OFFICE_SERVER};charset=utf8";
			try {
				$office_db = new PDO($office_dsn, $OFFICE_SERVER_USER, $OFFICE_SERVER_PW, array(PDO::ATTR_TIMEOUT => 10));
				$office_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
			} catch (PDOException $e) {
				echo 'Office connection failed: ' . $e->getMessage() . $lf;
			}
			echo $hr.$lf;
		}

		if ($xfer_members) {
			echo "Connecting with `{$coop_member_dbname}`...{$lf}";
			$coop_members_dsn = "mysql:dbname={$coop_member_dbname};host={$coop_host};charset=utf8";
			try {
				$coop_members_db = new PDO($coop_members_dsn, $coop_user, $coop_pw, array(PDO::ATTR_TIMEOUT => 10));
				$coop_members_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
			} catch (PDOException $e) {
				echo "Co-op member DB connection ({$coop_members_dsn}) failed: " . $e->getMessage() . $lf;
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
			}

			// Add non-member POS lookups
			$office_nonmembers = array(
					array(':card_no' => 999, ':discount' => 0, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Non-member', ':first_name' => '', ':modified' => 0),
					array(':card_no' => 62, ':discount' => 5, ':is_staff' => 0, ':is_senior' => 1, ':last_name' => 'Senior Non-member', ':first_name' => '', ':modified' => 0),
					array(':card_no' => 33, ':discount' => 67, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Too Good To Go', ':first_name' => '', ':modified' => 0),
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
<?php
				}
			}
			echo $lf.$hr.$lf;
			flush();
		}

		if ($xfer_products || $xfer_sales) {
			echo "Connecting with `{$coop_products_dbname}`...{$lf}";
			$coop_products_dsn = "mysql:dbname={$coop_products_dbname};host={$coop_host};charset=utf8";
			try {
				$coop_products_db = new PDO($coop_products_dsn, $coop_user, $coop_pw, array(PDO::ATTR_TIMEOUT => 10));
				$coop_products_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
			} catch (PDOException $e) {
				echo "Co-op product DB connection ({$coop_products_dsn}) failed: " . $e->getMessage() . $lf;
			}
		}

		if ($xfer_products) {
			$coop_products_q = $coop_products_db->query('SELECT * FROM ProductsForIS4C');

			$office_db->exec('UPDATE products SET inUse = 0 WHERE upc < 100000');
if ($use_prepared) {
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
}
			flush();
			while ($coop_product = $coop_products_q->fetch(PDO::FETCH_ASSOC)) {
				set_time_limit(60);
if ($use_prepared) {
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
else {
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
}
			}

if ($use_prepared) {
			// Add non-product POS lookups
			$office_nonproducts = [
					[':upc' => '0000000091111', ':description' => $asof_date, ':brand' => '', ':normal_price' => 0, ':department' => 0, ':tax' => 0, ':foodstamp' => 0, ':scale' => 0, ':wicable' => 0, ':qttyEnforced' => 0, ':cost' => 0, ':inUse' => 1, ':deposit' => NULL, ':default_vendor_id' => NULL, ':id' => 91111],
				];
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
}
else {
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
			echo "<br>\n";
			flush();
}

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
?>
				<br>
				<a href="<?=$url?>" target="<?=$tablename?>"><?=$label?></a><?=$synced?>
<?php
				}
			}
			echo $lf.$hr.$lf;
			flush();
		}


		if ($xfer_sales) {
			$sales_start_time = microtime(1);

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

				];

			$header_sql = "REPLACE ProductSales\n";
			$header_sql .= "\t(Source, UPC, SaleDate, Department, ItemCount, GrossPrice, MemberDiscount, SeniorDiscount, LastUpdate)\n";
			$header_sql .= "VALUES";

			foreach ($sales_fetch_sqls as $sales_fetch_sql) {
				$sales_fetch_q = $office_db->prepare($sales_fetch_sql);
				$params = array(
						':start_date' => $start_date,
						':end_date' => $end_date,
					);
				$r = $sales_fetch_q->execute($params);

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

					// clear destination range (but only once per data transfer!)
					if (!$sales_clear_q) {
						// echo "<pre style='background-color:#fdd;font:8px Courier'>DELETE FROM ProductSales WHERE SaleDate BETWEEN '$start_date' AND '$end_date'</pre>";
						$sales_clear_q = $coop_products_db->prepare('
								DELETE FROM ProductSales
								WHERE
									Source = "CORE-POS"
									AND SaleDate BETWEEN :start_date AND :end_date
							');
						$r = $sales_clear_q->execute($params);
						if (!$r) {
							echo "{$lf}— error clearing date range in sales table: " . $sales_clear_q->errorInfo()[2] . $lf;
						}
					}

					while ($f = $sales_fetch_q->fetch(PDO::FETCH_BOUND)) {

						// are we on a new date? if so, send old day's data (if it exists)
						// echo "<pre style='background-color:#fdd;font:8px Courier'>".htmlspecialchars("$header_sale_date != $sale_date? (".count($values_sqls).")")."</pre>";
						// echo "<pre style='background-color:#fdd;font:8px Courier'>".htmlspecialchars("$sale_date_nice: $upc")."</pre>";
						if ($header_sale_date != $sale_date) {
							if ($header_sale_date) {
								$insert_sql = $header_sql . join(",", $values_sqls);
								// echo "<pre style='background-color:#ddf;font:8px Courier'>".htmlspecialchars($insert_sql)."</pre>";
								$r = $coop_products_db->exec($insert_sql);
								if (!$r) {
									echo "{$lf}— error inserting data for {$header_sale_date}: " . $coop_products_db->errorInfo()[2] . $lf;
								}
								else {
									$date_gross = '$'.number_format($date_gross, 2);
									$date_net = '$'.number_format($date_net, 2);
									$date_reported_gross = '$'.number_format($date_reported_gross, 2);
									$date_reported_net = '$'.number_format($date_reported_net, 2);
									echo "{$date_records} records; {$date_gross} gross, {$date_net} net, {$date_reported_gross} reported gross, {$date_reported_net} reported net{$lf}";
									$total_records += $date_records;
								}
							}
							$header_sale_date = $sale_date;
							$values_sqls = array();

							echo $sale_date_nice;
							flush();
							usleep(10);
							set_time_limit(60);
							$date_records = $date_gross = $date_net = $date_reported_gross = $date_reported_net = 0;
						}

						// set up new row
						$upc_corrected = $upc . getCheckDigit($upc);
						$upcs_changed += ($upc_corrected === $upc? 0 : 1);

						if ($date_records++ % 5 === 0)
							echo '.';

						$date_gross += $gross_price;
						$total_gross += $gross_price;
						$date_net += $gross_price - $member_discount - $senior_discount;
						$total_net += $gross_price - $member_discount - $senior_discount;

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
								$date_reported_gross += $gross_price;
								$total_reported_gross += $gross_price;
								$date_reported_net += $gross_price - $member_discount - $senior_discount;
								$total_reported_net += $gross_price - $member_discount - $senior_discount;
						}

						$values_sql = "\n\t('CORE-POS', {$upc_corrected}, '{$sale_date}', {$department}, {$item_count}, {$gross_price}, {$member_discount}, {$senior_discount}, NOW())";
						// echo "<pre style='background-color:#ffd;font:8px Courier'>".htmlspecialchars($values_sql)."</pre>";
						$values_sqls[] = $values_sql;
					}
				}

			}
			if ($header_sale_date && count($values_sqls)) {
				$insert_sql = $header_sql . join(",", $values_sqls);
				// echo "<pre style='background-color:#dff;font:8px Courier'>".htmlspecialchars($insert_sql)."</pre>";
				$r = $coop_products_db->exec($insert_sql);
				if (!$r) {
					echo "{$lf}— error inserting data for {$header_sale_date}: " . $coop_products_db->errorInfo()[2] . $lf;
				}
				else {
					$date_gross = '$'.number_format($date_gross, 2);
					$date_net = '$'.number_format($date_net, 2);
					$date_reported_gross = '$'.number_format($date_reported_gross, 2);
					$date_reported_net = '$'.number_format($date_reported_net, 2);
					echo "{$date_records} records; {$date_gross} gross, {$date_net} net, {$date_reported_gross} reported gross, {$date_reported_net} reported net{$lf}";
					$total_records += $date_records;
				}
			}

			$sales_end_time = microtime(1);
			$total_duration = number_format($sales_end_time - $sales_start_time, 3);
			$overall_rate = number_format($total_records / ($sales_end_time - $sales_start_time), 3);
			$total_records = number_format($total_records);
			$upcs_changed = number_format($upcs_changed);
			echo "{$lf}Exported {$total_records} total records (adding {$upcs_changed} checksums) in {$total_duration} seconds, {$overall_rate} records/second average.{$lf}";

			$total_gross = '$'.number_format($total_gross, 2);
			$total_net = '$'.number_format($total_net, 2);
			$total_reported_gross = '$'.number_format($total_reported_gross, 2);
			$total_reported_net = '$'.number_format($total_reported_net, 2);
			echo "{$total_gross} total gross, {$total_net} total net, {$total_reported_gross} total reported gross, {$total_reported_net} total reported net{$lf}{$lf}";
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
	}
	if ($is_cron) {
		@file_put_contents('.update_office.done', date('Y-m-d H:i:s'));
	}
	else {
?>
</body>
<?php
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
				is_numeric($trigger) || ctype_digit($trigger)?
				(count($inserts) >= $trigger) // can trigger query by providing numeric $trigger
				: $trigger // can trigger query by providing $trigger = true
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
