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
if (!isset($GlobalReady)) {
  exit;
}

class ui_license_list extends FO_Plugin {
  var $Name = "license-list";
  var $Title = "Nomos License List";
  var $Version = "1.0";
  var $Dependency = array(
    "db",
    "browse",
    "license",
    "view-license"
  );
  var $DBaccess = PLUGIN_DB_READ;
  var $LoginFlag = 0;
  var $NoHeader = 0;


  /***********************************************************
  RegisterMenus(): Customize submenus.
  ***********************************************************/

  function RegisterMenus() {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array(
      "show",
      "format",
      "page",
      "upload",
      "item",
    ));
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (!empty($Item) && !empty($Upload)) {
      if (GetParm("mod", PARM_STRING) == $this->Name) {
        menu_insert("Browse::Nomos License List", 1);
//        menu_insert("Browse::[BREAK]", 100);
        menu_insert("Browse::Nomos License List Download", 1, $URI . "&output=dltext");
      }
      else {
        menu_insert("Browse::Nomos License List", 1, $URI, "Nomos license listing");
        menu_insert("Browse::Nomos License List Download", 1, $URI . "&output=dltext");
      }
    }
  } // RegisterMenus()


  /***********************************************************
  OutputOpen(): This function is called when user output is
  requested.  This function is responsible for assigning headers.
  If $Type is "HTML" then generate an HTTP header.
  If $Type is "XML" then begin an XML header.
  If $Type is "Text" then generate a text header as needed.
  The $ToStdout flag is "1" if output should go to stdout, and
  0 if it should be returned as a string.  (Strings may be parsed
  and used by other plugins.)
  ***********************************************************/
  function OutputOpen($Type, $ToStdout) {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    }
    if (GetParm("output", PARM_STRING) == 'dltext') {
      $Type = 'dltext';
    }
    $this->OutputType = $Type;
    $this->OutputToStdout = $ToStdout;
    $Item = GetParm("item", PARM_INTEGER);
    if (empty($Item)) {
      return;
    }
    switch ($this->OutputType) {
      case "dltext":
        $this->NoHeader = 1;
        $Path = Dir2Path($Item);
        $Name = $Path[count($Path) - 1]['ufile_name'] . ".txt";
        header("Content-Type: text");
        header('Content-Disposition: attachment; filename="' . $Name . '"');
        $V = "";
      break;
      case "XML":
        $V = "<xml>\n";
      break;
      case "HTML":
        header('Content-type: text/html');
        if ($this->NoHTML) {
          return;
        }
        $V = "";
        if (($this->NoMenu == 0) && ($this->Name != "menus")) {
          $Menu = & $Plugins[plugin_find_id("menus") ];
          $Menu->OutputSet($Type, $ToStdout);
        }
        else {
          $Menu = NULL;
        }
        /* DOCTYPE is required for IE to use styles! (else: css menu breaks) */
$text = _("' . "\n");
        $V.= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "xhtml1-frameset.dtd">$text";
$text = _("' . "\n");
        // $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">$text";
$text = _("' . "\n");
        // $V .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Loose//EN" "http://www.w3.org/TR/html4/loose.dtd">$text";
$text = _("' . "\n");
        // $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "xhtml1-strict.dtd">$text";
        $V.= "<html>\n";
        $V.= "<head>\n";
        if ($this->NoHeader == 0) {
          /** Known bug: DOCTYPE "should" be in the HEADER
           and the HEAD tags should come first.
$text = _("...");
$text1 = _(" tags that are NOT
");
           Also, IE will ignore <style>$text</style>$text1$text = _("...");
$text1 = _(" block.
");
           in a <head>$text</head>$text1           *
           */
          if (!empty($this->Title)) {
$text = _("Title) . "");
$text1 = _("\n");
            $V.= "<title>" . htmlentities($this->$text</title>$text1";
          }
          $V.= "<link rel='stylesheet' href='fossology.css'>\n";
          print $V;
          $V = "";
          if (!empty($Menu)) {
            print $Menu->OutputCSS();
          }
          $V.= "</head>\n";
          $V.= "<body class='text'>\n";
          print $V;
          $V = "";
          if (!empty($Menu)) {
            $Menu->Output($this->Title);
          }
        }
      break;
      case "Text":
      break;
      default:
      break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print $V;
    return;
  } // OutputOpen()

  /***********************************************************
  Output(): This function returns the scheduler status.
  ***********************************************************/

  function Output() 
  {
    global $PG_CONN, $DB;
    if (!$PG_CONN) { $dbok = $DB->db_init(); if (!$dbok) echo "NO DB connection"; }

    if ($this->State != PLUGIN_STATE_READY)  return (0);
    $V = "";
    $uploadtree_pk = GetParm("item", PARM_INTEGER);
    if (empty($uploadtree_pk)) return;
    $upload_pk = GetParm("upload", PARM_INTEGER);
    if (GetParm("output", PARM_STRING) == 'dltext')
      $dltext = true;
    else
      $dltext = false;

    /* get last nomos agent_pk that has data for this upload */
    $Agent_name = "nomos";
    $AgentRec = AgentARSList("nomos_ars", $upload_pk, 1);
    $agent_pk = $AgentRec[0]["agent_fk"];
    if ($AgentRec === false)
    {
      echo "No data available";
      return;
    }

    /* get the top of tree */
    $sql = "SELECT upload_fk, lft, rgt from uploadtree where uploadtree_pk='$uploadtree_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $toprow = pg_fetch_assoc($result);
    pg_free_result($result); 
  
    /* loop through all the records in this tree */
    $sql = "select uploadtree_pk, ufile_name, lft, rgt from uploadtree 
              where upload_fk='$toprow[upload_fk]' 
                    and lft>'$toprow[lft]'  and rgt<'$toprow[rgt]'
                    and ((ufile_mode & (1<<28)) = 0)";
    $outerresult = pg_query($PG_CONN, $sql);
    DBCheckResult($outerresult, $sql, __FILE__, __LINE__);

    /* Select each uploadtree row in this tree, write out text:
     * filepath : license list
     * e.g. Pound-2.4.tgz/Pound-2.4/svc.c: GPL_v3+, Indemnity
     */
    while ($row = pg_fetch_assoc($outerresult))
    { 
      $filepatharray = Dir2Path($row['uploadtree_pk']);
      $filepath = "";
      foreach($filepatharray as $uploadtreeRow)
      {
        if (!empty($filepath)) $filepath .= "/";
        $filepath .= $uploadtreeRow['ufile_name'];
      }
      $V .= $filepath . ": ". GetFileLicenses_string($agent_pk, 0, $row['uploadtree_pk']) ;
      if ($dltext)
        $V .= _("\n");
      else 
        $V .= "<br>";
    } 
    pg_free_result($outerresult);
    
    if (!$this->OutputToStdout) return ($V);
    print "$V";
    return;
  }
};
$NewPlugin = new ui_license_list;
$NewPlugin->Initialize();
?>
