<?php
/***********************************************************
 Copyright (C) 2009-2014 Hewlett-Packard Development Company, L.P.

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

define("TITLE_license_list_files", _("List Files for License"));

class LicenseListFiles extends FO_Plugin
{
  var $Name       = "license_list_files";
  var $Title      = TITLE_license_list_files;
  var $Version    = "1.0";
  var $Dependency = array("browse", "view");
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
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $rf_shortname = GetParm("lic",PARM_RAW);
    $Page = GetParm("page",PARM_INTEGER);
    $Excl = GetParm("excl",PARM_RAW);

    $rf_shortname = rawurlencode($rf_shortname);	
    $URL = $this->Name . "&item=$uploadtree_pk&lic=$rf_shortname&page=-1";
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

    $V="";
    $Time = time();
    $Max = 50;

    /*  Input parameters */
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $rf_shortname = GetParm("lic",PARM_RAW);
    $tag_pk = GetParm("tag",PARM_INTEGER);
    $Excl = GetParm("excl",PARM_RAW);
    $Exclic = GetParm("exclic",PARM_RAW);
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

    // Get upload_pk and $uploadtree_tablename
    $UploadtreeRec = GetSingleRec("uploadtree", "where uploadtree_pk=$uploadtree_pk");
    $UploadRec = GetSingleRec("upload", "where upload_pk=$UploadtreeRec[upload_fk]");
    $uploadtree_tablename = $UploadRec['uploadtree_tablename'];

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

        /*
        global $Plugins;
        $latestNomos=LatestAgentpk($UploadtreeRec['upload_fk'], "nomos_ars");
        $newestNomos=$Plugins[plugin_find('license') ]->getNewestAgent("nomos");
        $latestMonk=LatestAgentpk($uploadId, "monk_ars");
        $newestMonk=$Plugins[plugin_find('license') ]->getNewestAgent("monk");
        $goodAgents = array('nomos' => array('name' => 'N', 'latest' => $latestNomos, 'newest' =>$newestNomos, 'latestIsNewest' =>$latestNomos==$newestNomos['agent_pk']  ),
            'monk' => array('name' => 'M', 'latest' => $latestMonk, 'newest' =>$newestMonk, 'latestIsNewest' =>$latestMonk==$newestMonk['agent_pk']  ));
        */
        
        // Count is uploadtree recs, not pfiles
        
        $agentId = GetParm('agentId', PARM_INTEGER);
        if (empty($agentId))
        {
          $agentId = "any";
        }
        $CountArray = CountFilesWithLicense($agentId, $rf_shortname, $uploadtree_pk, $PkgsOnly, $CheckOnly, $tag_pk, $uploadtree_tablename);

        if (empty($CountArray)) {
          $V .=  _("<b> No files found for license $rf_shortname !</b>\n");
          break;
        }

        $Count = $CountArray['count'];
        $Unique = $CountArray['unique'];

        $text = _("files found");
        $text2 = _("with license");
        $V.= "$Unique $text $text2 <b>$rf_shortname</b>";
        if ($Count < $Max) $Max = $Count;
        $limit = ($Page < 0) ? "ALL":$Max;
        $order = " order by ufile_name asc";
        /** should delete $filesresult yourself */
        $filesresult = GetFilesWithLicense($agentId, $rf_shortname, $uploadtree_pk,
                                           $PkgsOnly, $Offset, $limit, $order, $tag_pk, $uploadtree_tablename);
        $NumFiles = pg_num_rows($filesresult);

        $file_result_temp = pg_fetch_all($filesresult);
        $sorted_file_result = array(); // the final file list will display
        $max_num = $NumFiles;
        /** sorting by ufile_name from DB, then reorder the duplicates indented */
        for($i = 0; $i < $max_num; $i++)
        {
          $row = $file_result_temp[$i];
          if (empty($row)) continue;
          array_push($sorted_file_result, $row);
          for($j = $i + 1; $j < $max_num; $j++)
          {
            $row_next = $file_result_temp[$j];
            if (!empty($row_next) && ($row['pfile_fk'] == $row_next['pfile_fk']))
            {
              array_push($sorted_file_result, $row_next);
              $file_result_temp[$j] = null;
            }
          }
        }

        $text = _("Display");
        $text1 = _("excludes");
        $text2 = _("files with these extensions");
        if (!empty($Excl)) $V .= "<br>$text <b>$text1</b> $text2: $Excl";

        $text2 = _("files with these licenses");
        if (!empty($Exclic)) $V .= "<br>$text <b>$text1</b> $text2: $Exclic";


        /* Get the page menu */
        if (($Max > 0) && ($Count >= $Max) && ($Page >= 0))
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
        $LinkLast = "view-license";
        $ShowBox = 1;
        $ShowMicro=NULL;

        // base url
        $ushortname = rawurlencode($rf_shortname);
        $baseURL = "?mod=" . $this->Name . "&item=$uploadtree_pk&lic=$ushortname&page=-1";

        $V .= "<table>";
        $text = _("File");
        $V .= "<tr><th>$text</th><th>&nbsp";
        $LastPfilePk = -1;
        $ExclArray = explode(":", $Excl);
        $ExclicArray = explode(":", $Exclic);
        foreach ($sorted_file_result as $row)
        {
          $pfile_pk = $row['pfile_fk'];
          $licstring = GetFileLicenses_string($row['agent_pk'], $pfile_pk, $row['uploadtree_pk'], $uploadtree_tablename);
          $URLlicstring = urlencode($licstring);

          // Allow user to exclude files with this extension
          $FileExt = GetFileExt($row['ufile_name']);
          $URL = $baseURL;
          if (!empty($Excl))
            $URL .= "&excl=$Excl:$FileExt";
          else
            $URL .= "&excl=$FileExt";
          if (!empty($Exclic)) $URL .= "&exclic=".urlencode($Exclic);
          $text = _("Exclude this file type.");
          $Header = "<a href=$URL>$text</a>";

          /* Allow user to exclude files with this exact license list */
          $URL = $baseURL;
          if (!empty($Exclic))
            $URL .= "&exclic=".urlencode($Exclic).":".$URLlicstring;
          else
            $URL .= "&exclic=$URLlicstring";
          if (!empty($Excl)) $URL .= "&excl=$Excl";

          $text = _("Exclude files with license");
          $Header .= "<br><a href=$URL>$text: $licstring.</a>";

          $ok = true;
          /* exclude by type */
          if ($Excl) if (in_array($FileExt, $ExclArray)) $ok = false;

          /* exclude by license */
          if ($Exclic) if (in_array($licstring, $ExclicArray)) $ok = false;

          if (empty($licstring)) $ok = false;

          if ($ok)
          {
            $V .= "<tr><td>";
            /* Tack on pfile to url - information only */
            $LinkLastpfile = $LinkLast . "&pfile=$pfile_pk";
            if ($LastPfilePk == $pfile_pk)
            {
              $indent = "<div style='margin-left:2em;'>";
              $outdent = "</div>";
            }
            else
            {
              $indent = "";
              $outdent = "";
            }
            $V .= $indent;
            $V .= Dir2Browse("browse", $row['uploadtree_pk'], $LinkLastpfile, $ShowBox, $ShowMicro, ++$RowNum, $Header,'',$uploadtree_tablename);
            $V .= $outdent;
            $V .= "</td>";
            $V .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>";  // spaces to seperate licenses

            // show the entire license list as a single string with links to the files
            // in this container with that license.
            $V .= "<td>$row[agent_name]: $licstring</td></tr>";
            $V .= "<tr><td colspan=3><hr></td></tr>";  // separate files
          }
          $LastPfilePk = $pfile_pk;
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

$NewPlugin = new LicenseListFiles;
$NewPlugin->Initialize();
