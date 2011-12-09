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

define("TITLE_admin_db_cleanup", _("Database Check"));

class admin_db_cleanup extends FO_Plugin
{
  var $Name       = "admin_db_cleanup";
  var $Version    = "1.0";
  var $Title      = TITLE_admin_db_cleanup;
  var $MenuList   = "Admin::Database::Check";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_USERADMIN;

  /**
   * \brief Fix the DB by deleting offending records.
   */
  function FixDB($CheckType)
  {
    global $PG_CONN;
    $text = _("Deleting");
    print $text . $CheckType['label'] . "...";
    print "<pre>";
    $sql = "DELETE " . $CheckType['sql'];
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    print "db command is:$sql\n";
    print "</pre>";
    if (pg_affected_rows($result) > 0)
    {
      $text = _("Deleted");
      $text1 = _("from");
      print $text .  pg_affected_rows($result) . $text1 ;
      print $CheckType['label'] . ".<br>\n";
    }
    pg_free_result($result);
  } // FixDB()

  /**
   * \brief Generate output.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $PG_CONN;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $Checks = array();
        $i=0;

        $Checks[$i]['tag']   = "bad_upload_pfile";
        $Checks[$i]['label'] = "Uploads missing pfiles";
        $Checks[$i]['sql']   = "FROM upload WHERE upload_pk IN (SELECT upload_fk FROM uploadtree WHERE parent IS NULL AND pfile_fk IS NULL) OR upload_pk NOT IN (SELECT upload_fk FROM uploadtree);";
        $Checks[$i]['list']  = "SELECT upload_filename AS list " . $Checks[$i]['sql'];
        $i++;

        $Checks[$i]['tag']   = "bad_foldercontents_upload";
        $Checks[$i]['label'] = "Foldercontents with invalid upload references";
        $Checks[$i]['sql']   = "FROM foldercontents WHERE foldercontents_mode = 2 AND child_id NOT IN (SELECT upload_pk FROM upload);";
        $i++;

        $Checks[$i]['tag']   = "bad_foldercontents_uploadtree";
        $Checks[$i]['label'] = "Foldercontents with invalid uploadtree references";
        $Checks[$i]['sql']   = "FROM foldercontents WHERE foldercontents_mode = 4 AND child_id NOT IN (SELECT uploadtree_pk FROM uploadtree);";
        $i++;

        $Checks[$i]['tag']   = "bad_foldercontents_folder";
        $Checks[$i]['label'] = "Foldercontents with invalid folder references";
        $Checks[$i]['sql']   = "FROM foldercontents WHERE foldercontents_mode = 1 AND child_id NOT IN (SELECT folder_pk FROM folder);";
        $i++;

        $Checks[$i]['tag']   = "unreferenced_folder";
        $Checks[$i]['label'] = "Unreferenced folders";
        $Checks[$i]['sql']   = "FROM folder WHERE folder_pk NOT IN (SELECT child_id FROM foldercontents WHERE foldercontents_mode = 1) AND folder_pk != '1';";
        $Checks[$i]['list']  = "SELECT folder_name AS list FROM folder WHERE folder_pk NOT IN (SELECT child_id FROM foldercontents WHERE foldercontents_mode = 1) AND folder_pk != '1' LIMIT 20;";
        $i++;

        $Checks[$i]['tag']   = "duplicate_attrib";
        $Checks[$i]['label'] = "Duplicate attrib records";
        $Checks[$i]['sql']   = "FROM attrib WHERE attrib_pk NOT IN (SELECT MIN(dup.attrib_pk) FROM attrib AS dup GROUP BY dup.pfile_fk, dup.attrib_key_fk, dup.attrib_value);";
        $i++;

        $Checks[$i]['tag']   = "unused_terms";
        $Checks[$i]['label'] = "License terms with no canonical names";
        $Checks[$i]['sql']   = "FROM licterm_words WHERE licterm_words_pk NOT IN (SELECT licterm_words_fk FROM licterm_map);";
        $Checks[$i]['list']  = "SELECT licterm_words_text AS list " . $Checks[$i]['sql'];
        $i++;

        if (0)
        {
          $Checks[$i]['tag']   = "abandoned_license_temp_table";
          $Checks[$i]['label'] = "Stranded temporary license lookup table";
          $Checks[$i]['sql']   = "FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_schema = 'public' AND table_name SIMILAR TO '^license_[[:digit:]]+$';";
          $Checks[$i]['list']  = "SELECT table_name AS list " . $Checks[$i]['sql'];
          $i++;

          $Checks[$i]['tag']   = "abandoned_metaanalysis_temp_table";
          $Checks[$i]['label'] = "Stranded temporary metaanalysis lookup table";
          $Checks[$i]['sql']   = "FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_schema = 'public' AND table_name SIMILAR TO '^metaanalysis_[[:digit:]]+$';";
          $Checks[$i]['list']  = "SELECT table_name AS list " . $Checks[$i]['sql'];
          $i++;
        }


        /* Check for anything to fix */
        $Args=0;
        for($i=0; !empty($Checks[$i]['tag']); $i++)
        {
          if (GetParm($Checks[$i]['tag'],PARM_INTEGER) == 1)
          {
            $text = _("Fixing Records");
            if ($Args==0) { print "<H1>$text</H1>\n"; }
            $this->FixDB($Checks[$i]);
            $Args++;
          }
        }
        $text = _("Fix Records");
        if ($Args > 0) { $V .= "<H1>$text</H1>\n"; }

