<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


// conform output format to destination

$is_http = array_key_exists('REMOTE_ADDR', $_SERVER);
$lf = $is_http? '<br>' : "\n";
$tab = $is_http? '&nbsp;&nbsp;&nbsp;&nbsp;' : "\t";
$sp = $is_http? '&nbsp;' : ' ';
$hr = $is_http? '<hr>' : ($lf . str_repeat('=', 20) . $lf);


// interpret params

$really_delete = false;
$days = $latest_date = null;

foreach ($argv as $arg) {
	if ($arg === 'really')
		$really_delete = true;
	elseif (ctype_digit($arg))
		$days = $arg;
	elseif (preg_match('~^\d{4}-\d\d-\d\d$~', $arg))
		$latest_date = $arg;
}
if ($days && $latest_date) {
	die('Both "days" and and end date were specified! Aborting rather than attempt to resolve this conflict' . $lf);
}
if ($latest_date && !strtotime($latest_date)) {
	die("Couldn’t parse end date '{$latest_date}'! Aborting" . $lf);
}
if (!$days && !$latest_date) {
	$days = 1;
}


// get lane settings and make connection

$lane_settings = ['localhost', 'localUser', 'localPass', 'pDatabase', 'tDatabase', 'laneno'];
$lane_settings = array_intersect_key(
	json_decode(file_get_contents('/CORE-POS/pos/is4c-nf/ini.json'), true),
	array_flip($lane_settings)
);

$lane_db = new PDO(
		'mysql:dbname='.$lane_settings['tDatabase'].';host='.$lane_settings['localhost'].';charset=utf8',
		$lane_settings['localUser'],
		$lane_settings['localPass']
	);
$lane_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");

$lane_tables_q = $lane_db->query("SHOW TABLES");
$lane_tables = $lane_tables_q->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('localtrans', $lane_tables)) {
	echo join($lf, $lane_tables) . $lf;
	die('No `localtrans` found in lane database! Aborting' . $lf);
}


// get office backend settings and make connection

$backend_settings = [
	'FANNIE_SERVER' => '192.168.1.50',
	'FANNIE_OP_DB' => 'office_opdata',
	'FANNIE_ARCHIVE_DB' => 'office_trans_archive',
];

$backend_db = new PDO(
		'mysql:dbname='.$backend_settings['FANNIE_ARCHIVE_DB'].';host='.$backend_settings['FANNIE_SERVER'].';charset=utf8',
		$lane_settings['localUser'],
		$lane_settings['localPass']
	);
$backend_db->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");

$backend_tables_q = $backend_db->query("SHOW TABLES");
$backend_tables = $backend_tables_q->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('bigArchive', $backend_tables)) {
	echo join($lf, $backend_tables) . $lf;
	die('No `bigArchive` found in backend database! Aborting' . $lf);
}


// finalize our start and end dates

$earliest_date_q = $lane_db->query("SELECT DATE(MIN(datetime)) FROM localtrans");
$earliest_date = $earliest_date_q->fetch(PDO::FETCH_COLUMN);
if (!($earliest_time = strtotime($earliest_date))) {
	echo var_export($earliest_date, true) . $lf;
	die('No earliest date found in `localtrans`! Aborting' . $lf);
}
if (!$latest_date) {
	$latest_date = date('Y-m-d', strtotime(intval($days - 1).' days', $earliest_time));
}


// format our reusable parameter queries

$template_for_date_q = "
SELECT
	MIN(datetime) min_datetime,
	MAX(datetime) max_datetime,
	FORMAT(SUM(quantity), 2) sum_quantity,
	FORMAT(SUM(total), 2) sum_total,
	COUNT(*) count_records
FROM :::TABLENAME:::
WHERE
	register_no = ".intval($lane_settings['laneno'])."
	AND datetime BETWEEN :date AND :date + INTERVAL 1 DAY
GROUP BY DATE(datetime)
";

$backend_for_date_q = strtr($template_for_date_q, [':::TABLENAME:::' => 'bigArchive']);
echo $backend_for_date_q;

$lane_for_date_q = strtr($template_for_date_q, [':::TABLENAME:::' => 'localtrans']);
echo $lane_for_date_q;

$delete_lane_for_date_q = preg_replace('~SELECT.+?FROM\s+(.+?)\s+GROUP BY.+~ms', 'DELETE FROM \1', $lane_for_date_q);
echo $delete_lane_for_date_q;

// create and initialize reusable parameter queries

$backend_for_date_q = $backend_db->prepare($backend_for_date_q);
if (!$backend_for_date_q) die('Couldn’t prepare backend fetch! Aborting' . $lf);
if (!$backend_for_date_q->bindParam('date', $date)) die('Couldn’t bind backend fetch :date param! Aborting' . $lf);

$lane_for_date_q = $lane_db->prepare($lane_for_date_q);
if (!$lane_for_date_q) die('Couldn’t prepare lane fetch! Aborting' . $lf);
if (!$lane_for_date_q->bindParam('date', $date)) die('Couldn’t bind lane fetch :date param! Aborting' . $lf);

$delete_lane_for_date_q = $lane_db->prepare($delete_lane_for_date_q);
if (!$delete_lane_for_date_q) die('Couldn’t prepare lane delete! Aborting' . $lf);
if (!$delete_lane_for_date_q->bindParam('date', $date)) die('Couldn’t bind lane delete :date param! Aborting' . $lf);


echo $lf.$hr.$lf;


// now traverse the databases

echo "Scanning from {$earliest_date} to {$latest_date}..." . $lf;
$date = $earliest_date;
$found = $matched = $mismatched = $deleted = 0;

while ($date <= $latest_date) {
	$lane_for_date_q->execute();
	$lane_for_date = $lane_for_date_q->fetch(PDO::FETCH_ASSOC);

	if (!$lane_for_date) {
		echo "{$tab}{$date}{$tab}no local data" . $lf;
	}
	else {
		$found += $lane_for_date['count_records'];

		$backend_for_date_q->execute();
		$backend_for_date = $backend_for_date_q->fetch(PDO::FETCH_ASSOC) ?: [];

		$match = ($backend_for_date === $lane_for_date);
		if ($match) {
			echo "{$tab}{$date}{$tab}MATCH...{$tab}" . join(', ', $backend_for_date) . $lf;
			$matched += $backend_for_date['count_records'];

			if ($really_delete) {
				if ($delete_lane_for_date_q->execute()) {
					$this_deleted = $delete_lane_for_date_q->rowCount();
					echo "{$tab}{$date}{$tab}DELETED {$this_deleted}" . $lf;
					$deleted += $this_deleted;
					if ($this_deleted !== intval($lane_for_date['count_records'])) {
						echo "WARNING: Deletion count {$this_deleted} didn’t match original found count ".$lane_for_date['count_records'].'! Aborting' . $lf;
						break;
					}
				}
				else {
					echo "{$tab}{$date}{$tab}DELETE FAILED!" . $lf;
				}
			}
			else {
				echo "{$tab}{$date}{$tab}(not actually deleting - re-run with 'really' param set to enable deletions)" . $lf;
			}
		}
		else {
			$mismatched += $backend_for_date['count_records'];
			echo "{$tab}{$date}{$tab}MISMATCH!{$tab}Server: " . join(', ', $backend_for_date) . "{$sp}{$sp}{$sp}{$sp}{$sp}Lane: " . join(', ', $lane_for_date) . $lf;
		}
	}

	$date = date('Y-m-d', strtotime($date.' +1 day'));
}

echo "Finished: found {$found}, matched {$matched}, mismatched {$mismatched}, deleted {$deleted}" . $lf . $lf;
