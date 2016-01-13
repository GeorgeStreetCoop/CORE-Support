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
				<td><?=installTextField('coop_host')?></td>
			</tr>
			<tr>
				<td>Username</td>
				<td><?=installTextField('coop_user')?></td>
			</tr>
			<tr>
				<td>Password</td>
				<td><?=installTextField('coop_pw', $COOP_PW, '', true, array('type'=>'password'))?></td>
			</tr>
			<tr>
				<td>Database</td>
				<td><?=installTextField('coop_db')?></td>
			</tr>
		</table>
		<table>
			<tr>
				<td colspan="2"><h3>Destination Database: CORE-POS Fannie</h3></td>
			</tr>
			<tr>
				<td>Host</td>
				<td><?=installTextField('FANNIE_SERVER', $FANNIE_SERVER, '127.0.0.1')?></td>
			</tr>
			<tr>
				<td>Username</td>
				<td><?=installTextField('FANNIE_SERVER_USER', $FANNIE_SERVER_USER, 'root')?></td>
			</tr>
			<tr>
				<td>Password</td>
				<td><?=installTextField('FANNIE_SERVER_PW', $FANNIE_SERVER_PW, '', true, array('type'=>'password'))?></td>
			</tr>
			<tr>
				<td>Database</td>
				<td><?=installTextField('FANNIE_OP_DB', $FANNIE_OP_DB, 'core_op')?></td>
			</tr>
		</table>
		<button type="submit">Update Now!</button>
	</form>

<?php
// 	var_export($_POST);
	extract($_POST);

	$coop_dsn = "mysql:dbname={$coop_db};host={$coop_host}";
	try {
		$coop_db = new PDO($coop_dsn, $coop_user, $coop_pw);
		$coop_products_q = $coop_db->query('SELECT * FROM CoopProductsForIS4C');
	} catch (PDOException $e) {
		echo 'CoopProducts connection failed: ' . $e->getMessage();
	}

	$fannie_dsn = "mysql:dbname={$FANNIE_OP_DB};host={$FANNIE_SERVER}";
	try {
		$fannie_db = new PDO($fannie_dsn, $FANNIE_SERVER_USER, $FANNIE_SERVER_PW);
		$fannie_products_q = $fannie_db->prepare('
				REPLACE products
				SET
					upc = :upc,
					description = :description,
					brand = :brand,
					normal_price = :normal_price,
					department = :department,
					tax = :tax,
					foodstamp = :foodstamp,
					wicable = :wicable,
					inUse = :inUse,
					id = :id
			');
	} catch (PDOException $e) {
		echo 'Fannie connection failed: ' . $e->getMessage();
	}

	while ($coop_product = $coop_products_q->fetch(PDO::FETCH_ASSOC)) {
		$params = array();
		foreach($coop_product as $column => $value){
			$params[':'.$column] = $value;
		}
		$r = $fannie_products_q->execute($params);
		echo ($r? '.' : '1'.$coop_product['upc']);																																							
		if (++$i % 300 === 0)
			echo "<br>\n";																																							
	}
?>
	<hr>
	<a href="../CORE-POS/fannie/sync/TableSyncPage.php?tablename=products">Synchronize Products to Lanes</a>
</body>


<?php

function installTextField($name, $current_val, $default, $bool, $html_vals)
{
	$html_vals['type'] = $html_vals['type']?: 'text';
	$html_vals['name'] = $html_vals['name']?: $name;
	$html_vals['value'] = $html_vals['value']?: $_POST[$name];

	return '<input type="'.$html_vals['type'].'" name="'.$html_vals['name'].'" value="'.$html_vals['value'].'" />';
}
