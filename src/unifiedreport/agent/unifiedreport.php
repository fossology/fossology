<?php
/*
 Author: Shaheem Azmal, anupam.ghosh@siemens.com
 SPDX-FileCopyrightText: © 2017-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Source for Unified report agent
 * @file
 * @brief Source for Unified report agent
 * @page unifiedreport Unified report agent
 * @tableofcontents
 * @section unifiedreportabout About Unified report agent
 * Unified report agent generates a `.docx`. The document follows
 * following pattern.
 * - Clearing information (department, report date, etc.)
 * - Component information (version, hash, main license, fossology link, etc.)
 * -# Assessment Summary
 *     Contains information like source notes, dependency notes, ECC, etc.
 * -# Required license compliance tasks
 *     -# Common obligations, restrictions and risks
 *     -# Additional obligations, restrictions & risks beyond common rules
 * -# Acknowledgements
 *     Every acknowledgement entered by the user during clearing.
 * -# Export Restrictions
 *     Contains findings of ECC.
 * -# Notes
 *     -# Notes on individual files
 *
 *         Comments added during clearing
 * -# Results of License Scan
 *
 *     Count of agent findings, concluded license and corresponding license name.
 * -# Main Licenses
 *
 *     List of License name, license text and file path for every global/main
 *     license marked during clearing.
 * -# Other OSS Licenses (Red)
 *
 *     Licenses which should be avoided (risk level 4-5)
 * -# Other OSS Licenses (Yellow)
 *
 *     Licenses with limited rules (risk level 2-3)
 * -# Other OSS Licenses (White)
 *
 *     Common licenses (risk level 0-1)
 * -# Overview of all licenses
 *
 *     List of licenses found with obligation
 * -# Copyrights
 *
 *     List of copyright statements, comments and file path.
 * -# Bulk findings
 *
 *     Monk bulk findings
 * -# Non Functional Licenses
 *
 *     Licenses which are not applicable on binary.
 * -# Irrelevant Files
 *
 *     Files marked as irrelevant during clearing
 *     -# Comment for irrelevant files
 * -# Clearing Protocol Change Log
 *
 *     Organization specific.
 *
 * Along with all this information, every page contains the header set using
 * `Report Header Text` under Configuration Variables. And the footer contains
 * organization name, report generation timestamp, FOSSology version used and
 * page number.
 *
 * @section unifiedreportactions Supported actions
 * Currently, unified report agent does not support CLI commands and read only
 * from scheduler.
 *
 * @section unifiedreportsource Agent source
 *   - @link src/unifiedreport/agent @endlink
 *   - @link src/unifiedreport/ui @endlink
 *   - Functional test cases \link src/unifiedreport/agent_tests/Functional @endlink
 */

/**
 * @var string REPORT_AGENT_NAME
 * Agent name
 */
define("REPORT_AGENT_NAME", "unifiedreport");

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Report\BulkMatchesGetter;
use Fossology\Lib\Report\LicenseClearedGetter;
use Fossology\Lib\Report\LicenseDNUGetter;
use Fossology\Lib\Report\LicenseIrrelevantGetter;
use Fossology\Lib\Report\LicenseMainGetter;
use Fossology\Lib\Report\LicenseNonFunctionalGetter;
use Fossology\Lib\Report\ObligationsGetter;
use Fossology\Lib\Report\OtherGetter;
use Fossology\Lib\Report\XpClearedGetter;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table;

include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/reportStatic.php");
include_once(__DIR__ . "/reportSummary.php");

/**
 * @class UnifiedReport
 * @brief Generates unified report
 */
class UnifiedReport extends Agent
{
  /** @var LicenseClearedGetter $licenseClearedGetter
   * LicenseClearedGetter object
   */
  private $licenseClearedGetter;

  /** @var LicenseMainGetter $licenseMainGetter
   * LicenseMainGetter object
   */
  private $licenseMainGetter;

  /** @var XpClearedGetter $cpClearedGetter
   * Copyright clearance object
   */
  private $cpClearedGetter;


  /** @var XpClearedGetter $ipraClearedGetter
   * IP clearance object
   */
  private $ipraClearedGetter;

  /** @var XpClearedGetter $eccClearedGetter
   * ECC clearance object
   */
  private $eccClearedGetter;

  /** @var LicenseIrrelevantGetter $licenseIrrelevantGetter
   * LicenseIrrelevantGetter object
   */
  private $licenseIrrelevantGetter;

  /** @var LicenseDNUGetter $licenseDNUGetter
   * LicenseDNUGetter object
   */
  private $licenseDNUGetter;

  /** @var LicenseNonFunctionalGetter $licenseNonFunctionalGetter
   * LicenseNonFunctionalGetter object
   */
  private $licenseNonFunctionalGetter;

  /** @var BulkMatchesGetter $bulkMatchesGetter
   * BulkMatchesGetter object
   */
  private $bulkMatchesGetter;

