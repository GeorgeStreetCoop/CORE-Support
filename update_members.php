<html>
<head>
	<meta charset="utf-8">
	<title>Update Fannie Members</title>
</head>
<body>

	<form method="post">
		<table>
			<tr>
				<td colspan="2"><h3>Source Database: Co-op Members<h3></td>
			</tr>
			<tr>
				<td>Host</td>
				<td><?=installTextField('coop_host', $coop_host, 'georgestreetcoop.com')?></td>
			</tr>
			<tr>
				<td>Username</td>
				<td><?=installTextField('coop_user', $coop_user, 'geor5702_backup')?></td>
			</tr>
			<tr>
				<td>Password</td>
				<td><?=installTextField('coop_pw', $coop_pw, '', true, array('type'=>'password'))?></td>
			</tr>
			<tr>
				<td>Database</td>
				<td><?=installTextField('coop_member_db', $coop_member_db, 'geor5702_members')?></td>
			</tr>
		</table>
		<table>
			<tr>
				<td colspan="2"><h3>Destination Database: CORE-POS</h3></td>
			</tr>
			<tr>
				<td>Host</td>
				<td><?=installTextField('OFFICE_SERVER', $OFFICE_SERVER, 'localhost')?></td>
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
		</table>
		<button type="submit">Update Now!</button>
	</form>

<?php
	ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_WARNING);
	ini_set('display_errors', 1);
	ini_set('log_errors', 0);
	ini_set('error_log', '/dev/null');

	if (count($_POST)) {
		extract($_POST);

		$coop_dsn = "mysql:dbname={$coop_member_db};host={$coop_host};charset=utf8";
		try {
			$coop_db = new PDO($coop_dsn, $coop_user, $coop_pw);
			$coop_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
		} catch (PDOException $e) {
			echo "Co-op connection ({$coop_dsn}) failed: " . $e->getMessage();
		}

		$office_dsn = "mysql:dbname={$OFFICE_OP_DB};host={$OFFICE_SERVER};charset=utf8";
		try {
			$office_db = new PDO($office_dsn, $OFFICE_SERVER_USER, $OFFICE_SERVER_PW);
			$office_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
		} catch (PDOException $e) {
			echo 'Office connection failed: ' . $e->getMessage();
		}

		$coop_members_q = $coop_db->query('SELECT * FROM MembersForIS4C');

		$office_custdata_q = $office_db->prepare('
				INSERT custdata
				SET
					CardNo = :card_no,
					personNum = 1,
					LastName = :last_name,
					FirstName = :first_name,
					CashBack = 20.00,
					Balance = 0.00,
					Discount = :discount + IF(:is_senior, 5, 0),
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
					Discount = :discount + IF(:is_senior, 5, 0),
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
			$params = $office_custdata_params = $office_meminfo_params = array();
			foreach ($coop_member as $column => $value) {
				$params[':'.$column] = $value;
			}
			$office_custdata_params = array_intersect_key($params, $office_custdata_paramlist);
			$office_meminfo_params = array_intersect_key($params, $office_meminfo_paramlist);
			$office_memdates_params = array_intersect_key($params, $office_memdates_paramlist);

			if (!($r = $office_custdata_q->execute($office_custdata_params)))
				reportInsertError($office_custdata_q, $office_custdata_params);
			if (!($s = $office_meminfo_q->execute($office_meminfo_params)))
				reportInsertError($office_meminfo_q, $office_meminfo_params);
			if (!($t = $office_memdates_q->execute($office_memdates_params)))
				reportInsertError($office_memdates_q, $office_memdates_params);

			if ($r && $s && $t) {
				echo '.';
				if (++$i % 500 === 0) {
					echo "<br>\n";
					flush();
				}
			}
			elseif ((++$e >= 5) && ($e > $i * 5))
				die;
		}

		// Add non-member POS lookups
		$office_nonmembers = array(
				array(':card_no' => 999, ':discount' => 0, ':is_staff' => 0, ':is_senior' => 0, ':last_name' => 'Non-member', ':first_name' => '', ':modified' => 0),
				// Use :discount = 0 because query adds 5 when it detects :is_senior == 1!
				array(':card_no' => 62, ':discount' => 0, ':is_staff' => 0, ':is_senior' => 1, ':last_name' => 'Senior Non-member', ':first_name' => '', ':modified' => 0),
			);
		foreach ($office_nonmembers as $office_nonmember) {
			if (!($r = $office_custdata_q->execute($office_nonmember)))
				reportInsertError($office_custdata_q, $office_nonmember);
			if ($r) {
				echo ',';
				if (++$i % 500 === 0) {
					echo "<br>\n";
					flush();
				}
			}
		}
?>
		<hr>
<?
		flush();
	}

	for ($i = 1; $i <= 3; $i++) {
		$ping = shell_exec("ping -q -t2 -c3 192.168.1.5{$i}");
		echo "Lane {$i} (192.168.1.5{$i}): <b style=\"color:";
		echo (preg_match('~0 packets received~', $ping)? 'red">DOWN' : 'green">UP');
		echo "</b><br>\n";
		flush();
	}
?>
	<a href="../CORE-POS/fannie/sync/TableSyncPage.php?tablename=custdata">Synchronize Members to Lanes</a>
	<a href="../CORE-POS/fannie/sync/TableSyncPage.php?tablename=memberCards">Synchronize Member Cards to Lanes</a>
	<a href="../CORE-POS/fannie/sync/TableSyncPage.php?tablename=memtype">Synchronize Member Types to Lanes</a>
	<hr>
</body>


<?php

function installTextField($name, $current_val, $default, $bool, $html_vals)
{
	$html_vals['type'] = $html_vals['type']?: 'text';
	$html_vals['name'] = $html_vals['name']?: $name;
	$html_vals['value'] = $html_vals['value']?: $_POST[$name]?: $current_val?: $default;

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
