<?php
/*
	COPY / RENAME TO ini.php
	MOSTLY SANE DEFAULTS
*/

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


/************************************************************************************
General Settings
************************************************************************************/

$CORE_LOCAL->set('OS','other', True);
$CORE_LOCAL->set('browserOnly',0, True);
$CORE_LOCAL->set('store','GeorgeStreetCoop', True);


/***********************************************************************************
Receipt & Printer Settings
************************************************************************************/

$CORE_LOCAL->set('newReceipt',1, True);

$CORE_LOCAL->set('receiptHeaderCount',0, True);
$CORE_LOCAL->set('receiptFooterCount',0, True);
$CORE_LOCAL->set('ckEndorseCount',0, True);
$CORE_LOCAL->set('chargeSlipCount',0, True);


/***********************************************************************************
Screen Message Settings
************************************************************************************/

$CORE_LOCAL->set('welcomeMsgCount',0, True);
$CORE_LOCAL->set('farewellMsgCount',0, True);


/***********************************************************************************
Credit Card
************************************************************************************/

$CORE_LOCAL->set('CCintegrate',0, True);
$CORE_LOCAL->set('gcIntegrate',0, True);
$CORE_LOCAL->set('RegisteredPaycardClasses',array('GoEMerchant'), True);


/***********************************************************************************
Other Settings
************************************************************************************/

$CORE_LOCAL->set('discountEnforced',1, True);

$CORE_LOCAL->set('memlistNonMember',0, True);
$CORE_LOCAL->set('cashOverLimit',1, True);
$CORE_LOCAL->set('dollarOver',50, True);
$CORE_LOCAL->set('defaultNonMem','11', True);

//$CORE_LOCAL->set('SigCapture','', True);
$CORE_LOCAL->set('SigCapture','', True);
$CORE_LOCAL->set('visitingMem','5', True);
$CORE_LOCAL->set('scaleDriver','ssd', True);
$CORE_LOCAL->set('CCSigLimit',0, True);
$CORE_LOCAL->set('SpecialUpcClasses',array(), True);
$CORE_LOCAL->set('DiscountTypeCount',5, True);
$CORE_LOCAL->set('DiscountTypeClasses',array('NormalPricing','EveryoneSale','MemberSale','EveryoneSale','StaffSale'), True);
$CORE_LOCAL->set('PriceMethodCount',3, True);
$CORE_LOCAL->set('PriceMethodClasses',array('BasicPM','GroupPM','QttyEnforcedGroupPM'), True);
$CORE_LOCAL->set('enableFranking',0, True);
$CORE_LOCAL->set('BottleReturnDept','', True);
$CORE_LOCAL->set('ReceiptHeaderImage','', True);
$CORE_LOCAL->set('TRDesiredTenders',array(), True);
$CORE_LOCAL->set('DrawerKickMedia', array(), True);

$CORE_LOCAL->set('DiscountModule','DiscountModule', True);
$CORE_LOCAL->set('refundDiscountable',1, True);
$CORE_LOCAL->set('LineItemDiscountMem','0.000000', True);
$CORE_LOCAL->set('LineItemDiscountNonMem','0.000000', True);
$CORE_LOCAL->set('kickerModule','Kicker', True);
$CORE_LOCAL->set('dualDrawerMode',0, True);
$CORE_LOCAL->set('FooterModules',array('SavedOrCouldHave','TransPercentDiscount','MemSales','EveryoneSales','MultiTotal'), True);
$CORE_LOCAL->set('touchscreen',True, True);
$CORE_LOCAL->set('CustomerDisplay',0, True);
$CORE_LOCAL->set('ModularTenders','0', True);
$CORE_LOCAL->set('TenderReportMod','DefaultTenderReport', True);
$CORE_LOCAL->set('TenderMap',array(), True);
$CORE_LOCAL->set('member_subtotal',True, True);
$CORE_LOCAL->set('SpecialDeptMap',array(), True);
$CORE_LOCAL->set('memberUpcPrefix','', True);
$CORE_LOCAL->set('PluginList',array('AllItemSearch','AlwaysFoodstampTotal','MemberCard','PriceCheck','QuickKeys','QuickMenus','VirtualCoupon'), True);

@include_once(dirname(__FILE__).'/ini-local.php');
