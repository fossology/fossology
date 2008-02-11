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

define("VIEW_BLOCK_HEX",8192);
define("VIEW_BLOCK_TEXT",100*VIEW_BLOCK_HEX);
define("MAXHIGHLIGHTCOLOR",8);

class ui_view extends Plugin
  {
  var $Type=PLUGIN_UI;
  var $Name="view";
  var $Version="1.0";
  var $Dependency=array("db","browse");

  var $HighlightColors = array("yellow","lightgreen","aqua","blueviolet","darkkhaki","orange","lightsteelblue","yellowgreen");
  var $Highlight=NULL;

  /***********************************************************
   _cmp_highlight(): Use for sorting the highlight list.
   ***********************************************************/
  function _cmp_highlight($a,$b)
    {
    if ($a['Start'] != $b['Start'])
	{
	return($a['Start'] > $b['Start'] ? 1 : -1);
	}
    if ($a['End'] != $b['End'])
	{
	return($a['End'] > $b['End'] ? 1 : -1);
	}
    return(0);
    } // _cmp_highlight()

  /***********************************************************
   AddHighlight(): Text can be highlighted!
   Start, End, and Color are required.
   If Color is -1, then uses last color.
   If Color is -2, then uses color AFTER last color.
   Name is only needed if you want to have it listed in the
   top menu with a hyperlink jump.
   Index is auto-assigned to Name, if not specified.
   ***********************************************************/
  function AddHighlight($ByteStart,$ByteEnd,$Color,
			$Match=NULL,$Name=NULL,$Index=-1,$RefURL=NULL)
    {
    $H = array();
    $H['Start'] = intval($ByteStart);
    $H['End'] = intval($ByteEnd);
    $Color = intval($Color);
    if ($Color < 0)
	{
	/* Reuse last color */
	if (empty($this->Highlight)) { $Color = 0; }
	else if ($Color == -1)
	  {
	  $Color = $this->Highlight[count($this->Highlight)-1]['Color'];
	  }
	else // if ($Color == -2)
	  {
	  $Color = $this->Highlight[count($this->Highlight)-1]['Color'] + 1;
	  }
	}
    $Color = $Color % MAXHIGHLIGHTCOLOR;
    $H['Color'] = intval($Color);
    $H['Match'] = htmlentities($Match);
    $H['RefURI'] = $RefURI;
    $H['Name'] = htmlentities($Name);
    if (intval($Index) != -1) { $H['Index'] = intval($Index); }
    else { $H['Index'] = count($Highlight)+1; }
    if (empty($this->Highlight)) { $this->Highlight = array($H); }
    else { array_push($this->Highlight,$H); }
    } // AddHighlight()

  /***********************************************************
   GetHighlightMenu(): If there is a highlight menu, create it.
   ***********************************************************/
  function GetHighlightMenu($PageBlockSize)
    {
    if (empty($this->Highlight)) { return; }
    $V = "<table>";
    $V .= "<tr><th>Match</th>";
    $V .= "<th></th><th></th>";
    $V .= "<th align='left'>Item</th>";
    $V .= "</tr>\n";
    $Uri = preg_replace('/&page=[0-9]*/',"",Traceback());
// print "<pre>"; print_r($this->Highlight) ; print "</pre>";
    foreach($this->Highlight as $H)
      {
      if (!empty($H['Name']))
	{
	$V .= "<tr bgcolor='" . $this->HighlightColors[$H['Color']] . "'>\n";
	$V .= "<td align='right'>" . $H['Match'] . "</td>\n";

	$Page = intval($H['Start'] / $PageBlockSize);
	$V .= "<td>";
	$V .= "<a href='$Uri&page=$Page#" . $H['Index'] . "'>view</a>";
	$V .= "</td>\n";

	$V .= "<td>";
	if (!empty($H['RefURI'])) { $V .= "<a href='" . $H['RefURI'] . "'>ref</a>"; }
	$V .= "</td>\n";

	$V .= "<td>" . $H['Name'] . "</td>\n";
	$V .= "</tr>\n";
	}
      }
    $V .= "</table>";
    return($V);
    } // GetHighlightMenu()

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

    $Pages=0; /* How many pages are there? */

    if ($CurrPage * $PageSize >= $MaxSize) { $CurrPage = 0; }
    if ($CurrPage < 0) { $CurrPage = 0; }

    if (($CurrPage-1) * $PageSize >= 0)
	{
	$V .= "<a href='$Uri&page=0'>[First]</a> ";
	$V .= "<a href='$Uri&page=" . ($CurrPage-1) . "'>[Prev]</a> ";
	$Pages++;
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
	$V .= "<a href='$Uri&page=" . ($CurrPage+1) . "'>[Next]</a>";
	$V .= "<a href='$Uri&page=" . (intval(($MaxSize-1)/$PageSize)) . "'>[Last]</a>";
	$Pages++;
	}
    $V .= "</font>";

    /* If there is only one page, return nothing */
    if ($Pages==0) { return; }
    return($V);
    } // GetFileJumpMenu()

  /***********************************************************
   FindHighlight(): REMOVE items from the Highlight array.
   This shifts items off of the highlight array based on the
   current position in the file.
   The top of the array ($Highlight[0]) identifies the current
   item to highlight.
   Returns: flags
     0 if no highlight
     0x01 if at start
     0x02 if highlighting
     0x04 if at end
   ***********************************************************/
  function FindHighlight($Pos)
    {
    while(!empty($this->Highlight) && ($Pos > $this->Highlight[0]['End']))
	{
	array_shift($this->Highlight);
	}
    if (empty($this->Highlight)) { return(0); }
    $rc=0;
    $Start = $this->Highlight[0]['Start'];
    $End = $this->Highlight[0]['End'];
    if ($Pos == $Start) { $rc |= 0x01; }
    if ($Pos == $End) { $rc |= 0x04; }
    if (($Pos >= $Start) && ($Pos <= $End))
	{ $rc |= 0x02; }
    return($rc);
    } // FindHighlight()

  /***********************************************************
   ReadHighlight(): Given a file handle, read up to the next
   highlight boundary (start or end).
   Returns: String read.
   NOTE: if strlen of return is < $MaxRead, then it hit a highlight.
   ***********************************************************/
  function ReadHighlight($Fin, $Pos, $MaxRead)
    {
    $this->FindHighlight($Pos);
    if (empty($this->Highlight)) { $Len = $MaxRead; }
    else
      {
      $Start = $this->Highlight[0]['Start'];
      $End = $this->Highlight[0]['End'];
      if ($Pos == $Start) { $Len = 1; }
      else if ($Pos == $End) { $Len = 1; }
      else if ($Pos < $Start) { $Len = min($MaxRead,$Start - $Pos); }
      else if ($Pos < $End) { $Len = min($MaxRead,$End - $Pos); }
      else
	{
	$Len=$MaxRead;
	}
      }
    return(fread($Fin,$Len));
    } // ReadHighlight()

  /***********************************************************
   ShowText(): Given a file handle, display "strings" of the file.
   Output goes to stdout!
   ***********************************************************/
  function ShowText($Fin,$Start=0,$Flowed,$FullLength=-1)
    {
    if (!$Fin) return;
    $Stat = fstat($Fin);
    if ($FullLength < 0) { $FullLength = $Stat['size']; }
    if (($Start < 0) || ($Start >= $Stat['size'])) { return; }
    fseek($Fin,$Start,SEEK_SET);
    $Length = $FullLength;
    if ($Length == 0) { return; }

    /* Performance note:
       Ideally, tables should be used for aligning columns.
       However, after a few thousand rows, most browsers hang.
       Thus, use text and not tables. */

    /* Process the file */
    if (!$Flowed) { print "<div class='mono'><pre>"; }
    else print "<div class='text'>";
    $MadeOutput=0;
    /* Begin color if it is IN but not at START of highlighting */
    $InColor=0;
    if ($this->FindHighlight($Start) & 0x03 == 0x02)
	{
	$H = $this->Highlight[0];
	print "<font style='background:" . $this->HighlightColors[$H['Color']] . ";'>";
	$InColor=1;
	}
    $S = $this->ReadHighlight($Fin,$Start,$Length);
    $ReadCount = strlen($S);
    while(($ReadCount > 0) && ($Length > 0))
      {
      /* Massage the data and print it */
      $S = preg_replace('/[^[:print:][:space:]]+/'," ",$S);
      $S = htmlentities($S);
      if ($Flowed)
	{
	$S = str_replace("\r\n","<br>\n",$S);
	$S = str_replace("\n","<br>\n",$S);
	$S = str_replace("\r","<br>\n",$S);
	$S = str_replace("\t","&nbsp;&nbsp;",$S);
	}

      if ($this->FindHighlight($Start) & 0x01)
	{
	if ($InColor) { print "</font>"; }
	$H = $this->Highlight[0]['Color'];
	print "<font style='background:" . $this->HighlightColors[$H] . ";'>";
	$InColor=1;
	if (!empty($this->Highlight[0]['Name']))
	  {
	  print "<a name='" . $this->Highlight[0]['Index'] . "'>" . $S . "</a>";
	  }
	else
	  {
	  print $S;
	  }
	}
      else
	{
	print $S;
	}

      $Length -= $ReadCount;
      $Start += $ReadCount;
      if (strlen(trim($S)) > 0) { $MadeOutput=1; }
 
      if ($InColor && ($this->FindHighlight($Start) & 0x04))
	{
	print "</font>";
	$InColor=0;
	if ($Length <= 0) { $Start = -1; }
	}

      if ($Length > 0)
	{
	$S = $this->ReadHighlight($Fin,$Start,$Length);
	$ReadCount = strlen($S);
	}
      } /* while reading */

    if ($InColor) { print "</font>"; }
    if (!$Flowed) { print "</pre>"; }
    if (!$MadeOutput)
	{
	print "<b>" . number_format($FullLength,0,"",",") . " bytes non-printable</b>\n";
	}
    print "</div>\n";

    fclose($Fin);
    } // ShowText()

  /***********************************************************
   ReadHex(): Read bytes from a file (or stop at EOF).
   This populates two strings: Hex and Text -- these represent
   the bytes.  This function also handles highlighting.
   Returns number of bytes read.
   ***********************************************************/
  function ReadHex($Fin,$Start,$Length, &$Text, &$Hex)
    {
    $Text = "";
    $Hex = "";
    $ReadCount=0;

    /* Begin color if it is IN but not at START of highlighting */
    $InColor=0;
    if ($this->FindHighlight($Start) & 0x03 == 0x02)
	{
	$H = $this->Highlight[0]['Color'];
	$Text .= "<font style='background:" . $this->HighlightColors[$H] . ";'>";
	$Hex .= "<font style='background:" . $this->HighlightColors[$H] . ";'>";
	$InColor=1;
	}

    while($Length > 0)
      {
      $S = $this->ReadHighlight($Fin,$Start,$Length);
      $ReadCount += strlen($S);

      /* If read nothing, then pad and exit */
      if (strlen($S) == 0)
	{
	if ($InColor) { $Hex .= "</font>"; $Text .= "</font>"; }
	/* Pad out and return */
	for($i=$ReadCount; $i < 16; $i++)
	  {
	  $Hex .= "&nbsp;&nbsp;&nbsp;";
	  $Text .= "&nbsp;";
	  }
	return($ReadCount);
	}

      else /* Read something */
	{
	/* Add color */
	if ($this->FindHighlight($Start) & 0x01)
	  {
	  if ($InColor) { $Hex .= "</font>"; $Text .= "</font>"; }
	  $H = $this->Highlight[0]['Color'];
	  $Hex .= "<font style='background:" . $this->HighlightColors[$H] . ";'>";
	  $Text .= "<font style='background:" . $this->HighlightColors[$H] . ";'>";
	  $InColor=1;
	  if (!empty($this->Highlight[0]['Name']))
	    {
	    $Hex .= "<a name='" . $this->Highlight[0]['Index'] . "'>";
	    }
	  }

	/* Process string */
	$ST = preg_replace("/[^[:print:]]/",'.',$S);
	$ST = htmlentities($ST);
	$ST = preg_replace("/[[:space:]]/",'&nbsp;',$ST);
	$Text .= $ST;
	$SH = bin2hex($S);
	$SH = preg_replace("/(..)/",'\1 ',$SH);
	// $SH = preg_replace("/(.......................) /",'\1 | ',$SH);
	$SH = str_replace(" ","&nbsp;",$SH);
	$Hex .= $SH;

	if (($this->FindHighlight($Start) & 0x01) && !empty($this->Highlight[0]['Name']))
	  {
	  $Hex .= "</a>";
	  }
	}

      $Length -= strlen($S);
      $Start += strlen($S);

      if ($InColor && ($this->FindHighlight($Start) & 0x04))
	{
	$Text .= "</font>";
	$Hex .= "</font>";
	$InColor=0;
	}
      } /* while reading */

    /* End coloring as needed */
    if ($InColor);
	{
	$Text .= "</font>";
	$Hex .= "</font>";
	}
    return($ReadCount);
    } // ReadHex()

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

    /*****
     This system prints two columns, so it must track TWO output strings.
     $Hex is the hex-string component.
     $Text is the text-string component.
     $S is the string read in from the file.
     Each line represents 16 bytes.
     The function ReadHex(): reads the 16 bytes and populates the strings.
     The populated strings can be LONGER than 16 bytes due to highlighting
     and HTML taint protection.
     *****/

    /* Process the file */
    print "<div class='mono'>";
    $ReadCount=1;
    while(($ReadCount > 0) && ($Length > 0))
      {
      $Tell = ftell($Fin);
      $ReadCount = $this->ReadHex($Fin,$Start,min($Length,16),$Text,$Hex);
      if ($ReadCount > 0)
	{
	/* show file location */
	$B = base_convert($Tell,10,16);
	print "0x";
	for($i=strlen($B); $i<8; $i++) { print("0"); }
	print "$B&nbsp;";

	print "|&nbsp;$Hex|&nbsp;|$Text|<br>\n";
	}
      $Start += $ReadCount;
      $Length -= $ReadCount;
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
     If there is paging, compute page conversions.
     ***********************************/
    switch($Format)
	{
	case 'hex':
		$PageHex = $Page;
		$PageText = intval($Page * VIEW_BLOCK_HEX / VIEW_BLOCK_TEXT);
		break;
	case 'text':
	case 'flow':
		$PageText = $Page;
		$PageHex = intval($Page * VIEW_BLOCK_TEXT / VIEW_BLOCK_HEX);
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
    if ($Format != 'hex') { $V .= "<a href='$Uri$Opt&format=hex&page=$PageHex'>Hex</a> | "; }
    else { $V .= "Hex | "; }
    if ($Format != 'text') { $V .= "<a href='$Uri$Opt&format=text&page=$PageText'>Plain Text</a> | "; }
    else { $V .= "Plain Text | "; }
    if ($Format != 'flow') { $V .= "<a href='$Uri$Opt&format=flow&page=$PageText'>Flowed Text</a> | "; }
    else { $V .= "Flowed Text | "; }
    $Opt="";
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
     Sort highlighting.
     ***********************************/
    if (!empty($this->Highlight))
	{
	usort($this->Highlight,array("ui_view","_cmp_highlight"));
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
	  $Filename = RepPath($Pfile);
	  $Fin = fopen($Filename,"rb");
	  $Pages = "";
	  $Uri = preg_replace('/&page=[0-9]*/','',Traceback());
	  if ($Format == 'hex')
	    {
	    $HighlightMenu = $this->GetHighlightMenu(VIEW_BLOCK_HEX);
	    if (!empty($HighlightMenu)) { print "<center>$HighlightMenu</center><hr>\n"; }
	    $PageMenu = $this->GetFileJumpMenu($Fin,$Page,VIEW_BLOCK_HEX,$Uri);
	    $PageSize = VIEW_BLOCK_HEX * $Page;
	    if (!empty($PageMenu)) { print "<center>$PageMenu</center><br>\n"; }
	    $this->ShowHex($Fin,$PageSize,VIEW_BLOCK_HEX);
	    if (!empty($PageMenu)) { print "<P /><center>$PageMenu</center><br>\n"; }
	    }
	  else if ($Format == 'text')
	    {
	    $HighlightMenu = $this->GetHighlightMenu(VIEW_BLOCK_TEXT);
	    if (!empty($HighlightMenu)) { print "<center>$HighlightMenu</center><hr>\n"; }
	    $PageMenu = $this->GetFileJumpMenu($Fin,$Page,VIEW_BLOCK_TEXT,$Uri);
	    $PageSize = VIEW_BLOCK_TEXT * $Page;
	    if (!empty($PageMenu)) { print "<center>$PageMenu</center><br>\n"; }
	    $this->ShowText($Fin,$PageSize,0,VIEW_BLOCK_TEXT);
	    if (!empty($PageMenu)) { print "<P /><center>$PageMenu</center><br>\n"; }
	    }
	  else if ($Format == 'flow')
	    {
	    $HighlightMenu = $this->GetHighlightMenu(VIEW_BLOCK_TEXT);
	    if (!empty($HighlightMenu)) { print "<center>$HighlightMenu</center><hr>\n"; }
	    $PageMenu = $this->GetFileJumpMenu($Fin,$Page,VIEW_BLOCK_TEXT,$Uri);
	    $PageSize = VIEW_BLOCK_TEXT * $Page;
	    if (!empty($PageMenu)) { print "<center>$PageMenu</center><br>\n"; }
	    $this->ShowText($Fin,$PageSize,1,VIEW_BLOCK_TEXT);
	    if (!empty($PageMenu)) { print "<P /><center>$PageMenu</center><br>\n"; }
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
