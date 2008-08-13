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
    /* Modify the schema to match current needs */
    /********************************************/

    /* Delete folders needs to support nulls in the job_upload_fk */
    $DB->Action("ALTER TABLE job
		 ALTER COLUMN job_upload_fk DROP NOT NULL,
		 ALTER COLUMN job_upload_fk SET DEFAULT NULL;");


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

    /***************************  release 0.7.0  ********************************/

    /*********************************************************************/
    /* Add attrib table indexes.  These are heavily used by pkgmetagetta */
    /*********************************************************************/
    $DB->Action("CREATE INDEX attrib_key_fk_idx ON attrib USING btree (attrib_key_fk)");
    $DB->Action("CREATE INDEX attrib_pfile_fk_idx ON attrib USING btree (pfile_fk)");

    /***************************  after 0.7.0  ********************************/

    /* The mimetype agent had a bug where some mimetypes contain
       commas or are missing the meta format ("class/type").
       This happens because magic() lies -- sometimes it returns
       strings that are not in the meta format, even when the meta
       format is specified.
       Also, there was a typo "application/octet-string" should be
       "application/octet-stream".
       Check for these errors and fix these now.
     */
    $CheckMime = "SELECT mimetype_pk FROM mimetype WHERE mimetype_name LIKE '%,%' OR mimetype_name NOT LIKE '%/%' OR mimetype_name = 'application/octet-string'";
    $BadMime = $DB->Action($CheckMime);
    if (count($BadMime) > 0)
      {
      /* Determine if ANY need to be fixed. */
      $BadPfile = $DB->Action("SELECT COUNT(*) AS count FROM pfile WHERE pfile_mimetypefk IN ($CheckMime);");
      print "Due to a previous bug (now fixed), " . number_format($BadPfile['count'],0,"",",") . " files are associated with " . number_format(count($BadMime),0,"",",") . " bad mimetypes.  Fixing now.\n";
      $DB->Action("UPDATE pfile SET pfile_mimetypefk = NULL WHERE pfile_mimetypefk IN ($CheckMime);");
      $DB->Action("DELETE FROM mimetype WHERE mimetype_name LIKE '%,%' OR mimetype_name NOT LIKE '%/%' OR mimetype_name = 'application/octet-string';");
      // $DB->Action("VACUUM ANALYZE mimetype;");
      /* Reset all mimetype analysis -- the ones that are done will be skipped.
         The ones that are not done will be re-done. */
      if ($BadPfile['count'] > 0)
        {
        print "  Rescheduling all mimetype analysis jobs.\n";
        print "  (The ones that are completed will be quickly closed with no additional work.\n";
        print "  Only the files that need to be re-scanned will be re-scanned.)\n";
        $DB->Action("UPDATE jobqueue SET jq_starttime=NULL,jq_endtime=NULL,jq_end_bits=0 WHERE jq_type = 'mimetype';");
	}
      }

    /***************************  release 1.0  ********************************/
    /* if pfile_fk or ufile_mode don't exist in table uploadtree 
      * create them and populate them from ufile table    
      * Leave the ufile columns there for now  */
     if (!$DB->ColExist("uploadtree", "pfile_fk"))
     {
         $DB->Action( "ALTER TABLE uploadtree ADD COLUMN pfile_fk integer" );
         $DB->Action("UPDATE uploadtree SET pfile_fk=(SELECT pfile_fk FROM ufile WHERE uploadtree.ufile_fk=ufile_pk)");
     }

     /* Move ufile_mode and ufile_name from ufile table to uploadtree table */
     if (!$DB->ColExist("uploadtree", "ufile_mode"))
     {
         $DB->Action( "ALTER TABLE uploadtree ADD COLUMN ufile_mode integer" );
         $DB->Action("UPDATE uploadtree SET ufile_mode=(SELECT ufile_mode FROM ufile WHERE uploadtree.ufile_fk=ufile_pk)");
     }
     if (!$DB->ColExist("uploadtree", "ufile_name"))
     {
         $DB->Action( "ALTER TABLE uploadtree ADD COLUMN ufile_name text" );
         $DB->Action("UPDATE uploadtree SET ufile_text=(SELECT ufile_text FROM ufile WHERE uploadtree.ufile_fk=ufile_pk)");
     }

     /* Make sure lft, rgt are in uploadtree, they will need adj2nest to populate */
     if (!$DB->ColExist("uploadtree", "lft"))
     {
         $DB->Action( "ALTER TABLE uploadtree ADD COLUMN lft integer" );
         $DB->Action( "CREATE INDEX lft_idx ON uploadtree USING btree (lft)");
     }
     if (!$DB->ColExist("uploadtree", "rgt"))
     {
         $DB->Action( "ALTER TABLE uploadtree ADD COLUMN rgt integer" );
     }
     
     /* Ignore errors if contraints already exist */
     $DB->Action( "ALTER TABLE agent_lic_raw ADD PRIMARY KEY (lic_pk)" );
    } // Install()

  }; // class core_schema

$NewPlugin = new core_schema;
$NewPlugin->Initialize();
?>
