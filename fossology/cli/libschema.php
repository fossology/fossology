<?php
/*
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
 */

/**
 * libschema
 * \brief utility functions needed by the schema* programs
 *
 * @version "$Id$"
 */

function ColExist($Table,$Col)
{
	global $PGCONN;
	$result = pg_query($PGCONN, "SELECT count(*) FROM pg_attribute, pg_type
              WHERE typrelid=attrelid AND typname = '$Table'
          AND attname='$Col' LIMIT 1");
	if ($result)
	{
		$count = pg_fetch_result($result, 0, 0);
		if ($count > 0) { return(1); }
	}
	return(0);
}

// check if a table exists, if not then create it
function CheckCreateTable($Table)
{
	global $PGCONN;
	if (!TblExist($Table))
	{
		$SQL = "CREATE TABLE \"$Table\" ();";
		if ($Debug)
		{
			print "$SQL\n";
		}
		else
		{
			$result = pg_query($PGCONN, $SQL);
			checkresult($result, $SQL, __LINE__);
		}
	}
}

/**
 * checkresult
 * \brief check the result of a query, on error (on result) echo the line number
 * pg last errro and optionally the sql that caused the error.
 *
 * @param array $result
 * @param string $sql
 * @param string $lineno
 * @return true if the result is ok.
 */
function checkresult($result, $sql="", $lineno)
{
	global $PGCONN;

	if (!$result)
	{
		echo "Line number: $lineno\n";
		echo pg_last_error($PGCONN);
		echo "\nSQL:\n$sql\n";
		exit(1);
	}
	return(TRUE);
}
/**
 * dbConnect
 * \brief establish a connection to the database.
 *
 *@param string $Options  an optional list of attributes for connecting to the
 * database. E.g.: "dbname=text host=text user=text password=text"
 *
 * @return $PGCONN on success, dies on failure (no return).
 */
function dbConnect($Options="")
{
	global $DATADIR, $PROJECT, $SYSCONFDIR;
	global $PGCONN;

	if (isset($PGCONN)) { return(TRUE); }
	$path="$SYSCONFDIR/$PROJECT/Db.conf";
	if (empty($Options))
	{
		$PGCONN = pg_pconnect(str_replace(";", " ", file_get_contents($path)));
	}
	else
	{
		$PGCONN = pg_pconnect(str_replace(";", " ", $Options));
	}
	if (!isset($PGCONN)) die ("connection to db failed\n");
	return($PGCONN);
}

/*
 * GetSchema
 * \brief Load the schema from the db into an array.
 */
function GetSchema()
{
	global $PGCONN;
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
	$result = pg_query($SQL);
	checkresult($result, $SQL, __LINE__);
	$Results = pg_fetch_all($result);

	for ($i = 0;!empty($Results[$i]['table']);$i++)
	{
		$R = & $Results[$i];
		$Table = $R['table'];
		if (preg_match('/[0-9]/', $Table)) {
			continue;
		}
		$Column = $R['column_name'];
		$Type = $R['type'];
		if ($Type == 'bpchar') {
			$Type = "char";
		}
		if ($R['modifier'] > 0) {
			$Type.= '(' . $R['modifier'] . ')';
		}
		$Desc = str_replace("'", "''", $R['description']);
		$Schema['TABLEID'][$Table][$R['ordinal']] = $Column;
		if (!empty($Desc)) {
			$Schema['TABLE'][$Table][$Column]['DESC'] = "COMMENT ON COLUMN \"$Table\".\"$Column\" IS '$Desc';";
		}
		else {
			$Schema['TABLE'][$Table][$Column]['DESC'] = "";
		}
		$Schema['TABLE'][$Table][$Column]['ADD'] = "ALTER TABLE \"$Table\" ADD COLUMN \"$Column\" $Type;";
		$Schema['TABLE'][$Table][$Column]['ALTER'] = "ALTER TABLE \"$Table\"";
		$Alter = "ALTER COLUMN \"$Column\"";
		// $Schema['TABLE'][$Table][$Column]['ALTER'] .= " $Alter TYPE $Type";
		if ($R['notnull'] == 't') {
			$Schema['TABLE'][$Table][$Column]['ALTER'].= " $Alter SET NOT NULL";
		}
		else {
			$Schema['TABLE'][$Table][$Column]['ALTER'].= " $Alter DROP NOT NULL";
		}
		if ($R['default'] != '') {
			// $R['default'] = preg_replace("/::.*/","",$R['default']);
			$R['default'] = preg_replace("/::bpchar/", "::char", $R['default']);
			$Schema['TABLE'][$Table][$Column]['ALTER'].= ", $Alter SET DEFAULT " . $R['default'];
		}
		$Schema['TABLE'][$Table][$Column]['ALTER'].= ";";
	}
	//print "GetSchema: Schema after TABLES is:\n";print_r($Schema) . "\n";
	/***************************/
	/* Get Views */
	/***************************/
	$SQL = "SELECT viewname,definition FROM pg_views WHERE viewowner = 'fossy';";
	//$SQL = "SELECT viewname,definition FROM pg_views WHERE viewowner = 'rando';";
	$result = pg_query($SQL);
	checkresult($result, $SQL, __LINE__);
	$Results = pg_fetch_all($result);
	for ($i = 0;!empty($Results[$i]['viewname']);$i++) {
		$SQL = "CREATE VIEW \"" . $Results[$i]['viewname'] . "\" AS " . $Results[$i]['definition'];
		$Schema['VIEW'][$Results[$i]['viewname']] = $SQL;
	}
	/***************************/
	/* Get Sequence */
	/***************************/
	$SQL = "SELECT relname
  FROM pg_class
  WHERE relkind = 'S'
  AND relnamespace IN (
  SELECT oid
  FROM pg_namespace
  WHERE nspname NOT LIKE 'pg_%'
  AND nspname != 'information_schema'
  );";
	$result = pg_query($SQL);
	checkresult($result, $SQL, __LINE__);
	$Results = pg_fetch_all($result);
	for ($i = 0;!empty($Results[$i]['relname']);$i++) {
		$SQL = "CREATE SEQUENCE \"" . $Results[$i]['relname'] . "\" START 1;";
		$Schema['SEQUENCE'][$Results[$i]['relname']] = $SQL;
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
	$result = pg_query($SQL);
	checkresult($result, $SQL, __LINE__);
	$Results = pg_fetch_all($result);
	/* Constraints use indexes into columns.  Covert those to column names. */
	for ($i = 0;!empty($Results[$i]['constraint_name']);$i++) {
		$Key = "";
		$Keys = split(" ", $Results[$i]['constraint_key']);
		foreach($Keys as $K) {
			if (empty($K)) {
				continue;
			}
			if (!empty($Key)) {
				$Key.= ",";
			}
			$Key.= '"' . $Schema['TABLEID'][$Results[$i]['table_name']][$K] . '"';
		}
		$Results[$i]['constraint_key'] = $Key;
		$Key = "";
		$Keys = split(" ", $Results[$i]['fk_constraint_key']);
		foreach($Keys as $K) {
			if (empty($K)) {
				continue;
			}
			if (!empty($Key)) {
				$Key.= ",";
			}
			$Key.= '"' . $Schema['TABLEID'][$Results[$i]['references_table']][$K] . '"';
		}
		$Results[$i]['fk_constraint_key'] = $Key;
	}
	/* Save the constraint */
	/** There are different types of constraints that must be stored in order **/
	/** CONSTRAINT: PRIMARY KEY **/
	for ($i = 0;!empty($Results[$i]['constraint_name']);$i++) {
		if ($Results[$i]['type'] != 'PRIMARY KEY') {
			continue;
		}
		$SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
		$SQL.= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
		$SQL.= " " . $Results[$i]['type'];
		$SQL.= " (" . $Results[$i]['constraint_key'] . ")";
		if (!empty($Results[$i]['references_table'])) {
			$SQL.= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
			$SQL.= " (" . $Results[$i]['fk_constraint_key'] . ")";
		}
		$SQL.= ";";
		$Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
		$Results[$i]['processed'] = 1;
	}
	/** CONSTRAINT: UNIQUE **/
	for ($i = 0;!empty($Results[$i]['constraint_name']);$i++) {
		if ($Results[$i]['type'] != 'UNIQUE') {
			continue;
		}
		$SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
		$SQL.= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
		$SQL.= " " . $Results[$i]['type'];
		$SQL.= " (" . $Results[$i]['constraint_key'] . ")";
		if (!empty($Results[$i]['references_table'])) {
			$SQL.= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
			$SQL.= " (" . $Results[$i]['fk_constraint_key'] . ")";
		}
		$SQL.= ";";
		$Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
		$Results[$i]['processed'] = 1;
	}
	//print "GetSchema: Schema after CONSTRAINT: UNIQUE is:\n";print_r($Schema) . "\n";
	/** CONSTRAINT: FOREIGN KEY **/
	for ($i = 0;!empty($Results[$i]['constraint_name']);$i++) {
		if ($Results[$i]['type'] != 'FOREIGN KEY') {
			continue;
		}
		$SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
		$SQL.= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
		$SQL.= " " . $Results[$i]['type'];
		$SQL.= " (" . $Results[$i]['constraint_key'] . ")";
		if (!empty($Results[$i]['references_table'])) {
			$SQL.= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
			$SQL.= " (" . $Results[$i]['fk_constraint_key'] . ")";
		}
		$SQL.= ";";
		$Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
		$Results[$i]['processed'] = 1;
	}
	//print "GetSchema: Schema after Foreign Key is:\n";print_r($Schema) . "\n";
	/** CONSTRAINT: ALL OTHERS **/
	for ($i = 0;!empty($Results[$i]['constraint_name']);$i++) {
		if ($Results[$i]['processed'] != 1) {
			continue;
		}
		$SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
		$SQL.= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
		$SQL.= " " . $Results[$i]['type'];
		$SQL.= " (" . $Results[$i]['constraint_key'] . ")";
		if (!empty($Results[$i]['references_table'])) {
			$SQL.= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
			$SQL.= " (" . $Results[$i]['fk_constraint_key'] . ")";
		}
		$SQL.= ";";
		$Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
		$Results[$i]['processed'] = 1;
	}
	//print "GetSchema: Schema at this point is:\n";print_r($Schema) . "\n";
	//print "GetSchemaDB: before Get Index Results are:\n";print_r($Results) . "\n";

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
	$result = pg_query($SQL);
	checkresult($result, $SQL, __LINE__);
	$Results = pg_fetch_all($result);
	for ($i = 0;!empty($Results[$i]['table']);$i++) {
		/* UNIQUE constraints also include indexes. */
		if (empty($Schema['CONSTRAINT'][$Results[$i]['index']])) {
			$Schema['INDEX'][$Results[$i]['table']][$Results[$i]['index']] = $Results[$i]['define'] . ";";
		}
	}
	if (0) {
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
		$result = pg_query($SQL);
		checkresult($result, $SQL, __LINE__);
		$Results = pg_fetch_all($result);
		for ($i = 0;!empty($Results[$i]['proname']);$i++) {
			$SQL = "CREATE or REPLACE function " . $Results[$i]['proname'] . "()";
			$SQL.= ' RETURNS ' . "TBD" . ' AS $$';
			$SQL.= " " . $Results[$i]['prosrc'];
			$SQL.= ";";
			$Schema['FUNCTION'][$Results[$i]['proname']] = $SQL;
		}
	}
	unset($Schema['TABLEID']);
	//print "GetSchema: schema returned is:\n";print_r($Schema) . "\n";
	return ($Schema);
} // GetSchema()

/**
 * initAgents()
 * \brief Every agent program must be run one time with a "-i" before
 * being used.  This allows them to configure the DB or insert any required
 * DB fields.
 *
 * @param string $Debug, debug flag
 * @return 0 on success, dies upon failure!
 *
 */
function initAgents($Debug = 1) {
	print "  Initializing agents.\n";
	flush();
	global $AGENTDIR;
	if (!is_dir($AGENTDIR)) {
		die("FATAL: Directory '$AGENTDIR' does not exist.\n");
	}
	$Dir = opendir($AGENTDIR);
	if (!$Dir) {
		die("FATAL: Unable to access '$AGENTDIR'.\n");
	}
	while (($File = readdir($Dir)) !== false) {
		$File = "$AGENTDIR/$File";
		/* skip directories; only process files */
		if (is_file($File)) {
			if ($Debug) {
				print "    Initializing agent: $File\n";
				flush();
			}
			system("'$File' -i", $Status);
			if ($Status != 0) {
				die("FATAL: '$File -i' failed to initialize\n");
			}
		}
	}
	return(0);
} // InitAgents()

/**
 * initBsamFiles
 * \brief Initialize bSam license cache datafiles.
 *
 * @param string $Debug, debug flag
 *
 */

function initBsamFiles($Debug = 1)
{
	print "  Initializing data files.  This may take a few minutes.\n";
	flush();
	$CWD = getcwd();
	global $DATADIR;
	global $AGENTDIR;
	global $PROJECTSTATEDIR;
	if ($Debug) {
		print "Going to $DATADIR/agents/licenses\n";
	}
	chdir("$DATADIR/agents/licenses");
	$CMD = 'find . -type f | grep -v "\.meta" | sed -e "s@^./@@"';
	$Filelist = explode("\n", shell_exec($CMD));
	sort($Filelist);
	$Realdir = "$PROJECTSTATEDIR/agents";
	if (!is_dir($Realdir)) {
		die("FATAL: Directory '$Realdir' does not exist. Aborting.\n");
	}
	if (!is_writable($Realdir)) {
		die("FATAL: Directory '$Realdir' is not writable. Aborting.\n");
	}
	$Realfile = "$Realdir/License.bsam";
	print "DBUG: initBsFiles: Realfile is:$Realfile\n";
	$Tempfile = $Realfile . ".new";
	print "DBUG: initBsFiles: Tempfile is:$Tempfile\n";
	if (file_exists($Tempfile)) {
		if (!unlink($Tempfile)) {
			print "initBsamFiles: Unable to delete '$Tempfile'\n";
			flush();
			return(1);
		}
	}
	$Count = 0;
	print "    Processing " . (count($Filelist) - 1) . " license templates.\n";
	flush();
	print "    ";
	foreach($Filelist as $File) {
		if (empty($File)) {
			continue;
		}
		$Count++;
		if (file_exists($File . ".meta")) {
			$CMD = "$AGENTDIR/Filter_License -Q -O -M '" . $File . ".meta' '$File' >> $Tempfile";
		}
		else {
			$CMD = "$AGENTDIR/Filter_License -Q -O '$File' >> $Tempfile";
		}
		if ($Debug) {
			print "$CMD\n";
		}
		else {
			print ".";
			flush();
			if (($Count % 50) == 0) {
				print "$Count\n    ";
				flush();
			}
			system($CMD, $rc);
			if ($rc != 0) {
				print "Command failed: '$CMD'. Aborting.\n";
				flush();
				return(1);
			}
		}
	}
	/* Test the new file */
	$CMD = "$AGENTDIR/bsam-engine -t '$Tempfile'";
	if ($Debug) {
		print "$CMD\n";
	}
	else {
		system($CMD, $rc);
		if ($rc != 0) {
			print "FAILED: Unable to validate the new cache file.\n";
			print "Command failed: '$CMD'. Aborting.\n";
			flush();
			return(1);
		}
	}
	/* Move it into place */
	@chgrp($Realfile, "fossy");
	@chmod($Realfile, 0660);
	$CMD = "cat '$Tempfile' > '$Realfile'";
	if ($Debug) {
		print "$CMD\n";
	}
	else {
		system($CMD, $rc);
		unlink($Tempfile);
		if ($rc != 0) {
			print "Command failed: '$CMD'. Aborting.\n";
			flush();
			return(1);
		}
	}
	print "!\n";
	flush();
	return(0);
} // initBsamFiles()

/**
 * initPlugins
 * \brief Initialize the UI plugins
 *
 * @return 0 on success,1 on failure
 */
function initPlugins($Verbose, $Debug)
{
	global $Plugins;

	$Max = count($Plugins);
	$FailFlag = 0;
	if($Verbose)
	{
		print "  Initializing plugins\n";
		flush();
	}
	for ($i = 0;$i < $Max;$i++) {
		$P = & $Plugins[$i];
		/* Init ALL plugins */
		if ($Debug) {
			print "    Initializing plugin '" . $P->Name . "'\n";
		}
		$State = $P->Install();
		if ($State != 0) {
			$FailFlag = 1;
			print "FAILED: " . $P->Name . " failed to install.\n";
			flush();
			return (1);
		}
	}
	return (0);
} // initPlugins()

/**
 * MakeFunctions
 * \brief Create any required DB functions.
 */
function MakeFunctions($Debug) {

	global $PGCONN;

	print "  Applying database functions\n";
	flush();
	/********************************************
	 GetRunnable() is a DB function for listing the runnable items
	 in the jobqueue. This is used by the scheduler.
	 ********************************************/
	$SQL = '
CREATE or REPLACE function getrunnable() returns setof jobqueue as $$
DECLARE
  jqrec jobqueue;
  jqrec_test jobqueue;
  jqcurse CURSOR FOR SELECT *
    FROM jobqueue
    INNER JOIN job
      ON jq_starttime IS NULL
      AND jq_end_bits < 2
      AND job_pk = jq_job_fk
    ORDER BY job_priority DESC
    ;
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
	if ($Debug) {
		print "$SQL;\n";
	}
	else {
		$result = pg_query($SQL);
		checkresult($result, $SQL, __LINE__);
	}
	/********************************************
	 * uploadtree2path(uploadtree_pk integer) is a DB function that returns
	 * the non-artifact parents of an uploadtree_pk
	 ********************************************/
	$SQL = '
CREATE or REPLACE function uploadtree2path(uploadtree_pk_in int) returns setof uploadtree as $$
DECLARE
  UTrec   uploadtree;
  UTpk    integer;
  sql     varchar;
BEGIN

  UTpk := uploadtree_pk_in;

    WHILE UTpk > 0 LOOP
      sql := ' . "'" . 'select * from uploadtree where uploadtree_pk=' . "'" . ' || UTpk;
      execute sql into UTrec;

      IF ((UTrec.ufile_mode & (1<<28)) = 0) THEN RETURN NEXT UTrec; END IF;
      UTpk := UTrec.parent;
    END LOOP;
  RETURN;
END;
$$
LANGUAGE plpgsql;
    ';
	if ($Debug) {
		print "$SQL;\n";
	}
	else {
		$result = pg_query($SQL);
		checkresult($result, $SQL, __LINE__);
	}
	return;
} // MakeFunctions()


function TblExist($Table)
{
	global $PGCONN;
	$result = pg_query($PGCONN, "SELECT count(*) AS count FROM pg_type
                WHERE typname = '$Table'");
	if ($result)
	{
		$count = pg_fetch_result($result, 0, 0);
		if ($count > 0) { return(1); }
	}
	return(0);
}

?>
