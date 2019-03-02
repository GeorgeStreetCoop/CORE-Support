<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once('/CORE-POS/fannie/config.php');

$is_http = (array_key_exists('REMOTE_ADDR', $_SERVER));
$lf = $is_http? '<br>' : "\n";

$sql = '
		SELECT
			CONCAT(
					LPAD(DATE_FORMAT(datetime, "%l"), 2, " "),
					":",
					LPAD(FLOOR(DATE_FORMAT(datetime, "%i") / 15) * 15, 2, "0"),
					DATE_FORMAT(datetime, "%p")
				) Timeframe,
			SUM(total) AS Sales
		FROM dtransactions AS d
		WHERE trans_type ="I" OR trans_type = "D" or trans_type="M"
		GROUP BY
			DATE_FORMAT(datetime, "%H"),
			FLOOR(DATE_FORMAT(datetime, "%i") / 15)
	';

$db = new PDO(
		'mysql:dbname='.$FANNIE_TRANS_DB.';host='.$FANNIE_SERVER.';charset=utf8',
		$FANNIE_SERVER_USER,
		$FANNIE_SERVER_PW
	);
$db->exec('SET NAMES "utf8" COLLATE "utf8_unicode_ci"');

$q = $db->prepare($sql);
$q->execute();
$q->bindColumn('Timeframe', $timeframe);
$q->bindColumn('Sales', $sales);


if (!$is_http) echo '==='.$lf;
while ($q->fetch(PDO::FETCH_BOUND)) {
	echo $timeframe.': $'.$sales.$lf;
}
if (!$is_http) echo '==='.$lf;
