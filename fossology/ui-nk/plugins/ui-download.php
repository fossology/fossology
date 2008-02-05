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

class ui_download extends Plugin
  {
  var $Type=PLUGIN_UI;
  var $Name="download";
  var $Version="1.0";
  var $Dependency=array("db");

  /***********************************************************
   OutputOpen(): This function is called when user output is
   requested.  This function is responsible for assigning headers.
   The type of output depends on the metatype for the pfile.
   If the pfile is not defined, then use application/octet-stream.
   ***********************************************************/
  function OutputOpen($Type,$ToStdout)
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $this->OutputType=$Type;
    $this->OutputToStdout=$ToStdout;

    global $Plugins;
    $DB = &$Plugins[plugin_find_id("db")];
    $Pfile = GetParm("pfile",PARM_INTEGER);
    if (empty($Pfile)) { return; }
    /* Get meta type */
    $Sql = "SELECT * FROM mimetype JOIN pfile ON pfile.pfile_mimetypefk = mimetype.mimetype_pk WHERE pfile_pk = $Pfile LIMIT 1;";
    $Results = $DB->Action($Sql);
    $Meta = $Results[0]['mimetype_name'];
    if (empty($Meta)) { $Meta = 'application/octet-stream'; }

    switch($this->OutputType)
      {
      case "XML":
	$V = "<xml>\n";
	break;
      case "HTML":
	header("Content-Type: $Meta");
	// header('Content-Length: ' . $Results[0]['pfile_size']);
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    return;
    } // OutputOpen()

  /***********************************************************
   OutputClose(): This function is called when user output is
   completed.
   ***********************************************************/
  function OutputClose()
    {
    } // OutputClose()

  /***********************************************************
   Output(): This function is called when user output is
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
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    $DB = &$Plugins[plugin_find_id("db")];
    $Pfile = GetParm("pfile",PARM_INTEGER);
    if (empty($Pfile)) { return; }
    switch($this->OutputType)
      {
      case "XML":
      case "HTML":
      case "Text":
      default:
	/* Regardless of the format, dump the file's contents */
	$Filename = RepPath($Pfile);
	if (empty($Filename)) return;
	if ($this->OutputToStdout) { readfile($Filename); }
	else { return($V); }
	break;
      }
    return;
    } // Output()

  };
$NewPlugin = new ui_download;
$NewPlugin->Initialize();
?>
