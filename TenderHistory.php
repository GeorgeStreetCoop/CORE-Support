<?php
	extract($_POST);
?>
<html>
<head>
	<meta charset="utf-8"	>
	<title>CORE-POS Tender History</title>
	<style>
body	{
	font-family: Arial, Helvetica, sans-serif;
	font-size: 62.5%;
	text-align: left;
	}
h1	{
	font: bold 1.7em Georgia, Times, serif;
	margin: 2px 0 10px 0;
	border-bottom: 1px dotted #7c2b83;
	} /* displayed at 20px */
h2	{
	font: bold 1.4em Georgia, Times, serif;
	margin: 18px 0 0px 0;
	} /* displayed at 17px */
h3	{
	font: normal 1.2em Georgia, Times, serif;
	margin: 12px 0 4px 0;
	} /* displayed at 12px */
@media print {
    form * {
    	display: none;
    }
}
	</style>
</head>
<body>

	<form method="post">
		<table>
			<tr>
				<td colspan="2"><h3>Destination Database: CORE-POS Office</h3></td>
			</tr>
			<tr>
				<td>Host</td>
				<td><input type="text" name="host" size="45" value="<?=$host?:'localhost'?>"></td>
			</tr>
			<tr>
				<td>Username / Password</td>
				<td>
					<input type="text" name="username" value="<?=$username?:'office'?>">
					&nbsp;
					<input type="password" name="password">
				</td>
			</tr>
			<tr>
				<td>Database</td>
				<td><input type="text" name="database" size="45" value="<?=$database?:'office_trans'?>"></td>
			</tr>
			<tr>
				<td>Start Date</td>
				<td><input type="date" name="startdate" value="<?=$startdate?:date('Y-m-d', 'one week ago')?>"></td>
			</tr>
			<tr>
				<td>End Date</td>
				<td><input type="date" name="enddate" value="<?=$enddate?:date('Y-m-d')?>"></td>
			</tr>
			<tr>
				<td>
				</td>
				<td>
					<button type="submit">Do It Now!</button>
				</td>
			</tr>
		</table>
	</form>