        /***************************************/
        $V .= _("On occasion, the database can become inconsistent.");
        $V .= _(" For example, there may be pfile records without uploadtree entries");
        $V .= _(" or uploadtree entries that are not linked by any upload records.");
        $V .= _(" Inconsistencies usually arise due to failed unpacking, partial deletions, and testing.\n");

        $V .= "<script language='javascript'>\n";
        $V .= "<!--\n";
        $V .= "function ShowHideDBDetails(name)\n";
        $V .= "  {\n";
        $V .= "  if (name.length < 1) { return; }\n";
        $V .= "  var Element, State;\n";
        $V .= "  if (document.getElementById) // standard\n";
        $V .= "    { Element = document.getElementById(name); }\n";
        $V .= "  else if (document.all) // IE 4, 5, beta 6\n";
        $V .= "    { Element = document.all[name]; }\n";
        $V .= "  else // if (document.layers) // Netscape 4 and older\n";
        $V .= "    { Element = document.layers[name]; }\n";
        $V .= "  State = Element.style;\n";
        $V .= "  if (State.display == 'none') { State.display='block'; }\n";
        $V .= "  else { State.display='none'; }\n";
        $V .= "  }\n";
        $V .= "// -->\n";
        $V .= "</script>\n";

        $sql = "SELECT COUNT(*) AS count FROM jobqueue WHERE (jq_type = 'unpack' OR jq_type = 'delagent' OR jq_type = 'license' OR jq_type = 'pkgmetagetta') AND jq_starttime IS NOT NULL AND jq_endtime IS NULL;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        $Count = $row['count'];
        pg_free_result($result);
        if ($Count == 1) { $Verb = "is"; $String = "task"; }
        else { $Verb = "are"; $String = "tasks"; }
        $text = _("Temporary inconsistencies may exist when a file is uploaded, being unpacked, or being deleted.\n");
        $V .= "<P>$text";
        $V .= "<ul>";
        $text = _("An uploaded file -- before being unpacked -- can appear inconsistent.\n");
        $V .= "<li>$text";
        $text = _("The unpack system creates pfiles first, then links them together when it completes.\n");
        $V .= "<li>$text";
        $text = _("The delete system removes records in series, so a partial delete (or delete in progress) can show inconsistencies.\n");
        $V .= "<li>$text";
        $V .= "</ul>\n";
        $text = _("There");
        $text1 = _("currently");
        $text2 = _("running");
        $text3 = _("in the job queue that may make records appear inconsistent.\n");
        $V .= "$text $Verb <b>$text1 $Count $String $text2</b> $text3";
        $V .= _("Fixing inconsistencies while any jobs are running could lead to job failures and further inconsistencies.\n");
        $text = _("NOTE: Some of these inconsistencies may not be resolvable from here due to table constraints.\n");
        $V.= "<P>$text";

        /****************************************************/
        $text = _("The following inconsistencies have been identified:\n");
        $V .= "<P>$text";
        $V .= "<form method='POST'>";
        $V .= "<table border=1 width='100%'>\n";
        $text = _("Fix");
        $text1 = _("Type of Inconsistency");
        $V .= "<tr><th width='5%'>$text</th><th width='80%'>$text1</th><th>Count</th></tr>\n";

        $FixCount=0;
        for($i=0; !empty($Checks[$i]['tag']); $i++)
        {
          $sql = "SELECT COUNT(*) AS count " . $Checks[$i]['sql'];
          $result = pg_query($PG_CONN, $sql);
          DBCheckResult($result, $sql, __FILE__, __LINE__);
          $row = pg_fetch_assoc($result);
          $Count = $row['count'];
          pg_free_result($result);
          $V .= "<tr>";
          if ($Count > 0)
          {
            $V .= "<td valign='top'><input type='checkbox' name='" . $Checks[$i]['tag'] . "' value='1'></td>";
            $FixCount++;
          }
          else { $V .= "<td></td>"; }
          $V .= "<td valign='top'>" . $Checks[$i]['label'];
          if (!empty($Checks[$i]['list']) && ($Count > 0))
          {
            $text = _("Details");
            $V .= " (<a href=\"javascript:ShowHideDBDetails('Details_$i')\">$text</a>)<br>\n";
            $V .= "<div id='Details_$i' style='display:none;'>";
            $sql = $Checks[$i]['list'];
            $result = pg_query($PG_CONN, $sql);
            DBCheckResult($result, $sql, __FILE__, __LINE__);
            for($j=0; ($row = pg_fetch_assoc($result)) and !empty($row['list']); $j++)
            {
              if ($j > 0) { $V .= "<br>\n"; }
              $V .= ($j+1) . ": " . htmlentities($row['list']);
            }
            pg_free_result($result);

            if ($j < $Count) { $V .= "<br>\n..."; }
            $V .= "</div>\n";
          }
          $V .= "</td>";
          $V .= "<td align='right' valign='top'>" . number_format($Count,0,"",",") . "</td></tr>\n";
        }

        $V .= "</table>\n";
        $V .= "<P />";
        $text = _("Fix");
        if ($FixCount > 0) { $V .= "<input type='submit' value='$text!'>"; }
        else { $V .= _("No database inconsistencies found.\n"); }
        $V .= "</form>";
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

};
$NewPlugin = new admin_db_cleanup;
$NewPlugin->Initialize();
?>
