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

define("VIEW_BLOCK_HEX",8192);
define("VIEW_BLOCK_TEXT",20*VIEW_BLOCK_HEX);
define("MAXHIGHLIGHTCOLOR",8);
define("TITLE_ui_view", _("View File"));

class ui_view extends FO_Plugin
{
  var $Name       = "view";
  var $Title      = TITLE_ui_view;
  var $Version    = "1.0";
  var $Dependency = array("browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  var $HighlightColors = array("yellow","lightgreen","aqua","mediumslateblue","darkkhaki","orange","lightsteelblue","yellowgreen");
  var $Highlight=NULL;

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("View file contents");
    menu_insert("Browse-Pfile::View",10,$this->Name,$text);
    // For the Browse menu, permit switching between detail and summary.
    $Format = GetParm("format",PARM_STRING);
    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) { $Page = 0; }
    $URI = Traceback_parm();
    $URI = preg_replace("/&format=[a-zA-Z0-9]*/","",$URI);
    $URI = preg_replace("/&page=[0-9]*/","",$URI);

    $PageHex  = NULL;
    $PageText = NULL;

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

    menu_insert("View::[BREAK]",-1);
    switch($Format)
    {
      case "hex":
        $text = _("View as unformatted text");
        menu_insert("View::Hex",-10);
        menu_insert("View::Text",-11,"$URI&format=text&page=$PageText",$text);
        $text = _("View as formatted text");
        menu_insert("View::Formatted",-12,"$URI&format=flow&page=$PageText",$text);
        break;
      case "text":
        $text = _("View as a hex dump");
        menu_insert("View::Hex",-10,"$URI&format=hex&page=$PageHex",$text);
        menu_insert("View::Text",-11);
        $text = _("View as formatted text");
        menu_insert("View::Formatted",-12,"$URI&format=flow&page=$PageText",$text);
        break;
      case "flow":
        $text = _("View as a hex dump");
        menu_insert("View::Hex",-10,"$URI&format=hex&page=$PageHex",$text);
        $text = _("View as unformatted text");
        menu_insert("View::Text",-11,"$URI&format=text&page=$PageText",$text);
        menu_insert("View::Formatted",-12);
        break;
      default:
        $text = _("View as a hex dump");
        menu_insert("View::Hex",-10,"$URI&format=hex&page=$PageHex",$text);
        $text = _("View as unformatted text");
        menu_insert("View::Text",-11,"$URI&format=text&page=$PageText",$text);
        $text = _("View as formatted text");
        menu_insert("View::Formatted",-12,"$URI&format=flow&page=$PageText",$text);
        break;
    }

