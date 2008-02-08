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
   GetFileJumpMenu(): Given a file handle and current page,
   generate the "Next" and "Prev" menu options.
   Returns String.
   ***********************************************************/
  function GetFileJumpMenu($Fin,$CurrPage,$PageSize,$Uri)
    {
    if (!$Fin) return;
    $Stat = fstat($Fin);
    $MaxSize = $Stat['size'];
    $V = "<font class='text'>";
    $CurrSize = $CurrPage * $PageSize;

    if ($CurrPage * $PageSize >= $MaxSize) { $CurrPage = 0; }
    if ($CurrPage < 0) { $CurrPage = 0; }

    if (($CurrPage-1) * $PageSize >= 0)
	{
	$V .= "<a href='$Uri&page=" . ($CurrPage-1) . "'>Prev</a> ";
	}
    for($i = $CurrPage-5; $i <= $CurrPage+5; $i ++)
      {
      if ($i == $CurrPage)
	{
	$V .= "<b>" . ($i+1) . "</b> ";
	}
      else if (($i * $PageSize >= 0) && ($i * $PageSize < $MaxSize))
	{
	$V .= "<a href='$Uri&page=$i'>" . ($i+1) . "</a> ";
	}
      }
    if (($CurrPage+1) * $PageSize < $MaxSize)
	{
	$V .= "<a href='$Uri&page=" . ($CurrPage+1) . "'>Next</a>";
	}
    $V .= "</font>";
    return($V);
    }

  /***********************************************************
   ShowFlowedText(): Given a pfile_pk, display the text file as HTML.
   Output goes to stdout!
   ***********************************************************/
  function ShowFlowedText($Fin,$Start=0,$FullLength=-1)
    {
    if (!$Fin) return;
    $Stat = fstat($Fin);
    if ($FullLength < 0) { $FullLength = $Stat['size']; }
    if (($Start < 0) || ($Start >= $Stat['size'])) { return; }
    fseek($Fin,$Start,SEEK_SET);
    $Length = $FullLength;
    fseek($Fin,$Start,SEEK_SET);

    /* Performance note:
       Ideally, tables should be used for aligning columns.
       However, after a few thousand rows, most browsers hang.
       Thus, use text and not tables. */

    /* Process the file */
    print "<div class='text'>";
    $MadeOutput=0;
    if ($Length > 0) { $S = fread($Fin,min(1024,$Length)); }
    while((strlen($S) > 0) && ($Length > 0))
      {
      $Length -= strlen($S);
      $S = preg_replace('/[^[:print:][:space:]]+/'," ",$S);
      $S = htmlentities($S);
      $S = str_replace("\r\n","<br>\n",$S);
      $S = str_replace("\n","<br>\n",$S);
      $S = str_replace("\r","<br>\n",$S);
      $S = str_replace("\t","&nbsp;&nbsp;",$S);
      print $S;
      if (strlen(trim($S)) > 0) { $MadeOutput=1; }
      if ($Length > 0) { $S = fread($Fin,min(1024,$Length)); }
      }
    if (!$MadeOutput)
	{
	print "<b>" . number_format($FullLength,0,"",",") . " bytes non-printable</b>\n";
	}
    print "</div>\n";

    fclose($Fin);
    } // ShowFlowedText()

  /***********************************************************
   ShowText(): Given a file handle, display "strings" of the file.
   Output goes to stdout!
   ***********************************************************/
  function ShowText($Fin,$Start=0,$FullLength=-1)
    {
    if (!$Fin) return;
    $Stat = fstat($Fin);
    if ($FullLength < 0) { $FullLength = $Stat['size']; }
    if (($Start < 0) || ($Start >= $Stat['size'])) { return; }
    fseek($Fin,$Start,SEEK_SET);
    $Length = $FullLength;

    /* Performance note:
       Ideally, tables should be used for aligning columns.
       However, after a few thousand rows, most browsers hang.
       Thus, use text and not tables. */

    /* Process the file */
    print "<div class='mono'>";
    print "<pre>";
    $MadeOutput=0;
    if ($Length > 0) { $S = fread($Fin,min(1024,$Length)); }
    while((strlen($S) > 0) && ($Length > 0))
      {
      $Length -= strlen($S);
      $S = preg_replace('/[^[:print:][:space:]]+/'," ",$S);
      $S = htmlentities($S);
      print $S;
      if (strlen(trim($S)) > 0) { $MadeOutput=1; }
      if ($Length > 0) { $S = fread($Fin,min(1024,$Length)); }
      }
    print "</pre>";
    if (!$MadeOutput)
	{
	print "<b>" . number_format($FullLength,0,"",",") . " bytes non-printable</b>\n";
	}
    print "</div>\n";

    fclose($Fin);
    } // ShowText()

  /***********************************************************
   ShowHex(): Given a file handle, display a "hex dump" of the file.
   Output goes to stdout!
   ***********************************************************/
  function ShowHex($Fin,$Start=0,$Length=-1)
    {
    if (!$Fin) return;
    $Stat = fstat($Fin);
    if ($Length < 0) { $Length = $Stat['size']; }
    if (($Start < 0) || ($Start >= $Stat['size'])) { return; }
    fseek($Fin,$Start,SEEK_SET);
    fseek($Fin,$Start,SEEK_SET);

    /* Performance note:
       Ideally, tables should be used for aligning columns.
       However, after a few thousand rows, most browsers hang.
       Thus, use text and not tables. */

    /* Process the file */
    print "<div class='mono'>";
    $Tell = ftell($Fin);
    $S = fread($Fin,min(16,$Length));
    if ($Length > 0) { $S = fread($Fin,min(16,$Length)); }
    while((strlen($S) > 0) && ($Length > 0))
      {
      $Length -= strlen($S);
      /* show file location */
      $B = base_convert($Tell,10,16);
      print "0x";
      for($i=strlen($B); $i<8; $i++) { print("0"); }
      print "$B&nbsp;";
      $Tell = ftell($Fin);

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
      $S = preg_replace("/[[:space:]]/",'&nbsp;',$S);
      print "|$S|<br>\n";
      if ($Length > 0) { $S = fread($Fin,min(16,$Length)); }
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
    $Pfile = GetParm("pfile",PARM_INTEGER);
    $Ufile = GetParm("ufile",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Folder = GetParm("folder",PARM_INTEGER);
    $Show = GetParm("show",PARM_STRING);
    $Item = GetParm("item",PARM_INTEGER);
    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Item) || empty($Pfile) || empty($Ufile) || empty($Upload))
	{ return; }
    if (empty($Page)) { $Page=0; };

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
	  $Filename = RepPath($Pfile);
	  $Fin = fopen($Filename,"rb");
	  $Pages = "";
	  $Uri = preg_replace('/&page=[0-9]*/','',Traceback());
	  if ($Format == 'hex')
	    {
	    $PageBlock = 8192;
	    $PageMenu = $this->GetFileJumpMenu($Fin,$Page,$PageBlock,$Uri);
	    $PageSize = $PageBlock * $Page;
	    print "<center>$PageMenu</center><br>\n";
	    $this->ShowHex($Fin,$PageSize,4096);
	    print "<P /><center>$PageMenu</center>\n";
	    }
	  else if ($Format == 'text')
	    {
	    $PageBlock = 100000;
	    $PageMenu = $this->GetFileJumpMenu($Fin,$Page,$PageBlock,$Uri);
	    $PageSize = $PageBlock * $Page;
	    print "<center>$PageMenu</center><br>\n";
	    $this->ShowText($Fin,$PageSize,$PageBlock);
	    print "<P /><center>$PageMenu</center>\n";
	    }
	  else if ($Format == 'flow')
	    {
	    $PageBlock = 100000;
	    $PageMenu = $this->GetFileJumpMenu($Fin,$Page,$PageBlock,$Uri);
	    $PageSize = $PageBlock * $Page;
	    print "<center>$PageMenu</center><br>\n";
	    $this->ShowFlowedText($Fin,$PageSize,$PageBlock);
	    print "<P /><center>$PageMenu</center>\n";
	    }
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
