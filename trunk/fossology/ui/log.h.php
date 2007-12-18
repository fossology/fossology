<?php
/***********************************************************
 log.h.php
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
***********************************************************/
//require_once("common.h.php");


/**
 * write to the log file
 *
 * @param string (required) $logtype  see $LOGTYPE ("debug", "warning", "error", "fatal")
 * @param string (required) $message
 * @param string (optional) $log_logger usually a function or script name, default 'NULL'
 * @param string (optional) $tablename (must be in table_enum, default 'unknown')
 * @param int    (optional) $log_rec_fk - record key, default 'NULL'
 * @param int    (optional) $log_jq_fk - job queue foreign key, default 'NULL'
 * @param boolean (optional) $printmsg - if true write message to std out
 *
 * Return the primary key of the new log record
 */
function log_write($logtype, $message, $log_logger='NULL', 
                   $tablename='Unknown', $log_rec_fk='NULL', $log_jq_fk='NULL', $printmsg=false)
{
    global $LOGTYPE, $TABLEENUM;

    if ($printmsg) echo "$message";

    $logrec = array("log_table_enum"=>$TABLEENUM[$tablename], 
                    "log_type"=>$LOGTYPE[$logtype],
                    "log_message"=>$message);

    if ($log_jq_fk != 'NULL') $logrec['log_jq_fk'] = $log_jq_fk;
    if ($log_rec_fk != 'NULL') $logrec['log_rec_fk'] = $log_rec_fk;
    if ($log_logger != 'NULL') $logrec['log_logger'] = $log_logger;

    return db_insert("log", $logrec, "log_log_pk_seq");
}


/**
 * write to the log file and die with a backtrace
 *
 * @param string (required) $logtype  see $LOGTYPE ("debug", "warning", "error", "fatal")
 * @param string (required) $message
 * @param string (optional) $log_logger usually a function or script name, default 'NULL'
 * @param string (optional) $tablename (must be in table_enum, default 'unknown')
 * @param int    (optional) $log_rec_fk - record key, default 'NULL'
 * @param int    (optional) $log_jq_fk - job queue foreign key, default 'NULL'
 *
 * Return void
 */
function log_writedie($logtype, $message, $log_logger='NULL', 
                   $tablename='Unknown', $log_rec_fk='NULL', $log_jq_fk='NULL')
{

    log_write($logtype, $message, $log_logger='NULL', 
                 $tablename='Unknown', $log_rec_fk='NULL', $log_jq_fk='NULL');
    echo "<pre>";
    debug_print_backtrace(); 
    echo "</pre>\n";
    exit(1);
}

?>
