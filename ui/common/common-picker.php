<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/*****************************************
 PickGarbage: 
   Do garbage collection on table file_picker.
   History that hasn't been accessed (picked) in the last $ExpireDays is deleted.

   This executes roughly every $ExeFreq times
   it is called.

 Params: None

 Returns: None
 *****************************************/
function PickGarbage()
{
  global $PG_CONN;
  $ExpireDays = 60;  // max days to keep in pick history
  $ExeFreq = 100;

  if ( rand(1,$ExeFreq) != 1) return;

  $sql = "delete from file_picker where last_access_date < (now() - interval '$ExpireDays days')";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
}
?>
