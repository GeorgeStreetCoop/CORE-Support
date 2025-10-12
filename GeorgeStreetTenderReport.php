<?php
/*******************************************************************************

    Copyright 2016, 2018 George Street Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

namespace COREPOS\pos\lib\ReceiptBuilding\TenderReports;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\ReceiptLib;

/**
  @class GeorgeStreetTenderReport
  Generate a tender report
*/
class GeorgeStreetTenderReport extends TenderReport {

static protected $print_handler;

static public function setPrintHandler($ph)
{
	self::$print_handler = $ph;
}

/** 
 Print a tender report
 */
static public function get($session)
{
	$receipt = ReceiptLib::biggerFont("Transaction Summary")."\n\n";
	$receipt .= ReceiptLib::biggerFont(date('D M j Y - g:ia'))."\n\n";
	$report_params = array();

	$lane_db = Database::tDataConnect();
	$lane_dbname = $session->get('tDatabase'); // 'lane';

	if ($lane_db->isConnected('core_translog')) {
		$this_lane = $session->get('laneno');
		$opdata_dbname = 'core_opdata';
		
// 		$transarchive = 'localtrans'; // gets too large if overnight truncation never takes place
		$transarchive = 'localtranstoday'; // truncated each night, may show no data if we run reports late

		$report_params += array(
			"Lane {$this_lane} tender" => "
					SELECT
						'Lane {$this_lane} tenders' Plural,
						DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
						t.TenderName GroupLabel,
						COUNT(*) GroupQuantity,
						'transaction' GroupQuantityLabel,
						-SUM(d.total) GroupValue
					FROM {$transarchive} d
						LEFT JOIN {$opdata_dbname}.tenders t ON d.trans_subtype = t.TenderCode
					WHERE d.emp_no != 9999 AND d.register_no != 99
						AND d.trans_status != 'X'
						AND d.trans_type = 'T'
						AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
					GROUP BY t.tenderName
					ORDER BY
						FIELD(d.trans_subtype, 'CA', 'CK', 'CC', 'DC', 'EF', d.trans_subtype),
						d.trans_subtype
				",
			);
	}

	if ($this_lane > 1) {
		$receipt .= "\n";
		$receipt .= ReceiptLib::boldFont();
		$receipt .= "Printing lane data only.";
		$receipt .= ReceiptLib::normalFont();
		$receipt .= "\n";

		$office_db = $lane_db;
		$office_dbname = $lane_dbname;
		$opdata_dbname = 'core_opdata';
// 		$transarchive = 'localtrans'; // gets too large if overnight truncation never takes place
		$transarchive = 'localtranstoday'; // truncated each night, may show no data if we run reports late
	}
	else {
		$office_host = $session->get('mServer'); // hostname or IP
		$office_dbms = $session->get('mDBMS');
		$office_dbname = $session->get('mDatabase'); // database name e.g. 'office';

		$office_fail = false;

		// check that we're even routable to the office server
		$office_ping = shell_exec("ping -c3 -i.2 -t2 -q {$office_host}"); // 3 pings .2sec apart, TTL=2, quiet
		if (preg_match('~0 packets received~', $office_ping))
			$office_fail = 'not responding to ping';
	
		// if rouatable, check that the office server is accepting MySQL connections
		if (!$office_fail) {
			ini_set('default_socket_timeout', 3); // sets timeout for pingport()'s call to stream_socket_client()
			if (!MiscLib::pingport($office_host, $office_dbms))
				$office_fail = "refused {$office_dbms} TCP connect";
		}

		// if office server is accepting MySQL connections, attempt connection
		if (!$office_fail) {
			$office_db = Database::mDataConnect();
			if (!$office_db->isConnected($office_dbname))
				$office_fail = "refused {$office_dbname} access";
		}

		if ($office_fail) {
			$receipt .= "\n";
			$receipt .= ReceiptLib::boldFont();
			$receipt .= "{$office_host} {$office_fail}.\n";
			$receipt .= "Printing lane data only.";
			$receipt .= ReceiptLib::normalFont();
			$receipt .= "\n";

			$office_db = $lane_db;
			$office_dbname = $lane_dbname;
			$opdata_dbname = 'core_opdata';

// 			$transarchive = 'localtrans'; // gets too large if overnight truncation never takes place
			$transarchive = 'localtranstoday'; // truncated each night, may show no data if we run reports late
		}
		else {
			$opdata_dbname = 'office_opdata';

// 			$transarchive = 'dtransactions'; // truncated each night, may show no data if we run reports late
			$transarchive = 'transarchive'; // has last 90 days of data
		}

		$report_params += array(
			'department' => "
						SELECT
							'departments' Plural,
							DATE_FORMAT(d.datetime, '%Y-%m-%d') TransDate,
							CONCAT_WS(' ', t.dept_no, t.dept_name) GroupLabel,
							SUM(IF(d.department IN (102, 113) OR d.scale = 1, 1, d.quantity)) GroupQuantity,
							'item' GroupQuantityLabel,
							SUM(d.total) GroupValue
						FROM {$transarchive} d
							LEFT JOIN {$opdata_dbname}.departments t ON d.department=t.dept_no
						WHERE d.emp_no != 9999 AND d.register_no != 99
							AND d.trans_status != 'X'
							AND d.department != 0
							AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
						GROUP BY t.dept_no
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
							AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
						GROUP BY (total = 0)
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
							AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
						GROUP BY percentDiscount
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
							LEFT JOIN {$opdata_dbname}.tenders t ON d.trans_subtype = t.TenderCode
						WHERE d.emp_no != 9999 AND d.register_no != 99
							AND d.trans_status != 'X'
							AND d.trans_type = 'T'
							AND DATE_FORMAT(d.datetime, '%Y-%m-%d') = (SELECT MAX(DATE_FORMAT(datetime, '%Y-%m-%d')) FROM {$transarchive})
						GROUP BY t.tenderName
						ORDER BY
							FIELD(d.trans_subtype, 'CA', 'CK', 'CC', 'DC', 'EF', d.trans_subtype),
							d.trans_subtype
					",
			);
	}

	foreach ($report_params as $report => $query) {
		$receipt .= "\n";

		$is_office_query = !preg_match('~^Lane ~', $report);

		$receipt .= ReceiptLib::boldFont();
		$receipt .= ReceiptLib::centerString(ucwords($report).' Report')."\n";
		$receipt .= ReceiptLib::normalFont();

		try {
			if ($is_office_query)
				$result = $office_db->query($query);
			else
				$result = $lane_db->query($query);
			$rows = $result->GetAll();
		}
		catch (Exception $e) {
			$receipt .= "$report error: ".$e->getMessage()."\n\n";
			continue;
		}

		$total_quantity = $total_value = 0;
		$plural = $group_label = $group_quantity = $group_quantity_label = $group_value = '';

		foreach ($rows as $row) {
			$plural = $row['Plural'];
			$group_label = $row['GroupLabel'];
			$group_quantity = $row['GroupQuantity'];
			$group_quantity_label = $row['GroupQuantityLabel'];
			$group_value = $row['GroupValue'];

			$total_quantity += $group_quantity;
			$total_value += $group_value;

			$group_quantity = rtrim(number_format($group_quantity, 3), '.0');
			$group_value = number_format($group_value, 2);

			$receipt .= ReceiptLib::boldFont();
			$receipt .= "{$group_label}: ";
			$receipt .= ReceiptLib::normalFont();
			$receipt .= "\${$group_value} from {$group_quantity} {$group_quantity_label}".($group_quantity==1?'':'s')."\n";
		}
		if ($is_office_query)
			$total_values[$report] = $total_value;

		$total_quantity = rtrim(number_format($total_quantity, 3), '.0');
		$total_value = number_format($total_value, 2);

		$receipt .= ReceiptLib::boldFont();
		if ($plural)
			$receipt .= "All ".ucwords($plural).": \${$total_value} from {$total_quantity} {$group_quantity_label}".($total_quantity==1?'':'s')."\n";
		else
			$receipt .= "No matching data found in ".($is_office_query? $office_dbname : $lane_dbname)."\n";
		$receipt .= ReceiptLib::normalFont();
	} // foreach ($report_params as $report => $query)

	$checksum = 0;
	$receipt .= "\n";
	foreach ($total_values as $report => $total_value) {
		switch ($report) {
			case 'department':
			case 'tax':
				$sign = +1;
				break;
			case 'discount':
			case 'tender':
				$sign = -1;
				break;
			default:
				continue;
		}
		$checksum += ($sign * $total_value);
		$total_value = number_format($total_value, 2);

		$receipt .= "\n";
		$receipt .= str_repeat(' ', 8);
		$receipt .= ReceiptLib::boldFont();
		$receipt .= ucwords($report).' Total:';
		$receipt .= ReceiptLib::normalFont();
		$receipt .= str_repeat(' ', 32 - strlen("{$report}{$total_value}"));
		$receipt .= ($sign < 0? '-' : '+') . " \${$total_value}";
	} // foreach ($total_values as $report => $total_value)

	if ($sign) {
		$checksum = number_format($checksum, 2);
		if ($checksum === '-0.00') $checksum = '0.00'; // remove possible floating point sign error

		$receipt .= "\n";
		$receipt .= str_repeat(' ', 38);
		$receipt .= str_repeat('_', 14);
		$receipt .= "\n";
		$receipt .= str_repeat(' ', 8);
		$receipt .= ReceiptLib::boldFont();
		$receipt .= 'Checksum (should be zero):';
		$receipt .= str_repeat(' ', 15 - strlen("{$checksum}"));
		$receipt .= "\${$checksum}";
		$receipt .= ReceiptLib::normalFont();
	}

	$receipt .= "\n";
	$receipt .= "\n";
	$receipt .= ReceiptLib::centerString("------------------------------------------------------");
	$receipt .= "\n";

	$receipt .= str_repeat("\n", 4);
	$receipt .= chr(27).chr(105); // cut

	return $receipt;
}

}