    $URI = Traceback_parm_keep(array("show","format","page","upload","item"));
    if (GetParm("mod",PARM_STRING) == $this->Name)
    {
      menu_insert("View::View",2);
      menu_insert("View-Meta::View",2);
    }
    else
    {
      $text = _("View file contents");
      menu_insert("View::View",2,$this->Name . $URI,$text);
      menu_insert("View-Meta::View",2,$this->Name . $URI,$text);
      menu_insert("Browse::View",-2,$this->Name . $URI,$text);
      menu_insert("Browse::[BREAK]",-1);
    }
  } // RegisterMenus()

  /**
   * \brief Use for sorting the highlight list.
   */
  static function _cmp_highlight($a,$b)
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

  /**
   * \brief Sort highlighting.
   * The list of highlights were probably not inserted in order...
   */
  function SortHighlightMenu	()
  {
    if (!empty($this->Highlight))
    {
      usort($this->Highlight,array("ui_view","_cmp_highlight"));
    }
    /* Now they are sorted by start offset */
    /* Make sure there are no overlaps */
    $End=-1;
    for($i=0; !empty($this->Highlight[$i]); $i++)
    {
      if ($this->Highlight[$i]['Start'] <= $End) { $this->Highlight[$i]['Start'] = $End+1; }
      $End = $this->Highlight[$i]['End'];
    }
  } // SortHighlightMenu()

  /**
   * \brief Text can be highlighted!
   * Start, End, and Color are required.
   * If Color is -1, then uses last color.
   * If Color is -2, then uses color AFTER last color.
   * Name is only needed if you want to have it listed in the
   * top menu with a hyperlink jump.
   * Index is auto-assigned to Name, if not specified.
   */
  function AddHighlight($ByteStart,$ByteEnd,$Color,
  $Match=NULL,$Name=NULL,$Index=-1,$RefURL=NULL)
  {
    $H = array();
    $H['Start'] = intval($ByteStart);
    $H['End'] = intval($ByteEnd);
    if (is_int($Color))
    {
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
    }
    $H['Color'] = $Color;
    $H['Match'] = htmlentities($Match);
    $H['RefURL'] = $RefURL;
    $H['Name'] = htmlentities($Name);
    if (intval($Index) != -1) { $H['Index'] = intval($Index); }
    else { $H['Index'] = count($this->Highlight)+1; }
    if (empty($this->Highlight)) { $this->Highlight = array($H); }
    else { array_push($this->Highlight,$H); }
  } // AddHighlight()

  /**
   * \brief if there is a highlight menu, create it.
   * 
   * \param $PageBlockSize
   * \param $ViewOnly     If true don't display match %
   * \param $DispView     If true, display view link
   */
  function GetHighlightMenu($PageBlockSize, $ViewOnly=False, $DispView=True)
  {
    if (empty($this->Highlight)) { return; }
    $First=1;
    $V = "<table border=1>";
    $Uri = preg_replace('/&page=[0-9]*/',"",Traceback());
    foreach($this->Highlight as $H)
    {
      if (!empty($H['Name']))
      {
        if ($First)
        {
          $First = 0;
          if (!$ViewOnly)
          {
            $V .= "<tr>";
            $text = _("Match");
            $V .= "<th>$text</th>";
            $V .= "<th></th>";
            $V .= "<th></th>";
            $text = _("Item");
            if (!$ViewOnly) $V .= "<th align='left'>$text</th>";
            $V .= "</tr>\n";
          }
        }
        if (key_exists('Color', $this->HighlightColors))
        $V .= "<tr bgcolor='" . $this->HighlightColors[$H['Color']] . "'>\n";

        if (!$ViewOnly) $V .= "<td align='right'>" . $H['Match'] . "</td>\n";

        $V .= "<td>";
        if ($PageBlockSize > 0)
        {
          $Page = intval($H['Start'] / $PageBlockSize);
          if ($DispView)
          {
            $text = _("view");
            $V .= "<a href='$Uri&page=$Page#" . $H['Index'] . "'>$text</a>";
          }
        }
        else
        {
          if ($DispView)
          {
            $text = _("view");
            $V .= "<a href='#" . $H['Index'] . "'>$text</a>";
          }
        }
        $V .= "</td>\n";

        if (!$ViewOnly)
        {
          $V .= "<td>";
          if (!empty($H['RefURL']))
          {
            $text = _("ref");
            $V .= "<a href='javascript:;' onClick=\"javascript:window.open('" . $H['RefURL'] . "','License','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\">$text</a>";
          }
          $V .= "</td>\n";
        }

        /* strip out characters we don't want to see */
        $S = strip_tags($H['Name']);
        $S = str_replace(chr(0xc2),"",$S);  // comes from utf-8 copyright symbol
        $S = str_replace("&Acirc;","",$S);  // comes from utf-8 copyright symbol
        $S = str_replace("\r","",$S);
        $S = str_replace("\n","",$S);
        $S = str_replace("\t","&nbsp;&nbsp;",$S);
        $V .= "<td>" . $S . "</td>\n";
        $V .= "</tr>\n";
      }
    }
    if ($First==1) return("");
    $V .= "</table>";
    return($V);
  } // GetHighlightMenu()

  /**
   * \brief Given a file handle and current page,
   * generate the "Next" and "Prev" menu options.
   * Returns String.
   */
  function GetFileJumpMenu($Fin,$CurrPage,$PageSize,$Uri)
  {
    if (!$Fin) return;
    $Stat = fstat($Fin);
    $MaxSize = $Stat['size'];
    $MaxPage = intval($MaxSize / $PageSize);
    $V = "<font class='text'>";
    $CurrSize = $CurrPage * $PageSize;

    $Pages=0; /* How many pages are there? */

    if ($CurrPage * $PageSize >= $MaxSize) { $CurrPage = 0; $CurrSize = 0; }
    if ($CurrPage < 0) { $CurrPage = 0; }

    if ($CurrPage > 0)
    {
      $text = _("First");
      $V .= "<a href='$Uri&page=0'>[$text]</a> ";
      $text = _("Prev");
      $V .= "<a href='$Uri&page=" . ($CurrPage-1) . "'>[$text]</a> ";
      $Pages++;
    }
    for($i = $CurrPage-5; $i <= $CurrPage+5; $i ++)
    {
      if ($i == $CurrPage)
      {
        $V .= "<b>" . ($i+1) . "</b> ";
      }
      else if (($i >= 0) && ($i <= $MaxPage))
      {
        $V .= "<a href='$Uri&page=$i'>" . ($i+1) . "</a> ";
      }
    }
    if ($CurrPage < $MaxPage)
    {
      $text = _("Next");
      $V .= "<a href='$Uri&page=" . ($CurrPage+1) . "'>[$text]</a>";
      $text = _("Last");
      $V .= "<a href='$Uri&page=" . (intval(($MaxSize-1)/$PageSize)) . "'>[$text]</a>";
      $Pages++;
    }
    $V .= "</font>";

    /* If there is only one page, return nothing */
    if ($Pages==0) { return; }
    return($V);
  } // GetFileJumpMenu()

  /**
   * \brief REMOVE items from the Highlight array.
   * This shifts items off of the highlight array based on the
   * current position in the file.
   * The top of the array ($Highlight[0]) identifies the current
   * item to highlight.
   * 
   * \return flags
   * 0 if no highlight
   * 0x01 if at start
   * 0x02 if highlighting
   * 0x04 if at end
   */
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

  /**
   * \brief Given a file handle, read up to the next
   * highlight boundary (start or end).
   * 
   * \return String read.
   * \note if strlen of return is < $MaxRead, then it hit a highlight.
   */
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

  /**
   * \brief Given a file handle, display "strings" of the file.
   * Output goes to stdout!
   */
  function ShowText($Fin,$Start,$Flowed,$FullLength=-1)
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
    if (($this->FindHighlight($Start) & 0x03) == 0x02)
    {
      $H = $this->Highlight[0];
      print "<font style='background:" . $this->HighlightColors[$H['Color']] . ";'>";
      $InColor=1;
    }
    $S = $this->ReadHighlight($Fin,$Start,$Length);
    $ReadCount = strlen($S);
    while(($ReadCount > 0) && ($Length > 0))
    {
      if ($Flowed)
      {
        $S = str_replace("\r","",$S);
        $S = str_replace("\t","&nbsp;&nbsp;",$S);
        $S = str_replace("<","&lt;",$S);
        $S = str_replace(">","&gt;",$S);
        $S = str_replace("\n","<br>\n",$S);
      }
      else
      {
        /* Massage the data and print it */
        $S = preg_replace('/[^[:print:][:space:]]+/'," ",$S);
        $S = htmlentities($S);
      }

      if (($this->FindHighlight($Start)) & 0x01)
      {
        if ($InColor) { print "</font>"; }
        $H = $this->Highlight[0]['Color'];
        print "<font style='background:" . $this->HighlightColors[$H] . ";'>";
        $InColor=1;
        if (!empty($this->Highlight[0]['Name']))
        {
          print "<a name='" . $this->Highlight[0]['Index'] . "'></a>" . $S;
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
  } // ShowText()

  /**
   * \brief Read bytes from a file (or stop at EOF).
   * This populates two strings: Hex and Text -- these represent
   * the bytes.  This function also handles highlighting.
   * 
   * \return number of bytes read.
   */
  function ReadHex($Fin,$Start,$Length, &$Text, &$Hex)
  {
    $Text = "<font class='mono'>";
    $Hex = "<font class='mono'>";
    $ReadCount=0;

    /* Begin color if it is IN but not at START of highlighting */
    /** If it is START, then it will be added inside the loop **/
    $InColor=0;
    if ($this->FindHighlight($Start) & 0x02)
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
        $ST = preg_replace("/[^[:print:][:space:]]/",'.',$S);
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
    if ($InColor)
    {
      $Text .= "</font>";
      $Hex .= "</font>";
    }
    $Text .= "</font>";
    $Hex .= "</font>";
    return($ReadCount);
  } // ReadHex()

  /**
   * \brief Given a file handle, display a "hex dump" of the file.
   * Output goes to stdout!
   */
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
        print "<font class='mono'>0x";
        for($i=strlen($B); $i<8; $i++) { print("0"); }
        print "$B&nbsp;";
        print "</font>";

        print "|&nbsp;$Hex|&nbsp;|$Text|<br>\n";
      }
      $Start += $ReadCount;
      $Length -= $ReadCount;
    }
    print "</div>\n";
  } // ShowHex()

  /**
   * \brief Generate the view contents in HTML and sends it
   *  to stdout.
   *
   * \param $Name - the name for this plugin.
   * 
   * \note This function is intended to be called from other plugins.
   */
  function ShowView($Fin=NULL, $BackMod="browse",
  $ShowMenu=1, $ShowHeader=1, $ShowText=NULL, $ViewOnly=False, $DispView=True)
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    $Upload = GetParm("upload",PARM_INTEGER);
    $Folder = GetParm("folder",PARM_INTEGER);
    $Show = GetParm("show",PARM_STRING);
    $Item = GetParm("item",PARM_INTEGER);
    $nomosagent_pk = GetParm("napk",PARM_INTEGER);
    $Page = GetParm("page",PARM_INTEGER);
    $bucketagent_pk = GetParm("bapk",PARM_INTEGER);
    if (!$Fin && (empty($Item) || empty($Upload))) { return; }
    if (empty($Page)) { $Page=0; };

    /* Get uploadtree table name */
    $uploadtree_tablename = "uploadtree";
    if (!empty($Upload))
    $uploadtree_tablename = GetUploadtreeTablename($Upload);

    switch(GetParm("format",PARM_STRING))
    {
      case 'hex':	$Format='hex'; break;
      case 'text':	$Format='text'; break;
      case 'flow':	$Format='flow'; break;
      default:
        /* Determine default show based on mime type */
        if (empty($Item))
        $Format = 'text';
        else
        {
          $Meta = GetMimeType($Item);
          list($Type,$Junk) = explode("/",$Meta,2);
          if ($Type == 'text')
          { $Format = 'flow'; }
          else switch($Meta)
          {
            case "application/octet-string":
            case "application/x-awk":
            case "application/x-csh":
            case "application/x-javascript":
            case "application/x-perl":
            case "application/x-shellscript":
            case "application/x-rpm-spec":
            case "application/xml":
            case "message/rfc822":
              $Format='flow';
              break;
            default:
              $Format = 'flow';
          }
        }
        break;
    }

    /**********************************
     Display micro header
     **********************************/
    if ($ShowHeader)
    {
      $Uri = Traceback_uri() . "?mod=browse";
      $Opt="";
      if (!empty($Item)) { $Opt .= "&item=$Item"; }
      if (!empty($Upload)) { $Opt .= "&upload=$Upload"; }
      if (!empty($Folder)) { $Opt .= "&folder=$Folder"; }
      if (!empty($Show)) { $Opt .= "&show=$Show"; }
      /* No item */
      $V .= Dir2Browse($BackMod,$Item,NULL,1,"View", -1, '', '', $uploadtree_tablename) . "<P />\n";
    } // if ShowHeader

    $this->SortHighlightMenu();

    /***********************************
     Display file contents
     ***********************************/
    print $V;
    $V = "";
    $openedFin = False;
    if (empty($Fin))
    {
      $Fin = @fopen( RepPathItem($Item) ,"rb");
      if ($Fin) $openedFin = true;
      if (empty($Fin))
      {
        /* Added by vincent implement when view files which not in repository, ask user if want to reunpack*/
        /** BEGIN **/
        /* If this is a POST, then process the request. */
        $uploadunpack = GetParm('uploadunpack',PARM_INTEGER);
        $uploadpk = $Upload;
        $flag = 0;

        $P = &$Plugins[plugin_find_id("ui_reunpack")];
        $state = $P->CheckStatus($uploadpk, "unpack", "unpack");
        //print "<p>$state</p>";
        if ( $state == 0 || $state == 2)
        {
          if (!empty($uploadunpack))
          {
            $rc = $P->AgentAdd($uploadpk);
            if (empty($rc))
            {
              /* Need to refresh the screen */
              $text = _("Unpack added to job queue");
              $V .= displayMessage($text);
              $flag = 1;
              $text = _("Reunpack job is running: you can see it in");
              $text1 = _("jobqueue");
              print "<p> <font color=red>$text <a href='" . Traceback_uri() . "?mod=showjobs'>$text1</a></font></p>";
            }
            else
            {
              $text = _("Unpack of Upload failed");
              $V .= displayMessage("$text: $rc");
            }
            print $V;
          }
        }
        else {
          $flag = 1;
          $text = _("Reunpack job is running: you can see it in");
          $text1 = _("jobqueue");
          print "<p> <font color=red>$text <a href='" . Traceback_uri() . "?mod=showjobs'>$text1</a></font></p>";
        }
        $text = _("File contents are not available in the repository.");
        print "$text\n";
        $P = &$Plugins[plugin_find_id("ui_reunpack")];
        print $P->ShowReunpackView($Item,$flag);
        return;
      }
      /** END **/
    }
    rewind($Fin);
    $Pages = "";
    $Uri = preg_replace('/&page=[0-9]*/','',Traceback());
    $HighlightMenu = "";

    $HighlightMenu .= "</center>";  // some fcn left a dangling center

    if ($Format == 'hex')
    {
      $HighlightMenu .= $this->GetHighlightMenu(VIEW_BLOCK_HEX, $ViewOnly, $DispView);
      if (!empty($HighlightMenu)) { print "<center>$HighlightMenu</center><hr>\n"; }
      $PageMenu = $this->GetFileJumpMenu($Fin,$Page,VIEW_BLOCK_HEX,$Uri);
      $PageSize = VIEW_BLOCK_HEX * $Page;
      if (!empty($PageMenu)) { print "<center>$PageMenu</center><br>\n"; }
      $this->ShowHex($Fin,$PageSize,VIEW_BLOCK_HEX);
      if (!empty($PageMenu)) { print "<P /><center>$PageMenu</center><br>\n"; }
    }
    else if ($Format == 'text')
    {
      $HighlightMenu .= $this->GetHighlightMenu(VIEW_BLOCK_TEXT, $ViewOnly, $DispView);
      if (!empty($HighlightMenu)) { print "<center>$HighlightMenu</center><hr>\n"; }
      $PageMenu = $this->GetFileJumpMenu($Fin,$Page,VIEW_BLOCK_TEXT,$Uri);
      $PageSize = VIEW_BLOCK_TEXT * $Page;
      if (!empty($PageMenu)) { print "<center>$PageMenu</center><br>\n"; }
      $this->ShowText($Fin,$PageSize,0,VIEW_BLOCK_TEXT);
      if (!empty($PageMenu)) { print "<P /><center>$PageMenu</center><br>\n"; }
    }
    else if ($Format == 'flow')
    {
      $HighlightMenu .= $this->GetHighlightMenu(VIEW_BLOCK_TEXT, $ViewOnly, $DispView);
      if (!empty($HighlightMenu)) { print "<center>$HighlightMenu</center><hr>\n"; }
      $PageMenu = $this->GetFileJumpMenu($Fin,$Page,VIEW_BLOCK_TEXT,$Uri);
      $PageSize = VIEW_BLOCK_TEXT * $Page;
      if (!empty($PageMenu)) { print "<center>$PageMenu</center><br>\n"; }
      if (!empty($ShowText)) { echo $ShowText, "<hr>"; }
      $this->ShowText($Fin,$PageSize,1,VIEW_BLOCK_TEXT);
      if (!empty($PageMenu)) { print "<P /><center>$PageMenu</center><br>\n"; }
    }
    if ($openedFin) fclose($Fin);
    return;
  } // ShowView()

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for content.
   * (OutputOpen and Output are separated so one plugin
   * can call another plugin's Output.)
   * This uses $OutputType.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function Output()
  {
    global $PG_CONN;

    if ($this->State != PLUGIN_STATE_READY) 
    { 
      return;
    }
    if (!$PG_CONN)
    {
      DBconnect();
      if (!$PG_CONN)
      {
         echo "NO DB connection";
      }
    }

    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        if ($this->OutputToStdout)
        {
          $this->ShowView(NULL,"browse");
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
  }

};
$NewPlugin = new ui_view;
$NewPlugin->Initialize();
?>
