<?php
/*******************************************************************************

    Copyright 2001, 2004 Wedge Community Co-op

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
    in the file license.txt along with CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if (!isset($CORE_LOCAL))
	require_once(realpath(dirname(__FILE__).'/lib/LocalStorage/conf.php'));

/**
  Optionally define register number and
  store number for all lanes by IP address.
  Add additional lines for other IPs
*/
$CORE_LOCAL->set('LaneMap', array(
    '192.168.1.51' => array('register_id' => 1, 'store_id' => 1),
    '192.168.1.52' => array('register_id' => 2, 'store_id' => 1),
    '192.168.1.53' => array('register_id' => 3, 'store_id' => 1),
));

/************************************************************************************
General Settings
************************************************************************************/

$CORE_LOCAL->set('discountEnforced',1, True);
$CORE_LOCAL->set('DiscountModule','DiscountModule', True);
$CORE_LOCAL->set('refundDiscountable',1, True);
$CORE_LOCAL->set('LineItemDiscountMem',0, True);
$CORE_LOCAL->set('LineItemDiscountNonMem',0, True);
$CORE_LOCAL->set('defaultNonMem',99999, True);
$CORE_LOCAL->set('RestrictDefaultNonMem',0, True);
$CORE_LOCAL->set('visitingMem','', True);
$CORE_LOCAL->set('memlistNonMember',0, True);
$CORE_LOCAL->set('useMemTypeTable',0, True);
$CORE_LOCAL->set('BottleReturnDept','', True);
$CORE_LOCAL->set('enableFranking',0, True);
$CORE_LOCAL->set('kickerModule','Kicker', True);
$CORE_LOCAL->set('dualDrawerMode',0, True);
$CORE_LOCAL->set('scaleDriver','ssd', True);
$CORE_LOCAL->set('screenLines',11, True);
$CORE_LOCAL->set('FooterModules',array('SavedOrCouldHave','TransPercentDiscount','MemSales','EveryoneSales','MultiTotal'), True);
$CORE_LOCAL->set('Notifiers',array(), True);
$CORE_LOCAL->set('touchscreen',0, True);
$CORE_LOCAL->set('CustomerDisplay',0, True);
$CORE_LOCAL->set('member_subtotal',1, True);
$CORE_LOCAL->set('TotalActions',array(), True);
$CORE_LOCAL->set('cashOverLimit',0, True);
$CORE_LOCAL->set('dollarOver',0, True);
$CORE_LOCAL->set('fntlDefault',1, True);
$CORE_LOCAL->set('TenderReportMod','DefaultTenderReport', True);
$CORE_LOCAL->set('TenderMap',array(), True);
$CORE_LOCAL->set('ReceiptDriver','ESCPOSPrintHandler', True);
$CORE_LOCAL->set('emailReceiptFrom','', True);
$CORE_LOCAL->set('RBFetchData','DefaultReceiptDataFetch', True);
$CORE_LOCAL->set('RBFilter','DefaultReceiptFilter', True);
$CORE_LOCAL->set('RBSort','DefaultReceiptSort', True);
$CORE_LOCAL->set('RBTag','DefaultReceiptTag', True);
$CORE_LOCAL->set('ReceiptMessageMods',array(), True);
$CORE_LOCAL->set('UpcIncludeCheckDigits',0, True);
$CORE_LOCAL->set('EanIncludeCheckDigits',0, True);
$CORE_LOCAL->set('ItemNotFound','ItemNotFound', True);
$CORE_LOCAL->set('SpecialUpcClasses',array(), True);
$CORE_LOCAL->set('houseCouponPrefix','00499999', True);
$CORE_LOCAL->set('CouponsAreTaxable',1, True);
$CORE_LOCAL->set('EquityDepartments',array('0'), True);
$CORE_LOCAL->set('ArDepartments',array('0'), True);
$CORE_LOCAL->set('roundUpDept',701, True);
$CORE_LOCAL->set('DiscountTypeClasses',array(), True);
$CORE_LOCAL->set('PriceMethodClasses',array(), True);
$CORE_LOCAL->set('DiscountableSaleItems',1, True);
$CORE_LOCAL->set('SpecialDeptMap',array(), True);
$CORE_LOCAL->set('VariableWeightReWriter','ZeroedPriceReWrite', True);
$CORE_LOCAL->set('LoudLogins',1, True);
$CORE_LOCAL->set('SecurityCancel',20, True);
$CORE_LOCAL->set('SecuritySR',20, True);
$CORE_LOCAL->set('SecurityTR',20, True);
$CORE_LOCAL->set('SecurityRefund',20, True);
$CORE_LOCAL->set('SecurityLineItemDiscount',20, True);
$CORE_LOCAL->set('VoidLimit',0, True);
$CORE_LOCAL->set('Debug_CoreLocal',0, True);
$CORE_LOCAL->set('Debug_Redirects',0, True);

@include_once(dirname(__FILE__).'/ini-local.php');
