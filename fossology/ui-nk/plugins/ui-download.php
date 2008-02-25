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
    global $DB;
    $Pfile = GetParm("pfile",PARM_INTEGER);
    $Ufile = GetParm("ufile",PARM_INTEGER);
    if (empty($Pfile) || empty($Ufile))
	{
	$this->OutputType = "corrupt";
	return;
	}

    /* Get filename */
    /** By using pfile and ufile, we cut down the risk of users blindly
        guessing in order to download arbitrary files.
	NOTE: The user can still iterate through every possible pfile and
	ufile in order to find files.  And since the numbers are sequential,
	they can optimize their scan.
	However, it will still take plenty of queries to find most files.
	Later: This will check if the user has access permission to the ufile.
     **/
    $Sql = "SELECT * FROM ufile WHERE ufile_pk = $Ufile AND pfile_fk = $Pfile LIMIT 1;";
    $Results = $DB->Action($Sql);
    $Name = $Results[0]['ufile_name'];
    if (empty($Name))
	{
	$this->OutputType = "corrupt";
	return;
	}

    /* Get meta type */
    switch($this->OutputType)
      {
      case "XML":
	$V = "<xml>\n";
	break;
      case "HTML":
	$Meta = GetMimeType($Pfile);
	header("Content-Type: $Meta");
	// header('Content-Length: ' . $Results[0]['pfile_size']);
	header('Content-Disposition: attachment; filename="' . $Name . '"');
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
    global $DB;
    $Pfile = GetParm("pfile",PARM_INTEGER);
    if (empty($Pfile)) { return; }
    switch($this->OutputType)
      {
      case "XML":
      case "HTML":
      case "Text":
	/* Regardless of the format, dump the file's contents */
	$Filename = RepPath($Pfile);
	if (empty($Filename)) return;
	if ($this->OutputToStdout) { readfile($Filename); }
	else { return($V); }
      default:
	break;
      }
    return;
    } // Output()

  };
$NewPlugin = new ui_download;
$NewPlugin->Initialize();
?>