<?php
	ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_WARNING);
	ini_set('display_errors', 1);
	ini_set('log_errors', 0);
	ini_set('error_log', '/dev/null');

	$office_dsn = "mysql:dbname={$database};host={$host};charset=utf8";
	try {
		$office_db = new PDO($office_dsn, $username, $password);
		$office_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
	} catch (PDOException $e) {
		echo 'Office connection failed: ' . $e->getMessage();
	}

	$transarchive = 'transarchive';
	$report_params = array(
		'department' => "
					SELECT
						'departments' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						CONCAT_WS(' ', t.dept_no, t.dept_name) GroupLabel,
						SUM(IF(d.department IN (102, 113) OR d.scale = 1, 1, d.quantity)) GroupQuantity,
						'item' GroupQuantityLabel,
						SUM(d.total) GroupValue
					FROM {$transarchive} d
						LEFT JOIN core_opdata.departments t ON d.department=t.dept_no
					WHERE d.emp_no != 9999 AND d.register_no != 99
						AND d.trans_status != 'X'
--						AND (d.trans_type = 'D' OR (d.trans_type = 'D' AND d.trans_subtype IN ('NA', 'AD')))
						AND d.department <> 0
					GROUP BY TransDate, t.dept_no
					HAVING TransDate = :trans_date
				",
		'tax' => "
					SELECT
						'taxes' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						IF(d.total = 0, 'Non-taxed', 'Taxed') GroupLabel,
						COUNT(*) GroupQuantity,
						'transaction' GroupQuantityLabel,
						SUM(d.total) GroupValue
					FROM {$transarchive} d
					WHERE d.emp_no != 9999 AND d.register_no != 99
						AND d.trans_status != 'X'
						AND d.trans_type = 'A' AND d.upc = 'TAX'
					GROUP BY TransDate, (total = 0)
					HAVING TransDate = :trans_date
				",
		'discount' => "
					SELECT
						'discounts' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						CONCAT(d.percentDiscount, '%') GroupLabel,
						COUNT(*) GroupQuantity,
						'transaction' GroupQuantityLabel,
						-SUM(d.total) GroupValue
					FROM {$transarchive} d
					WHERE d.emp_no != 9999 AND d.register_no != 99
						AND d.trans_status != 'X'
						AND d.trans_type = 'S' AND d.upc = 'DISCOUNT'
					GROUP BY TransDate, percentDiscount
					HAVING TransDate = :trans_date
				",
		'tender' => "
					SELECT
						'tenders' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						t.TenderName GroupLabel,
						COUNT(*) GroupQuantity,
						'transaction' GroupQuantityLabel,
						-SUM(d.total) GroupValue
					FROM {$transarchive} d
						LEFT JOIN core_opdata.tenders t ON d.trans_subtype = t.TenderCode
					WHERE d.emp_no != 9999 AND d.register_no != 99
						AND d.trans_status != 'X'
						AND d.trans_type = 'T'
					GROUP BY TransDate, t.tenderName
					HAVING TransDate = :trans_date
					ORDER BY TransDate,
						FIELD(d.trans_subtype, 'CA', 'CK', 'CC', 'DC', 'EF', d.trans_subtype),
						d.trans_subtype
				",
		);
	foreach ($report_params as $report => $query) {
		$prepared_report_params[$report] = $office_db->prepare($query);
	}

	for ($date = strtotime($startdate); $date <= strtotime($enddate); $date = strtotime('tomorrow', $date)) {
		echo "<h2>".date('D M j Y', $date)."</h2>";
		$params[':trans_date'] = date('Y-m-d', $date);

		foreach ($prepared_report_params as $report => $prepared) {
			$prepared->execute($params);

			echo("<h3>".ucwords($report)." Report</h3>");

			$total_quantity = $total_value = 0;

			while ($row = $prepared->fetch(PDO::FETCH_ASSOC)) {
				$plural = $row['Plural'];
				$group_label = $row['GroupLabel'];
				$group_quantity = $row['GroupQuantity'];
				$group_quantity_label = $row['GroupQuantityLabel'];
				$group_value = $row['GroupValue'];

				$total_quantity += $group_quantity;
				$total_value += $group_value;

				$group_quantity = rtrim(number_format($group_quantity, 3), '.0');
				$group_value = number_format($group_value, 2);

				echo("<b>{$group_label}: </b> &nbsp; ");
				echo("\${$group_value} from {$group_quantity} {$group_quantity_label}".($group_quantity==1?'':'s')."<br>\n");
			}
			$total_values[$report] = $total_value;

			$total_quantity = rtrim(number_format($total_quantity, 3), '.0');
			$total_value = number_format($total_value, 2);

			echo("<b>All ".ucwords($plural).": \${$total_value} from {$total_quantity} {$group_quantity_label}".($total_quantity==1?'':'s')."</b><br>\n");
		}

		$checksum = 0;
		echo("\n"."<br>\n");
		echo '<span style="color:gray">';
		foreach ($total_values as $report => $total_value) {
			switch ($report) {
				case 'discount':
				case 'tender':
					$sign = -1;
					break;
				default:
					$sign = 1;
			}
			$checksum += ($sign * $total_value);
			$total_value = number_format($total_value, 2);

			echo(ucwords($report)." Total: ".($sign < 0? '-' : '+') . "\${$total_value}<br>\n");
		}
		$checksum = number_format($checksum, 2);
		if ($checksum === '0.00' || $checksum === '-0.00')
			$checksum = '<span style="color:green">$0.00</span>';
		else
			$checksum = '<span style="color:red">$'.$checksum.'</span>';

		echo(str_repeat('_', 14)."<br>\n");
		echo("Checksum (should be zero): <b>{$checksum}</b><br>\n");
		echo '</span>';

		echo "<hr>\n";
	}
	echo $receipt;

