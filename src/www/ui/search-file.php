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

define("TITLE_search_file", _("Search for File"));

class search_file extends FO_Plugin
{
  var $Name       = "search_file";
  var $Title      = TITLE_search_file;
  var $Version    = "1.0";
  var $MenuList   = "Search";
  var $MenuOrder  = 90;
  var $Dependency = array("view","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  public $LoginFlag  = 0;

  function PostInitialize()
  {
    /* This plugin is only valid if the system allows global searching */
    if (IsRestrictedTo())
    $this->State = PLUGIN_STATE_INVALID;
    else
    $this->State = PLUGIN_STATE_READY;
    return $this->State;
  }

  /**
   * \brief Customize submenus.
   */ 
  function RegisterMenus()
  {
    menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);
  } // RegisterMenus()


  /**
   * \brief Given a filename, return all uploadtree.
   */
  function GetUploadtreeFromName($Filename, $tag, $Page, $MimetypeNot, $Mimetype, $SizeMin, $SizeMax, $ContainerOnly=true)
  {
    global $PG_CONN;
    $Max = 50;
    $Filename = str_replace("'","''",$Filename); // protect DB
    $Terms = explode(' ', $Filename);
    $SQL = "SELECT * FROM uploadtree INNER JOIN pfile ON pfile_pk = pfile_fk";
    if ($ContainerOnly) $SQL .= " AND ((uploadtree.ufile_mode & (1<<29)) != 0) ";
    foreach($Terms as $Key => $T)
    {
      if (empty($T)) continue;
      $SQL .= " AND ufile_name like '$T'";
    }
    $NeedAnd=0;
    if (!empty($Mimetype) && ($Mimetype >= 0))
    {
      if ($NeedAnd) { $SQL .= " AND"; }
      else { $SQL .= " WHERE"; }
      $SQL .= " pfile.pfile_mimetypefk ";
      if ($MimetypeNot != 0) { $SQL .= "!"; }
      $SQL .= "= $Mimetype";
      $NeedAnd=1;
    }
    if (!empty($SizeMin) && ($SizeMin >= 0))
    {
      if ($NeedAnd) { $SQL .= " AND"; }
      else { $SQL .= " WHERE"; }
      $SQL .= " pfile.pfile_size > $SizeMin";
      $NeedAnd=1;
    }
    if (!empty($SizeMax) && ($SizeMax >= 0))
    {
      if ($NeedAnd) { $SQL .= " AND"; }
      else { $SQL .= " WHERE"; }
      $SQL .= " pfile.pfile_size < $SizeMax";
      $NeedAnd=1;
    }
    $Offset = $Page * $Max;
    $SQL .= " ORDER BY pfile_fk,ufile_name LIMIT $Max OFFSET $Offset;";

    if (!empty($tag))
    {
      $TagSQL = "SELECT * FROM (";
      $TagSQL .= substr($SQL,0,-1);
      $TagSQL .= ") U INNER JOIN (SELECT * FROM tag_file INNER JOIN tag ON tag_pk = tag_fk AND (tag = '$tag' OR tag LIKE '$tag')) T ON U.pfile_fk = T.pfile_fk";
      $SQL = $TagSQL;
    }

    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);

    $V = "";
    $Count = pg_num_rows($result); 
    //$V .= "<pre>" . htmlentities($SQL) . "</pre>\n";

    if (($Page > 0) || ($Count >= $Max))
    {
      $Uri = Traceback_uri() . "?mod=" . $this->Name;
      $Uri .= "&filename=" . urlencode($Filename);
      $Uri .= "&tag=" . urlencode($tag);
      $Uri .= "&sizemin=$SizeMin";
      $Uri .= "&sizemax=$SizeMax";
      $Uri .= "&notmimetype=$MimetypeNot";
      $Uri .= "&mimetype=$Mimetype";
      $Uri .= "&allfiles=" . GetParm("allfiles",PARM_INTEGER);
      $VM = MenuEndlessPage($Page, ($Count >= $Max),$Uri) . "<P />\n";
      $V .= $VM;
    }
    else
    {
      $VM = "";
    }

    if ($Count == 0)
    {
      $V .= _("No results.\n");
      pg_free_result($result);
      return($V);
    }

