<?php
/***********************************************************
Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

global $SysConf;
global $MODDIR;
require_once("$MODDIR/lib/php/libschema.php");

define("TITLE_core_schema", _("Database Schema"));

class core_schema extends FO_Plugin {
  var $Name = "schema";
  var $Title = TITLE_core_schema;
  var $Version = "1.0";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_USERADMIN;
  var $PluginLevel = 100;
  var $MenuList = "Admin::Database::Schema";
  var $LoginFlag = 1; /* must be logged in to use this */
  var $Filename = "plugins/core-schema.dat";

  /***********************************************************
  CompareSchema(): Get the current schema and display it to the screen.
  ***********************************************************/
  function CompareSchema($Filename) {
    $Red = '#FF8080';
    $Blue = '#8080FF';
    /**************************************/
    /** BEGIN: Term list from ExportTerms() **/
    /**************************************/
    require_once ($Filename); /* this will DIE if the file does not exist. */
    /**************************************/
    /** END: Term list from ExportTerms() **/
    /**************************************/
    $Current = GetSchema();
    print "<ul>\n";
$text = _("Tables");
    print "<li><a href='#Table'>$text</a>\n";
$text = _("Sequences");
    print "<li><a href='#Sequence'>$text</a>\n";
$text = _("Views");
    print "<li><a href='#View'>$text</a>\n";
$text = _("Indexes");
    print "<li><a href='#Index'>$text</a>\n";
$text = _("Constraints");
    print "<li><a href='#Constraint'>$text</a>\n";
    if (count($Schema['FUNCTION']) > 0) {
$text = _("Functions");
      print "<li><a href='#Function'>$text</a>\n";
    }
    print "</ul>\n";
    print "<ul>\n";
$text = _("This color indicates the current schema (items that should be removed).");
    print "<li><font color='$Red'>$text</font>\n";
$text = _("This color indicates the default schema (items that should be applied).");
    print "<li><font color='$Blue'>$text</font>\n";
    print "</ul>\n";
    print "<a name='Table'></a><table width='100%' border='1'>\n";
    $LastTableName = "";
    if (!empty($Schema['TABLE'])) foreach($Schema['TABLE'] as $TableName => $Columns) {
      if (empty($TableName)) {
        continue;
      }
      foreach($Columns as $ColName => $Val) {
        if ($Val == $Current['TABLE'][$TableName][$ColName]) {
          continue;
        }
        if ($LastTableName != $TableName) {
$text = _("Table");
$text1 = _("Column");
$text2 = _("Description");
$text3 = _("Add SQL");
$text4 = _("Alter SQL\n");
          print "<tr><th><a name='Table-$TableName'></a>$text<th>$text1<th>$text2<th>$text3<th>$text4";
          $LastTableName = $TableName;
        }
        if (empty($ColName)) {
          continue;
        }
        print "<tr bgcolor='$Blue'><td>" . htmlentities($TableName);
        print "<td>" . htmlentities($ColName);
        print "<td>" . $Val['DESC'];
        print "<td>" . $Val['ADD'];
        print "<td>" . $Val['ALTER'];
        print "\n";
      }
    }
    if (!empty($Current['TABLE'])) foreach($Current['TABLE'] as $TableName => $Columns) {
      if (empty($TableName)) {
        continue;
      }
      foreach($Columns as $ColName => $Val) {
        if ($Val == $Schema['TABLE'][$TableName][$ColName]) {
          continue;
        }
        if ($LastTableName != $TableName) {
$text = _("Table");
$text1 = _("Column");
$text2 = _("Description");
$text3 = _("Add SQL");
$text4 = _("Alter SQL\n");
          print "<tr><th><a name='Table-$TableName'></a>$text<th>$text1<th>$text2<th>$text3<th>$text4";
          $LastTableName = $TableName;
        }
        if (empty($ColName)) {
          continue;
        }
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
$text = _("Sequence");
$text1 = _("Definition\n");
    print "<th>$text<th>$text1";
    if (!empty($Schema['SEQUENCE'])) foreach($Schema['SEQUENCE'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      if ($Description == $Current['SEQUENCE'][$Name]) {
        continue;
      }
      print "<tr bgcolor='$Blue'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    if (!empty($Current['SEQUENCE'])) foreach($Current['SEQUENCE'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      if ($Description == $Schema['SEQUENCE'][$Name]) {
        continue;
      }
      print "<tr bgcolor='$Red'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    print "</table>\n";
    print "<P/>\n";
    print "<a name='View'></a><table width='100%' border='1'>\n";
$text = _("View");
$text1 = _("Definition\n");
    print "<th>$text<th>$text1";
    if (!empty($Schema['VIEW'])) foreach($Schema['VIEW'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      if ($Description == $Current['VIEW'][$Name]) {
        continue;
      }
      print "<tr bgcolor='$Blue'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    if (!empty($Current['VIEW'])) foreach($Current['VIEW'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      if ($Description == $Schema['VIEW'][$Name]) {
        continue;
      }
      print "<tr bgcolor='$Red'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    print "</table>\n";
    print "<P/>\n";
    print "<a name='Index'></a><table width='100%' border='1'>\n";
$text = _("Table");
$text1 = _("Index");
$text2 = _("Definition\n");
    print "<th>$text<th>$text1<th>$text2";
    if (!empty($Schema['INDEX'])) foreach($Schema['INDEX'] as $Table => $Indexes) {
      if (empty($Table)) {
        continue;
      }
      foreach($Indexes as $Index => $Define) {
        if ($Define == $Current['INDEX'][$Table][$Index]) {
          continue;
        }
        print "<tr bgcolor='$Blue'><td>" . htmlentities($Table);
        print "<td>" . htmlentities($Index);
        print "<td>" . htmlentities($Define);
      }
    }
    if (!empty($Current['INDEX'])) foreach($Current['INDEX'] as $Table => $Indexes) {
      if (empty($Table)) {
        continue;
      }
      foreach($Indexes as $Index => $Define) {
        if ($Define == $Schema['INDEX'][$Table][$Index]) {
          continue;
        }
        print "<tr bgcolor='$Red'><td>" . htmlentities($Table);
        print "<td>" . htmlentities($Index);
        print "<td>" . htmlentities($Define);
      }
    }
    print "</table>\n";
    print "<P/>\n";
    print "<a name='Constraint'></a><table width='100%' border='1'>\n";
$text = _("Constraint");
$text1 = _("Definition\n");
    print "<th>$text<th>$text1";
    if (!empty($Schema['CONSTRAINT'])) foreach($Schema['CONSTRAINT'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      if ($Description == $Current['CONSTRAINT'][$Name]) {
        continue;
      }
      print "<tr bgcolor='$Blue'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    if (!empty($Current['CONSTRAINT'])) foreach($Current['CONSTRAINT'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      if ($Description == $Schema['CONSTRAINT'][$Name]) {
        continue;
      }
      print "<tr bgcolor='$Red'><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    print "</table>\n";
  } // CompareSchema()
  /***********************************************************
  ViewSchema(): Get the current schema and display it to the screen.
  ***********************************************************/
  function ViewSchema() {
    $Schema = GetSchema();
    print "<ul>\n";
$text = _("Tables");
    print "<li><a href='#Table'>$text</a>\n";
$text = _("Sequences");
    print "<li><a href='#Sequence'>$text</a>\n";
$text = _("Views");
    print "<li><a href='#View'>$text</a>\n";
$text = _("Indexes");
    print "<li><a href='#Index'>$text</a>\n";
$text = _("Constraints");
    print "<li><a href='#Constraint'>$text</a>\n";
    if (count($Schema['FUNCTION']) > 0) {
$text = _("Functions");
      print "<li><a href='#Function'>$text</a>\n";
    }
    print "</ul>\n";
    print "<a name='Table'></a><table width='100%' border='1'>\n";
    $LastTableName = "";
    if (!empty($Schema['TABLE'])) foreach($Schema['TABLE'] as $TableName => $Columns) {
      if (empty($TableName)) {
        continue;
      }
      foreach($Columns as $ColName => $Val) {
        if ($LastTableName != $TableName) {
$text = _("Table");
$text1 = _("Column");
$text2 = _("Description");
$text3 = _("Add SQL");
$text4 = _("Alter SQL\n");
          print "<tr><th><a name='Table-$TableName'></a>$text<th>$text1<th>$text2<th>$text3<th>$text4";
          $LastTableName = $TableName;
        }
        if (empty($ColName)) {
          continue;
        }
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
$text = _("Sequence");
$text1 = _("Definition\n");
    print "<th>$text<th>$text1";
    if (!empty($Schema['SEQUENCE'])) foreach($Schema['SEQUENCE'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      print "<tr><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    print "</table>\n";
    print "<P/>\n";
    print "<a name='View'></a><table width='100%' border='1'>\n";
$text = _("View");
$text1 = _("Definition\n");
    print "<th>$text<th>$text1";
    if (!empty($Schema['VIEW'])) foreach($Schema['VIEW'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      print "<tr><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    print "</table>\n";
    print "<P/>\n";
    print "<a name='Index'></a><table width='100%' border='1'>\n";
$text = _("Table");
$text1 = _("Index");
$text2 = _("Definition\n");
    print "<th>$text<th>$text1<th>$text2";
    if (!empty($Schema['INDEX'])) foreach($Schema['INDEX'] as $Table => $Indexes) {
      if (empty($Table)) {
        continue;
      }
      foreach($Indexes as $Index => $Define) {
        print "<tr><td>" . htmlentities($Table);
        print "<td>" . htmlentities($Index);
        print "<td>" . htmlentities($Define);
      }
    }
    print "</table>\n";
    print "<P/>\n";
    print "<a name='Constraint'></a><table width='100%' border='1'>\n";
$text = _("Constraint");
$text1 = _("Definition\n");
    print "<th>$text<th>$text1";
    if (!empty($Schema['CONSTRAINT'])) foreach($Schema['CONSTRAINT'] as $Name => $Description) {
      if (empty($Name)) {
        continue;
      }
      print "<tr><td>" . htmlentities($Name) . "<td>" . htmlentities($Description) . "\n";
    }
    print "</table>\n";
    if (count($Schema['FUNCTION']) > 0) {
      print "<P/>\n";
      print "<a name='Function'></a><table width='100%' border='1'>\n";
$text = _("Function");
$text1 = _("Definition\n");
      print "<th>$text<th>$text1";
      if (!empty($Schema['FUNCTION'])) foreach($Schema['FUNCTION'] as $Name => $Description) {
        if (empty($Name)) {
          continue;
        }
        print "<tr><td>" . htmlentities($Name) . "<td><pre>" . htmlentities($Description) . "</pre>\n";
      }
      print "</table>\n";
    }
  } // ViewSchema()
  /***********************************************************
  ExportSchema(): Export the current schema to a file.
  ***********************************************************/
  function ExportSchema($Filename = NULL) {
    if (empty($Filename)) {
      $Filename = $this->Filename;
    }
    $Schema = GetSchema();
    $Fout = fopen($Filename, "w");
    if (!$Fout) {
      return ("Failed to write to $Filename\n");
    }
    fwrite($Fout, "<?php\n");
    fwrite($Fout, "/* This file is generated by " . $this->Name . " */\n");
    fwrite($Fout, "/* Do not manually edit this file */\n\n");
    fwrite($Fout, "  global \$GlobalReady;\n");
    fwrite($Fout, "  if (!isset(\$GlobalReady)) { exit; }\n\n");
    fwrite($Fout, '  $Schema=array();' . "\n");
    foreach($Schema as $K1 => $V1) {
      $K1 = str_replace('"', '\"', $K1);
      $A1 = '  $Schema["' . $K1 . "\"]";
      if (!is_array($V1)) {
        $V1 = str_replace('"', '\"', $V1);
        fwrite($Fout, "$A1 = \"$V1\";\n");
      }
      else {
        foreach($V1 as $K2 => $V2) {
          $K2 = str_replace('"', '\"', $K2);
          $A2 = $A1 . '["' . $K2 . '"]';
          if (!is_array($V2)) {
            $V2 = str_replace('"', '\"', $V2);
            fwrite($Fout, "$A2 = \"$V2\";\n");
          }
          else {
            foreach($V2 as $K3 => $V3) {
              $K3 = str_replace('"', '\"', $K3);
              $A3 = $A2 . '["' . $K3 . '"]';
              if (!is_array($V3)) {
                $V3 = str_replace('"', '\"', $V3);
                fwrite($Fout, "$A3 = \"$V3\";\n");
              }
              else {
                foreach($V3 as $K4 => $V4) {
                  $V4 = str_replace('"', '\"', $V4);
                  $A4 = $A3 . '["' . $K4 . '"]';
                  fwrite($Fout, "$A4 = \"$V4\";\n");
                } /* K4 */
                fwrite($Fout, "\n");
              }
            } /* K3 */
            fwrite($Fout, "\n");
          }
        } /* K2 */
        fwrite($Fout, "\n");
      }
    } /* K1 */
    fwrite($Fout, "?>\n");
    fclose($Fout);
    print "Data written to $Filename\n";
  } // ExportSchema()
  /***********************************************************
  MigrateSchema(): Any special code for migrating data.
  This is called AFTER columns/tables are added and AFTER
  constraints are removed.
  But it is called BEFORE old columns are dropped.
  ***********************************************************/
  function MigrateSchema() {
    global $DB;
    print "  Migrating database records\n";
    flush();
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
    if (count($BadMime) > 0) {
      /* Determine if ANY need to be fixed. */
      $BadPfile = $DB->Action("SELECT COUNT(*) AS count FROM pfile WHERE pfile_mimetypefk IN ($CheckMime);");
      print "Due to a previous bug (now fixed), " . number_format($BadPfile['count'], 0, "", ",") . " files are associated with " . number_format(count($BadMime) , 0, "", ",") . " bad mimetypes.  Fixing now.\n";
      $DB->Action("UPDATE pfile SET pfile_mimetypefk = NULL WHERE pfile_mimetypefk IN ($CheckMime);");
      $DB->Action("DELETE FROM mimetype WHERE mimetype_name LIKE '%,%' OR mimetype_name NOT LIKE '%/%' OR mimetype_name = 'application/octet-string';");
      // $DB->Action("VACUUM ANALYZE mimetype;");
      /* Reset all mimetype analysis -- the ones that are done will be skipped.
      The ones that are not done will be re-done. */
      if ($BadPfile['count'] > 0) {
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
    if ($DB->TblExist("ufile") && $DB->ColExist("upload", "ufile_fk")) {
      $DB->Action("UPDATE upload SET pfile_fk = ufile.pfile_fk FROM ufile WHERE upload.pfile_fk IS NULL AND upload.ufile_fk = ufile.ufile_pk;");
    }
    if ($DB->TblExist("ufile") && $DB->ColExist("uploadtree", "ufile_fk")) {
      $DB->Action("UPDATE uploadtree SET pfile_fk = ufile.pfile_fk FROM ufile WHERE uploadtree.pfile_fk IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
      $DB->Action("UPDATE uploadtree SET ufile_mode = ufile.ufile_mode FROM ufile WHERE uploadtree.ufile_mode IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
      $DB->Action("UPDATE uploadtree SET ufile_name = ufile.ufile_name FROM ufile WHERE uploadtree.ufile_name IS NULL AND uploadtree.ufile_fk = ufile.ufile_pk;");
    }
    /************ Delete obsolete tables and columns ************/
    if ($DB->TblExist("ufile")) {
      $DB->Action("DROP TABLE ufile CASCADE;");
    }
    if ($DB->TblExist("proj")) {
      $DB->Action("DROP TABLE proj CASCADE;");
    }
    if ($DB->TblExist("log")) {
      $DB->Action("DROP TABLE log CASCADE;");
    }
    if ($DB->TblExist("table_enum")) {
      $DB->Action("DROP TABLE table_enum CASCADE;");
    }
    if ($DB->ColExist("job", "job_submitter")) {
      $DB->Action("ALTER TABLE \"job\" DROP COLUMN \"job_submitter\" ;");
    }
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
    for ($i = 0;!empty($Tables[$i]['table']);$i++) {
      /* Reset each sequence to the max value in the column. */
      $Seq = $Tables[$i]['value'];
      $Seq = preg_replace("/.*'(.*)'.*/", '$1', $Seq);
      $Table = $Tables[$i]['table'];
      $Column = $Tables[$i]['column'];
      $Results = $DB->Action("SELECT max($Column) AS max FROM \"$Table\" LIMIT 1;");
      $Max = intval($Results[0]['max']);
      if (empty($Max) || ($Max <= 0)) {
        $Max = 1;
      }
      else {
        $Max++;
      }
      // print "Setting table($Table) column($Column) sequence($Seq) to $Max\n";
      $DB->Action("SELECT setval('$Seq',$Max);");
    }
  } // MigrateSchema()
  /***********************************************************
  InitSchema(): Initialize any new schema elements.
  ***********************************************************/
  function InitSchema($Debug) {
    /* Make sure every upload has left and right indexes set. */
    global $LIBEXECDIR;
    print "  Initializing new tables and columns\n";
    flush();
    system("$LIBEXECDIR/agents/adj2nest -a");
    global $Plugins;
    $Max = count($Plugins);
    $FailFlag = 0;
    print "  Initializing plugins\n";
    flush();
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
    $this->InitAgents($Debug);
    return (0);
  } // InitSchema()
  
  /***********************************************************
  InitAgents(): Every agent program must be run one time with
  a "-i" before being used.  This allows them to configure the DB
  or insert any required DB fields.
  Returns 0 on success, dies upon failure!
  ***********************************************************/
  function InitAgents($Debug = 1) {
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
  } // InitAgents()

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
  function Output() {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
      break;
      case "HTML":
        $Init = GetParm('View', PARM_INTEGER);
        if ($Init == 1) {
          $rc = $this->ViewSchema();
          if (!empty($rc)) {
            $V.= displayMessage($rc);
          }
          $V.= "<hr>\n";
        }
        $Init = GetParm('Compare', PARM_INTEGER);
        if ($Init == 1) {
          $rc = $this->CompareSchema($this->Filename);
          if (!empty($rc)) {
            $V.= displayMessage($rc);
          }
          $V.= "<hr>\n";
        }
        /* Undocumented parameter: Used for exporting the current terms. */
        $Init = GetParm('Export', PARM_INTEGER);
        if ($Init == 1) {
          $rc = $this->ExportSchema($this->Filename);
          if (!empty($rc)) {
            $V.= displayMessage($rc);
          }
          $V.= "<hr>\n";
        }
        $Init = GetParm('Apply', PARM_INTEGER);
        if ($Init == 1) {
          print "<pre>";
          $rc = ApplySchema($this->Filename, 0, 0);
          print "</pre>";
          if (!empty($rc)) {
            $V.= displayMessage($rc);
          }
          $V.= "<hr>\n";
        }
        $V.= "<form method='post'>\n";
        $V.= _("Viewing, exporting, and applying the schema is only used by installation and debugging.\n");
        $V.= _("Otherwise, you should not need to use this functionality.\n");
$text = _("Using this functionality willy-nilly may");
$text1 = _("TOTALLY SCREW UP");
$text2 = _("your FOSSology database.");
        $V.= "<P/><b>$text <u><i>$text1</i></u>$text2</b>\n";
        $V.= "<P/>\n";
        $V.= "<table width='100%' border='1'>\n";
$text = _("Check to view the current schema. The output generation is harmless, but extremely technical.");
        $V.= "<tr><td width='2%'><input type='checkbox' value='1' name='View'><td>$text<br>\n";
$text = _("Highlight the differences between the default schema (blue) and current schema (red).");
        $V.= "<tr><td><input type='checkbox' value='1' name='Compare'><td>$text<br>\n";
$text = _("Check to export the current schema. This will overwrite your default schema configuration file. Don't do this unless you know");
$text1 = _("exactly");
$text2 = _("what you are doing. The default configuration file is the only one that is supported. This will overwrite your default file.");
        $V.= "<tr><td><input type='checkbox' value='1' name='Export'><td>$text <i>$text1</i> $text2<br>\n";
$text = _("Check to apply the last exported schema. This will overwrite and atempt to migrate your database schema according to the default configuration file. Non-standard columns, tables, constraints, and views can and will be destroyed.\n");
        $V.= "<tr><td><input type='checkbox' value='1' name='Apply'><td>$text";
        $V.= "</table>\n";
        $V.= "<P/>\n";
        $V.= "<input type='submit' value='Go!'>";
        $V.= "</form>\n";
      break;
      case "Text":
      break;
      default:
      break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ($V);
    return;
  } // Output()

}; // class core_schema
$NewPlugin = new core_schema;
?>
