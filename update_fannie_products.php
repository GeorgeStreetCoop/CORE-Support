<html>
<head>
	<title>Update Fannie Products</title>
</head>
<body>

	<form method="post">
		<table>
			<tr>
				<td colspan="2"><h3>Source Database: CoopProducts<h3></td>
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
				<td><?=installTextField('coop_product_db', $coop_product_db, 'geor5702_products')?></td>
			</tr>
		</table>
		<table>
			<tr>
				<td colspan="2"><h3>Destination Database: CORE-POS Fannie</h3></td>
			</tr>
			<tr>
				<td>Host</td>
				<td><?=installTextField('FANNIE_SERVER', $FANNIE_SERVER, 'localhost')?></td>
			</tr>
			<tr>
				<td>Username</td>
				<td><?=installTextField('FANNIE_SERVER_USER', $FANNIE_SERVER_USER, 'office')?></td>
			</tr>
			<tr>
				<td>Password</td>
				<td><?=installTextField('FANNIE_SERVER_PW', $FANNIE_SERVER_PW, '', true, array('type'=>'password'))?></td>
			</tr>
			<tr>
				<td>Database</td>
				<td><?=installTextField('FANNIE_OP_DB', $FANNIE_OP_DB, 'fannie_opdata')?></td>
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

		$coop_dsn = "mysql:dbname={$coop_product_db};host={$coop_host}";
		try {
			$coop_db = new PDO($coop_dsn, $coop_user, $coop_pw);
		} catch (PDOException $e) {
			echo "Co-op connection ({$coop_dsn}) failed: " . $e->getMessage();
		}

		$fannie_dsn = "mysql:dbname={$FANNIE_OP_DB};host={$FANNIE_SERVER}";
		try {
			$fannie_db = new PDO($fannie_dsn, $FANNIE_SERVER_USER, $FANNIE_SERVER_PW);
		} catch (PDOException $e) {
			echo 'Fannie connection failed: ' . $e->getMessage();
		}

		$coop_products_q = $coop_db->query('SELECT * FROM CoopProductsForIS4C');

		$fannie_products_q = $fannie_db->prepare('
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
					inUse = :inUse,
					deposit = :deposit,
					id = :id
				ON DUPLICATE KEY UPDATE
					upc = :upc,
					description = :description,
					brand = :brand,
					normal_price = :normal_price,
					department = :department,
--					tax = :tax,
--					foodstamp = :foodstamp,
--					scale = :scale,
--					discount = 1,
--					wicable = :wicable,
--					inUse = :inUse,
					deposit = :deposit,
					id = :id
			');

		flush();
		while ($coop_product = $coop_products_q->fetch(PDO::FETCH_ASSOC)) {
			$params = array();
			foreach ($coop_product as $column => $value) {
				$params[':'.$column] = $value;
			}

			if (!($r = $fannie_products_q->execute($params)))
				reportInsertError($fannie_products_q, $params);

			if ($r) {
				echo '.';
				if (++$i % 500 === 0) {
					echo "<br>\n";
					flush();
				}
			}
			elseif ((++$e >= 5) && ($e > $i * 5))
				die;
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
	<a href="../CORE-POS/fannie/sync/TableSyncPage.php?tablename=products">Synchronize Products to Lanes</a>
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