    if ($Page==0)
    {
      $SQL = preg_replace('/\*/','COUNT(*) AS count',$SQL,1);
      $SQL = preg_replace('/ ORDER BY .*;/',';',$SQL);
      $CountR = pg_query($PG_CONN, $SQL);
      DBCheckResult($CountR, $SQL, __FILE__, __LINE__);
      $row = pg_fetch_assoc($CountR);
      pg_free_result($CountR);
      $text = _("Total matched:");
      $V .= "$text " . number_format($row['count'],0,"",",") . "<br>\n";
    }

    $V .= Dir2FileList($result,"browse","view",$Page*$Max + 1);
    pg_free_result($result);

    /* put page menu at the bottom, too */
    if (!empty($VM)) { $V .= "<P />\n" . $VM; }
    return($V);
  } // GetUploadtreeFromName()


  /**
   * \brief Given a tag, return all uploadtree.
   */
  function GetUploadtreeFromTag($tag,$Page)
  {
    global $PG_CONN;
    $Max = 50;
    $SQL = "SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_file INNER JOIN tag ON tag_pk = tag_fk AND (tag = '$tag' OR tag LIKE '$tag')) T ON uploadtree.pfile_fk = T.pfile_fk UNION SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_uploadtree INNER JOIN tag ON tag_pk = tag_fk AND (tag = '$tag' OR tag LIKE '$tag')) T ON uploadtree.uploadtree_pk = T.uploadtree_fk";
    $Offset = $Page * $Max;
    $SQL .= " ORDER BY ufile_name LIMIT $Max OFFSET $Offset;";
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);

    $V = "";
    $Count = pg_num_rows($result); 
    //$V .= "<pre>" . htmlentities($SQL) . "</pre>\n";

    if (($Page > 0) || ($Count >= $Max))
    {
      $Uri = Traceback_uri() . "?mod=" . $this->Name;
      $Uri .= "&tag=" . urlencode($tag);
      $VM = MenuEndlessPage($Page, ($Count >= $Max),$Uri) . "<P />\n";
      $V .= $VM;
    }
    else
    {
      $VM = "";
    }

    if ($Count == 0)
    {
      $V .= _("No results.\n");
      pg_free_result($result);
      return($V);
    }

    if ($Page==0)
    {
      $SQL = preg_replace('/\*/','COUNT(*) AS count',$SQL,1);
      $SQL = preg_replace('/ ORDER BY .*;/',';',$SQL);
      $CountR = pg_query($PG_CONN, $SQL);
      DBCheckResult($CountR, $SQL, __FILE__, __LINE__);
      $row = pg_fetch_assoc($CountR);
      pg_free_result($CountR);
      $text = _("Total matched:");
      $V .= "$text " . number_format($row['count'],0,"",",") . "<br>\n";
    }

    $V .= Dir2FileList($result,"browse","view",$Page*$Max + 1);
    pg_free_result($result);

    /* put page menu at the bottom, too */
    if (!empty($VM)) { $V .= "<P />\n" . $VM; }
    return($V);
  } /* GetUploadtreeFromTag */

  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $Plugins;
    global $PG_CONN;

    $V="";

    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $V .= menu_to_1html(menu_find("Search",$MenuDepth),1);

        $Filename = GetParm("filename",PARM_STRING);
        $tag = GetParm("tag",PARM_STRING);
        $SizeMin = GetParm("sizemin",PARM_TEXT) . 'x';
        if ($SizeMin != 'x') { $SizeMin=intval($SizeMin); }
        else { $SizeMin = -1; }
        if ($SizeMin < 0) { $SizeMin=-1; }
        $SizeMax = GetParm("sizemax",PARM_TEXT) . 'x';
        if ($SizeMax != 'x') { $SizeMax=intval($SizeMax); }
        else { $SizeMax = -1; }
        if ($SizeMax < 0) { $SizeMax=-1; }
        $MimetypeNot = GetParm("notmimetype",PARM_INTEGER);
        $Mimetype = GetParm("mimetype",PARM_INTEGER);
        $Page = GetParm("page",PARM_INTEGER);
        $allfiles = GetParm("allfiles",PARM_INTEGER);
        $ContainerOnly = (empty($allfiles)) ? true : false;

        $V .= "<form action='" . Traceback_uri() . "?mod=" . $this->Name . "' method='POST'>\n";
        $V .= "<ul>\n";

        $text = _("Enter the filename to find:");
        $V .= "<li><b>$text</b>";
        $V .= "<INPUT type='text' name='filename' size='40' value='" . htmlentities($Filename) . "'>\n";
        $V .= "<br>" . _("You can use '%' as a wild-card. ");
        $V .= _("For example, '%v3.war', or 'mypkg%.tar'.");
        $V .= "<p>";

        $V .= "<li>";
        $V .= "<INPUT type='checkbox' name='allfiles' value='1'";
        if ($allfiles == '1') { $V .= " checked"; }
        $V .= ">";
        $text = " <b>" . _("Show All Files") . "</b>";
        $V .= " $text";
        $text = _("By default only containers (rpms, tars, isos, etc) are shown.");
        $V .= "<br>$text";
        $V .= "<p>";

        $text = _("Tag to find");
        $V .= "<li><b>$text:</b>  <input name='tag' size='30' value='" . htmlentities($tag) . "'>\n";
        $V .= "<p>";

        /* lots of code for mimetype */
        $text = _("Mimetype ");
        $V .= "<li><b>$text</b>";
        $V .= "<select name='notmimetype'>\n";
        if ($MimetypeNot == 0)
        {
          $text = _("IS");
          $V .= "<option value='0' selected>$text</option>\n";
          $text = _("IS NOT");
          $V .= "<option value='1'>$text</option>\n";
        }
        else
        {
          $text = _("IS");
          $V .= "<option value='0'>$text</option>\n";
          $text = _("IS NOT");
          $V .= "<option value='1' selected>$text</option>\n";
        }
        $V .= "</select>\n";
        $V .= "<select name='mimetype'>\n";
        $sql = "SELECT * FROM mimetype ORDER BY mimetype_name;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $text = _("Select mimetype...");
        $V .= "<option value='-1'>$text</option>\n";
        while (($row = pg_fetch_assoc($result)) and !empty($row['mimetype_pk']))
        {
          if ($row['mimetype_pk'] == $Mimetype)
          {
            $V .= "<option value='" . $row['mimetype_pk'] . "' selected>";
          }
          else
          {
            $V .= "<option value='" . $row['mimetype_pk'] . "'>";
          }
          $V .= $row['mimetype_name'];
          $V .= "</option>\n";
        }
        pg_free_result($result);

        $V .= "</select>\n";
        $V .= "<p>";
        /* End of mimetype */

        $Value=$SizeMin; if ($Value < 0) { $Value=''; }
        $text = _("File size is");
        $text1 = _(" bytes\n");
        $V .= "<li><b>$text &gt; </b><input name='sizemin' size=10 value='$Value'>$text1";
        $Value=$SizeMax; if ($Value < 0) { $Value=''; }
        $V .= "<p>";

        $text = _("File size is");
        $text1 = _(" bytes\n");
        $V .= "<li><b>$text &lt; </b><input name='sizemax' size=10 value='$Value'>$text1";

        $V .= "</ul>\n";
        $text = _("Search");
        $V .= "<input type='submit' value='$text!'>\n";
        $V .= "</form>\n";

        if (!empty($Filename))
        {
          if (empty($Page)) { $Page = 0; }
          $V .= "<hr>\n";
          $text = _("Files matching");
          $V .= "<H2>$text " . htmlentities($Filename) . "</H2>\n";
          $V .= $this->GetUploadtreeFromName($Filename,$tag,$Page,$MimetypeNot,$Mimetype,$SizeMin,$SizeMax, $ContainerOnly);
        } else {
          if (!empty($tag))
          {
            if (empty($Page)) { $Page = 0; }
            $V .= "<hr>\n";
            $text = _("Files matching");
            $V .= "<H2>$text " . htmlentities($Filename) . "</H2>\n";
            $V .= $this->GetUploadtreeFromTag($tag,$Page);
          }
        }
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
$NewPlugin = new search_file;
$NewPlugin->Initialize();
?>
