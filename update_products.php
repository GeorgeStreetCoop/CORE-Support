<html>
<head>
	<meta charset="utf-8">
	<title>Update CORE-POS Products</title>
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

		$coop_dsn = "mysql:dbname={$coop_product_db};host={$coop_host};charset=utf8";
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

		$coop_products_q = $coop_db->query('SELECT * FROM CoopProductsForIS4C');

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
					cost = :cost,
					inUse = :inUse,
					deposit = :deposit,
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
					cost = :cost,
					inUse = :inUse,
					deposit = :deposit,
					id = :id
			');

		flush();
		while ($coop_product = $coop_products_q->fetch(PDO::FETCH_ASSOC)) {
			$params = array();
			foreach ($coop_product as $column => $value) {
				$params[':'.$column] = $value;
			}

			// Make brand and description safe for current CORE-POS charset limitations
			$params[':brand'] = textASCII($params[':brand']);
			$params[':description'] = textASCII($params[':description']);

			if (!($r = $office_products_q->execute($params)))
				reportInsertError($office_products_q, $params);

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

function textASCII($text_utf8)
{
	static $map_from = array('ä', 'é', 'í', 'ñ', 'Ö', 'ü');
	static $map_to = array('a', 'e', 'i', 'n', 'O', 'u');

	$text_ascii = iconv('UTF-8', 'ASCII//TRANSLIT', str_replace($map_from, $map_to, $text_utf8));
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
