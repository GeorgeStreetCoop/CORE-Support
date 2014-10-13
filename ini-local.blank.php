<?php
/*
	COPY / RENAME TO ini-local.php
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

$CORE_LOCAL->set('store_id',1, True);
$CORE_LOCAL->set('store','GeorgeStreetCoop', True);
$CORE_LOCAL->set('laneno',1, True);


/************************************************************************************
Data Connection Settings
************************************************************************************/
$CORE_LOCAL->set('mDBMS','pdomysql', True);
				// Options: mssql, mysql, pgsql
$CORE_LOCAL->set('mServer','localhost', True);
$CORE_LOCAL->set('mUser','pos', True);
$CORE_LOCAL->set('mPass','', True);
$CORE_LOCAL->set('mDatabase','core_server', True);

$CORE_LOCAL->set('DBMS','pdomysql', True);
$CORE_LOCAL->set('localhost','localhost', True);
$CORE_LOCAL->set('localUser','pos', True);
$CORE_LOCAL->set('localPass','', True);
$CORE_LOCAL->set('tDatabase','core_translog', True);
$CORE_LOCAL->set('pDatabase','core_opdata', True);


/***********************************************************************************
Receipt & Printer Settings
************************************************************************************/

$CORE_LOCAL->set('print',1, True);
$CORE_LOCAL->set('newReceipt',1, True);
$CORE_LOCAL->set('printerPort','/dev/ttyS1', True);


/***********************************************************************************
Screen Message Settings
************************************************************************************/

$CORE_LOCAL->set('trainingMsgCount',0, True);
$CORE_LOCAL->set('alertBar','Lane 1', True);


/***********************************************************************************
Credit Card
************************************************************************************/

$CORE_LOCAL->set('ccLive',0, True);


/***********************************************************************************
Other Settings
************************************************************************************/

$CORE_LOCAL->set('lockScreen',1, True);
$CORE_LOCAL->set('scalePort','/dev/ttyS0', True);
$CORE_LOCAL->set('timeout','180000', True);
