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

  var $Filename = "plugins/core-schema.dat";

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
    /* Using information_schema.columns is easier, but missing the
       column description.  Also, it does not distinguish a default of "NULL"
       from having no default (technicality, but no implementation
       difference). */
    $SQLInfo = "SELECT table_name AS table,
	ordinal_position AS ordinal,
	column_name AS column_name,
	data_type AS type,
	character_maximum_length AS modifier,
	is_nullable AS notnull,
	column_default AS default
	FROM information_schema.columns
	WHERE table_catalog = 'fossology'
	AND table_schema = 'public';
	";

    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['table']); $i++)
      {
      $R = &$Results[$i];
      $Table = $R['table'];
      if (preg_match('/[0-9]/',$Table)) { continue; }
      $Column = $R['column_name'];
      $Type = $R['type'];
      if ($Type == 'bpchar') { $Type = "char"; }
      if ($R['modifier'] > 0) { $Type .= '(' . $R['modifier'] . ')'; }
      $Desc = str_replace("'","''",$R['description']);

      $Schema['TABLEID'][$Table][$R['ordinal']] = $Column;
      if (!empty($Desc))
	{
	$Schema['TABLE'][$Table][$Column]['DESC'] = "COMMENT ON COLUMN \"$Table\".\"$Column\" IS '$Desc';";
	}
      else
	{
	$Schema['TABLE'][$Table][$Column]['DESC'] = "";
	}
      $Schema['TABLE'][$Table][$Column]['ADD'] = "ALTER TABLE \"$Table\" ADD COLUMN \"$Column\" $Type;";
      $Schema['TABLE'][$Table][$Column]['ALTER'] = "ALTER TABLE \"$Table\"";
      $Alter = "ALTER COLUMN \"$Column\"";
      // $Schema['TABLE'][$Table][$Column]['ALTER'] .= " $Alter TYPE $Type";
      if ($R['notnull'] == 't') { $Schema['TABLE'][$Table][$Column]['ALTER'] .= " $Alter SET NOT NULL"; }
      else { $Schema['TABLE'][$Table][$Column]['ALTER'] .= " $Alter DROP NOT NULL"; }
      if ($R['default'] != '')
	{
	// $R['default'] = preg_replace("/::.*/","",$R['default']);
	$R['default'] = preg_replace("/::bpchar/","::char",$R['default']);
	$Schema['TABLE'][$Table][$Column]['ALTER'] .= ", $Alter SET DEFAULT " . $R['default'];
	}
      $Schema['TABLE'][$Table][$Column]['ALTER'] .= ";";
      }

    /***************************/
    /* Get Views */
    /***************************/
    $SQL = "SELECT viewname,definition FROM pg_views WHERE viewowner = 'fossy';";
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['viewname']); $i++)
      {
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
    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['relname']); $i++)
      {
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
	$Key .= '"' . $Schema['TABLEID'][$Results[$i]['table_name']][$K] . '"';
	}
      $Results[$i]['constraint_key'] = $Key;
      $Key = "";
      $Keys = split(" ",$Results[$i]['fk_constraint_key']);
      foreach($Keys as $K)
	{
	if (empty($K)) { continue; }
	if (!empty($Key)) { $Key .= ","; }
	$Key .= '"' . $Schema['TABLEID'][$Results[$i]['references_table']][$K] . '"';
	}
      $Results[$i]['fk_constraint_key'] = $Key;
      }

    /* Save the constraint */
    /** There are different types of constraints that must be stored in order **/
    /** CONSTRAINT: PRIMARY KEY **/
    for($i=0; !empty($Results[$i]['constraint_name']); $i++)
      {
      if ($Results[$i]['type'] != 'PRIMARY KEY') { continue; }
      $SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $SQL .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $SQL .= " " . $Results[$i]['type'];
      $SQL .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
	{
	$SQL .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
	$SQL .= " (" . $Results[$i]['fk_constraint_key'] . ")";
	}
      $SQL .= ";";
      $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
      $Results[$i]['processed'] = 1;
      }

    /** CONSTRAINT: UNIQUE **/
    for($i=0; !empty($Results[$i]['constraint_name']); $i++)
      {
      if ($Results[$i]['type'] != 'UNIQUE') { continue; }
      $SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $SQL .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $SQL .= " " . $Results[$i]['type'];
      $SQL .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
	{
	$SQL .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
	$SQL .= " (" . $Results[$i]['fk_constraint_key'] . ")";
	}
      $SQL .= ";";
      $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
      $Results[$i]['processed'] = 1;
      }

    /** CONSTRAINT: FOREIGN KEY **/
    for($i=0; !empty($Results[$i]['constraint_name']); $i++)
      {
      if ($Results[$i]['type'] != 'FOREIGN KEY') { continue; }
      $SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $SQL .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $SQL .= " " . $Results[$i]['type'];
      $SQL .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
	{
	$SQL .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
	$SQL .= " (" . $Results[$i]['fk_constraint_key'] . ")";
	}
      $SQL .= ";";
      $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
      $Results[$i]['processed'] = 1;
      }

    /** CONSTRAINT: ALL OTHERS **/
    for($i=0; !empty($Results[$i]['constraint_name']); $i++)
      {
      if ($Results[$i]['processed'] != 1) { continue; }
      $SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $SQL .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $SQL .= " " . $Results[$i]['type'];
      $SQL .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
	{
	$SQL .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
	$SQL .= " (" . $Results[$i]['fk_constraint_key'] . ")";
	}
      $SQL .= ";";
      $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
      $Results[$i]['processed'] = 1;
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
      /* UNIQUE constraints also include indexes. */
      if (empty($Schema['CONSTRAINT'][$Results[$i]['index']]))
        {
        $Schema['INDEX'][$Results[$i]['table']][$Results[$i]['index']] = $Results[$i]['define'] . ";";
	}
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

    $SQLinfo = "SELECT r.routine_name AS name,
	p.parameter_mode, p.parameter_name, p.data_type,
	r.routine_definition AS definition
	FROM information_schema.parameters AS p
	INNER JOIN information_schema.routines AS r
	ON r.specific_name = p.specific_name
	AND r.specific_catalog = p.specific_catalog
	AND r.specific_schema = p.specific_schema
	WHERE r.routine_type = 'FUNCTION'
	AND r.specific_catalog = 'fossology'
	AND r.specific_schema = 'public';
	";

    $Results = $DB->Action($SQL);
    for($i=0; !empty($Results[$i]['proname']); $i++)
      {
      $SQL = "CREATE or REPLACE function " . $Results[$i]['proname'] . "()";
      $SQL .= ' RETURNS ' . "TBD" . ' AS $$';
      $SQL .= " " . $Results[$i]['prosrc'];
      $SQL .= ";";
      $Schema['FUNCTION'][$Results[$i]['proname']] = $SQL;
      }
}

    unset($Schema['TABLEID']);
    return($Schema);
    } // GetSchema()

  /***********************************************************
   CompareSchema(): Get the current schema and display it to the screen.
   ***********************************************************/
  function CompareSchema($Filename)
    {
    $Red = '#FF8080';
    $Blue = '#8080FF';
    /**************************************/
    /** BEGIN: Term list from ExportTerms() **/
    /**************************************/
    require_once($Filename); /* this will DIE if the file does not exist. */
    /**************************************/
    /** END: Term list from ExportTerms() **/
    /**************************************/
    $Current = $this->GetSchema();

    print "<ul>\n";
    print "<li><a href='#Table'>Tables</a>\n";
    print "<li><a href='#Sequence'>Sequences</a>\n";
    print "<li><a href='#View'>Views</a>\n";
    print "<li><a href='#Index'>Indexes</a>\n";
    print "<li><a href='#Constraint'>Constraints</a>\n";
    if (count($Schema['FUNCTION']) > 0)
      {
      print "<li><a href='#Function'>Functions</a>\n";
      }
    print "</ul>\n";
    print "<ul>\n";
    print "<li><font color='$Red'>This color indicates the current schema (items that should be removed).</font>\n";
    print "<li><font color='$Blue'>This color indicates the default schema (items that should be applied).</font>\n";
    print "</ul>\n";

    print "<a name='Table'></a><table width='100%' border='1'>\n";
    $LastTableName="";
    if (!empty($Schema['TABLE']))
    foreach($Schema['TABLE'] as $TableName => $Columns)
      {
      if (empty($TableName)) { continue; }
      foreach($Columns as $ColName => $Val)
	{
	if ($Val == $Current['TABLE'][$TableName][$ColName]) { continue; }
	if ($LastTableName != $TableName)
	  {
	  print "<tr><th><a name='Table-$TableName'></a>Table<th>Column<th>Description<th>Add SQL<th>Alter SQL\n";
	  $LastTableName = $TableName;
	  }
	if (empty($ColName)) { continue; }
	print "<tr bgcolor='$Blue'><td>" . htmlentities($TableName);
	print "<td>" . htmlentities($ColName);
	print "<td>" . $Val['DESC'];
	print "<td>" . $Val['ADD'];
	print "<td>" . $Val['ALTER'];
	print "\n";
	}
      }
    if (!empty($Current['TABLE']))
    foreach($Current['TABLE'] as $TableName => $Columns)
      {
      if (empty($TableName)) { continue; }
      foreach($Columns as $ColName => $Val)
	{
	if ($Val == $Schema['TABLE'][$TableName][$ColName]) { continue; }
	if ($LastTableName != $TableName)
	  {
	  print "<tr><th><a name='Table-$TableName'></a>Table<th>Column<th>Description<th>Add SQL<th>Alter SQL\n";
	  $LastTableName = $TableName;
	  }
	if (empty($ColName)) { continue; }
	print "<tr bgcolor='$Red'><td>" . htmlentities($TableName);
	print "<td>" . htmlentities($ColName);
	print "<td>" . $Val['DESC'];
	print "<td>" . $Val['ADD'];
	print "<td>" . $Val['ALTER'];
	print "\n";
	}
      }
    print "</table>\n";

    print "<P/>\n";
    print "<a name='Sequence'></a><table width='100%' border='1'>\n";
    print "<th>Sequence<th>Definition\n";
    if (!empty($Schema['SEQUENCE']))
    foreach($Schema['SEQUENCE'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      if ($Description == $Current['SEQUENCE'][$Name]) { continue; }
      print "<tr bgcolor='$Blue'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    if (!empty($Current['SEQUENCE']))
    foreach($Current['SEQUENCE'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      if ($Description == $Schema['SEQUENCE'][$Name]) { continue; }
      print "<tr bgcolor='$Red'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    print "</table>\n";


    print "<P/>\n";
    print "<a name='View'></a><table width='100%' border='1'>\n";
    print "<th>View<th>Definition\n";
    if (!empty($Schema['VIEW']))
    foreach($Schema['VIEW'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      if ($Description == $Current['VIEW'][$Name]) { continue; }
      print "<tr bgcolor='$Blue'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    if (!empty($Current['VIEW']))
    foreach($Current['VIEW'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      if ($Description == $Schema['VIEW'][$Name]) { continue; }
      print "<tr bgcolor='$Red'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    print "</table>\n";

    print "<P/>\n";
    print "<a name='Index'></a><table width='100%' border='1'>\n";
    print "<th>Table<th>Index<th>Definition\n";
    if (!empty($Schema['INDEX']))
    foreach($Schema['INDEX'] as $Table => $Indexes)
      {
      if (empty($Table)) { continue; }
      foreach($Indexes as $Index => $Define)
	{
	if ($Define == $Current['INDEX'][$Table][$Index]) { continue; }
	print "<tr bgcolor='$Blue'><td>" . htmlentities($Table);
	print "<td>" . htmlentities($Index);
	print "<td>" . htmlentities($Define);
	}
      }
    if (!empty($Current['INDEX']))
    foreach($Current['INDEX'] as $Table => $Indexes)
      {
      if (empty($Table)) { continue; }
      foreach($Indexes as $Index => $Define)
	{
	if ($Define == $Schema['INDEX'][$Table][$Index]) { continue; }
	print "<tr bgcolor='$Red'><td>" . htmlentities($Table);
	print "<td>" . htmlentities($Index);
	print "<td>" . htmlentities($Define);
	}
      }
    print "</table>\n";

    print "<P/>\n";
    print "<a name='Constraint'></a><table width='100%' border='1'>\n";
    print "<th>Constraint<th>Definition\n";
    if (!empty($Schema['CONSTRAINT']))
    foreach($Schema['CONSTRAINT'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      if ($Description == $Current['CONSTRAINT'][$Name]) { continue; }
      print "<tr bgcolor='$Blue'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    if (!empty($Current['CONSTRAINT']))
    foreach($Current['CONSTRAINT'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      if ($Description == $Schema['CONSTRAINT'][$Name]) { continue; }
      print "<tr bgcolor='$Red'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    print "</table>\n";
    } // CompareSchema()

  /***********************************************************
   ViewSchema(): Get the current schema and display it to the screen.
   ***********************************************************/
  function ViewSchema()
    {
    $Schema = $this->GetSchema();

    print "<ul>\n";
    print "<li><a href='#Table'>Tables</a>\n";
    print "<li><a href='#Sequence'>Sequences</a>\n";
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
    if (!empty($Schema['TABLE']))
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
    print "<a name='Sequence'></a><table width='100%' border='1'>\n";
    print "<th>Sequence<th>Definition\n";
    if (!empty($Schema['SEQUENCE']))
    foreach($Schema['SEQUENCE'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      print "<tr><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    print "</table>\n";

    print "<P/>\n";
    print "<a name='View'></a><table width='100%' border='1'>\n";
    print "<th>View<th>Definition\n";
    if (!empty($Schema['VIEW']))
    foreach($Schema['VIEW'] as $Name => $Description)
      {
      if (empty($Name)) { continue; }
      print "<tr><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
      }
    print "</table>\n";

    print "<P/>\n";
    print "<a name='Index'></a><table width='100%' border='1'>\n";
    print "<th>Table<th>Index<th>Definition\n";
    if (!empty($Schema['INDEX']))
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
    if (!empty($Schema['CONSTRAINT']))
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
      if (!empty($Schema['FUNCTION']))
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
  function ExportSchema($Filename=NULL)
    {
    if (empty($Filename)) { $Filename = $this->Filename; }
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
   MigrateSchema(): Any special code for migrating data.
   This is called AFTER columns/tables are added and AFTER
   constraints are removed.
   But it is called BEFORE old columns are dropped.
   ***********************************************************/
  function MigrateSchema()
    {
    global $DB;
    print "  Migrating database records\n"; flush();

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
    if ($DB->TblExist("ufile") && $DB->ColExist("upload", "ufile_fk"))
	{
	$DB->Action("UPDATE upload SET pfile_fk = ufile.pfile_fk FROM ufile WHERE upload.pfile_fk IS NULL AND upload.ufile_fk = ufile.ufile_pk;");
	}

     if ($DB->TblExist("ufile") && $DB->ColExist("uploadtree", "ufile_fk"))
	{
	$DB->Action("UPDATE uploadtree SET pfile_fk = ufile.pfile_fk FROM ufile WHERE uploadtree.pfile_fk IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
	$DB->Action("UPDATE uploadtree SET ufile_mode = ufile.ufile_mode FROM ufile WHERE uploadtree.ufile_mode IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
	$DB->Action("UPDATE uploadtree SET ufile_name = ufile.ufile_name FROM ufile WHERE uploadtree.ufile_name IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
	}

    /************ Delete obsolete tables and columns ************/
    if ($DB->TblExist("ufile")) { $DB->Action("DROP TABLE ufile CASCADE;"); }
    if ($DB->TblExist("proj")) { $DB->Action("DROP TABLE proj CASCADE;"); }
    if ($DB->TblExist("log")) { $DB->Action("DROP TABLE log CASCADE;"); }
    if ($DB->TblExist("table_enum")) { $DB->Action("DROP TABLE table_enum CASCADE;"); }
    if ($DB->ColExist("job","job_submitter")) { $DB->Action("ALTER TABLE \"job\" DROP COLUMN \"job_submitter\" ;"); }

    /********************************************/
    /* Sequences can get out of sequence; Fix the sequences! */
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
	$Results = $DB->Action("SELECT max($Column) AS max FROM \"$Table\" LIMIT 1;");
	$Max = intval($Results[0]['max']);
	if (empty($Max) || ($Max <= 0)) { $Max = 1; }
	else { $Max++; }
	// print "Setting table($Table) column($Column) sequence($Seq) to $Max\n";
	$DB->Action("SELECT setval('$Seq',$Max);");
	}
    } // MigrateSchema()

  /***********************************************************
   InitSchema(): Initialize any new schema elements.
   ***********************************************************/
  function InitSchema($Debug)
    {
    /* Make sure every upload has left and right indexes set. */
    global $LIBEXECDIR;
    print "  Initializing new tables and columns\n"; flush();
    system("$LIBEXECDIR/agents/adj2nest -a");

    global $Plugins;
    $Max = count($Plugins);
    $FailFlag=0;
    print "  Initializing plugins\n"; flush();
    for($i=0; $i < $Max; $i++)
      {
      $P = &$Plugins[$i];
      /* Init ALL plugins */
      if ($Debug) { print "    Initializing plugin '" . $P->Name . "'\n"; }
      $State = $P->Install();
      if ($State != 0)
	{
	$FailFlag = 1;
	print "FAILED: " . $P->Name . " failed to install.\n"; flush();
	return(1);
	}
      }
    $this->InitAgents($Verbose);
    $this->InitDatafiles($Debug);
    return(0);
    } // InitSchema()

  /***********************************************************
   InitAgents(): Every agent program must be run one time with
   a "-i" before being used.  This allows them to configure the DB
   or insert any required DB fields.
   Returns 0 on success, dies upon failure!
   ***********************************************************/
  function InitAgents($Debug=1)
    {
    print "  Initializing agents.\n"; flush();
    global $AGENTDIR;
    if (!is_dir($AGENTDIR)) { die("FATAL: Directory '$AGENTDIR' does not exist.\n"); }
    $Dir = opendir($AGENTDIR);
    if (! $Dir) { die("FATAL: Unable to access '$AGENTDIR'.\n"); }
    while(($File = readdir($Dir)) !== false)
      {
      $File = "$AGENTDIR/$File";
      /* skip directories; only process files */
      if (is_file($File))
        {
	if ($Debug) { print "    Initializing agent: $File\n"; flush(); }
	system("'$File' -i",$Status);
	if ($Status != 0)
	  {
	  die("FATAL: '$File -i' failed to initialize\n");
	  }
	}
      }
    } // InitAgents()

  /***********************************************************
   InitDatafiles(): Initialize any datafiles.
   ***********************************************************/
  function InitDatafiles($Debug=1)
    {
    print "  Initializing data files.  This may take a few minutes.\n"; flush();
    $CWD = getcwd();
    global $DATADIR;
    global $AGENTDIR;
    global $PROJECTSTATEDIR;
    if ($Debug) { print "Going to $DATADIR/agents/licenses\n"; }
    chdir("$DATADIR/agents/licenses");
    $CMD = 'find . -type f | grep -v "\.meta" | sed -e "s@^./@@"';
    $Filelist = explode("\n",shell_exec($CMD));
    sort($Filelist);

    $Realdir = "$PROJECTSTATEDIR/agents";
    if (!is_dir($Realdir)) { die("FATAL: Directory '$Realdir' does not exist. Aborting.\n"); }
    if (!is_writable($Realdir)) { die("FATAL: Directory '$Realdir' is not writable. Aborting.\n"); }

    $Realfile = "$Realdir/License.bsam";
    $Tempfile = $Realfile . ".new";
    if (file_exists($Tempfile))
      {
      if (!unlink($Tempfile))
        {
        print "Unable to delete '$Tempfile'\n";
	flush();
	exit(1);
	}
      }
    $Count=0;
    print "    Processing " . (count($Filelist)-1) . " license templates.\n";
    flush();
    print "    ";
    foreach($Filelist as $File)
      {
      if (empty($File)) { continue; }
      $Count++;
      if (file_exists($File . ".meta"))
        {
	$CMD = "$AGENTDIR/Filter_License -Q -O -M '" . $File . ".meta' '$File' >> $Tempfile";
	}
      else
        {
	$CMD = "$AGENTDIR/Filter_License -Q -O '$File' >> $Tempfile";
	}
      if ($Debug) { print "$CMD\n"; }
      else
        {
        print "."; flush();
	if (($Count % 50) == 0) { print "$Count\n    "; flush(); }
        system($CMD,$rc);
	if ($rc != 0)
	  {
	  print "Command failed: '$CMD'. Aborting.\n";
	  flush();
	  exit;
	  }
	}
      }
    /* Test the new file */
    $CMD = "$AGENTDIR/bsam-engine -t '$Tempfile'";
    if ($Debug) { print "$CMD\n"; }
    else
      {
      system($CMD,$rc);
      if ($rc != 0)
	  {
	  print "FAILED: Unable to validate the new cache file.\n";
	  print "Command failed: '$CMD'. Aborting.\n";
	  flush();
	  exit;
	  }
      }

    /* Move it into place */
    @chgrp($Realfile,"fossy");
    @chmod($Realfile,0660);
    $CMD = "cat '$Tempfile' > '$Realfile'";
    if ($Debug) { print "$CMD\n"; }
    else
      {
      system($CMD,$rc);
      unlink($Tempfile);
      if ($rc != 0)
	  {
	  print "Command failed: '$CMD'. Aborting.\n";
	  flush();
	  exit;
	  }
      }
    print "!\n"; flush();
    } // InitDatafiles()

  /***********************************************************
   MakeFunctions(): Create any required functions.
   ***********************************************************/
  function MakeFunctions($Debug)
    {
    global $DB;
    print "  Applying database functions\n"; flush();

    /********************************************/
    /* GetRunnable() is a DB function for listing the runnable items
       in the jobqueue. This is used by the scheduler. */
    /********************************************/
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
    if ($Debug) { print "$SQL;\n"; }
    else { $DB->Action($SQL); }

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
    if ($Debug) { print "$SQL;\n"; }
    else { $DB->Action($SQL); }
    } // MakeFunctions()

  /***********************************************************
   ApplySchema(): Apply the current schema from a file.
   NOTE: The order for add/delete is important!
   ***********************************************************/
  function ApplySchema($Filename=NULL,$Debug=1,$Verbose=1)
    {
    global $DB;
    if (empty($DB)) { return("No database connection."); }
    if (empty($Filename)) { $Filename = $this->Filename; }

    print "Applying database schema\n"; flush();

    /**************************************/
    /** BEGIN: Term list from ExportTerms() **/
    /**************************************/
    require_once($Filename); /* this will DIE if the file does not exist. */
    /**************************************/
    /** END: Term list from ExportTerms() **/
    /**************************************/

    /* Very basic sanity check (so we don't delete everything!) */
    if ((count($Schema['TABLE']) < 5) ||
	(count($Schema['VIEW']) < 1) ||
	(count($Schema['SEQUENCE']) < 5) ||
	(count($Schema['INDEX']) < 5) ||
	(count($Schema['CONSTRAINT']) < 5))
	{
	print "FATAL: Schema from '$Filename' appears invalid.\n";
	flush();
	exit(1);
	}

    $DB->Action("SET statement_timeout = 0;"); /* turn off DB timeouts */
    $DB->Action("BEGIN;");
    $DB->Debug=1; /* show errors */
    $DB->Error=0; /* clear any previous errors */
    $Curr = $this->GetSchema();
    /* The gameplan: Make $Curr look like $Schema. */
    // print "<pre>"; print_r($Schema); print "</pre>";

    /************************************/
    /* Add sequences */
    /************************************/
    if (!empty($Schema['SEQUENCE']))
    foreach($Schema['SEQUENCE'] as $Name => $SQL)
      {
      if (empty($Name)) { continue; }
      if ($Curr['SEQUENCE'][$Name] == $SQL) { continue; }
      if ($Debug) { print "$SQL\n"; }
      else { $DB->Action($SQL); }
      if ($DB->Error) { exit(1); }
      }

    /************************************/
    /* Add tables/columns (dependent on sequences for default values) */
    /************************************/
    if (!empty($Schema['TABLE']))
    foreach($Schema['TABLE'] as $Table => $Columns)
      {
      if (empty($Table)) { continue; }
      if (!$DB->TblExist($Table))
	{
	$SQL = "CREATE TABLE \"$Table\" ();";
	if ($Debug) { print "$SQL\n"; }
	else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
	}
      foreach($Columns as $Column => $Val)
	{
	if ($Curr['TABLE'][$Table][$Column]['ADD'] != $Val['ADD'])
	  {
	  $Rename="";
	  if ($DB->ColExist($Table,$Column))
	    {
	    /* The column exists, but it looks different!
	       Solution: Delete the column! */
	    $Rename = $Column . "_old";
	    $SQL = "ALTER TABLE \"$Table\" RENAME COLUMN \"$Column\" TO \"$Rename\";";
	    if ($Debug) { print "$SQL\n"; }
	    else { $DB->Action($SQL); }
           if ($DB->Error) {
             exit(1);
           }
	    }
	  if ($Debug) { print $Val['ADD'] . "\n"; }
	  else { $DB->Action($Val['ADD']); }
          if ($DB->Error) { exit(1); }
	  if (!empty($Rename))
	    {
	    /* copy over the old data */
	    $SQL = "UPDATE \"$Table\" SET \"$Column\" = \"$Rename\";";
	    if ($Debug) { print "$SQL\n"; }
	    else { $DB->Action($SQL); }
            if ($DB->Error) { exit(1); }
	    $SQL = "ALTER TABLE \"$Table\" DROP COLUMN \"$Rename\";";
	    if ($Debug) { print "$SQL\n"; }
	    else { $DB->Action($SQL); }
            if ($DB->Error) { exit(1); }
	    }
	  }
	if ($Curr['TABLE'][$Table][$Column]['ALTER'] != $Val['ALTER'])
	  {
	  if ($Debug) { print $Val['ALTER'] . "\n"; }
	  else { $DB->Action($Val['ALTER']); }
	  if ($DB->Error) { exit(1); }
	  }
	if ($Curr['TABLE'][$Table][$Column]['DESC'] != $Val['DESC'])
	  {
	  if (empty($Val['DESC']))
	    {
	    $SQL = "COMMENT ON COLUMN \"$Table\".\"$Column\" IS '';";
	    }
	  else
	    {
	    $SQL = $Val['DESC'];
	    }
	  if ($Debug) { print "$SQL\n"; }
	  else { $DB->Action($SQL); }
	  if ($DB->Error) { exit(1); }
	  }
	}
      }

    /************************************/
    /* Add views (dependent on columns) */
    /************************************/
    if (!empty($Schema['VIEW']))
    foreach($Schema['VIEW'] as $Name => $SQL)
      {
      if (empty($Name)) { continue; }
      if ($Curr['VIEW'][$Name] == $SQL) { continue; }
      if (!empty($Curr['VIEW'][$Name]))
	{
	/* Delete it if it exists and looks different */
	$SQL1 = "DROP VIEW \"$Name\";";
	if ($Debug) { print "$SQL1\n"; }
	else { $DB->Action($SQL1); }
	if ($DB->Error) { exit(1); }
	}
      /* Create the view */
      if ($Debug) { print "$SQL\n"; }
      else { $DB->Action($SQL); }
      if ($DB->Error) { exit(1); }
      }

    /************************************/
    /* Delete constraints */
    /* Delete now, so they won't interfere with migrations. */
    /************************************/
    if (!empty($Curr['CONSTRAINT']))
    foreach($Curr['CONSTRAINT'] as $Name => $SQL)
      {
      if (empty($Name)) { continue; }
      /* Only process tables that I know about */
      $Table = preg_replace("/^ALTER TABLE \"(.*)\" ADD CONSTRAINT.*/",'${1}',$SQL);
      $TableFk = preg_replace("/^.*FOREIGN KEY .* REFERENCES \"(.*)\" \(.*/",'${1}',$SQL);
      if ($TableFk == $SQL) { $TableFk = $Table; }
      /* If I don't know the primary or foreign table... */
      if (empty($Schema['TABLE'][$Table]) && empty($Schema['TABLE'][$TableFk]))
	{
	continue;
	}
      /* If it is already set correctly, then skip it. */
      if ($Schema['CONSTRAINT'][$Name] == $SQL) { continue; }
      $SQL = "ALTER TABLE \"$Table\" DROP CONSTRAINT \"$Name\" CASCADE;";
      if ($Debug) { print "$SQL\n"; }
      else { $DB->Action($SQL); }
      if ($DB->Error) { exit(1); }
      }
    /* Reload current since the CASCADE may have changed things */
    $Curr = $this->GetSchema();

    /************************************/
    /* Delete indexes */
    /************************************/
    $Curr = $this->GetSchema(); /* constraints and indexes are linked, recheck */
    if (!empty($Curr['INDEX']))
    foreach($Curr['INDEX'] as $Table => $IndexInfo)
      {
      if (empty($Table)) { continue; }
      /* Only delete indexes on known tables */
      if (empty($Schema['TABLE'][$Table])) { continue; }
      foreach($IndexInfo as $Name => $SQL)
	{
	if (empty($Name)) { continue; }
	/* Only delete indexes that are different */
	if ($Schema['INDEX'][$Table][$Name] == $SQL) { continue; }
	$SQL = "DROP INDEX \"$Name\";";
	if ($Debug) { print "$SQL\n"; }
	else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
	}
      }

    /************************************/
    /* Add indexes (dependent on columns) */
    /************************************/
    if (!empty($Schema['INDEX']))
    foreach($Schema['INDEX'] as $Table => $IndexInfo)
      {
      if (empty($Table)) { continue; }
      foreach($IndexInfo as $Name => $SQL)
	{
	if (empty($Name)) { continue; }
	if ($Curr['INDEX'][$Table][$Name] == $SQL) { continue; }
	if ($Debug) { print "$SQL\n"; }
	else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
	$SQL = "REINDEX INDEX \"$Name\";";
	if ($Debug) { print "$SQL\n"; }
	else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
	}
      }

    /************************************/
    /* Add constraints (dependent on columns, views, and indexes) */
    /************************************/
    $Curr = $this->GetSchema(); /* constraints and indexes are linked, recheck */
    if (!empty($Schema['CONSTRAINT']))
      {
      /* Constraints must be added in the correct order! */
      /* CONSTRAINT: PRIMARY KEY */
      foreach($Schema['CONSTRAINT'] as $Name => $SQL)
        {
        if (empty($Name)) { continue; }
        if ($Curr['CONSTRAINT'][$Name] == $SQL) { continue; }
	if (!preg_match("/PRIMARY KEY/",$SQL)) { continue; }
        if ($Debug) { print "$SQL\n"; }
        else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
        }
      /* CONSTRAINT: UNIQUE */
      foreach($Schema['CONSTRAINT'] as $Name => $SQL)
        {
        if (empty($Name)) { continue; }
        if ($Curr['CONSTRAINT'][$Name] == $SQL) { continue; }
	if (!preg_match("/UNIQUE/",$SQL)) { continue; }
        if ($Debug) { print "$SQL\n"; }
        else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
        }
      /* CONSTRAINT: FOREIGN KEY */
      foreach($Schema['CONSTRAINT'] as $Name => $SQL)
        {
        if (empty($Name)) { continue; }
        if ($Curr['CONSTRAINT'][$Name] == $SQL) { continue; }
	if (!preg_match("/FOREIGN KEY/",$SQL)) { continue; }
        if ($Debug) { print "$SQL\n"; }
        else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
        }
      /* All other constraints */
      foreach($Schema['CONSTRAINT'] as $Name => $SQL)
        {
        if (empty($Name)) { continue; }
        if ($Curr['CONSTRAINT'][$Name] == $SQL) { continue; }
	if (preg_match("/PRIMARY KEY/",$SQL)) { continue; }
	if (preg_match("/UNIQUE/",$SQL)) { continue; }
	if (preg_match("/FOREIGN KEY/",$SQL)) { continue; }
        if ($Debug) { print "$SQL\n"; }
        else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
        }
      } /* Add constraints */

    /************************************/
    /* CREATE FUNCTIONS */
    /************************************/
    $this->MakeFunctions($Debug);

    /************************************/
    /* MIGRATE DATA */
    /************************************/
    $this->MigrateSchema();
    /* Reload current since CASCADE during migration may have changed things */
    $Curr = $this->GetSchema();

    /************************************/
    /* Delete views */
    /************************************/
    print "  Removing obsolete views\n"; flush();
    /* Get current tables and columns used by all views */
    /* Delete if: uses table I know and column I do not know. */
    /* Without this delete, we won't be able to drop columns. */
    $SQL = "SELECT view_name,table_name,column_name
	FROM information_schema.view_column_usage
	WHERE table_catalog='fossology'
	ORDER BY view_name,table_name,column_name;";
    $Results = $DB->Action($SQL);
    if ($DB->Error) { exit(1); }
    for($i=0; !empty($Results[$i]['view_name']); $i++)
      {
      $View = $Results[$i]['view_name'];
      $Table = $Results[$i]['table_name'];
      if (empty($Schema['TABLE'][$Table])) { continue; }
      $Column = $Results[$i]['column_name'];
      if (empty($Schema['TABLE'][$Table][$Column]))
	{
	$SQL = "DROP VIEW \"$View\";";
	if ($Debug) { print "$SQL\n"; }
	else { $DB->Action($SQL); }
        if ($DB->Error) { exit(1); }
	}
      }

    /************************************/
    /* Delete columns/tables */
    /************************************/
    print "  Removing obsolete columns\n"; flush();
    if (!empty($Curr['TABLE']))
    foreach($Curr['TABLE'] as $Table => $Columns)
      {
      if (empty($Table)) { continue; }
      /* only delete from tables I know */
      if (empty($Schema['TABLE'][$Table])) { continue; }
      foreach($Columns as $Column => $Val)
	{
	if (empty($Column)) { continue; }
	if (empty($Schema['TABLE'][$Table][$Column]))
	  {
	  $SQL = "ALTER TABLE \"$Table\" DROP COLUMN \"$Column\";";
	  if ($Debug) { print "$SQL\n"; }
	  else { $DB->Action($SQL); }
          if ($DB->Error) { exit(1); }
	  }
	}
      }

    /************************************/
    /* Delete sequences */
    /* DO NOT DELETE: cannot map to tables I use. */
    /************************************/

    /************************************/
    /* Commit changes */
    /************************************/
    print "  Committing changes...\n"; flush();
    $DB->Action("COMMIT;");
    if ($DB->Error)
      {
      print "FAILURE while applying schema.\n";
      flush();
      exit(1);
      }

    /************************************/
    /* Flush any cached data. */
    /************************************/
    print "  Purging cached results\n"; flush();
    ReportCachePurgeAll();

    /************************************/
    /* Initialize all remaining plugins. */
    /************************************/
    if ($this->InitSchema($Verbose))
      {
      return("Unable to initialize the new schema.\n");
      }
    print "New schema applied and initialization completed.\n";
    $DB->Action("SET statement_timeout = 120000;"); /* reset DB timeouts */
    return;
    } // ApplySchema()

  /***********************************************************
   (): This function is called when user output is
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
	$Init = GetParm('Compare',PARM_INTEGER);
	if ($Init == 1)
	  {
	  $rc = $this->CompareSchema($this->Filename);
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
	  $rc = $this->ExportSchema($this->Filename);
	  if (!empty($rc))
	    {
	    $V .= PopupAlert($rc);
	    }
	  $V .= "<hr>\n";
	  }
	$Init = GetParm('Apply',PARM_INTEGER);
	if ($Init == 1)
	  {
	  print "<pre>";
	  $rc = $this->ApplySchema($this->Filename,0,0);
	  print "</pre>";
	  if (!empty($rc))
	    {
	    $V .= PopupAlert($rc);
	    }
	  $V .= "<hr>\n";
	  }

	$V .= "<form method='post'>\n";
	$V .= "Viewing, exporting, and applying the schema is only used by installation and debugging.\n";
	$V .= "Otherwise, you should not need to use this functionality.\n";
	$V .= "<P/><b>Using this functionality willy-nilly may <u><i>TOTALLY SCREW UP</i></u> your FOSSology database.</b>\n";

	$V .= "<P/>\n";
	$V .= "<table width='100%' border='1'>\n";
	$V .= "<tr><td width='2%'><input type='checkbox' value='1' name='View'><td>Check to view the current schema. The output generation is harmless, but extremely technical.<br>\n";
	$V .= "<tr><td><input type='checkbox' value='1' name='Compare'><td>Highlight the differences between the default schema (blue) and current schema (red).<br>\n";
	$V .= "<tr><td><input type='checkbox' value='1' name='Export'><td>Check to export the current schema. This will overwrite your default schema configuration file. Don't do this unless you know <i>exactly</i> what you are doing. The default configuration file is the only one that is supported. This will overwrite your default file.<br>\n";
	$V .= "<tr><td><input type='checkbox' value='1' name='Apply'><td>Check to apply the last exported schema. This will overwrite and atempt to migrate your database schema according to the default configuration file. Non-standard columns, tables, constraints, and views can and will be destroyed.\n";
	$V .= "</table>\n";
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
