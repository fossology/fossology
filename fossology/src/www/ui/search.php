<?php
/***********************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_search", _("Search"));

class search extends FO_Plugin
{
  var $Name       = "search";
  var $Title      = TITLE_search;
  var $Version    = "1.0";
  var $MenuList   = "";
  var $Dependency = array("view","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /**
   * \brief Given a tag, return all uploadtree.
   */
  function GetUploadtreeFromTag($Item,$tag,$Page,$searchtype)
  {
    global $PG_CONN;
    $Max = 50;

    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $sql = "SELECT lft,rgt,upload_fk FROM uploadtree WHERE uploadtree_pk = $Item;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $text = _("Invalid URL, nonexistant item");
      return "<h2>$text $Uploadtree_pk</h2>";
    }
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    $SQL = "SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_file INNER JOIN tag ON tag_pk = tag_fk AND (tag = '$tag' OR tag LIKE '$tag')) T ON uploadtree.pfile_fk = T.pfile_fk WHERE uploadtree.upload_fk = $upload_pk AND uploadtree.lft >= $lft AND uploadtree.rgt <= $rgt UNION SELECT * FROM uploadtree INNER JOIN (SELECT * FROM tag_uploadtree INNER JOIN tag ON tag_pk = tag_fk AND (tag = '$tag' OR tag LIKE '$tag')) T ON uploadtree.uploadtree_pk = T.uploadtree_fk WHERE uploadtree.upload_fk = $upload_pk AND uploadtree.lft >= $lft AND uploadtree.rgt <= $rgt";
    $Offset = $Page * $Max;

    /* search only containers of all files */
    if ($searchtype == 'package')
    {
      $SQL .= " AND ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0)";
    }
    $SQL .= " ORDER BY ufile_name LIMIT $Max OFFSET $Offset;";
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);

    $V = "";
    $Count = pg_num_rows($result);
    //$V .= "<pre>" . htmlentities($SQL) . "</pre>\n";

    if (($Page > 0) || ($Count >= $Max))
    {
      $Uri = Traceback_uri() . "?mod=" . $this->Name;
      $Uri .= "&upload=$upload_pk";
      $Uri .= "&item=$Item";
      $Uri .= "&tag=" . urlencode($tag);
      $Uri .= "&searchtype=" . urlencode($searchtype);
      $VM = MenuEndlessPage($Page, ($Count >= $Max),$Uri) . "<P />\n";
      $V .= $VM;
    }
    else
    {
      $VM = "";
    }

    if ($Count == 0)
    {
      pg_free_result($result);
      $V .= _("No results.\n");
      return($V);
    }

    if ($Page==0)
    {
      $SQL = preg_replace('/\*/','COUNT(*) AS count',$SQL,1);
      $SQL = preg_replace('/ ORDER BY .*;/',';',$SQL);
      $CountR = pg_query($PG_CONN, $SQL);
      DBCheckResult($CountR, $SQL, __FILE__, __LINE__);
      $text = _("Total matched:");
      $row = pg_fetch_assoc($CountR);
      pg_free_result($CountR);
      $V .= "$text " . number_format($row['count'],0,"",",") . "<br>\n";
    }

    $V .= Dir2FileList($result,"browse","view",$Page*$Max + 1);
    pg_free_result($result);

    /* put page menu at the bottom, too */
    if (!empty($VM)) { $V .= "<P />\n" . $VM; }
    return($V);
  }

  /**
   * \brief Given a filename, return all uploadtree.
   */
  function GetUploadtreeFromName($Item,$Filename,$tag,$Page,$MimetypeNot,$Mimetype,$SizeMin,$SizeMax,$searchtype)
  {
    $Max = 50;
    global $PG_CONN;

    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $sql = "SELECT lft,rgt,upload_fk, pfile_fk FROM uploadtree WHERE uploadtree_pk = $Item;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $text = _("Invalid URL, nonexistant item");
      return "<h2>$text $Uploadtree_pk</h2>";
    }
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    $pfile_pk = $row["pfile_fk"];
    pg_free_result($result);

    $Filename = str_replace("'","''",$Filename); // protect DB
    $SQL = "SELECT * FROM uploadtree INNER JOIN pfile ON pfile_pk = pfile_fk AND ufile_name like '$Filename'";
    $NeedAnd=0;
    if (!empty($Mimetype) && ($Mimetype >= 0))
    {
      if ($NeedAnd) { $SQL .= " AND"; }
      else { $SQL .= " WHERE"; }
      $SQL .= " (pfile.pfile_mimetypefk ";
      if ($MimetypeNot != 0) { $SQL .= "!"; }
      $SQL .= "= $Mimetype";
      if ($MimetypeNot != 0) { $SQL .= " OR pfile.pfile_mimetypefk IS NULL)"; }
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

    $SQL .= "  AND upload_fk = $upload_pk AND lft >= $lft AND rgt <= $rgt";
    /* search only containers of all files */
    if ($searchtype == 'package')
    {
      $SQL .= " AND ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0)";
    }

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

    /* get last nomos agent_pk that has data for this upload */
    $Agent_name = "nomos";
    $AgentRec = AgentARSList("nomos_ars", $upload_pk, 1);
    $nomosagent_pk = $AgentRec[0]["agent_fk"];
    if ($nomosagent_pk)
    {
      /* add licenses to results */
      while ($utprec = pg_fetch_assoc($result))
      {
        $utprec['licenses'] = GetFileLicenses_string($nomosagent_pk, $utprec['pfile_fk'], 0);
      }
    }

    $V = "";
    $Count = pg_num_rows($result);
    //$V .= "<pre>" . htmlentities($SQL) . "</pre>\n";

    if (($Page > 0) || ($Count >= $Max))
    {
      $Uri = Traceback_uri() . "?mod=" . $this->Name;
      $Uri .= "&upload=$upload_pk";
      $Uri .= "&item=$Item";
      $Uri .= "&filename=" . urlencode($Filename);
      $Uri .= "&tag=" . urlencode($tag);
      $Uri .= "&searchtype=" . urlencode($searchtype);
      $Uri .= "&sizemin=$SizeMin";
      $Uri .= "&sizemax=$SizeMax";
      $Uri .= "&notmimetype=$MimetypeNot";
      $Uri .= "&mimetype=$Mimetype";
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
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
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
        menu_insert("Browse::Search", 1);
      }
      else {
        $text = _("Search");
        menu_insert("Browse::Search", 1, $URI, $text);
      }
    }
  } // RegisterMenus()

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    $uTime = microtime(true);
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    global $Plugins;
    global $PG_CONN;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        /************************/
        /* Show the folder path */
        /************************/
        $V .= Dir2Browse($this->Name,$Item,NULL,1,"Browse") . "<P />\n";

        $searchtype = GetParm("searchtype",PARM_STRING);
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

        $V .= "<form action='" . Traceback_uri() . "?mod=" . $this->Name . "' method='POST'>\n";
        $V .= "<ul>\n";
        $text = _("Search for");
        $text1 = _("Containers only(rpms,tars,isos,etc).");
        $text2 = _("All Files");

        if ($searchtype == 'package')
        {
          $SelectedP = " checked ";
        }else{
          $SelectedP = "";
        }
        $V .= "<li>$text: <input type='radio' name='searchtype' value='package' $SelectedP>$text1 \n";
        if ($searchtype == 'file')
        {
          $Selected = " checked ";
        }else{
          $Selected = "";
        }
        $V .= "<input type='radio' name='searchtype' value='file' $Selected>$text2\n";
        $text = _("Enter the filename to find: ");
        $V .= "<li>$text";
        $V .= "<INPUT type='text' name='filename' size='40' value='" . htmlentities($Filename) . "'>\n";
        $V .= _("You can use '%' as a wild-card.\n");

        $text = _("Tag to find");
        $V .= "<li>$text:  <input name='tag' size='30' value='" . htmlentities($tag) . "'>\n";

        $text = _("Mimetype ");
        $V .= "<li>$text";
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
        while(($row = pg_fetch_assoc($result)) and !empty($row['mimetype_pk']))
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
        $Value=$SizeMin; if ($Value < 0) { $Value=''; }
        $text = _("File size is");
        $text1 = _(" bytes\n");
        $V .= "<li>$text &gt; <input name='sizemin' size=10 value='$Value'>$text1";
        $Value=$SizeMax; if ($Value < 0) { $Value=''; }
        $text = _("File size is");
        $text1 = _(" bytes\n");
        $V .= "<li>$text &lt; <input name='sizemax' size=10 value='$Value'>$text1";

        $V .= "</ul>\n";
        $V .= "<input type='hidden' name='item' value='$Item'>\n";
        $V .= "<input type='hidden' name='upload' value='$Upload'>\n";
        $text = _("Search");
        $V .= "<input type='submit' value='$text!'>\n";
        $V .= "</form>\n";

        if (!empty($Filename))
        {
          if (empty($Page)) { $Page = 0; }
          $V .= "<hr>\n";
          $text = _("Files matching");
          $V .= "<H2>$text " . htmlentities($Filename) . "</H2>\n";
          $V .= $this->GetUploadtreeFromName($Item,$Filename,$tag,$Page,$MimetypeNot,$Mimetype,$SizeMin,$SizeMax,$searchtype);
        } else {
          if (!empty($tag))
          {
            if (empty($Page)) { $Page = 0; }
            $V .= "<hr>\n";
            $text = _("Files matching");
            $V .= "<H2>$text " . htmlentities($Filename) . "</H2>\n";
            $V .= $this->GetUploadtreeFromTag($Item,$tag,$Page,$searchtype);
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
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    $text = _("Elapsed time: %.2f seconds");
    printf( "<p><small>$text</small>", $Time);
    return;
  } // Output()

};
$NewPlugin = new search;
$NewPlugin->Initialize();

?>
