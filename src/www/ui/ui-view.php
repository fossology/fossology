<?php
/***********************************************************
 * Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\TextFragment;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\TextRenderer;
use Monolog\Logger;

define("VIEW_BLOCK_HEX", 8192);
define("VIEW_BLOCK_TEXT", 1 * VIEW_BLOCK_HEX);
define("MAXHIGHLIGHTCOLOR", 8);
define("TITLE_ui_view", _("View File"));

class ui_view extends FO_Plugin
{
  /**
   * @var Logger
   */
  private $logger;

  /**
   * @var TextRenderer
   */
  private $textRenderer;

  /**
   * @var HighlightProcessor
   */
  private $highlightProcessor;

  function __construct()
  {
    $this->Name = "view";
    $this->Title = TITLE_ui_view;
    $this->Version = "1.0";
    $this->Dependency = array("browse");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;

    parent::__construct();

    global $container;
    $this->logger = $container->get("logger");
    $this->textRenderer = $container->get("view.text_renderer");
    $this->highlightProcessor = $container->get("view.highlight_processor");
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("View file contents");
    menu_insert("Browse-Pfile::View", 10, $this->Name, $text);
    // For the Browse menu, permit switching between detail and summary.
    $Format = $this->getFormatParameter(null);
    $Page = GetParm("page", PARM_INTEGER);

    $URI = Traceback_parm();
    $URI = preg_replace("/&format=[a-zA-Z0-9]*/", "", $URI);
    $URI = preg_replace("/&page=[0-9]*/", "", $URI);

    $PageHex = NULL;
    $PageText = NULL;

    /***********************************
     * If there is paging, compute page conversions.
     ***********************************/
    switch ($Format)
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

    menu_insert("View::[BREAK]", -1);
    switch ($Format)
    {
      case "hex":
        $text = _("View as unformatted text");
        menu_insert("View::Hex", -10);
        menu_insert("View::Text", -11, "$URI&format=text&page=$PageText", $text);
        $text = _("View as formatted text");
        menu_insert("View::Formatted", -12, "$URI&format=flow&page=$PageText", $text);
        break;
      case "text":
        $text = _("View as a hex dump");
        menu_insert("View::Hex", -10, "$URI&format=hex&page=$PageHex", $text);
        menu_insert("View::Text", -11);
        $text = _("View as formatted text");
        menu_insert("View::Formatted", -12, "$URI&format=flow&page=$PageText", $text);
        break;
      case "flow":
        $text = _("View as a hex dump");
        menu_insert("View::Hex", -10, "$URI&format=hex&page=$PageHex", $text);
        $text = _("View as unformatted text");
        menu_insert("View::Text", -11, "$URI&format=text&page=$PageText", $text);
        menu_insert("View::Formatted", -12);
        break;
      default:
        $text = _("View as a hex dump");
        menu_insert("View::Hex", -10, "$URI&format=hex&page=$PageHex", $text);
        $text = _("View as unformatted text");
        menu_insert("View::Text", -11, "$URI&format=text&page=$PageText", $text);
        $text = _("View as formatted text");
        menu_insert("View::Formatted", -12, "$URI&format=flow&page=$PageText", $text);
        break;
    }

    $URI = Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    if (GetParm("mod", PARM_STRING) == $this->Name)
    {
      menu_insert("View::View", 2);
      menu_insert("View-Meta::View", 2);
    } else
    {
      $text = _("View file contents");
      menu_insert("View::View", 2, $this->Name . $URI, $text);
      menu_insert("View-Meta::View", 2, $this->Name . $URI, $text);
      menu_insert("Browse::View", -2, $this->Name . $URI, $text);
      menu_insert("Browse::[BREAK]", -1);
    }
  } // RegisterMenus()

  /**
   * \brief Given a file handle and current page,
   * generate the "Next" and "Prev" menu options.
   * Returns String.
   */
  function GetFileJumpMenu($Fin, $CurrPage, $PageSize, $Uri)
  {
    if (!$Fin) return;
    $Stat = fstat($Fin);
    $MaxSize = $Stat['size'];
    $MaxPage = intval($MaxSize / $PageSize);
    $V = "<font class='text'>";
    $CurrSize = $CurrPage * $PageSize;

    $Pages = 0; /* How many pages are there? */

    if ($CurrPage * $PageSize >= $MaxSize)
    {
      $CurrPage = 0;
      $CurrSize = 0;
    }
    if ($CurrPage < 0)
    {
      $CurrPage = 0;
    }

    if ($CurrPage > 0)
    {
      $text = _("First");
      $V .= "<a href='$Uri&page=0'>[$text]</a> ";
      $text = _("Prev");
      $V .= "<a href='$Uri&page=" . ($CurrPage - 1) . "'>[$text]</a> ";
      $Pages++;
    }
    for ($i = $CurrPage - 5; $i <= $CurrPage + 5; $i++)
    {
      if ($i == $CurrPage)
      {
        $V .= "<b>" . ($i + 1) . "</b> ";
      } else if (($i >= 0) && ($i <= $MaxPage))
      {
        $V .= "<a href='$Uri&page=$i'>" . ($i + 1) . "</a> ";
      }
    }
    if ($CurrPage < $MaxPage)
    {
      $text = _("Next");
      $V .= "<a href='$Uri&page=" . ($CurrPage + 1) . "'>[$text]</a>";
      $text = _("Last");
      $V .= "<a href='$Uri&page=" . (intval(($MaxSize - 1) / $PageSize)) . "'>[$text]</a>";
      $Pages++;
    }
    $V .= "</font>";

    /* If there is only one page, return nothing */
    if ($Pages == 0)
    {
      return;
    }
    return ($V);
  } // GetFileJumpMenu()

  /**
   * \brief Given a file handle, display "strings" of the file.
   * Output goes to stdout!
   */
  function ShowText($inputFile, $startOffset, $Flowed, $outputLength = -1, $splitPositions = null, $insertBacklink = false)
  {
    print $this->getText($inputFile,$startOffset,$Flowed,$outputLength,$splitPositions,$insertBacklink);
  }
  /**
   * \brief Given a file handle, display "strings" of the file.
   */
  function getText($inputFile, $startOffset, $Flowed, $outputLength = -1, $splitPositions = null, $insertBacklink = false)
  {
    if (!($outputLength = $this->checkAndPrepare($inputFile, $startOffset, $outputLength)))
    {
      return "";
    }

    $output ="";
    $output .= ($Flowed ? '<div class="text">' : '<div class="mono"><pre>');

    fseek($inputFile, $startOffset, SEEK_SET);
    $textFragment = new TextFragment($startOffset, fread($inputFile, $outputLength));

    $renderedText = $this->textRenderer->renderText($textFragment, $splitPositions, $insertBacklink);

    $output .=($Flowed ? nl2br($renderedText) : $renderedText) . (!$Flowed ? "</pre>" : "") . "</div>\n";

    return $output;
  } // ShowText()


  /**
   * \brief Given a file handle, display a "hex dump" of the file.
   * Output goes to stdout!
   */
  function ShowHex($inputFile, $startOffset = 0, $outputLength = -1, $splitPositions = null)
  {
    print $this->getHex($inputFile,$startOffset,$outputLength,$splitPositions);
  }

  /**
   * \brief Given a file handle, display a "hex dump" of the file.
   * Output goes to stdout!
   */
  function getHex($inputFile, $startOffset = 0, $outputLength = -1, $splitPositions = null)
  {
    if (!($outputLength = $this->checkAndPrepare($inputFile, $startOffset, $outputLength)))
    {
      return "";
    }

    $output = "";
    fseek($inputFile, $startOffset, SEEK_SET);
    $textFragment = new TextFragment($startOffset, fread($inputFile, $outputLength));

    $output .= "<div class='mono'>";

    $renderedText = $this->textRenderer->renderHex($textFragment, $splitPositions);
    $output .=  $renderedText;

    $output .=  "</div>\n";

    return $output;
  } // ShowHex()

  private function checkAndPrepare($inputFile, $startOffset, $outputLength)
  {
    if (!$inputFile)
      return False;

    $inputFileStat = fstat($inputFile);
    $inputFileSize = $inputFileStat['size'];

    if ($outputLength < 0)
    {
      $outputLength = $inputFileSize;
    }

    if (($startOffset < 0) || ($startOffset >= $inputFileSize))
    {
      return False;
    }

    if ($outputLength == 0)
    {
      return false;
    }
    return $outputLength;
  }


  /**
   * \brief Generate the view contents in HTML and sends it
   *  to stdout.
   *
   * @param resource $inputFile
   * @param string $BackMod
   * @param int $ShowMenu
   * @param int $ShowHeader
   * @param null $ShowText
   * @param bool $ViewOnly
   * @param bool $DispView
   * @param Highlight[] $highlightEntries
   * @param bool $insertBacklink
   *
   * \note This function is intended to be called from other plugins.
   */
  function ShowView($inputFile = NULL, $BackMod = "browse",
                    $ShowMenu = 1, $ShowHeader = 1, $ShowText = NULL, $ViewOnly = False, $DispView = True, $highlightEntries = array(), $insertBacklink = false)
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return;
    }
    print $this->getView($inputFile , $BackMod,
         $ShowHeader , $ShowText, $highlightEntries , $insertBacklink);
  }

  /**
   * \brief Generate the view contents in HTML
   *
   * @param resource $inputFile
   * @param string $BackMod
   * @param int $ShowMenu
   * @param int $ShowHeader
   * @param null $ShowText
   * @param bool $ViewOnly
   * @param bool $DispView
   * @param Highlight[] $highlightEntries
   * @param bool $insertBacklink
   *
   * \note This function is intended to be called from other plugins.
   */
  function getView($inputFile = NULL, $BackMod = "browse",
                     $ShowHeader = 1, $ShowText = NULL,  $highlightEntries = array(), $insertBacklink = false, $getPageMenuInline = false)
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return "";
    }
    global $Plugins;

    $Upload = GetParm("upload", PARM_INTEGER);
    if (!empty($Upload))
    {
      $UploadPerm = GetUploadPerm($Upload);
      if ($UploadPerm < PERM_READ) return;
    }

    $Folder = GetParm("folder", PARM_INTEGER);
    $Show = GetParm("show", PARM_STRING);
    $Item = GetParm("item", PARM_INTEGER);
    $Page = GetParm("page", PARM_INTEGER);
    $licenseId = GetParm("licenseId", PARM_INTEGER);
    if (!$inputFile && (empty($Item) || empty($Upload)))
    {
      return "";
    }

    $uploadtree_tablename = GetUploadtreeTablename($Upload);

    $Format = $this->getFormatParameter($Item);

    $output="";
    /**********************************
     * Display micro header
     **********************************/
    if ($ShowHeader)
    {
      $Uri = Traceback_uri() . "?mod=browse";
      $Opt = "";
      if (!empty($Item))
      {
        $Opt .= "&item=$Item";
      }
      if (!empty($Upload))
      {
        $Opt .= "&upload=$Upload";
      }
      if (!empty($Folder))
      {
        $Opt .= "&folder=$Folder";
      }
      if (!empty($Show))
      {
        $Opt .= "&show=$Show";
      }
      /* No item */
      $header = Dir2Browse($BackMod, $Item, NULL, 1, "View", -1, '', '', $uploadtree_tablename) . "<P />\n";
      $output.=$header;
    } // if ShowHeader

    /***********************************
     * Display file contents
     ***********************************/
    $V = "";
    $openedFin = False;
    if (empty($inputFile))
    {
      $inputFile = @fopen(RepPathItem($Item), "rb");
      if ($inputFile) $openedFin = true;
      if (empty($inputFile))
      {
        /* Added by vincent implement when view files which not in repository, ask user if want to reunpack*/
        /** BEGIN **/
        /* If this is a POST, then process the request. */
        $uploadunpack = GetParm('uploadunpack', PARM_INTEGER);
        $uploadpk = $Upload;
        $flag = 0;

        $P = & $Plugins[plugin_find_id("ui_reunpack")];
        $state = $P->CheckStatus($uploadpk, "reunpack", "ununpack");
        //print "<p>$state</p>";
        if ($state == 0 || $state == 2)
        {
          if (!empty($uploadunpack))
          {
            $rc = $P->AgentAdd($uploadpk);
            if (empty($rc))
            {
              /* Need to refresh the screen */
              $text = _("Unpack added to job queue");
              $this->vars['message'] = $text;
              $flag = 1;
              $text = _("Reunpack job is running: you can see it in");
              $text1 = _("jobqueue");
              print "<p> <font color=red>$text <a href='" . Traceback_uri() . "?mod=showjobs'>$text1</a></font></p>";
            } else
            {
              $text = _("Unpack of Upload failed");
              $this->vars['message'] = "$text: $rc";
            }
            $output .= $V;
          }
        } else
        {
          $flag = 1;
          $text = _("Reunpack job is running: you can see it in");
          $text1 = _("jobqueue");
          $output .=  "<p> <font color=red>$text <a href='" . Traceback_uri() . "?mod=showjobs'>$text1</a></font></p>";
        }
        $text = _("File contents are not available in the repository.");
        $output .=  "$text\n";
        $P = & $Plugins[plugin_find_id("ui_reunpack")];
        $output .=  $P->ShowReunpackView($Item, $flag);
        return $output;
      }
      /** END **/
    }
    rewind($inputFile);
    $Uri = preg_replace('/&page=[0-9]*/', '', Traceback());

    $blockSize = $Format == 'hex' ? VIEW_BLOCK_HEX : VIEW_BLOCK_TEXT;

    $this->highlightProcessor->sortHighlights($highlightEntries);

    if (!isset($Page))
    {
      $Page = 0;
      if (!empty($licenseId))
      {
        foreach ($highlightEntries as $highlightEntry)
        {
          if ($highlightEntry->getLicenseId() == $licenseId)
          {
            $Page = intval($highlightEntry->getStart() / $blockSize);
            break;
          }
        }
      }
    };

    if (!empty($ShowText))
    {
      echo $ShowText, "<hr>";
    }
    $PageMenu = $this->GetFileJumpMenu($inputFile, $Page, $blockSize, $Uri);
    $PageSize = VIEW_BLOCK_HEX * $Page;
    if (!empty($PageMenu) and !$getPageMenuInline)
    {
     $output .=  "<center>$PageMenu</center><br>\n";
    }

    $splitPositions = $this->highlightProcessor->calculateSplitPositions($highlightEntries);

    if ($Format == 'hex')
    {
       $output .= $this->getHex($inputFile, $PageSize, VIEW_BLOCK_HEX, $splitPositions);
    } else
    {
      $output .= $this->getText($inputFile, $PageSize, $Format == 'text' ? 0 : 1, VIEW_BLOCK_TEXT, $splitPositions, $insertBacklink);
    }

    if (!empty($PageMenu) and !$getPageMenuInline)
    {
      $output .= "<P /><center>$PageMenu</center><br>\n";
    }

    if ($openedFin) fclose($inputFile);
    if($getPageMenuInline)
      return array($PageMenu, $output);
    else
      return $output;
  } // ShowView()

  protected function htmlContent()
  {
    return $this->ShowView(NULL, "browse");
  }

  /**
   * @param $Item
   * @return string
   */
  protected function getFormatParameter($Item)
  {
    switch (GetParm("format", PARM_STRING))
    {
      case 'hex':
        $Format = 'hex';
        break;
      case 'text':
        $Format = 'text';
        break;
      case 'flow':
        $Format = 'flow';
        break;
      default:
        /* Determine default show based on mime type */
        if (empty($Item))
          $Format = 'flow';
        else
        {
          $Meta = GetMimeType($Item);
          list($Type, $Junk) = explode("/", $Meta, 2);
          if ($Type == 'text')
          {
            $Format = 'text';
          } else {
            $Format = 'flow';
          }
        }
        break;
    }
    return $Format;
  }

}

$NewPlugin = new ui_view;
$NewPlugin->Initialize();
