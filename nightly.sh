# office_trans transaction tables
#   dlog_15: dlog_90_view from past 15 days [TransArchiveTask->reloadDlog15($sql)]
#   dtransactions: today's transactions
#   transarchive: everything from past 92 days [TransArchiveTask->rotateQuarter($sql, $dates)]

# office_trans transaction views
#   dlog: dtransactions where datetime >= curdate() and trans_status not in (D, X, Z) and emp_no != 9999 and register_no != 99
#   dlog_90_view: transarchive where trans_status not in (D, X, Z) and emp_no != 9999 and register_no != 99

# office_trans_archive transaction tables
#   bigarchive: seems to be everything (3200)

# office_trans_archive transaction views
#   dlogbig: bigarchive where trans_status not in (D, X, Z) and emp_no != 9999 and register_no != 99



# products -> productBackup; custdata -> custdataBackup
php ../CORE-POS/fannie/classlib2.0/FannieTask.php TableSnapshotTask

# dtransactions ∂-> dlog_15, transarchive, bigArchive
php ../CORE-POS/fannie/classlib2.0/FannieTask.php TransArchiveTask

# dlog(dtransactions) -> dlog_15
# php ../CORE-POS/fannie/classlib2.0/FannieTask.php SameDayReportingTask

# dlog_15 -> stockpurchases, equity_history_sum
# php ../CORE-POS/fannie/classlib2.0/FannieTask.php EquityHistoryTask

# must run after Transaction Archiving, per http://github.com/CORE-POS/IS4C/wiki/Charge-Accounts
php ../CORE-POS/fannie/classlib2.0/FannieTask.php ArHistoryTask

# $dtrans, $dlog -> InventoryCache
php ../CORE-POS/fannie/classlib2.0/FannieTask.php InventoryTask

# push products, productUser, custdata, memberCards, custReceiptMessage, CustomerNotifications, employees, departments, tenders, houseCoupons, houseVirtualCoupons 
# php ../CORE-POS/fannie/classlib2.0/FannieTask.php LaneSyncTask

# $dlog -> products
php ../CORE-POS/fannie/classlib2.0/FannieTask.php LastSoldTask

# $dlog -> weeksLastQuarter, productWeeklyLastQuarter, productSummaryLastQuarter
php ../CORE-POS/fannie/classlib2.0/FannieTask.php ProductSummarizeLastQuarter

# dlog_90_view -> CashPerformDay, CashPerformDay_cache, reportDataCache, batchMergeTable, shelftags, 
php ../CORE-POS/fannie/classlib2.0/FannieTask.php ReportDataCacheTask
