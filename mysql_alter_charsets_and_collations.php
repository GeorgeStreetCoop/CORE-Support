<?php
// adapted from http://www.phpwact.org/php/i18n/utf-8/mysql
 
// this script will output the queries need to change all fields/tables to a different collation
// it is HIGHLY suggested you take a MySQL dump prior to running any of the generated
// this code is provided as is and without any warranty

?>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>Alter Charsets and Collations</title>
</head>
<body>
<?

if (count($_POST)) {
	set_time_limit(0);
 
	$host = $_POST['host'];
	$username = $_POST['username'];
	$password = $_POST['password'];
	$database = $_POST['database'];

	$convert_from = $_POST['convert_from'];
	$convert_to = $_POST['convert_to'];

	$execute = isset($_POST['execute']);

	echo "<pre>\n";
	$ret = convertDatabase($host, $username, $password, $database, $convert_from, $convert_to, $execute);
	echo "</pre>\n";
}

?>
	<form method="post">
		<table>
			<tr>
				<td colspan="2"><h3>Database to update:</h3></td>
			</tr>
			<tr>
				<td>Host</td>
				<td><input type="text" name="host" size="45" value="<?=$host?>"></td>
			</tr>
			<tr>
				<td>Username / Password</td>
				<td>
					<input type="text" name="username" value="<?=$username?>">
					&nbsp;
					<input type="password" name="password">
				</td>
			</tr>
			<tr>
				<td>Database</td>
				<td><input type="text" name="database" size="45" value="<?=$database?>"></td>
			</tr>
			<tr>
				<td>Convert From / To</td>
				<td>
					<input type="text" name="convert_from" value="<?=$convert_from?>">
					<input type="text" name="convert_to" value="<?=$convert_to?>">
				</td>
			</tr>
			<tr>
				<td>
					<input type="checkbox" name="execute" value="1">Execute Queries
				</td>
				<td>
					<button type="submit">Do It Now!</button>
				</td>
			</tr>
		</table>
	</form>
</body>
</html>

<?

function convertDatabase($host, $username, $password, $database, $convert_from, $convert_to, $execute)
{
	$character_set = substr($convert_to, 0, strpos($convert_to, '_'));

	$show_alter_database = true;
	$show_alter_table = true;
	$show_alter_field = true;

	$ret = mysql_connect($host, $username, $password);
	if ($ret === false) return mysql_error();
	$ret = mysql_select_db($database);
	if ($ret === false) return mysql_error();

	$rs_tables = mysql_query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
	if ($rs_tables === false) return mysql_error();

	$commands["{$database}"] = ("USE `$database`");

	if ($show_alter_database) {
		$commands["{$database}."] = ("ALTER DATABASE `$database` DEFAULT CHARACTER SET $character_set COLLATE $convert_to");
	}

	while ($row_tables = mysql_fetch_row($rs_tables)) {
		$table = mysql_real_escape_string($row_tables[0]);

		if ($show_alter_table) {
			$commands["{$database}.{$table}"] = ("ALTER TABLE `$table` DEFAULT CHARACTER SET $character_set COLLATE $convert_to");
		}
 
		$rs = mysql_query("SHOW FULL FIELDS FROM `$table`");
		if ($rs === false) return mysql_error();
		while ($row=mysql_fetch_assoc($rs)) {

			if (is_null($row['Collation'])) {
				// no string
				continue;
			}

			if ($row['Collation'] == $convert_to) {
				// already there
				continue;
 			}

			if (strlen($convert_from) && $row['Collation'] != $convert_from) {
				// doesn't match target criteria
				continue;
 			}
 
			// Is the field allowed to be null?
			if ($row['Null']=='YES') {
				$nullable = ' NULL ';
			} else {
				$nullable = ' NOT NULL';
			}
 
			// Does the field default to null, a string, or nothing?
			if ($row['Default']==NULL) {
				$default = " DEFAULT NULL";
			} else if ($row['Default']!='') {
				$default = " DEFAULT '".mysql_real_escape_string($row['Default'])."'";
			} else {
				$default = '';
			}
 
			// Alter field collation:
			// ALTER TABLE `account` CHANGE `email` `email` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL
			if ($show_alter_field) {
				$field = mysql_real_escape_string($row['Field']);
				$commands["{$database}.{$table}.{$field}"] = ("ALTER TABLE `$table` CHANGE `$field` `$field` $row[Type] CHARACTER SET $character_set COLLATE $convert_to $nullable $default");
			}
		}
	}

	if ($execute) {
		$errors = array();
		foreach ($commands as $ref => $command) {
			echo htmlentities($command) . '&nbsp;&nbsp;&nbsp;';
			$ret = mysql_query($command);
			if ($ret === false) {
				$errors[$command] = $ref.': '.mysql_error();
				echo '<span style="color:red">'.htmlentities(mysql_error())."</span>\n";
			}
			else {
				echo "<span style=\"color:green\"><b>√</b></span>\n";
			}
			flush();
		}
		return $errors;
	}
	else {
		echo join(";\r\n", $commands);
	}
	return true;
}