  /** @var LicenseIrrelevantGetter $licenseIrrelevantCommentGetter
   * licenseIrrelevantCommentGetter object
   */
  private $licenseIrrelevantCommentGetter;

  /** @var ObligationsGetter $obligationsGetter
   * obligationsGetter object
   */
  private $obligationsGetter;

  /** @var OtherGetter $otherGetter
   * otherGetter object
   */
  private $otherGetter;

  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /** @var UserDao $userDao
   * UserDao object
   */
  private $userDao;

  /** @var int $rowHeight
   * Row height for table
   */
  private $rowHeight = 500;

  /** @var array $tablestyle
   * Table style attributes
   */
  private $tablestyle = array("borderSize" => 2,
                              "name" => "Arial",
                              "borderColor" => "000000",
                              "cellSpacing" => 5,
                              "alignment"   => JcTable::START,
                              "layout"      => Table::LAYOUT_FIXED
                             );

  /** @var array $subHeadingStyle
   * Sub heading style attributes
   */
  private $subHeadingStyle = array("size" => 9,
                                   "align" => "center",
                                   "bold" => true
                                  );

  /** @var array $licenseColumn
   * License column style attributes
   */
  private $licenseColumn = array("size" => "9",
                                 "bold" => true
                                );

  /** @var array $licenseTextColumn
   * License column text style attributes
   */
  private $licenseTextColumn = array("name" => "Courier New",
                                     "size" => 9,
                                     "bold" => false
                                    );

  /** @var array $filePathColumn
   * File path column style attributes
   */
  private $filePathColumn = array("size" => "9",
                                  "bold" => false
                                 );

  /** @var string $groupBy
   * @todo Unused variable
   */
  private $groupBy;

