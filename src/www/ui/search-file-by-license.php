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

/**
 * \file search-file-by-license.php
 * \brief This plugin is used to list all files associated
 * with a specific license.
 * This is NOT intended to be a user-UI plugin.
 * This is intended as an active plugin to provide support
 * data to the UI.
 */

define("TITLE_search_file_by_license", _("List Files based on License"));

class search_file_by_license extends FO_Plugin
{
  var $Name       = "search_file_by_license";
  var $Title      = TITLE_search_file_by_license;
  var $Version    = "1.0";
  var $Dependency = array("license");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    $Time = time();
    $Max = 50;

    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $UploadTreePk = GetParm("item",PARM_INTEGER);
        $Page = GetParm("page",PARM_INTEGER);
        $WantLic = GetParm("lic",PARM_RAW);
        $WantLic = str_replace("\\'","'",$WantLic);
        if (empty($UploadTreePk) || empty($WantLic))
        {
          return;
        }
        if (empty($Page)) { $Page=0; }
        $Offset = $Page * $Max;

        /* Get License Name */
        $text = _("The following files contain the license");
        $V .= "$text '<b>";
        $V .= htmlentities($WantLic);
        $V .= "</b>'.\n";

        /* Load licenses */
        $Lics = array();
        $Offset = $Page*$Max;
        $Lics = LicenseSearch($UploadTreePk,$WantLic,$Offset,$Max);

        /* Save the license results */
        $Count = count($Lics);

        /* Get the page menu */
        if (($Count >= $Max) || ($Page > 0))
        {
          $VM = "<P />\n" . MenuEndlessPage($Page,intval((($Count+$Offset)/$Max))) . "<P />\n";
          $V .= $VM;
        }
        else
        {
          $VM = "";
        }

        /* Offset is +1 to start numbering from 1 instead of zero */
        $V .= Dir2FileList($Lics,"browse","view-license",$Offset + 1,1);

        if (!empty($VM)) { $V .= $VM . "\n"; }
        $V .= "<hr>\n";
        $Time = time() - $Time;
        $text = _("Elaspsed time:");
        $text1 = _("seconds");
        $V .= "<small>$text $Time $text1</small>\n";
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
$NewPlugin = new search_file_by_license;
$NewPlugin->Initialize();

?>
