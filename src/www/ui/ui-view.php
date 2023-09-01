<?php
/*
 SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\TextFragment;
use Fossology\Lib\UI\Component\MicroMenu;
use Fossology\Lib\View\HighlightProcessor;
use Fossology\Lib\View\TextRenderer;
use Monolog\Logger;

class ui_view extends FO_Plugin
{
  const NAME = "view";
  /** @var Logger */
  private $logger;
  /** @var TextRenderer */
  private $textRenderer;
  /** @var HighlightProcessor */
  private $highlightProcessor;
  /** @var UploadDao */
  private $uploadDao;
  /** @var int */
  protected $blockSizeHex = 8192;
  /** @var int */
  protected $blockSizeText = 81920;

  function __construct()
  {
    $this->Name = self::NAME;
    $this->Title = _("View File");
    $this->Dependency = array("browse");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;

    parent::__construct();

    if (array_key_exists('BlockSizeHex', $GLOBALS['SysConf']['SYSCONFIG'])) {
      $this->blockSizeHex = max(64,
        $GLOBALS['SysConf']['SYSCONFIG']['BlockSizeHex']);
    }
    if (array_key_exists('BlockSizeText', $GLOBALS['SysConf']['SYSCONFIG'])) {
      $this->blockSizeText = max(64,
        $GLOBALS['SysConf']['SYSCONFIG']['BlockSizeText']);
    }

    global $container;
    $this->logger = $container->get("logger");
    $this->textRenderer = $container->get("view.text_renderer");
    $this->highlightProcessor = $container->get("view.highlight_processor");
    $this->uploadDao = $container->get("dao.upload");
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $tooltipText = _("View file contents");
    menu_insert("Browse-Pfile::View", 10, $this->Name, $tooltipText);
    // For the Browse menu, permit switching between detail and summary.

    $itemId = GetParm("item", PARM_INTEGER);
    $textFormat = $this->microMenu->getFormatParameter($itemId);
    $pageNumber = GetParm("page", PARM_INTEGER);
    $this->microMenu->addFormatMenuEntries($textFormat, $pageNumber);

    $URI = Traceback_parm_keep(
      array(
        "show",
        "format",
        "page",
        "upload",
        "item"
      ));
    $menuPosition = 59;
    $menuText = "View";
    $tooltipText = _("View file contents");
    $this->microMenu->insert(MicroMenu::TARGET_DEFAULT, $menuText, $menuPosition,
      $this->Name, $this->Name . $URI, $tooltipText);

    if (GetParm("mod", PARM_STRING) != $this->Name) {
      menu_insert("Browse::{$menuText}", - 2, $this->Name . $URI, $tooltipText);
      menu_insert("Browse::[BREAK]", - 1);
    }
  } // RegisterMenus()

  /**
   * \brief Given a file handle and current page,
   * generate the "Next" and "Prev" menu options.
   * Returns String.
   */
  function GetFileJumpMenu($Fin, $CurrPage, $PageSize, $Uri)
  {
    if (! $Fin) {
      return;
    }
    $Stat = fstat($Fin);
    $MaxSize = $Stat['size'];
    $MaxPage = intval($MaxSize / $PageSize);
    $V = "<font class='text'>";
    $CurrSize = $CurrPage * $PageSize;

    $Pages = 0; /* How many pages are there? */

    if ($CurrPage * $PageSize >= $MaxSize) {
      $CurrPage = 0;
      $CurrSize = 0;
    }
    if ($CurrPage < 0) {
      $CurrPage = 0;
    }

    if ($CurrPage > 0) {
      $text = _("First");
      $V .= "<a href='$Uri&page=0'>[$text]</a> ";
      $text = _("Prev");
      $V .= "<a href='$Uri&page=" . ($CurrPage - 1) . "'>[$text]</a> ";
      $Pages ++;
    }
    for ($i = $CurrPage - 5; $i <= $CurrPage + 5; $i ++) {
      if ($i == $CurrPage) {
        $V .= "<b>" . ($i + 1) . "</b> ";
      } else if (($i >= 0) && ($i <= $MaxPage)) {
        $V .= "<a href='$Uri&page=$i'>" . ($i + 1) . "</a> ";
      }
    }
    if ($CurrPage < $MaxPage) {
      $text = _("Next");
      $V .= "<a href='$Uri&page=" . ($CurrPage + 1) . "'>[$text]</a>";
      $text = _("Last");
      $V .= "<a href='$Uri&page=" . (intval(($MaxSize - 1) / $PageSize)) .
        "'>[$text]</a>";
      $Pages ++;
    }
    $V .= "</font>";

    /* If there is only one page, return nothing */
    if ($Pages == 0) {
      return;
    }
    return ($V);
  } // GetFileJumpMenu()

  /**
   * \brief Given a file handle, display "strings" of the file.
   * Output goes to stdout!
   */
  function ShowText($inputFile, $startOffset, $Flowed, $outputLength = -1,
    $splitPositions = null, $insertBacklink = false)
  {
    print
      $this->getText($inputFile, $startOffset, $Flowed, $outputLength,
        $splitPositions, $insertBacklink);
  }

  /**
   * \brief Given a file handle, display "strings" of the file.
   */
  function getText($inputFile, $startOffset, $Flowed, $outputLength = -1,
    $splitPositions = null, $insertBacklink = false, $fromRest = false)
  {
    if (! ($outputLength = $this->checkAndPrepare($inputFile, $startOffset,
      $outputLength))) {
      return "";
    }

    $output = "";
    $output .= ($Flowed ? '<div class="text">' : '<div class="mono"><pre style="overflow:unset;">');

    fseek($inputFile, $startOffset, SEEK_SET);
    $textFragment = new TextFragment($startOffset,
      fread($inputFile, $outputLength));

    $renderedText = $this->textRenderer->renderText($textFragment,
      $splitPositions, $insertBacklink);

    $output .= ($Flowed ? nl2br($renderedText) : $renderedText) .
      (! $Flowed ? "</pre>" : "") . "</div>\n";

    return $fromRest ? $renderedText : $output;
  } // ShowText()

  /**
   * \brief Given a file handle, display a "hex dump" of the file.
   * Output goes to stdout!
   */
  function ShowHex($inputFile, $startOffset = 0, $outputLength = -1,
    $splitPositions = array())
  {
    print $this->getHex($inputFile, $startOffset, $outputLength, $splitPositions);
  }

  /**
   * \brief Given a file handle, display a "hex dump" of the file.
   * Output goes to stdout!
   */
  function getHex($inputFile, $startOffset = 0, $outputLength = -1,
    $splitPositions = array())
  {
    if (! ($outputLength = $this->checkAndPrepare($inputFile, $startOffset,
      $outputLength))) {
      return "";
    }

    $output = "";
    fseek($inputFile, $startOffset, SEEK_SET);
    $textFragment = new TextFragment($startOffset,
      fread($inputFile, $outputLength));

    $output .= "<div class='mono'>";

    $renderedText = $this->textRenderer->renderHex($textFragment,
      $splitPositions);
    $output .= $renderedText;

    $output .= "</div>\n";

    return $output;
  } // ShowHex()

  private function checkAndPrepare($inputFile, $startOffset, $outputLength)
  {
    if (! $inputFile) {
      return False;
    }

    $inputFileStat = fstat($inputFile);
    $inputFileSize = $inputFileStat['size'];

    if ($outputLength < 0) {
      $outputLength = $inputFileSize;
    }

    if (($startOffset < 0) || ($startOffset >= $inputFileSize)) {
      return False;
    }

    if ($outputLength == 0) {
      return false;
    }
    return $outputLength;
  }

  /**
   * \brief Generate the view contents in HTML and sends it
   * to stdout.
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
  function ShowView($inputFile = null, $BackMod = "browse", $ShowMenu = 1, $ShowHeader = 1,
    $ShowText = null, $ViewOnly = false, $DispView = true, $highlightEntries = array(),
    $insertBacklink = false)
  {
    return $this->getView($inputFile, $BackMod, $ShowHeader, $ShowText,
      $highlightEntries, $insertBacklink);
  }

  /**
   * \brief Generate the view contents in HTML
   *
   * @param resource $inputFile
   * @param string $BackMod
   * @param int $ShowHeader
   * @param $ShowText
   * @param Highlight[] $highlightEntries
   * @param bool $insertBacklink
   * @param bool $getPageMenuInline
   *
   * \note This function is intended to be called from other plugins.
   * @return array|string|string[]
   */
  function getView($inputFile = null, $BackMod = "browse", $ShowHeader = 1, $ShowText = null,
    $highlightEntries = array(), $insertBacklink = false, $getPageMenuInline = false)
  {
    if ($this->State != PLUGIN_STATE_READY) {
      $output = "Invalid plugin state: " . $this->State;
      return $getPageMenuInline ? array("Error", $output) : $output;
    }

    $Upload = GetParm("upload", PARM_INTEGER);
    if (! empty($Upload) &&
      ! $this->uploadDao->isAccessible($Upload, Auth::getGroupId())) {
      $output = "Access denied";
      return $getPageMenuInline ? array("Error", $output) : $output;
    }

    $Item = GetParm("item", PARM_INTEGER);
    $Page = GetParm("page", PARM_INTEGER);
    $licenseId = GetParm("licenseId", PARM_INTEGER);
    if (! $inputFile && empty($Item)) {
      $output = "invalid input file";
      return $getPageMenuInline ? array("Error", $output) : $output;
    }

    $uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($Upload);

    if ($ShowHeader) {
      $Uri = Traceback_uri() . "?mod=browse" .
        Traceback_parm_keep(array('item', 'show', 'folder', 'upload'));
      /* No item */
      $header = Dir2Browse($BackMod, $Item, null, $showBox = 0, "View", - 1, '',
        '', $uploadtree_tablename);
      $this->vars['micromenu'] = $header;
    }

    /* Display file contents */
    $output = "";
    $openedFin = False;
    $Format = $this->microMenu->getFormatParameter($Item);
    if (empty($inputFile)) {
      $inputFile = @fopen(RepPathItem($Item), "rb");
      if ($inputFile) {
        $openedFin = true;
      }
      if (empty($inputFile)) {
        $output = $this->outputWhenFileNotInRepo($Upload, $Item);
        return $getPageMenuInline ? array("Error", $output) : $output;
      }
    }
    rewind($inputFile);
    $Uri = preg_replace('/&page=[0-9]*/', '', Traceback());

    $blockSize = $Format == 'hex' ? $this->blockSizeHex : $this->blockSizeText;

    if (! isset($Page) && ! empty($licenseId)) {
      $startPos = - 1;
      foreach ($highlightEntries as $highlightEntry) {
        if ($highlightEntry->getLicenseId() == $licenseId &&
          ($startPos == - 1 || $startPos > $highlightEntry->getStart())) {
          $startPos = $highlightEntry->getStart();
        }
      }
      if ($startPos != - 1) {
        $Page = floor($startPos / $blockSize);
      }
    }

    if (! empty($ShowText)) {
      echo $ShowText, "<hr>";
    }
    $PageMenu = $this->GetFileJumpMenu($inputFile, $Page, $blockSize, $Uri);
    $PageSize = $blockSize * $Page;
    if (! empty($PageMenu) and ! $getPageMenuInline) {
      $output .= "<center>$PageMenu</center><br>\n";
    }

    $startAt = $PageSize;
    $endAt = $PageSize + $blockSize;
    $relevantHighlightEntries = array();
    foreach ($highlightEntries as $highlightEntry) {
      if ($highlightEntry->getStart() < $endAt &&
        $highlightEntry->getEnd() >= $startAt) {
        $relevantHighlightEntries[] = $highlightEntry;
      }
    }

    $this->highlightProcessor->sortHighlights($relevantHighlightEntries);

    $splitPositions = $this->highlightProcessor->calculateSplitPositions(
      $relevantHighlightEntries);

    if ($Format == 'hex') {
      $output .= $this->getHex($inputFile, $PageSize, $this->blockSizeHex,
        $splitPositions);
    } else {
      $output .= $this->getText($inputFile, $PageSize, $Format == 'text' ? 0 : 1,
        $this->blockSizeText, $splitPositions, $insertBacklink);
    }

    if (! empty($PageMenu) and ! $getPageMenuInline) {
      $output .= "<P /><center>$PageMenu</center><br>\n";
    }

    if ($openedFin) {
      fclose($inputFile);
    }

    return $getPageMenuInline ? array($PageMenu, $output) : $output;
  }

  /*
   * Added by vincent implement when view files which not in repository, ask
   * user if want to reunpack
   */
  protected function outputWhenFileNotInRepo($uploadpk, $item)
  {
    global $Plugins;
    $reunpackPlugin = & $Plugins[plugin_find_id("ui_reunpack")];
    $state = $reunpackPlugin->CheckStatus($uploadpk, "reunpack", "ununpack");

    /* If this is a POST, then process the request. */
    $uploadunpack = GetParm('uploadunpack', PARM_INTEGER);
    $flag = 0;
    $output = '';

    if ($state != 0 && $state != 2) {
      $flag = 1;
      $text = _("Reunpack job is running: you can see it in");
      $text1 = _("jobqueue");
      $output .= "<p> <font color=red>$text <a href='" . Traceback_uri() .
        "?mod=showjobs'>$text1</a></font></p>";
    } elseif (! empty($uploadunpack)) {
      $rc = $reunpackPlugin->AgentAdd($uploadpk);
      if (empty($rc)) {
        /* Need to refresh the screen */
        $this->vars['message'] = _("Unpack added to job queue");
        $flag = 1;
        $text = _("Reunpack job is running: you can see it in");
        $text1 = _("jobqueue");
        $output .= "<p> <font color=red>$text <a href='" . Traceback_uri() .
          "?mod=showjobs'>$text1</a></font></p>";
      } else {
        $text = _("Unpack of Upload failed");
        $this->vars['message'] = "$text: $rc";
      }
    }

    $text = _("File contents are not available in the repository.");
    $output .= "$text\n";
    $output .= $reunpackPlugin->ShowReunpackView($item, $flag);
    return $output;
  }

  public function Output()
  {
    return $this->ShowView(null, "browse");
  }
}

$NewPlugin = new ui_view();
$NewPlugin->Initialize();
