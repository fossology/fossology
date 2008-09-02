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
  var $DBaccess    = PLUGIN_DB_USERADMIN;
  var $PluginLevel = 100;
  var $MenuList    = "Admin::Database::Schema";
  var $LoginFlag   = 1; /* must be logged in to use this */

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
  jqcurse CURSOR FOR SELECT *  FROM jobqueue WHERE jq_starttime IS NULL AND jq_end_bits < 2;
  jdep_row jobdepends;
  success integer;
BEGIN
  open jqcurse;
<<MYLABEL>>
  LOOP
    FETCH jqcurse INTO jqrec;
    IF FOUND
    THEN -- check all dependencies
      success := 1;
      <<DEPLOOP>>
      FOR jdep_row IN SELECT *  FROM jobdepends WHERE jdep_jq_fk=jqrec.jq_pk LOOP
	-- has the dependency been satisfied?
	SELECT INTO jqrec_test * FROM jobqueue WHERE jdep_row.jdep_jq_depends_fk=jq_pk AND jq_endtime IS NOT NULL AND jq_end_bits < 2;
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
     * Drop the ufile columns */
     if (!$DB->ColExist("upload", "pfile_fk"))
     {
	 $DB->Action("ALTER TABLE upload ADD COLUMN pfile_fk integer;");
	 $DB->Action("UPDATE upload SET pfile_fk = ufile.pfile_fk FROM ufile WHERE upload.pfile_fk IS NULL AND upload.ufile_fk = ufile.ufile_pk;");
     }

     if (!$DB->ColExist("uploadtree", "pfile_fk"))
     {
	 $DB->Action("ALTER TABLE uploadtree ADD COLUMN pfile_fk integer");
	 $DB->Action("UPDATE uploadtree SET pfile_fk = ufile.pfile_fk FROM ufile WHERE uploadtree.pfile_fk IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
     }

     /* Move ufile_mode and ufile_name from ufile table to uploadtree table */
     if (!$DB->ColExist("uploadtree", "ufile_mode"))
     {
	 $DB->Action("ALTER TABLE uploadtree ADD COLUMN ufile_mode integer");
	 $DB->Action("UPDATE uploadtree SET ufile_mode = ufile.ufile_mode FROM ufile WHERE uploadtree.ufile_mode IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
     }
     if (!$DB->ColExist("uploadtree", "ufile_name"))
     {
	 $DB->Action("ALTER TABLE uploadtree ADD COLUMN ufile_name text");
	 $DB->Action("UPDATE uploadtree SET ufile_name = ufile.ufile_name FROM ufile WHERE uploadtree.ufile_name IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
     }

     if ($DB->ColExist("uploadtree", "lft"))
     {
       $DB->Action("ALTER TABLE uploadtree ADD COLUMN lft integer");
       $DB->Action("CREATE INDEX lft_idx ON uploadtree USING btree (lft)");
     }
     if (!$DB->ColExist("uploadtree", "rgt"))
     {
       $DB->Action("ALTER TABLE uploadtree ADD COLUMN rgt integer");
     }

     $DB->Action("ALTER TABLE agent_lic_raw ADD COLUMN lic_tokens integer");
     
     /* Ignore errors if contraints already exist */
     $DB->Action("ALTER TABLE agent_lic_raw ADD PRIMARY KEY (lic_pk)");


     /************ Rebuild folder view ************/
     $DB->Action("DROP VIEW folderlist;");
     $DB->Action("CREATE VIEW folderlist AS
	SELECT folder.folder_pk,
	       folder.folder_name AS name,
	       folder.folder_desc AS description,
	       foldercontents.parent_fk AS parent,
	       foldercontents.foldercontents_mode,
	       NULL::\"unknown\" AS ts,
	       NULL::\"unknown\" AS upload_pk,
	       NULL::\"unknown\" AS pfile_fk,
	       NULL::\"unknown\" AS ufile_mode
	FROM folder, foldercontents
	WHERE foldercontents.foldercontents_mode = 1
	AND foldercontents.child_id = folder.folder_pk
	UNION ALL 
	SELECT NULL::\"unknown\" AS folder_pk,
	       ufile_name AS name,
	       upload.upload_desc AS description,
	       foldercontents.parent_fk AS parent,
	       foldercontents.foldercontents_mode, upload.upload_ts AS ts,
	       upload.upload_pk,
	       uploadtree.pfile_fk,
	       ufile_mode
	FROM upload
	INNER JOIN uploadtree ON upload_pk = upload_fk
	AND parent IS NULL
	INNER JOIN foldercontents ON foldercontents.foldercontents_mode = 2
	AND foldercontents.child_id = upload.upload_pk;");

     /************ Delete old columns and tables ************/
     /* Drop obsolete ufile table, ignore errors */
     $DB->Action("ALTER TABLE uploadtree ALTER COLUMN ufile_fk DROP NOT NULL;");
     $DB->Action("ALTER TABLE uploadtree DROP CONSTRAINT uploadtree_ufilefk;");
     $DB->Action("ALTER TABLE upload ALTER COLUMN ufile_fk DROP NOT NULL;");
     $DB->Action("DROP VIEW leftnav;");
     $DB->Action("DROP VIEW uptreeup;");
     $DB->Action("DROP VIEW uptreeattrib;");
     $DB->Action("DROP VIEW uptreeatkey;");
     $DB->Action("DROP VIEW uplicense;");
     $DB->Action("DROP VIEW lic_progress;");
     $DB->Action("DROP TABLE ufile;");


    /********************************************
     * uploadtree2path(uploadtree_pk integer) is a DB function that returns
     * the non-artifact parents of an uploadtree_pk
     ********************************************/
    $sql = '
CREATE or REPLACE function uploadtree2path(uploadtree_pk_in int) returns setof uploadtree as $$ 
DECLARE
  UTrec   uploadtree;
  UTpk    integer;
  sql     varchar;
BEGIN

  UTpk := uploadtree_pk_in;

    WHILE UTpk > 0 LOOP
      sql := \'select * from uploadtree where uploadtree_pk=\' || UTpk;
      execute sql into UTrec;
    
      IF ((UTrec.ufile_mode & (1<<28)) = 0) THEN RETURN NEXT UTrec; END IF;
      UTpk := UTrec.parent;
    END LOOP;
  RETURN;
END;
$$ 
LANGUAGE plpgsql;
    ';
    $DB->Action($sql);

     /* Create the report_cache table */
    $sql = ' 
CREATE TABLE report_cache (
    report_cache_pk serial NOT NULL,
    report_cache_tla timestamp without time zone DEFAULT now() NOT NULL,
    report_cache_key text NOT NULL,
    report_cache_value text NOT NULL,
    report_cache_uploadfk integer
);
ALTER TABLE ONLY report_cache ADD CONSTRAINT report_cache_pkey PRIMARY KEY (report_cache_pk);
ALTER TABLE ONLY report_cache ADD CONSTRAINT report_cache_report_cache_key_key UNIQUE (report_cache_key);
CREATE INDEX report_cache_tlats ON report_cache USING btree (report_cache_tla);
    ';
    $DB->Action($sql);

    /* Create the report_cache_user table 
     * This allows the cache to be turned off/on on a per user basis 
     */
    $sql = ' 
CREATE TABLE report_cache_user (
    report_cache_user_pk serial NOT NULL,
    user_fk integer NOT NULL,
    cache_on character(1) DEFAULT \'Y\'::bpchar NOT NULL
);
COMMENT ON TABLE report_cache_user IS \'Allow the report cached to be turned off for individual users.  This is mostly for developers.\';
COMMENT ON COLUMN report_cache_user.cache_on IS \'Y or N\';
ALTER TABLE ONLY report_cache_user
    ADD CONSTRAINT report_cache_user_pkey PRIMARY KEY (report_cache_user_pk);
    ';
    $DB->Action($sql);

     /* Make sure every upload has left and right indexes set. */
     global $LIBEXECDIR;
     system("$LIBEXECDIR/agents/adj2nest -a");
    } // Install()

  /******************************************
   GetSchema(): Load the schema into an array.
   ******************************************/
  function GetSchema()
    {
    global $DB;
    if (empty($DB)) { return(1); } /* No DB */

    $Schema = array();

    /***************************/
    /* Get the tables */
    /***************************/
    $SQL = "SELECT class.relname AS table,
	attr.attnum AS ordinal,
	attr.attname AS column_name,
	type.typname AS type,
	attr.atttypmod-4 AS modifier,
	attr.attnotnull AS notnull,
	attr.atthasdef AS hasdefault,
	attrdef.adsrc AS default,
	col_description(attr.attrelid, attr.attnum) AS description
	FROM pg_class AS class
	INNER JOIN pg_attribute AS attr ON attr.attrelid = class.oid
	AND attr.attnum > 0
	INNER JOIN pg_type AS type ON attr.atttypid = type.oid
	INNER JOIN information_schema.tables AS tab ON class.relname = tab.table_name
	AND tab.table_type = 'BASE TABLE'
	AND tab.table_schema = 'public'
	LEFT OUTER JOIN pg_attrdef AS attrdef ON adrelid = attrelid
	AND adnum = attnum
	ORDER BY class.relname,attr.attnum;
	";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['table']); $i++)
      {
      $R = &$Results[$i];
      $Table = $R['table'];
      if (preg_match('/[0-9]/',$Table)) { continue; }
      $Column = $R['column_name'];
      $Type = $R['type'];
      if ($R['modifier'] > 0) { $Type .= '[' . $R['modifier'] . ']'; }
      $Desc = str_replace("'","''",$R['description']);

      $Schema['TABLEID'][$Table][$R['ordinal']] = $Column;
      if (!empty($Desc))
        {
        $Schema['TABLE'][$Table][$Column]['DESC'] = "COMMENT ON COLUMN $Table.$Column IS '$Desc'";
	}
      else
        {
	$Schema['TABLE'][$Table][$Column]['DESC'] = "";
	}
      $Schema['TABLE'][$Table][$Column]['ADD'] = "ALTER TABLE $Table ADD COLUMN $Column $Type";
      $Schema['TABLE'][$Table][$Column]['ALTER'] = "ALTER TABLE $Table ALTER COLUMN $Column TYPE $Type";
      if ($R['notnull'] == 't') { $Schema['TABLE'][$Table][$Column]['ALTER'] .= " SET NOT NULL"; }
      else { $Schema['TABLE'][$Table][$Column]['ALTER'] .= " DROP NOT NULL"; }
      if ($R['hasdefault'] == 't') { $Schema['TABLE'][$Table][$Column]['ALTER'] .= " SET DEFAULT " . $R['default']; }
      else { $Schema['TABLE'][$Table][$Column]['ALTER'] .= " DROP DEFAULT"; }
      }

    /***************************/
    /* Get Views */
    /***************************/
    $SQL = "SELECT viewname,definition FROM pg_views WHERE viewowner = 'fossy';";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['viewname']); $i++)
      {
      $Schema['VIEW'][$Results[$i]['viewname']] = $Results[$i]['definition'];
      }

    /***************************/
    /* Get Index */
    /***************************/
    $SQL = "SELECT tablename AS table, indexname AS index, indexdef AS define
	FROM pg_indexes
	INNER JOIN information_schema.tables ON table_name = tablename
	AND table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND schemaname = 'public'
	ORDER BY tablename,indexname;
	";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['table']); $i++)
      {
      $Schema['INDEX'][$Results[$i]['table']][$Results[$i]['index']] = $Results[$i]['define'];
      }

    /***************************/
    /* Get Constraints */
    /***************************/
    $SQL = "SELECT c.conname AS constraint_name,
	CASE c.contype
		WHEN 'c' THEN 'CHECK'
		WHEN 'f' THEN 'FOREIGN KEY'
		WHEN 'p' THEN 'PRIMARY KEY'
		WHEN 'u' THEN 'UNIQUE'
	END AS type,
	CASE WHEN c.condeferrable = 'f' THEN 0 ELSE 1 END AS is_deferrable,
	CASE WHEN c.condeferred = 'f' THEN 0 ELSE 1 END AS is_deferred,
	t.relname AS table_name, array_to_string(c.conkey, ' ') AS constraint_key,
	CASE confupdtype
		WHEN 'a' THEN 'NO ACTION'
		WHEN 'r' THEN 'RESTRICT'
		WHEN 'c' THEN 'CASCADE'
		WHEN 'n' THEN 'SET NULL'
		WHEN 'd' THEN 'SET DEFAULT'
	END AS on_update,
	CASE confdeltype
		WHEN 'a' THEN 'NO ACTION'
		WHEN 'r' THEN 'RESTRICT'
		WHEN 'c' THEN 'CASCADE'
		WHEN 'n' THEN 'SET NULL'
		WHEN 'd' THEN 'SET DEFAULT' END AS on_delete, CASE confmatchtype
		WHEN 'u' THEN 'UNSPECIFIED'
		WHEN 'f' THEN 'FULL'
		WHEN 'p' THEN 'PARTIAL'
	END AS match_type,
	t2.relname AS references_table,
	array_to_string(c.confkey, ' ') AS fk_constraint_key
	FROM pg_constraint AS c
	LEFT JOIN pg_class AS t ON c.conrelid = t.oid
	INNER JOIN information_schema.tables AS tab ON t.relname = tab.table_name
	LEFT JOIN pg_class AS t2 ON c.confrelid = t2.oid
	ORDER BY constraint_name,table_name;
	";
    $Results = $DB->Action($SQL);

    /* Constraints use indexes into columns.  Covert those to column names. */
    for($i=0; !empty($Results[$i]['constraint_name']); $i++)
      {
      $Key = "";
      $Keys = split(" ",$Results[$i]['constraint_key']);
      foreach($Keys as $K)
        {
	if (empty($K)) { continue; }
	if (!empty($Key)) { $Key .= ","; }
	$Key .= $Schema['TABLEID'][$Results[$i]['table_name']][$K];
	}
      $Results[$i]['constraint_key'] = $Key;
      $Key = "";
      $Keys = split(" ",$Results[$i]['fk_constraint_key']);
      foreach($Keys as $K)
        {
	if (empty($K)) { continue; }
	if (!empty($Key)) { $Key .= ","; }
	$Key .= $Schema['TABLEID'][$Results[$i]['references_table']][$K];
	}
      $Results[$i]['fk_constraint_key'] = $Key;
      }

    /* Save the constraint */
    for($i=0; !empty($Results[$i]['constraint_name']); $i++)
      {
      $SQL = "ALTER TABLE " . $Results[$i]['table_name'];
      $SQL .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $SQL .= " " . $Results[$i]['type'];
      $SQL .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
        {
	$SQL .= " REFERENCES " . $Results[$i]['references_table'];
        $SQL .= " (" . $Results[$i]['constraint_key'] . ")";
	}
      $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
      }

if (0)
{
    /***************************/
    /* Get Functions */
    /***************************/
    // prosrc
    // proretset == setof
    $SQL = "SELECT proname AS name,
	pronargs AS input_num,
	proargnames AS input_names,
	proargtypes AS input_type,
	proargmodes AS input_modes,
	proretset AS setof,
	prorettype AS output_type
	FROM pg_proc AS proc
	INNER JOIN pg_language AS lang ON proc.prolang = lang.oid
	WHERE lang.lanname = 'plpgsql'
	ORDER BY proname;";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['proname']); $i++)
      {
      $SQL = "CREATE or REPLACE function " . $Results[$i]['proname'] . "()";
      $SQL .= ' RETURNS ' . "TBD" . ' AS $$';
      $SQL .= " " . $Results[$i]['prosrc'];
      $Schema['FUNCTION'][$Results[$i]['proname']] = $SQL;
      }
}

    unset($Schema['TABLEID']);
    return($Schema);
    } // GetSchema()

  /***********************************************************
   ViewSchema(): Get the current schema and display it to the screen.
   ***********************************************************/
  function ViewSchema()
    {
    $Schema = $this->GetSchema();

    print "<ul>\n";
    print "<li><a href='#Table'>Tables</a>\n";
    print "<ol>\n";
    foreach($Schema['TABLE'] as $TableName => $Columns)
      {
      if (empty($TableName)) { continue; }
      print "<li><a href='#Table-$TableName'>$TableName</a>\n";
      }
    print "</ol>\n";
    print "<li><a href='#View'>Views</a>\n";
    print "<li><a href='#Index'>Indexes</a>\n";
    print "<li><a href='#Constraint'>Constraints</a>\n";
    if (count($Schema['FUNCTION']) > 0)
      {
      print "<li><a href='#Function'>Functions</a>\n";
      }
    print "</ul>\n";

    print "<a name='Table'></a><table width='100%' border='1'>\n";
    $LastTableName="";
    foreach($Schema['TABLE'] as $TableName => $Columns)
      {
      if (empty($TableName)) { continue; }
      foreach($Columns as $ColName => $Val)
	{
	if ($LastTableName != $TableName)
	  {
	  print "<tr><th><a name='Table-$TableName'></a>Table<th>Column<th>Description<th>Add SQL<th>Alter SQL\n";
	  $LastTableName = $TableName;
	  }
	if (empty($ColName)) { continue; }
	print "<tr><td>" . htmlentities($TableName);
	print "<td>" . htmlentities($ColName);
	print "<td>" . $Val['DESC'];
	print "<td>" . $Val['ADD'];
	print "<td>" . $Val['ALTER'];
	print "\n";
	}
      }
    print "</table>\n";

    print "<P/>\n";
    print "<a name='View'></a><table width='100%' border='1'>\n";
    print "<th>View<th>Definition\n";
    foreach($Schema['VIEW'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      print "<tr><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    print "</table>\n";

    print "<P/>\n";
    print "<a name='Index'></a><table width='100%' border='1'>\n";
    print "<th>Table<th>Index<th>Definition\n";
    foreach($Schema['INDEX'] as $Table => $Indexes)
      {
      if (empty($Table)) { continue; }
      foreach($Indexes as $Index => $Define)
	{
	print "<tr><td>" . htmlentities($Table);
	print "<td>" . htmlentities($Index);
	print "<td>" . htmlentities($Define);
	}
      }
    print "</table>\n";

    print "<P/>\n";
    print "<a name='Constraint'></a><table width='100%' border='1'>\n";
    print "<th>Constraint<th>Definition\n";
    foreach($Schema['CONSTRAINT'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      print "<tr><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    print "</table>\n";

    if (count($Schema['FUNCTION']) > 0)
      {
      print "<P/>\n";
      print "<a name='Function'></a><table width='100%' border='1'>\n";
      print "<th>Function<th>Definition\n";
      foreach($Schema['FUNCTION'] as $Name => $Description)
        {
        if (empty($Name)) { continue; }
        print "<tr><td>" . htmlentities($Name) . "<td><pre>" . htmlentities($Description) . "</pre>\n";
        }
      print "</table>\n";
      }

    } // ViewSchema()

  /***********************************************************
   ExportSchema(): Export the current schema to a file.
   ***********************************************************/
  function ExportSchema($Filename)
    {
    $Schema = $this->GetSchema();
    $Fout = fopen($Filename,"w");
    if (!$Fout)
	{
	return("Failed to write to $Filename\n");
	}

    fwrite($Fout,"<?php\n");
    fwrite($Fout,"/* This file is generated by " . $this->Name . " */\n");
    fwrite($Fout,"/* Do not manually edit this file */\n\n");
    fwrite($Fout,"  global \$GlobalReady;\n");
    fwrite($Fout,"  if (!isset(\$GlobalReady)) { exit; }\n\n");
    fwrite($Fout,'  $Schema=array();' . "\n");
    foreach($Schema as $K1 => $V1)
      {
      $K1 = str_replace('"','\"',$K1);
      $A1 = '  $Schema["' . $K1 . "\"]";
      if (!is_array($V1))
        {
        $V1 = str_replace('"','\"',$V1);
	fwrite($Fout,"$A1 = \"$V1\";\n");
	}
      else
        {
	foreach($V1 as $K2 => $V2)
	  {
          $K2 = str_replace('"','\"',$K2);
	  $A2 = $A1 . '["' . $K2 . '"]';
          if (!is_array($V2))
            {
            $V2 = str_replace('"','\"',$V2);
	    fwrite($Fout,"$A2 = \"$V2\";\n");
	    }
          else
            {
	    foreach($V2 as $K3 => $V3)
	      {
              $K3 = str_replace('"','\"',$K3);
	      $A3 = $A2 . '["' . $K3 . '"]';
              if (!is_array($V3))
                {
                $V3 = str_replace('"','\"',$V3);
	        fwrite($Fout,"$A3 = \"$V3\";\n");
	        }
	      else
	        {
		foreach($V3 as $K4 => $V4)
		  {
                  $V4 = str_replace('"','\"',$V4);
	          $A4 = $A3 . '["' . $K4 . '"]';
	          fwrite($Fout,"$A4 = \"$V4\";\n");
		  } /* K4 */
		fwrite($Fout,"\n");
		}
	      } /* K3 */
	    fwrite($Fout,"\n");
	    }
	  } /* K2 */
	fwrite($Fout,"\n");
	}
      } /* K1 */
    fwrite($Fout,"?>\n");
    fclose($Fout);
    print "Data written to $Filename\n";
    } // ExportSchema()

  /***********************************************************
   ApplySchema(): Apply the current schema from a file.
   ***********************************************************/
  function ApplySchema($Filename)
    {
    /**************************************/
    /** BEGIN: Term list from ExportTerms() **/
    /**************************************/
    require_once($Filename);
    /**************************************/
    /** END: Term list from ExportTerms() **/
    /**************************************/
    print "<pre>"; print_r($Schema); print "</pre>";
    } // ApplySchema()

  /***********************************************************
   Output(): This function is called when user output is
   requested.  This function is responsible for content.
   (OutputOpen and Output are separated so one plugin
   can call another plugin's Output.)
   This uses $OutputType.
   The $ToStdout flag is "1" if output should go to stdout, and
   0 if it should be returned as a string.  (Strings may be parsed
   and used by other plugins.)
   ***********************************************************/
  function Output()
    {
    global $Plugins;
    $Filename = "plugins/core-schema.dat";

    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V=""; 
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$Init = GetParm('View',PARM_INTEGER);
	if ($Init == 1)
	  {
	  $rc = $this->ViewSchema();
	  if (!empty($rc))
	    {
	    $V .= PopupAlert($rc);
	    }
	  $V .= "<hr>\n";
	  }
	/* Undocumented parameter: Used for exporting the current terms. */
	$Init = GetParm('Export',PARM_INTEGER);
	if ($Init == 1)
	  {
	  $rc = $this->ExportSchema($Filename);
	  if (!empty($rc))
	    {
	    $V .= PopupAlert($rc);
	    }
	  $V .= "<hr>\n";
	  }
	$Init = GetParm('Apply',PARM_INTEGER);
	if ($Init == 1)
	  {
	  $rc = $this->ApplySchema($Filename);
	  if (!empty($rc))
	    {
	    $V .= PopupAlert($rc);
	    }
	  $V .= "<hr>\n";
	  }

	$V .= "<form method='post'>\n";
	$V .= "Viewing, exporting, and applying the schema is only used by installation and debugging.\n";
	$V .= "Otherwise, you should not need to use this functionality.\n";
	$V .= "<P/><b>Using this functionality may <u><i>TOTALLY SCREW UP</i></u> your FOSSology database.</b>\n";

	$V .= "<P/>\n";
	$V .= "<input type='checkbox' value='1' name='View'>Check to view the current schema. (The output generation is harmless, but extremely technical.)<br>\n";
	$V .= "<input type='checkbox' value='1' name='Export'>(TBD) Check to export the current schema.<br>\n";
	$V .= "<input type='checkbox' value='1' name='Apply'>(TBD) Check to apply the last exported schema.\n";
	$V .= "<P/>\n";
	$V .= "<input type='submit' value='Go!'>";
	$V .= "</form>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    } // Output()

  }; // class core_schema

$NewPlugin = new core_schema;
$NewPlugin->Initialize();
?>
