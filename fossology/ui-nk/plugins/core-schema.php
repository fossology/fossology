<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

class core_schema extends FO_Plugin
  {
  var $Name        = "schema";
  var $Title       = "Database Schema";
  var $Version     = "1.0";
  var $Dependency  = array("db");
  var $DBaccess    = PLUGIN_DB_WRITE;

  /******************************************
   This plugin is used to configure the schema.
   Only the 'Install()' function is used.
   ******************************************/

  /******************************************
   Install(): Create and configure the schema.
   ******************************************/
  function Install()
    {
    global $DB;
    if (empty($DB)) { return(1); } /* No DB */

    /* GetRunnable() is a DB function for listing the runnable items
       in the jobqueue. */
    $GetRunnable = '
CREATE or REPLACE function getrunnable() returns setof jobqueue as $$
DECLARE
  jqrec jobqueue;
  jqrec_test jobqueue;
  jqcurse CURSOR FOR SELECT *  from jobqueue where jq_endtime is null and jq_end_bits < 2;
  jdep_row jobdepends;
  success integer;
BEGIN
  open jqcurse;
<<MYLABEL>>
  LOOP
    FETCH jqcurse into jqrec;
    IF FOUND
    THEN -- check all dependencies
      success := 1;
      <<DEPLOOP>>
      FOR jdep_row IN SELECT *  from jobdepends where jdep_jq_fk=jqrec.jq_pk LOOP
        -- has the dependency been satisfied?
        SELECT INTO jqrec_test * from jobqueue where jdep_row.jdep_jq_depends_fk=jq_pk and jq_endtime is not null and jq_end_bits != 2;
        IF NOT FOUND
        THEN
          success := 0;
          EXIT DEPLOOP;
        END IF;
      END LOOP DEPLOOP;

      IF success=1 THEN RETURN NEXT jqrec; END IF;
    ELSE EXIT;
    END IF;
  END LOOP MYLABEL;
RETURN;
END;
$$
LANGUAGE plpgsql;
    ';
    $DB->Action($GetRunnable);
    } // Install()

  };
$NewPlugin = new core_schema;
$NewPlugin->Initialize();
?>