  function __construct()
  {
    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement");
    $this->ipraClearedGetter = new XpClearedGetter("ipra", "ipra");
    $this->eccClearedGetter = new XpClearedGetter("ecc", "ecc");
    $this->licenseClearedGetter = new LicenseClearedGetter();
    $this->licenseMainGetter = new LicenseMainGetter();
    $this->bulkMatchesGetter = new BulkMatchesGetter();
    $this->licenseIrrelevantGetter = new LicenseIrrelevantGetter();
    $this->licenseIrrelevantCommentGetter = new LicenseIrrelevantGetter(false);
    $this->licenseDNUGetter = new LicenseDNUGetter();
    $this->licenseDNUCommentGetter = new LicenseDNUGetter(false);
    $this->licenseNonFunctionalGetter = new LicenseNonFunctionalGetter();
    $this->licenseNonFunctionalCommentGetter = new LicenseNonFunctionalGetter(false);
    $this->otherGetter = new OtherGetter();
    $this->obligationsGetter = new ObligationsGetter();

    parent::__construct(REPORT_AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get("dao.upload");
    $this->userDao = $this->container->get("dao.user");
  }


  /**
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $groupId = $this->groupId;
    $userId = $this->userId;

    $this->heartbeat(0);

    $licenses = $this->licenseClearedGetter->getCleared($uploadId, $this, $groupId, true, "license", false);
    $this->heartbeat(empty($licenses) ? 0 : count($licenses["statements"]));

    $licensesMain = $this->licenseMainGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licensesMain) ? 0 : count($licensesMain["statements"]));

    $licensesHist = $this->licenseClearedGetter->getLicenseHistogramForReport($uploadId, $groupId);
    $this->heartbeat(empty($licensesHist) ? 0 : count($licensesHist["statements"]));

    $bulkLicenses = $this->bulkMatchesGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($bulkLicenses) ? 0 : count($bulkLicenses["statements"]));

    $this->licenseClearedGetter->setOnlyAcknowledgements(true);
    $licenseAcknowledgements = $this->licenseClearedGetter->getCleared($uploadId, $this, $groupId, true, "license", false);
    $this->heartbeat(empty($licenseAcknowledgements) ? 0 : count($licenseAcknowledgements["statements"]));

    $this->licenseClearedGetter->setOnlyComments(true);
    $licenseComments = $this->licenseClearedGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licenseComments) ? 0 : count($licenseComments["statements"]));

    $licensesIrre = $this->licenseIrrelevantGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licensesIrre) ? 0 : count($licensesIrre["statements"]));

    $licensesIrreComment = $this->licenseIrrelevantCommentGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licensesIrreComment) ? 0 : count($licensesIrreComment["statements"]));

    $licensesDNU = $this->licenseDNUGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licensesDNU) ? 0 : count($licensesDNU["statements"]));

    $licensesDNUComment = $this->licenseDNUCommentGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licensesDNUComment) ? 0 : count($licensesDNUComment["statements"]));

    $licensesNonFunctional = $this->licenseNonFunctionalGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licensesNonFunctional) ? 0 : count($licensesNonFunctional["statements"]));

    $licensesNonFunctionalComment = $this->licenseNonFunctionalCommentGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licensesNonFunctionalComment) ? 0 : count($licensesNonFunctionalComment["statements"]));

    $copyrights = $this->cpClearedGetter->getCleared($uploadId, $this, $groupId, true, "copyright", true);
    $this->heartbeat(empty($copyrights["scannerFindings"]) ? 0 : count($copyrights["scannerFindings"]) + count($copyrights["userFindings"]));

    $ecc = $this->eccClearedGetter->getCleared($uploadId, $this, $groupId, true, "ecc", false);
    $this->heartbeat(empty($ecc) ? 0 : count($ecc["statements"]));

    $ipra = $this->ipraClearedGetter->getCleared($uploadId, $this, $groupId, true, "ipra", false);
    $this->heartbeat(empty($ip) ? 0 : count($ip["statements"]));

    $otherStatement = $this->otherGetter->getReportData($uploadId);
    $this->heartbeat(empty($otherStatement) ? 0 : count($otherStatement));
    $otherStatement['includeDNU'] = (count($licensesDNU["statements"]) > 0) ? true : false;
    $otherStatement['includeNonFunctional'] = (count($licensesNonFunctional["statements"]) > 0) ? true : false;

    $contents = array(
                        "licenses" => $licenses,
                        "bulkLicenses" => $bulkLicenses,
                        "licenseAcknowledgements" => $licenseAcknowledgements,
                        "licenseComments" => $licenseComments,
                        "copyrights" => $copyrights,
                        "ecc" => $ecc,
                        "ipra" => $ipra,
                        "licensesIrre" => $licensesIrre,
                        "licensesIrreComment" => $licensesIrreComment,
                        "licensesDNU" => $licensesDNU,
                        "licensesDNUComment" => $licensesDNUComment,
                        "licensesNonFunctional" => $licensesNonFunctional,
                        "licensesNonFunctionalComment" => $licensesNonFunctionalComment,
                        "licensesMain" => $licensesMain,
                        "licensesHist" => $licensesHist,
                        "otherStatement" => $otherStatement
                     );

    $this->writeReport($contents, $uploadId, $groupId, $userId);
    return true;
  }

  /**
   * @brief Setting default heading styles and paragraph styles
   * @param[in,out] PhpWord &$phpWord PhpWord object
   * @param int $timestamp            Report gen timestamp
   * @param string $userName          User generating the report
   */
  private function documentSettingsAndStyles(PhpWord &$phpWord, $timestamp, $userName)
  {

    $topHeading = array("size" => 22,
                        "bold" => true,
                        "underline" => "single"
                       );

    $mainHeading = array("size" => 20,
                         "bold" => true,
                         "color" => "000000"
                        );

    $subHeading = array("size" => 16,
                        "italic" => true
                       );

    $subSubHeading = array("size" => 14,
                           "bold" => true
                          );

    $paragraphStyle = array("spaceAfter" => 0,
                            "spaceBefore" => 0,
                            "spacing" => 0
                           );

    $phpWord->addNumberingStyle('hNum',
        array('type' => 'multilevel', 'levels' => array(
            array('pStyle' => 'Heading01', 'format' => 'bullet', 'text' => ''),
            array('pStyle' => 'Heading2', 'format' => 'decimal', 'text' => '%2.'),
            array('pStyle' => 'Heading3', 'format' => 'decimal', 'text' => '%2.%3.'),
            array('pStyle' => 'Heading4', 'format' => 'decimal', 'text' => '%2.%3.%4.'),
            )
        )
    );

    /* Adding styles for the document*/
    $phpWord->setDefaultFontName("Arial");
    $phpWord->addTitleStyle(1, $topHeading, array('numStyle' => 'hNum', 'numLevel' => 0));
    $phpWord->addTitleStyle(2, $mainHeading, array('numStyle' => 'hNum', 'numLevel' => 1));
    $phpWord->addTitleStyle(3, $subHeading, array('numStyle' => 'hNum', 'numLevel' => 2));
    $phpWord->addTitleStyle(4, $subSubHeading, array('numStyle' => 'hNum', 'numLevel' => 3));
    $phpWord->addParagraphStyle("pStyle", $paragraphStyle);

    /* Setting document properties*/
    $properties = $phpWord->getDocInfo();
    $properties->setCreator($userName);
    $properties->setCompany("Your Organisation");
    $properties->setTitle("Clearing Report");
    $properties->setDescription("OSS clearing report by Fossology tool");
    $properties->setSubject("Copyright (C) ".date("Y", $timestamp).", Your Organisation");
  }

  /**
   * @brief Generate global license table
   * @param Section $section
   * @param array $mainLicenses
   * @param array $titleSubHeading
   * @param $heading
   */
  private function globalLicenseTable(Section $section, $mainLicenses, $titleSubHeading, $heading)
  {
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;

    $section->addTitle(htmlspecialchars($heading), 2);
    $section->addText($titleSubHeading, $this->subHeadingStyle);

    $table = $section->addTable($this->tablestyle);
    if (!empty($mainLicenses)) {
      foreach ($mainLicenses as $licenseMain) {
        if ($licenseMain["risk"] == "4" || $licenseMain["risk"] == "5") {
          $styleColumn = array("bgColor" => "F9A7B0");
        } elseif ($licenseMain["risk"] == "2" || $licenseMain["risk"] == "3") {
          $styleColumn = array("bgColor" => "FEFF99");
        } else {
          $styleColumn = array("bgColor" => "FFFFFF");
        }
        $table->addRow($this->rowHeight);
        $cell1 = $table->addCell($firstColLen, $styleColumn);
        $cell1->addText(htmlspecialchars($licenseMain["content"], ENT_DISALLOWED), $this->licenseColumn, "pStyle");
        $cell2 = $table->addCell($secondColLen);
        // replace new line character
        $licenseText = str_replace("\n", "</w:t>\n<w:br />\n<w:t xml:space=\"preserve\">", htmlspecialchars($licenseMain["text"], ENT_DISALLOWED));
        $licenseText = str_replace("\r", "", $licenseText);
        $cell2->addText($licenseText, $this->licenseTextColumn, "pStyle");
        if (!empty($licenseMain["files"])) {
          $cell3 = $table->addCell($thirdColLen, $styleColumn);
          asort($licenseMain["files"]);
          foreach ($licenseMain["files"] as $fileName) {
            $cell3->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
          }
        } else {
          $cell3 = $table->addCell($thirdColLen, $styleColumn)->addText("");
        }
      }
    } else {
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText("");
      $table->addCell($secondColLen)->addText("");
      $table->addCell($thirdColLen)->addText("");
    }
    $section->addTextBreak();
  }

  /**
   * @brief This function lists out the bulk licenses,
   * comments of identified licenses
   * @param Section $section
   * @param string $title
   * @param array $licenses
   * @param array $titleSubHeading
   */
  private function bulkLicenseTable(Section $section, $title, $licenses, $titleSubHeading)
  {
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;
    if (!empty($title)) {
      $section->addTitle(htmlspecialchars($title), 2);
    }
    $section->addText($titleSubHeading, $this->subHeadingStyle);

    $table = $section->addTable($this->tablestyle);
    if (!empty($licenses)) {
      foreach ($licenses as $licenseStatement) {
        $table->addRow($this->rowHeight);
        $cell1 = $table->addCell($firstColLen, "pStyle");
        $cell1->addText(htmlspecialchars($licenseStatement["content"], ENT_DISALLOWED), $this->licenseColumn, "pStyle");
        $cell2 = $table->addCell($secondColLen, "pStyle");
        // replace new line character
        $licenseText = str_replace("\n", "</w:t>\n<w:br />\n<w:t xml:space=\"preserve\">", htmlspecialchars($licenseStatement["text"], ENT_DISALLOWED));
        $licenseText = str_replace("\r", "", $licenseText);
        $cell2->addText($licenseText, $this->licenseTextColumn, "pStyle");
        $cell3 = $table->addCell($thirdColLen, "pStyle");
        asort($licenseStatement["files"]);
        foreach ($licenseStatement["files"] as $fileName) {
          $cell3->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
        }
      }
    } else {
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText("");
      $table->addCell($secondColLen)->addText("");
      $table->addCell($thirdColLen)->addText("");
    }
    $section->addTextBreak();
  }

  /**
   * @brief This function lists out the red, white & yellow licenses
   * @param Section $section
   * @param string $title
   * @param array $licenses
   * @param array $riskarray
   * @param array $titleSubHeading
   */
  private function licensesTable(Section $section, $title, $licenses, $riskarray, $titleSubHeading)
  {
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;
    $emptyFlag = false;

    $section->addTitle(htmlspecialchars($title), 2);
    $section->addText($titleSubHeading, $this->subHeadingStyle);

    $table = $section->addTable($this->tablestyle);
    if (!empty($licenses)) {
      foreach ($licenses as $licenseStatement) {
        if (in_array($licenseStatement['risk'], $riskarray['riskLevel'])) {
          $emptyFlag = true;
          $table->addRow($this->rowHeight);
          $cell1 = $table->addCell($firstColLen, $riskarray['color']);
          $cell1->addText(htmlspecialchars($licenseStatement["content"], ENT_DISALLOWED), $this->licenseColumn, "pStyle");
          $cell2 = $table->addCell($secondColLen);
          // replace new line character
          $licenseText = str_replace("\n", "</w:t>\n<w:br />\n<w:t xml:space=\"preserve\">", htmlspecialchars($licenseStatement["text"], ENT_DISALLOWED));
          $licenseText = str_replace("\r", "", $licenseText);
          $cell2->addText($licenseText, $this->licenseTextColumn, "pStyle");
          $cell3 = $table->addCell($thirdColLen, $riskarray['color']);
          asort($licenseStatement["files"]);
          foreach ($licenseStatement["files"] as $fileName) {
            $cell3->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
          }
        } else {
          continue;
        }
      }
    }

    if (empty($emptyFlag)) {
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText("");
      $table->addCell($secondColLen)->addText("");
      $table->addCell($thirdColLen)->addText("");
    }
    $section->addTextBreak();
  }

  /**
   * @brief Copyright or ecc table.
   * @param Section $section
   * @param string $title
   * @param array $statementsCEI
   * @param array $titleSubHeading
   * @param string $text
   */
  private function getRowsAndColumnsForCEI(Section $section, $title, $statementsCEI, $titleSubHeading, $text="")
  {
    $smallRowHeight = 50;
    $firstColLen = 6500;
    $secondColLen = 5000;
    $thirdColLen = 4000;
    $textStyle = array("size" => 10, "bold" => true);

    $section->addTitle(htmlspecialchars($title), 2);
    if (!empty($text)) {
      $section->addText($text, $textStyle);
    }
    $section->addText($titleSubHeading, $this->subHeadingStyle);

    $table = $section->addTable($this->tablestyle);
    if (!empty($statementsCEI)) {
      foreach ($statementsCEI as $statements) {
        if (!empty($statements['content'])) {
          $table->addRow($smallRowHeight);
          $cell1 = $table->addCell($firstColLen);
          $text = html_entity_decode($statements['content']);
          $cell1->addText(htmlspecialchars($text, ENT_DISALLOWED), $this->licenseTextColumn, "pStyle");
          $cell2 = $table->addCell($secondColLen);
          $cell2->addText(htmlspecialchars($statements['comments'], ENT_DISALLOWED), $this->licenseTextColumn, "pStyle");
          $cell3 = $table->addCell($thirdColLen);
          asort($statements["files"]);
          foreach ($statements['files'] as $fileName) {
            $cell3->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
          }
        }
      }
    } else {
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText("");
      $table->addCell($secondColLen)->addText("");
      $table->addCell($thirdColLen)->addText("");
    }
    $section->addTextBreak();
  }

  /**
   * @brief Irrelevant files in report.
   * @param Section $section
   * @param string $title
   * @param array $licensesIrre
   * @param array $titleSubHeading
   */
  private function getRowsAndColumnsForIrre(Section $section, $title, $licensesIrre, $titleSubHeading)
  {
    $firstColLen = 5000;
    $secondColLen = 5000;
    $thirdColLen = 5000;
    $rowWidth = 200;

    $section->addTitle(htmlspecialchars($title), 2);
    $section->addText($titleSubHeading, $this->subHeadingStyle);

    $table = $section->addTable($this->tablestyle);
    if (!empty($licensesIrre)) {
      foreach ($licensesIrre as $statements) {
        $table->addRow($rowWidth, "pStyle");
        $cell1 = $table->addCell($firstColLen)->addText(htmlspecialchars($statements['content']),null, "pStyle");
        $cell2 = $table->addCell($secondColLen)->addText(htmlspecialchars($statements['fileName']),null, "pStyle");
        $cell3 = $table->addCell($thirdColLen);
        asort($statements["licenses"]);
        foreach ($statements['licenses'] as $licenseName) {
          $cell3->addText(htmlspecialchars($licenseName), $this->filePathColumn, "pStyle");
        }
      }
    } else {
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen, "pStyle")->addText("");
      $table->addCell($secondColLen, "pStyle")->addText("");
      $table->addCell($thirdColLen, "pStyle")->addText("");
    }
    $section->addTextBreak();
  }

