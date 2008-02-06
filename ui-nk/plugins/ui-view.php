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
   ShowFlowedText(): Given a pfile_pk, display the text file as HTML.
   Output goes to stdout!
   ***********************************************************/
  function ShowFlowedText($Pfile)
    {
    $Filename = RepPath($Pfile);
    $Fin = fopen($Filename,"rb");
    if (!$Fin) return;

    /* Performance note:
       Ideally, tables should be used for aligning columns.
       However, after a few thousand rows, most browsers hang.
       Thus, use text and not tables. */

    /* Process the file */
    print "<div class='text'>";
    $S = fread($Fin,1024);
    while(strlen($S) > 0)
      {
      $S = htmlentities($S);
      $S = str_replace("\r\n","<br>\n",$S);
      $S = str_replace("\n","<br>\n",$S);
      $S = str_replace("\r","<br>\n",$S);
      $S = str_replace("\t","&nbsp;&nbsp;",$S);
      $S = preg_replace("/[^[:print:][:space:]]+/"," ",$S);
      print $S;
      $S = fread($Fin,1024);
      }
    print "</div>\n";

    fclose($Fin);
    } // ShowFlowedText()

  /***********************************************************
   ShowText(): Given a pfile_pk, display "strings" of the file.
   Output goes to stdout!
   ***********************************************************/
  function ShowText($Pfile)
    {
    $Filename = RepPath($Pfile);
    $Fin = fopen($Filename,"rb");
    if (!$Fin) return;

    /* Performance note:
       Ideally, tables should be used for aligning columns.
       However, after a few thousand rows, most browsers hang.
       Thus, use text and not tables. */

    /* Process the file */
    print "<div class='mono'>";
    print "<pre>";
    $S = fread($Fin,1024);
    while(strlen($S) > 0)
      {
      $S = htmlentities($S);
      $S = preg_replace("/[^[:print:][:space:]]+/"," ",$S);
      print $S;
      $S = fread($Fin,1024);
      }
    print "</pre>";
    print "</div>\n";

    fclose($Fin);
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

    /* Performance note:
       Ideally, tables should be used for aligning columns.
       However, after a few thousand rows, most browsers hang.
       Thus, use text and not tables. */

    /* Process the file */
    print "<div class='mono'>";
    $S = fread($Fin,16);
    while(strlen($S) > 0)
      {
      /* Convert to hex */
      $Hex = bin2hex($S);
      /** Make sure it is always 32 characters (16 bytes) **/
      for($i=strlen($Hex); $i < 32; $i+=2) { $Hex .= "  "; }
      /** Add in the spacings **/
      $Hex = preg_replace("/(..)/",'\1 ',$Hex);
      $Hex = preg_replace("/(.......................) /",'\1 | ',$Hex);
      $Hex = str_replace(" ","&nbsp;",$Hex);
      print "| $Hex";
      $S = preg_replace("/[^[:print:]]/",'.',$S);
      $S = htmlentities($S);
      $S = str_replace("/[[:space:]]/","&nbsp;",$S);
      print "|$S|<br>\n";
      $S = fread($Fin,16);
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

    switch(GetParm("format",PARM_STRING))
	{
	case 'hex':	$Format='hex'; break;
	case 'text':	$Format='text'; break;
	case 'flow':	$Format='flow'; break;
	default:
	  /* Determine default show based on mime type */
	  $Meta = GetMimeType($Pfile);
	  list($Type,$Junk) = split("/",$Meta,2);
	  if ($Type == 'text') { $Format = 'flow'; }
	  else { $Format = 'hex'; }
	  break;
	}

    /***********************************
     Create micro menu
     ***********************************/
    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    $Opt="";
    if (!empty($Pfile)) { $Opt .= "&pfile=$Pfile"; }
    if (!empty($Ufile)) { $Opt .= "&ufile=$Ufile"; }
    if (!empty($Upload)) { $Opt .= "&upload=$Upload"; }
    if (!empty($Folder)) { $Opt .= "&folder=$Folder"; }
    if (!empty($Show)) { $Opt .= "&show=$Show"; }
    if (!empty($Item)) { $Opt .= "&item=$Item"; }
    $V .= "<div align=right><small>";
    if ($Format != 'hex') { $V .= "<a href='$Uri$Opt&format=hex'>Hex</a> | "; }
    else { $V .= "Hex | "; }
    if ($Format != 'text') { $V .= "<a href='$Uri$Opt&format=text'>Plain Text</a> | "; }
    else { $V .= "Plain Text | "; }
    if ($Format != 'flow') { $V .= "<a href='$Uri$Opt&format=flow'>Flowed Text</a> | "; }
    else { $V .= "Flowed Text | "; }
    $V .= "<a href='" . Traceback() . "'>Refresh</a>";
    $V .= "</small></div>\n";

    /**********************************
      Display micro header
     **********************************/
    $Uri = Traceback_uri() . "?mod=browse";
    $Opt="";
    if (!empty($Pfile)) { $Opt .= "&pfile=$Pfile"; }
    if (!empty($Ufile)) { $Opt .= "&ufile=$Ufile"; }
    if (!empty($Upload)) { $Opt .= "&upload=$Upload"; }
    if (!empty($Folder)) { $Opt .= "&folder=$Folder"; }
    if (!empty($Show)) { $Opt .= "&show=$Show"; }
    /* No item */
    $V .= "<div style='border: thin dotted gray; background-color:lightyellow'>\n";
    $Path = Dir2Path($Item,$Ufile);
    $FirstPath=1;
    $Last = &$Path[count($Path)-1];

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
	  else if ($Format == 'flow') { $V = $this->ShowFlowedText($Pfile); }
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
