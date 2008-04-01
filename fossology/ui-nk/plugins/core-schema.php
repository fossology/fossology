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

    /********************************************/
    /* Sequences can get out of sequence due to the fossologyinit.sql code.
       Fix the sequences! */
    /********************************************/
    /** SQL = all table + column + default value that use sequences **/
    $SQL = "SELECT a.table_name AS table,
	b.column_name AS column,
	b.column_default AS value
	FROM information_schema.tables AS a
	INNER JOIN information_schema.columns AS b
	  ON b.table_name = a.table_name
	WHERE a.table_type = 'BASE TABLE'
	AND a.table_schema = 'public'
	AND b.column_default LIKE 'nextval(%)'
	;";
    $Tables = $DB->Action($SQL);
    for($i=0; !empty($Tables[$i]['table']); $i++)
	{
	/* Reset each sequence to the max value in the column. */
	$Seq = $Tables[$i]['value'];
	$Seq = preg_replace("/.*'(.*)'.*/",'$1',$Seq);
	$Table = $Tables[$i]['table'];
	$Column = $Tables[$i]['column'];
	$Results = $DB->Action("SELECT max($Column) AS max FROM $Table LIMIT 1;");
	$Max = $Results[0]['max'];
	if (empty($Max)) { $Max = 1; }
	// print "Setting table($Table) column($Column) sequence($Seq) to $Max\n";
	$DB->Action("SELECT setval('$Seq',$Max);");
	}

    /********************************************/
    /* GetRunnable() is a DB function for listing the runnable items
       in the jobqueue. This is used by the scheduler. */
    /********************************************/
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
//    $DB->Action($GetRunnable);

    /********************************************/
    /* Have the scheduler initialize all agents */
    /* This only works without SSH. */
    /********************************************/
    // global $AGENTDIR;
    // print "Initializing the scheduler\n";
    // system("$AGENTDIR/scheduler -i");
    // print "Testing the scheduler\n";
    // system("$AGENTDIR/scheduler -t");
    } // Install()

  };
$NewPlugin = new core_schema;
$NewPlugin->Initialize();
?>
