<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Highlight;

/**
 * \file
 * \brief Run an analysis for a single file, do not store results in the DB.
 */

define("TITLE_AGENT_NOMOS_ONCE", _("One-Shot License Analysis"));

/**
 * @class agent_nomos_once
 * @brief Class to run one-shot nomos
 */
class agent_nomos_once extends FO_Plugin
{

  const FILE_INPUT = 'file_input';          ///< Resource key for input file
  public $HighlightInfoKeywords = array();  ///< Highlight info for keywords
  public $HighlightInfoLicenses = array();  ///< Highlight info for licenses
  function __construct()
  {
    $this->Name = "agent_nomos_once";
    $this->Title = TITLE_AGENT_NOMOS_ONCE;
    $this->Dependency = array();
    $this->NoHTML = 0; // always print text output for now
    /* For anyone to access, without login, use: */
    $this->DBaccess = PLUGIN_DB_READ;
    /* To require login access, use: */
    // public $DBaccess = PLUGIN_DB_WRITE;
    // public $LoginFlag = 1;
    $this->LoginFlag = 0;
    parent::__construct();
  }

  /**
   * @brief Analyze one uploaded file.
   * @param string $FilePath the filepath to the file to analyze.
   * @return mixed If $getHighlightInfo is true, returns an array with display html, keyword highlights, and license highlights. If false, returns only display html
   */
  public function AnalyzeFile($FilePath, $getHighlightInfo = false)
  {
    global $SYSCONFDIR;

    exec("$SYSCONFDIR/mods-enabled/nomos/agent/nomos -S $FilePath", $out, $rtn);
    $licensesFromAgent = explode('contains license(s)', $out[0]);
    $licenses_and_Highlight = end($licensesFromAgent);
    $licenses = explode('Highlighting Info at', $licenses_and_Highlight);

    preg_match_all('/Keyword at (?P<position>\d+), length (?P<length>\d+),/',
      $licenses[1], $this->HighlightInfoKeywords);
    preg_match_all(
      '/License #(?P<name>[^#]*)# at (?P<position>\d+), length (?P<length>\d+),/',
      $licenses[1], $this->HighlightInfoLicenses);

    if ($getHighlightInfo) {
      return array($licenses[0], $this->HighlightInfoKeywords, $this->HighlightInfoLicenses);
    }
    return ($licenses[0]);
  }

  // AnalyzeFile()
  /**
   * @copydoc FO_Plugin::Install()
   * @see FO_Plugin::Install()
   */
  function Install()
  {
    global $PG_CONN;
    if (empty($PG_CONN)) {
      return (1);
    } else {
      return (0);
    }
  }

  /**
   * \brief Change the type of output
   * based on user-supplied parameters.
   *
   * \return 1 on success.
   * \see FO_Plugin::RegisterMenus()
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return 0;
    }
    $ShowHeader = GetParm('showheader', PARM_INTEGER);
    if (empty($ShowHeader)) {
      $ShowHeader = 0;
    }
    $ThisMod = (GetParm("mod", PARM_STRING) == $this->Name) ? 1 : 0;
    /*
     * This if stmt is true only for wget.
     * For wget, populate the $_FILES array, just like the UI post would do.
     * Sets the unlink_flag if there is a temp file.
     */
    if ($ThisMod && empty($_POST['showheader']) &&
      ($_SERVER['REQUEST_METHOD'] == "POST")) {
      $Fin = fopen("php://input", "r");
      $Ftmp = tempnam(NULL, "fosslic-alo-");
      $Fout = fopen($Ftmp, "w");
      while (! feof($Fin)) {
        $Line = fgets($Fin);
        fwrite($Fout, $Line);
      }
      fclose($Fin);
      fclose($Fout);

      /*
       * Populate _FILES from wget so the processing logic only has to look in
       * one
       * place wether the data came from wget or the UI
       */
      if (filesize($Ftmp) > 0) {
        $_FILES['licfile']['tmp_name'] = $Ftmp;
        $_FILES['licfile']['size'] = filesize($Ftmp);
        $_FILES['licfile']['unlink_flag'] = 1;
        $this->NoHTML = 1;
      } else {
        unlink($Ftmp);
        /*
         * If there is no input data, then something is wrong.
         * For example the php POST limit is too low and prevented
         * the data from coming through. Or there was an apache redirect,
         * which removes the POST data.
         */
        $tooltipText = _(
          "FATAL: your file did not get passed throught.  Make sure this page wasn't a result of a web server redirect, or that it didn't exceed your php POST limit.");
        echo $tooltipText;
      }
    }