  /**
   * @brief License histogram into report.
   * @param Section $section
   * @param array $dataHistogram
   * @param array $titleSubHeading
   * @param $heading
   */
  private function licenseHistogram(Section $section, $dataHistogram, $titleSubHeading, $heading)
  {
    $firstColLen = 2000;
    $secondColLen = 2000;
    $thirdColLen = 5000;

    $section->addTitle(htmlspecialchars($heading), 2);
    $section->addText($titleSubHeading, $this->subHeadingStyle);

    $table = $section->addTable($this->tablestyle);

    foreach ($dataHistogram as $licenseData) {
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText($licenseData['scannerCount'], "pStyle");
      $table->addCell($secondColLen)->addText($licenseData['editedCount'], "pStyle");
      $table->addCell($thirdColLen)->addText(htmlspecialchars($licenseData['licenseShortname']), "pStyle");
    }
    $section->addTextBreak();
  }


  /**
   * @brief Writes the report to a file
   *
   * The file name is of format `<packageName>_clearing_report_<D_M_d_m_Y_h_i_s>.docx`.
   *
   * The docx format used is Word2007.
   * @param array $contents
   * @param int $uploadId
   * @param int $groupId
   * @param int $userId
   */
  private function writeReport($contents, $uploadId, $groupId, $userId)
  {
    global $SysConf;

    $userName = $this->userDao->getUserName($userId);
    $groupName = $this->userDao->getGroupNameById($groupId);
    $packageName = $this->uploadDao->getUpload($uploadId)->getFilename();
    //replace '(',')',' ' with '_' to avoid conflict while creating report.
    $packageName = str_replace('(','_',$packageName);
    $packageName = str_replace(' ','_',$packageName);
    $packageName = str_replace(')','_',$packageName);

    $parentItem = $this->uploadDao->getParentItemBounds($uploadId);
    $docLayout = array("orientation" => "landscape",
                       "marginLeft" => "950",
                       "marginRight" => "950",
                       "marginTop" => "950",
                       "marginBottom" => "950"
                      );

    /* Creating the new DOCX */
    $phpWord = new PhpWord();

    /* Get start time */
    $jobInfo = $this->dbManager->getSingleRow("SELECT extract(epoch FROM jq_starttime) "
              ." AS ts, jq_cmd_args FROM jobqueue WHERE jq_job_fk=$1", array($this->jobId));
    $timestamp = $jobInfo['ts'];
    $packageUri = "";
    if (!empty($jobInfo['jq_cmd_args'])) {
      $packageUri = trim($jobInfo['jq_cmd_args']);
      $packageUri = preg_replace("/api\/.*/i", "", $packageUri); // Remove api/v1/report
      $packageUri .= "?mod=showjobs&upload=" . $uploadId;
    }

    /* Applying document properties and styling */
    $this->documentSettingsAndStyles($phpWord, $timestamp, $userName);

    /* Creating document layout */
    $section = $phpWord->addSection($docLayout);

    $reportSummarySection = new ReportSummary();
    $reportStaticSection = new ReportStatic($timestamp);

    list($obligations, $whiteLists) = $this->obligationsGetter->getObligations($contents['licenses']['statements'],
      $contents['licensesMain']['statements'], $uploadId, $groupId);

    /* Header starts */
    $reportStaticSection->reportHeader($section);

    list($contents['licensesMain']['statements'], $contents['licenses']['statements']) = $this->licenseClearedGetter->updateIdentifiedGlobalLicenses($contents['licensesMain']['statements'], $contents['licenses']['statements']);

    /* Summery table */
    $assignedToUserId = $this->uploadDao->getAssignee($uploadId, $groupId);
    if ($assignedToUserId != 1) {
      $assignedToUserName = $this->userDao->getUserName($assignedToUserId);
    } else {
      $assignedToUserName = "";
    }
    $reportSummarySection->summaryTable($section, $uploadId, $userName,
      $contents['licensesMain']['statements'], $contents['licenses']['statements'],
      $contents['licensesHist']['statements'], $contents['otherStatement'], $timestamp, $groupName, $packageUri, $assignedToUserName);

    if (!empty($contents['otherStatement']['ri_unifiedcolumns'])) {
      $unifiedColumns = (array) json_decode($contents['otherStatement']['ri_unifiedcolumns'], true);
    } else {
      $unifiedColumns = UploadDao::UNIFIED_REPORT_HEADINGS;
    }

    $heading = array_keys($unifiedColumns['assessment'])[0];
    $isEnabled = array_values($unifiedColumns['assessment'])[0];
    if ($isEnabled) {
      /* Assessment summery table */
      $bookMarkCell = $reportStaticSection->assessmentSummaryTable($section, $contents['otherStatement'], $heading);
    }

    $heading = array_keys($unifiedColumns['compliancetasks'])[0];
    $isEnabled = array_values($unifiedColumns['compliancetasks'])[0];
    if ($isEnabled) {
      /* Todoinfo table */
      $reportStaticSection->todoTable($section, $heading);
      /* Todoobligation table */
      $reportStaticSection->todoObliTable($section, $obligations);
    }

    $heading = array_keys($unifiedColumns['acknowledgements'])[0];
    $isEnabled = array_values($unifiedColumns['acknowledgements'])[0];
    if ($isEnabled) {
      /* Display acknowledgement */
      $titleSubHeadingAcknowledgement = "(Reference to the license, Text of acknowledgements, File path)";
      $this->bulkLicenseTable($section, $heading, $contents['licenseAcknowledgements']['statements'], $titleSubHeadingAcknowledgement);
    }

    $heading = array_keys($unifiedColumns['exportrestrictions'])[0];
    $isEnabled = array_values($unifiedColumns['exportrestrictions'])[0];
    if ($isEnabled) {
      /* Display Ecc statements and files */
      $titleSubHeadingCEI = "(Statements, Comments, File path)";
      $section->addBookmark("eccInternalLink");
      $textEcc ="The content of this paragraph is not the result of the evaluation"
               ." of the export control experts (the ECCN). It contains information"
               ." found by the scanner which shall be taken in consideration by"
               ." the export control experts during the evaluation process. If"
               ." the scanner identifies an ECCN it will be listed here. (NOTE:"
               ." The ECCN is seen as an attribute of the component release and"
               ." thus it shall be present in the component catalogue.";
      $this->getRowsAndColumnsForCEI($section, $heading, $contents['ecc']['statements'], $titleSubHeadingCEI, $textEcc);
    }

    $heading = array_keys($unifiedColumns['intellectualProperty'])[0];
    $isEnabled = array_values($unifiedColumns['intellectualProperty'])[0];
    if ($isEnabled) {
      /* Display IPRA statements and files */
      $heading = "Patent Relevant Statements";
      $textIpra = "The content of this paragraph is not the result of the evaluation of the IP professionals. It contains information found by the scanner which shall be taken in consideration by the IP professionals during the evaluation process.";
      $this->getRowsAndColumnsForCEI($section, $heading, $contents['ipra']['statements'], $titleSubHeadingCEI, $textIpra);
    }

    $heading = array_keys($unifiedColumns['notes'])[0];
    $isEnabled = array_values($unifiedColumns['notes'])[0];
    if ($isEnabled) {
      /* Display comments entered for report */
      $subHeading = "Notes on individual files";
      $reportStaticSection->notes($section, $heading, $subHeading);
      $titleSubHeadingNotes = "(License name, Comment Entered, File path)";
      $this->bulkLicenseTable($section, "", $contents['licenseComments']['statements'], $titleSubHeadingNotes);
    }

    $heading = array_keys($unifiedColumns['scanresults'])[0];
    $isEnabled = array_values($unifiedColumns['scanresults'])[0];
    if ($isEnabled) {
      /* Display scan results and edited results */
      $titleSubHeadingHistogram = "(Scanner count, Concluded license count, License name)";
      $this->licenseHistogram($section, $contents['licensesHist']['statements'], $titleSubHeadingHistogram, $heading);
    }

    $heading = array_keys($unifiedColumns['mainlicenses'])[0];
    $isEnabled = array_values($unifiedColumns['mainlicenses'])[0];
    if ($isEnabled) {
      /* Display global licenses */
      $titleSubHeadingLicense = "(License name, License text, File path)";
      $this->globalLicenseTable($section, $contents['licensesMain']['statements'], $titleSubHeadingLicense, $heading);
    }

    $heading = array_keys($unifiedColumns['redlicense'])[0];
    $isEnabled = array_values($unifiedColumns['redlicense'])[0];
    if ($isEnabled) {
      /* Display licenses(red) name,text and files */
      $redLicense = array("color" => array("bgColor" => "F9A7B0"), "riskLevel" => array("5", "4"));
      $this->licensesTable($section, $heading, $contents['licenses']['statements'], $redLicense, $titleSubHeadingLicense);
    }

    $heading = array_keys($unifiedColumns['yellowlicense'])[0];
    $isEnabled = array_values($unifiedColumns['yellowlicense'])[0];
    if ($isEnabled) {
      /* Display licenses(yellow) name,text and files */
      $yellowLicense = array("color" => array("bgColor" => "FEFF99"), "riskLevel" => array("3", "2"));
      $this->licensesTable($section, $heading, $contents['licenses']['statements'], $yellowLicense, $titleSubHeadingLicense);
    }

    $heading = array_keys($unifiedColumns['whitelicense'])[0];
    $isEnabled = array_values($unifiedColumns['whitelicense'])[0];
    if ($isEnabled) {
      /* Display licenses(white) name,text and files */
      $whiteLicense = array("color" => array("bgColor" => "FFFFFF"), "riskLevel" => array("", "0", "1"));
      $this->licensesTable($section, $heading, $contents['licenses']['statements'], $whiteLicense, $titleSubHeadingLicense);
    }

    $heading = array_keys($unifiedColumns['overviewwithwithoutobligations'])[0];
    $isEnabled = array_values($unifiedColumns['overviewwithwithoutobligations'])[0];
    if ($isEnabled) {
      $titleSubHeadingObli = "(License ShortName, Obligation)";
      $reportStaticSection->allLicensesWithAndWithoutObligations($section, $heading, $obligations, $whiteLists, $titleSubHeadingObli);
    }

    $heading = array_keys($unifiedColumns['copyrights'])[0];
    $isEnabled = array_values($unifiedColumns['copyrights'])[0];
    if ($isEnabled) {
      /* Display copyright statements and files */
      $this->getRowsAndColumnsForCEI($section, $heading, $contents['copyrights']['scannerFindings'], $titleSubHeadingCEI);
    }

    $heading = array_keys($unifiedColumns['copyrightsuf'])[0];
    $isEnabled = array_values($unifiedColumns['copyrightsuf'])[0];
    if ($isEnabled) {
      /* Display user findings copyright statements and files */
      $this->getRowsAndColumnsForCEI($section, $heading, $contents['copyrights']['userFindings'], $titleSubHeadingCEI);
    }

    $heading = array_keys($unifiedColumns['bulkfindings'])[0];
    $isEnabled = array_values($unifiedColumns['bulkfindings'])[0];
    if ($isEnabled) {
      /* Display Bulk findings name,text and files */
      $this->bulkLicenseTable($section, $heading, $contents['bulkLicenses']['statements'], $titleSubHeadingLicense);
    }

    $heading = array_keys($unifiedColumns['licensenf'])[0];
    $isEnabled = array_values($unifiedColumns['licensenf'])[0];
    if ($isEnabled) {
      /* Display NON-Functional Licenses license files */
      $reportStaticSection->getNonFunctionalLicenses($section, $heading);
    }

    $heading = array_keys($unifiedColumns['irrelevantfiles'])[0];
    $isEnabled = array_values($unifiedColumns['irrelevantfiles'])[0];
    if ($isEnabled) {
      /* Display irrelavant license files */
      $titleSubHeadingIrre = "(Path, Files, Licenses)";
      $this->getRowsAndColumnsForIrre($section, $heading, $contents['licensesIrre']['statements'], $titleSubHeadingIrre);

      /* Display irrelavant file license comment  */
      $subHeading = "Comment for Irrelevant files";
      $section->addTitle(htmlspecialchars("$subHeading"), 3);
      $titleSubHeadingNotes = "(License name, Comment Entered, File path)";
      $this->bulkLicenseTable($section, "", $contents['licensesIrreComment']['statements'], $titleSubHeadingNotes);
    }

    $heading = array_keys($unifiedColumns['dnufiles'])[0];
    $isEnabled = array_values($unifiedColumns['dnufiles'])[0];
    if ($isEnabled) {
      /* Display Do not use license files */
      if ($contents['otherStatement']['includeDNU']) {
        // adding an internal bookmark
        $columnStyleWithUnderline = array("size" => 11, "color" => "0000A0", 'underline' => 'single');
        $section->addBookmark('DNUBookmark');
        $bookMarkCell->addLink('DNUBookmark', htmlspecialchars(' NOTE: DO NOT USE files found! Please check Do not use files section', ENT_COMPAT, 'UTF-8'), $columnStyleWithUnderline, "pStyle", true);
      }
      $titleSubHeadingIrre = "(Path, Files, Licenses)";
      $this->getRowsAndColumnsForIrre($section, $heading, $contents['licensesDNU']['statements'], $titleSubHeadingIrre);

      /* Display Do not use file license comment  */
      $subHeading = "Comment for Do not use files";
      $section->addTitle(htmlspecialchars("$subHeading"), 3);
      $titleSubHeadingNotes = "(License name, Comment Entered, File path)";
      $this->bulkLicenseTable($section, "", $contents['licensesDNUComment']['statements'], $titleSubHeadingNotes);
    }

    /* Display Non functional license files */
    $heading = "Non functional Files";
    if ($contents['otherStatement']['includeNonFunctional']) {
      // adding an internal bookmark
      $columnStyleWithUnderline = array("size" => 11, "color" => "0000A0", 'underline' => 'single');
      $section->addBookmark('nonFunctionalBookmark');
      $bookMarkCell->addLink('nonFunctionalBookmark', htmlspecialchars(' NOTE: Non functional files found! Please check Non functional files section', ENT_COMPAT, 'UTF-8'), $columnStyleWithUnderline, "pStyle", true);
    }
    $titleSubHeadingIrre = "(Path, Files, Licenses)";
    $this->getRowsAndColumnsForIrre($section, $heading, $contents['licensesNonFunctional']['statements'], $titleSubHeadingIrre);

    /* Display Non functional file license comment  */
    $subHeading = "Comment for Non functional files";
    $section->addTitle(htmlspecialchars("$subHeading"), 3);
    $titleSubHeadingNotes = "(License name, Comment Entered, File path)";
    $this->bulkLicenseTable($section, "", $contents['licensesNonFunctionalComment']['statements'], $titleSubHeadingNotes);

    $heading = array_keys($unifiedColumns['changelog'])[0];
    $isEnabled = array_values($unifiedColumns['changelog'])[0];
    if ($isEnabled) {
      /* clearing protocol change log table */
      $reportStaticSection->clearingProtocolChangeLogTable($section, $heading);
    }

    /* Footer starts */
    $reportStaticSection->reportFooter($phpWord, $section, $contents['otherStatement']);

    $fileBase = $SysConf["FOSSOLOGY"]["path"]."/report/";
    if (!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0022);
    $fileName = $fileBase. "$packageName"."_clearing_report_".date("D_M_d_m_Y_h_i_s").".docx";
    $objWriter = IOFactory::createWriter($phpWord, "Word2007");
    $objWriter->save($fileName);

    $this->updateReportTable($uploadId, $this->jobId, $fileName);
  }


  /**
   * @brief Update database with generated report path.
   * @param int $uploadId
   * @param int $jobId
   * @param string $filename
   */
  private function updateReportTable($uploadId, $jobId, $filename)
  {
    $this->dbManager->getSingleRow("INSERT INTO reportgen(upload_fk, job_fk, filepath) VALUES($1,$2,$3)",
      array($uploadId, $jobId, $filename), __METHOD__);
  }
}

$agent = new UnifiedReport();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
