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

class ui_view extends Plugin
  {
  var $Type=PLUGIN_UI;
  var $Name="view";
  var $Version="1.0";
  var $Dependency=array("db","browse");

  /***********************************************************
   ShowText(): Given a pfile_pk, display "strings" of the file.
   Output goes to stdout!
   ***********************************************************/
  function ShowText($Pfile)
    {
    // $Filename = 
    print "<div class='mono'>";
    print "</div>\n";
    } // ShowText()

  /***********************************************************
   ShowHex(): Given a pfile_pk, display a "hex dump" of the file.
   Output goes to stdout!
   ***********************************************************/
  function ShowHex($Pfile)
    {
    $Filename = RepPath($Pfile);
    $Fin = fopen($Filename,"rb");
    if (!$Fin) return;

    /* Process the file */
    print "<div class='mono'>";
    $S = fread($Fin,32);
    while(strlen($S) > 0)
      {
      /* Print the hex */
      $Hex = bin2hex($S);
      print "$Hex\n";
      $S = fread($Fin,32);
      }
    print "</div>\n";

    fclose($Fin);
    } // ShowHex()

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
    $Ufile = GetParm("ufile",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Folder = GetParm("folder",PARM_INTEGER);
    $Show = GetParm("show",PARM_STRING);
    $Item = GetParm("item",PARM_INTEGER);
    if (empty($Item) || empty($Pfile) || empty($Ufile) || empty($Upload))
	{ return; }

    /* Display micro header */
    $V .= "<div style='border: thin dotted gray; background-color:lightyellow'>\n";
    $Path = Dir2Path($Item,$Ufile);
    $FirstPath=1;
    $Last = &$Path[count($Path)-1];
    $Uri = Traceback_uri() . "?mod=browse";
    $Opt = "";
    if ($Folder) { $Opt .= "&folder=$Folder"; }
    if ($Upload) { $Opt .= "&upload=$Upload"; }
    if ($Show) { $Opt .= "&show=$Show"; }

    $V .= "<font class='text'>\n";
    foreach($Path as $P)
      {
      if (empty($P['ufile_name'])) { continue; }
      if (!$FirstPath) { $V .= "/ "; }
      if ($P != $Last) { $V .= "<a href='$Uri&item=" . $P['uploadtree_pk'] . "$Opt'>"; }
      if (Isdir($P['ufile_mode']))
	{
	$V .= $P['ufile_name'];
	}
      else
	{
	if (!$FirstPath && ($P != $Last)) { $V .= "<br>\n&nbsp;&nbsp;"; }
	$V .= "<b>" . $P['ufile_name'] . "</b>";
	}
      if ($P != $Last) { $V .= "</a>"; }
      $FirstPath=0;
      }
    $V .= "</div><P />\n";
    $V .= "</font>\n";

    switch(GetParm("format",PARM_STRING))
	{
	case 'hex':	$Format='hex'; break;
	case 'text':	$Format='text'; break;
	default:
	  /* Determine default show based on mime type */
	  $Meta = GetMimeType($Pfile);
	  list($Type,$Junk) = split("/",$Meta,2);
	  if ($Type == 'text') { $Format = 'text'; }
	  else { $Format = 'hex'; }
	  break;
	}

    /***********************************
     Display file contents
     ***********************************/
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	if ($this->OutputToStdout)
	  {
	  print $V;
	  if ($Format == 'text') { $V = $this->ShowText($Pfile); }
	  else if ($Format == 'hex') { $V = $this->ShowHex($Pfile); }
	  }
	break;
      case "Text":
      default:
	break;
      }
    return;
    } // Output()

  };
$NewPlugin = new ui_view;
$NewPlugin->Initialize();
?>