    /* Only register with the menu system if the user is logged in. */
    if (! empty($_SESSION[Auth::USER_NAME])) {
      if (array_key_exists(Auth::USER_LEVEL, $_SESSION) &&
        $_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_WRITE) {
        menu_insert("Main::Upload::One-Shot Nomos Analysis", $this->MenuOrder,
          $this->Name, $this->MenuTarget);
      }
    }
  }

  // RegisterMenus()

  /**
   * \brief Generate the text for this plugin.
   * \see FO_Plugin::Output()
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    $tmp_name = '';
    if (array_key_exists(self::FILE_INPUT, $_FILES) &&
      array_key_exists('tmp_name', $_FILES[self::FILE_INPUT])) {
      $tmp_name = $_FILES[self::FILE_INPUT]['tmp_name'];
    }

    /*
     * For REST API:
     * wget -qO - --post-file=myfile.c http://myserv.com/?mod=agent_nomos_once
     */
    if ($this->OutputType != 'HTML' && file_exists($tmp_name)) {
      echo $this->AnalyzeFile($tmp_name) . "\n";
      unlink($tmp_name);
      return;
    }
    if (file_exists($tmp_name)) {
      $this->vars['content'] = $this->htmlAnalyzedContent($tmp_name,
        $_FILES[self::FILE_INPUT]['name']);
    } else if ($this->OutputType == 'HTML') {
      return $this->render('oneshot-upload.html.twig', $this->vars);
    }
    if (array_key_exists('licfile', $_FILES) &&
      array_key_exists('unlink_flag', $_FILES['licfile'])) {
      unlink($tmp_name);
    }
    unset($_FILES[self::FILE_INPUT]);
    $this->vars['styles'] .= "<link rel='stylesheet' href='css/highlights.css'>\n";
    return $this->render($this->getTemplateName(), $this->vars);
  }

  /**
   * @brief Create the HTML for the one-shot UI
   * @param string $tmp_name Temporary file location (by server)
   * @param string $filename Actual file name
   * @return string HTML
   */
  private function htmlAnalyzedContent($tmp_name, $filename)
  {
    $text = _(
      "A one shot license analysis shows the following license(s) in file");
    $keep = "$text <em>$filename:</em> ";
    $keep .= "<strong>" . $this->AnalyzeFile($tmp_name) . "</strong><br>";
    $this->vars['message'] = $keep;

    global $Plugins;
    /** @var ui_view $view */
    $view = & $Plugins[plugin_find_id("view")];
    $ModBack = GetParm("modback", PARM_STRING);

    $highlights = array();

    for ($index = 0; $index < count($this->HighlightInfoKeywords['position']); $index ++) {
      $position = $this->HighlightInfoKeywords['position'][$index];
      $length = $this->HighlightInfoKeywords['length'][$index];

      $highlights[] = new Highlight($position, $position + $length,
        Highlight::KEYWORD);
    }

    for ($index = 0; $index < count($this->HighlightInfoLicenses['position']); $index ++) {
      $position = $this->HighlightInfoLicenses['position'][$index];
      $length = $this->HighlightInfoLicenses['length'][$index];
      $name = $this->HighlightInfoLicenses['name'][$index];

      $highlights[] = new Highlight($position, $position + $length,
        Highlight::SIGNATURE, $name);
    }

    $inputFile = fopen($tmp_name, "r");
    if ($inputFile) {
      $rtn = $view->getView($inputFile, $ModBack, 0, NULL, $highlights);
      fclose($inputFile);
      return $rtn;
    }
  }
}

$NewPlugin = new agent_nomos_once();
$NewPlugin->Install();
