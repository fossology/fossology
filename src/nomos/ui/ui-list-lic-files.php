<?php
/***********************************************************
 Copyright (C) 2009-2011 Hewlett-Packard Development Company, L.P.

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


/**
 * \file ui-list-lic-files.php
 * \brief This plugin is used to:
 * List files for a given license shortname in a given
 * uploadtree.
 */

define("TITLE_list_lic_files", _("List Files for License"));

class list_lic_files extends FO_Plugin
{
  var $Name       = "list_lic_files";
  var $Title      = TITLE_list_lic_files;
  var $Version    = "1.0";
  var $Dependency = array("db","nomoslicense");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    // micro-menu
    $nomosagent_pk = GetParm("napk",PARM_INTEGER);
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $rf_shortname = GetParm("lic",PARM_RAW);
    $Page = GetParm("page",PARM_INTEGER);
    $Excl = GetParm("excl",PARM_RAW);

    $URL = $this->Name . "&napk=$nomosagent_pk&item=$uploadtree_pk&lic=$rf_shortname&page=-1";
    if (!empty($Excl)) $URL .= "&excl=$Excl";
    $text = _("Show All Files");
    menu_insert($this->Name."::Show All",0, $URL, $text);

  } // RegisterMenus()


  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    global $Plugins;

    $V="";
    $Time = time();
    $Max = 50;

    /*  Input parameters */
    $nomosagent_pk = GetParm("napk",PARM_INTEGER);
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $rf_shortname = GetParm("lic",PARM_RAW);
    $tag_pk = GetParm("tag",PARM_INTEGER);
    $Excl = GetParm("excl",PARM_RAW);
    $rf_shortname = rawurldecode($rf_shortname);
    if (empty($uploadtree_pk) || empty($rf_shortname))
    {
      $text = _("is missing required parameters.");
      echo $this->Name . " $text";
      return;
    }
    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) {
      $Page=0;
    }

    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        // micro menus
        $V .= menu_to_1html(menu_find($this->Name, $MenuDepth),0);

        /* Load licenses */
        $Offset = ($Page < 0) ? 0 : $Page*$Max;
        $order = "";
        $PkgsOnly = false;
        $CheckOnly = false;

        // Count is uploadtree recs, not pfiles
        $CountArray = CountFilesWithLicense($nomosagent_pk, $rf_shortname, $uploadtree_pk, $PkgsOnly, $CheckOnly, $tag_pk);
        $Count = $CountArray['count'];
        $Unique = $CountArray['unique'];

        $text = _("files found");
        $text1 =_("unique");
        $text2 = _("with license");
        $V.= "$Count $text ($Unique $text1) $text2 <b>$rf_shortname</b>";
        if ($Count < $Max) $Max = $Count;
        $limit = ($Page < 0) ? "ALL":$Max;
        $order = " order by ufile_name asc";
        /** should delete $filesresult yourself */
        $filesresult = GetFilesWithLicense($nomosagent_pk, $rf_shortname, $uploadtree_pk,
                                           $PkgsOnly, $Offset, $limit, $order, $tag_pk);
        $NumFiles = pg_num_rows($filesresult);
        $text = _("Display");
        $text1 = _("excludes");
        $text2 = _("files with these extensions");
        if (!empty($Excl)) $V .= "<br>$text <b>$text1</b> $text1: $Excl";

        /* Get the page menu */
        if (($Count >= $Max) && ($Page >= 0))
        {
          $VM = "<P />\n" . MenuEndlessPage($Page,intval((($Count+$Offset)/$Max))) . "<P />\n";
          $V .= $VM;
        }
        else
        {
          $VM = "";
        }

        /* Offset is +1 to start numbering from 1 instead of zero */
        $RowNum = $Offset;
        $LinkLast = "view-license&napk=$nomosagent_pk";
        $ShowBox = 1;
        $ShowMicro=NULL;

        // base url
        $ushortname = rawurlencode($rf_shortname);
        $baseURL = "?mod=" . $this->Name . "&napk=$nomosagent_pk&item=$uploadtree_pk&lic=$ushortname&page=-1";

        $V .= "<table>";
        $text = _("File");
        $V .= "<tr><th>$text</th><th>&nbsp";
        while ($row = pg_fetch_assoc($filesresult))
        {
          // Allow user to exclude files with this extension
          $FileExt = GetFileExt($row['ufile_name']);
          if (!empty($Excl))
          $URL = $baseURL . "&excl=$Excl:$FileExt";
          else
          $URL = $baseURL . "&excl=$FileExt";
          $text = _("Exclude this file type.");
          $Header = "<a href=$URL>$text</a>";

          $ok = true;
          if ($Excl)
          {
            $ExclArray = explode(":", $Excl);
            if (in_array($FileExt, $ExclArray)) $ok = false;
          }
          if ($ok)
          {
            $V .= "<tr><td>";
            /* Tack on pfile to url - information only */
            $pfile_pk = $row['pfile_fk'];
            $LinkLastpfile = $LinkLast . "&pfile=$pfile_pk";
            $V .= Dir2Browse("browse", $row['uploadtree_pk'], $LinkLastpfile, $ShowBox, $ShowMicro, ++$RowNum, $Header);
            $V .= "</td>";
            $V .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>";  // spaces to seperate licenses

            $licstring = GetFileLicenses_string($nomosagent_pk, $pfile_pk, $row['uploadtree_pk']);

            // show the entire license list as a single string with links to the files
            // in this container with that license.
            $V .= "<td>$licstring</td></tr>";
            $V .= "<tr><td colspan=3><hr></td></tr>";  // separate files
          }
        }
        pg_free_result($filesresult);
        $V .= "</table>";

        if (!empty($VM)) {
          $V .= $VM . "\n";
        }
        $V .= "<hr>\n";
        $Time = time() - $Time;
        $text = _("Elapsed time");
        $text1 = _("seconds");
        $V .= "<small>$text: $Time $text1</small>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return($V);
    }
    print($V);
    return;
  } // Output()

};
$NewPlugin = new list_lic_files;
$NewPlugin->Initialize();

?>
