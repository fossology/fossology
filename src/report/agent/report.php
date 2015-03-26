<?php
/*
 Author: Daniele Fognini, Shaheem Azmal, Anupam Ghosh
 Copyright (C) 2014-2015, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("REPORT_AGENT_NAME", "report");

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Report\LicenseClearedGetter;
use Fossology\Lib\Report\LicenseIrrelevantGetter;
use Fossology\Lib\Report\XpClearedGetter;
use PhpOffice\PhpWord;

include_once(__DIR__ . "/version.php");

class ReportAgent extends Agent
{

  /** @var LicenseClearedGetter  */
  private $licenseClearedGetter;
  
  /** @var cpClearedGetter */
  private $cpClearedGetter;

  /** @var ipClearedGetter */
  private $ipClearedGetter;

  /** @var eccClearedGetter */
  private $eccClearedGetter;

  /** @var  LicenseIrrelevantGetter*/
  private $LicenseIrrelevantGetter;

  /** @var UploadDao */
  private $uploadDao;

  /** @var fontFamily */
  private $fontFamily = "Arial";

  /** @var tablestyle */
  private $tablestyle = array("borderSize" => 2,
                              "name" => "Arial",
                              "borderColor" => "000000",
                              "cellSpacing" => 5
                             );
  /** @var tableHeading */
  private $tableHeading = array("color" => "000000",
                                "size" => 18,
                                "bold" => true,
                                "name" => "Arial"
                               );

  private $paragraphStyle = array("spaceAfter" => 0,
                                  "spaceBefore" => 0,
                                  "spacing" => 0
                                 );
  
  function __construct()
  {
    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement", false, "content ilike 'Copyright%'");
    $this->ipClearedGetter = new XpClearedGetter("ip", null, true);
    $this->eccClearedGetter = new XpClearedGetter("ecc", null, true);
    $this->licenseClearedGetter = new LicenseClearedGetter();
    $this->LicenseIrrelevantGetter = new LicenseIrrelevantGetter();

    parent::__construct(REPORT_AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get("dao.upload");
  }


  function processUploadId($uploadId)
  {
    $groupId = $this->groupId;

    $this->heartbeat(0);
    $licenses = $this->licenseClearedGetter->getCleared($uploadId, $groupId);
    $this->heartbeat(count($licenses["statements"]));
    $licensesIrre = $this->LicenseIrrelevantGetter->getCleared($uploadId, $groupId);
    $this->heartbeat(count($licensesIrre["statements"]));
    $copyrights = $this->cpClearedGetter->getCleared($uploadId, $groupId);
    $this->heartbeat(count($copyrights["statements"]));
    $ecc = $this->eccClearedGetter->getCleared($uploadId, $groupId);
    $this->heartbeat(count($ecc["statements"]));
    $ip = $this->ipClearedGetter->getCleared($uploadId, $groupId);
    $this->heartbeat(count($ip["statements"]));
    $contents = array("licenses" => $licenses,
                      "copyrights" => $copyrights,
                      "ecc" => $ecc,
                      "ip" => $ip,
                      "licensesIrre" => $licensesIrre
    );
    $this->writeReport($contents, $uploadId);

    return true;
  }


  /**
   * @brief Design the header section of report
   * @param section as a param 
   *
   */
  private function reportHeader($section)
  {
    $headerStyle = array("color" => "48CCCD", "size" => 20, "bold" => true, "name" => $this->fontFamily);
    $header = $section->createHeader();
    $header->addText(htmlspecialchars("SIEMENS"), $headerStyle);
  }


  /**
   * @brief Design the footer section of report
   * @param section as a param 
   */
  private function reportFooter($phpWord, $section)
  { 
    global $SysConf;

    $commitId = $SysConf['BUILD']['COMMIT_HASH'];
    $commitDate = $SysConf['BUILD']['COMMIT_DATE'];
    $styleTable = array('borderSize'=>10, 'borderColor'=>'FFFFFF' );
    $styleFirstRow = array('borderTopSize'=>10, 'borderTopColor'=>'000000');
    $phpWord->addTableStyle('footerTableStyle', $styleTable, $styleFirstRow);
    $footerStyle = array("color" => "000000", "size" => 9, "bold" => true, "name" => $this->fontFamily);
    $footerTime = "Gen Date: ".date("Y/m/d H:i:s T");
    $footerCopyright = "Copyright © 2015 Siemens AG - Restricted"; 
    $footerSpace = str_repeat("  ", 7);
    $footerPageNo = "Page {PAGE} of {NUMPAGES}";
    $footer = $section->createFooter(); 
    $table = $footer->addTable("footerTableStyle");
    $table->addRow(200, $styleFirstRow);
    $cell = $table->addCell(15000,$styleFirstRow)->addPreserveText(htmlspecialchars("$footerCopyright $footerSpace $footerTime $footerSpace FOSSologyNG Ver:#$commitId-$commitDate $footerSpace $footerPageNo"), $footerStyle); 
  }


  /**
   * @brief Design the header section of report
   * @param section as a param 
   */
  private function reportTitle($section)
  {
    $titleStyle = array("name" => $this->fontFamily, "size" => 22, "bold" => true, "underline" => "single");
    $title = "License Clearing Report - V1";
    $section->addText(htmlspecialchars($title), $titleStyle);
    $section->addTextBreak(); 
  }


  /**
   * @brief Design the summaryTable of the report
   * @param1 section
   */        
  private function summaryTable($section, $packageName)
  {
    
    $paragraphStyle = array("spaceAfter" => 2, "spaceBefore" => 2,"spacing" => 2);          
    $cellRowContinue = array("vMerge" => "continue");
    $firstRowStyle = array("size" => 14, "bold" => true);
    $firstRowStyle1 = array("size" => 12, "bold" => true);
    $firstRowStyle2 = array("size" => 12, "bold" => false);
    $checkBoxStyle = array("size" => 10);

    $cellRowSpan = array("vMerge" => "restart", "valign" => "top");
    $cellColSpan = array("gridSpan" => 3, "valign" => "center");

    $rowWidth = 200;
    $cellFirstLen = 2500;
    $cellSecondLen = 3800;
    $cellThirdLen = 5500; 

    $table = $section->addTable($this->tablestyle);
    
    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellColSpan)->addText(htmlspecialchars(" Clearing report for OSS component"), $firstRowStyle, $paragraphStyle);
    
    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" Clearing Information"), $firstRowStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Department"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(" FOSSologyNG Generation"), $firstRowStyle2, $paragraphStyle);
    
    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Type"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(" OSS clearing only"), $firstRowStyle2, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Prepared by"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(" <date> <last name, first name> <department>"), $firstRowStyle2, $paragraphStyle);
      
    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Reviewed by (opt.)"),$firstRowStyle1,$paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(" <date> <last name, first name> <department>"), $firstRowStyle2, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Released by"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(" FOSSologyNG Generation"), $firstRowStyle2, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Clearing Status"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen);
    $cell->addCheckBox("inprogress", htmlspecialchars(" in progress"), $checkBoxStyle, $paragraphStyle);
    $cell->addCheckBox("release", htmlspecialchars(" release"), $checkBoxStyle, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" Component Information"), $firstRowStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Community"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(" <URL>"), $firstRowStyle2, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Component"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars($packageName), $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Version"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(""), $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Source URL"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(""), $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Release date"), $firstRowStyle1, $paragraphStyle);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(""), $paragraphStyle);

    $table->addRow($rowWidth);
    $cell = $table->addCell($cellFirstLen, $cellRowContinue);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Main license(s)"), $firstRowStyle1);
    $cell = $table->addCell($cellThirdLen)->addText(htmlspecialchars(" <list here the name(s) of the global license(s)>"), $firstRowStyle2);

    $section->addTextBreak();
  }

  /**
   * @brief Design the clearingProtocolChangeLogTable of the report
   * @param1 section 
   */ 
  private function clearingProtocolChangeLogTable($section)
  {
    $thColor = array("bgColor" => "C0C0C0");
    $thText = array("size" => 12, "bold" => true);
    $rowWidth = 600;
    $rowWidth1 = 200;
    $cellFirstLen = 2000;
    $cellSecondLen = 4500;
    $cellThirdLen = 9000;

    $heading = "1. Clearing Protocol Change Log";
    $section->addText(htmlspecialchars($heading), $this->tableHeading);

    $table = $section->addTable($this->tablestyle);

    $table->addRow($rowWidth);
    $cell = $table->addCell($cellFirstLen, $thColor)->addText(htmlspecialchars("Last Update"), $thText);
    $cell = $table->addCell($cellSecondLen, $thColor)->addText(htmlspecialchars("Responsible"), $thText);
    $cell = $table->addCell($cellThirdLen, $thColor)->addText(htmlspecialchars("Comments"), $thText);

    $table->addRow($rowWidth1);
    $cell = $table->addCell($cellFirstLen);
    $cell = $table->addCell($cellSecondLen);
    $cell = $table->addCell($cellThirdLen);

    $section->addTextBreak();
  }

  /**
   * @brief Design the functionalityTable of the report
   * @param1 section 
   */ 
  private function functionalityTable($section)
  {
    $infoTextStyle = array("name" => $this->fontFamily, "size" => 11, "color" => "0000FF");
    $heading = "2. Functionality";
    $infoText = "<Hint: look in ohloh.net in the mainline portal or Component database or on the communities web page for information>";
 
    $section->addText(htmlspecialchars($heading), $this->tableHeading);
    $section->addText(htmlspecialchars($infoText), $infoTextStyle);

    $section->addTextBreak();
  }


  /**
   * @brief Design the assessmentSummaryTable of the report
   * @param1 section 
   */ 
  private function assessmentSummaryTable($section)
  { 
    $paragraphStyle = array("spaceAfter" => 0, "spaceBefore" => 0,"spacing" => 0);          
    $heading = "3. Assessment Summary:";
    $infoText = "The following table only contains significant obligations, restrictions & risks for a quick overview – all obligations, restrictions & risks according to Section 3 must be considered.";
      
    $infoTextStyle = array("name" => $this->fontFamily, "size" => 10, "color" => "000000");
    $leftColStyle = array("size" => 11, "color" => "000000","bold" => true);
    $rightColStyleBlue = array("size" => 11, "color" => "0000A0","italic" => true);
    $rightColStyleBlack = array("size" => 11, "color" => "000000");
    $rightColStyleBlackWithItalic = array("size" => 11, "color" => "000000","italic" => true);

    $rowWidth = 200;
    $cellFirstLen = 5000;
    $cellSecondLen = 10500;

    $section->addText(htmlspecialchars($heading), $this->tableHeading);
    $section->addText(htmlspecialchars($infoText), $infoTextStyle);

    $table = $section->addTable($this->tablestyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen)->addText(htmlspecialchars(" General assessment"), $leftColStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" <e.g. strong copyleft effect, license incompatibilities,  or also “easy to fulfill obligations, common rules only”>"), $rightColStyleBlue, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen)->addText(htmlspecialchars(" Mainline Portal Status for component"), $leftColStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen);
    $cell->addCheckBox("mainline", htmlspecialchars(" Mainline"), $rightColStyleBlack, $paragraphStyle);
    $cell->addCheckBox("specific", htmlspecialchars(" Specific"), $rightColStyleBlack, $paragraphStyle);
    $cell->addCheckBox("denied", htmlspecialchars(" Denied"), $rightColStyleBlack, $paragraphStyle);
 
    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen)->addText(htmlspecialchars(" License Incompatibility found"), $leftColStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen);
    $cell->addCheckBox("no", htmlspecialchars(" no"), $rightColStyleBlackWithItalic, $paragraphStyle);
    $cell->addCheckBox("yes", htmlspecialchars(" yes"), $rightColStyleBlackWithItalic, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen)->addText(htmlspecialchars(" Source / binary integration notes"), $leftColStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen);
    $cell->addCheckBox("nocriticalfiles", htmlspecialchars(" no critical files found, source code and binaries can be used as is"), $rightColStyleBlackWithItalic, $paragraphStyle);
    $cell->addCheckBox("criticalfiles", htmlspecialchars(" critical files found, source code needs to be adapted and binaries possibly re-built"), $rightColStyleBlackWithItalic, $paragraphStyle);
    $cell->addText(htmlspecialchars(" <if there are critical files found, please provide some additional information or refer to chapter(s) in this documents where additional information is given>"), $rightColStyleBlue, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen)->addText(htmlspecialchars(" Dependency notes"), $leftColStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen);
    $cell->addCheckBox("nodependenciesfound", htmlspecialchars(" no dependencies found, neither in source code nor in binaries"), $rightColStyleBlackWithItalic, $paragraphStyle);
    $cell->addCheckBox("dependenciesfoundinsourcecode", htmlspecialchars(" dependencies found in source code"), $rightColStyleBlackWithItalic, $paragraphStyle);
    $cell->addCheckBox("dependenciesfoundinbinaries", htmlspecialchars(" dependencies found in binaries"), $rightColStyleBlackWithItalic, $paragraphStyle);
    $cell->addText(htmlspecialchars(" <if there are dependencies found, please provide some additional information or refer to chapter(s) in this documents where additional information is given>"), $rightColStyleBlue, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen)->addText(htmlspecialchars(" Additional notes"), $leftColStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" <e.g. only global license was cleared since the project who requested the clearing only uses the component without mixing it with Siemens code>"), $rightColStyleBlue, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($cellFirstLen)->addText(htmlspecialchars(" General Risks (optional)"), $leftColStyle, $paragraphStyle);
    $cell = $table->addCell($cellSecondLen)->addText(htmlspecialchars(" <e.g. not maintained by community anymore – must be supported by Siemens – see ohloh.net for info>"), $rightColStyleBlue, $paragraphStyle);
    
    $section->addTextBreak();
  }

  /**
   * @brief Design the todosection of the report
   * @param1 section 
   */ 
  private function todoTable($section)
  {
    $paragraphStyle = array("spaceAfter" => 0, "spaceBefore" => 0,"spacing" => 0);          
    $rowStyle = array("bgColor" => "C0C0C0", "spaceBefore" => 0, "spaceAfter" => 0, "spacing" => 0);
    $rowTextStyleLeft = array("size" => 10, "bold" => true);
    $rowTextStyleRight = array("name" => $this->fontFamily, "size" => 10, "bold" => false);
    $rowTextStyleRightBold = array("size" => 10, "bold" => true);
    $subHeadingStyle = array("name" => $this->fontFamily, "size "=> 14, "italic" => true);  
 
    $heading = "4. When using this component, you need to fulfill the following “ToDos”";
    $subHeading = " 4.1. Common obligations, restrictions and risks:";
    $subHeadingInfoText = "  There is a list of common rules which was defined to simplify the To-Dos for development and distribution. The following list contains rules for development, and      distribution which must always be followed!";
    $rowWidth = 5;
    $firstColLen = 500;
    $secondColLen = 15000;
    
    $section->addText(htmlspecialchars($heading), $this->tableHeading);
    $section->addText(htmlspecialchars($subHeading), $subHeadingStyle);
    $section->addText(htmlspecialchars($subHeadingInfoText), $rowTextStyleRight);

    $r1c1 = "1";
    $r2c1 = "1.a";
    $r3c1 = "1.b";
    $r4c1 = "1.c";
    $r5c1 = "2";
    $r6c1 = "2.a";
    $r7c1 = "2.b";
    $r8c1 = "3";
    $r9c1 = "3.a";
    $r10c1 = "3.b";
    $r11c1 = "3.c";
    $r12c1 = "4";
    $r13c1 = "4.a";

    $r1c2 = "Documentation of license conditions and copyright notices in product documentation (ReadMe_OSS)";
    $r2c21 = "All relevant licenses (global and others - see below) must be added to the legal approved Readme_OSS template.";
    $r2c22 = "Remark:";
    $r2c23 = "“Do Not Use” licenses must not be added to the ReadMe_OSS";
    $r3c2 = "Add all copyrights to README_OSS";
    $r4c2 = "Add all relevant acknowledgements to Readme_OSS";
    $r5c21 = "Modifications in Source Code";
    $r5c22 = "If modifications are permitted:";
    $r6c2 = "Do not change or delete Copyright, patent, trademark, attribution notices or any further legal notices or license texts in any files - i.e. neither within any source file of the component package nor in any of its documentation files.";
    $r7c21 = "Document all changes and modifications in source code files with copyright notices:";
    $r7c22 = "Add copyright (including company and date), function, reason for modification in the header.";
    $r7c23 = "Example:";
    $r7c24 = "© Siemens AG, 2013";
    $r7c25 = "March 18th, 2013 Modified helloworld() – fix memory leak";
    $r8c2 = "Obligations and risk assessment regarding distribution";
    $r9c2 = "Ensure that your distribution terms which are agreed with Siemens’ customers (e.g. standard terms, “AGB”, or individual agreements) define that the open source license conditions shall prevail over the Siemens’ license conditions with respect to the open source software (usually this is part of Readme OSS).";
    $r10c2 = "Do not use any names, trademarks, service marks or product names of the author(s) and/or licensors to endorse or promote products derived from this software component without the prior written consent of the author(s) and/or the owner of such rights.";
    $r11c2 = "Consider for your product/project if you accept the general risk that";
    $r11c21 = "• it cannot be verified whether contributors to open source software are legally permitted to contribute (the code could e.g. belong to his employer, and not the developer). Usually, disclaimers or contribution policies exclude responsibility for contributors even to verify the legal status.	";
    $r11c22 = "•  there is no warranty or liability from the community – i.e. error corrections must be made by Siemens, and Siemens must cover all damages";
    $r12c2 = "IC SG EA specific rules";
    $r13c21 = "The following statement must be added to any manual. The language of the statement is equal to the manual language. Check translation with documentation department.";
    $r13c22 = "English:";
    $r13c23 = "	The product contains, among other things, Open Source Software developed by third parties. The Open Source Software used in the product and the license agreements concerning this software can be found in the Readme_OSS. These Open Source Software files are protected by copyright. Your compliance with those license conditions will entitle you to use the Open Source Software as foreseen in the relevant license. In the event of conflicts between Siemens license conditions and the Open Source Software license conditions, the Open Source Software conditions shall prevail with respect to the Open Source Software portions of the software. The Open Source Software is licensed royalty-free. Insofar as the applicable Open Source Software License Conditions provide for it you can order the source code of the Open Source Software from your Siemens sales contact - against payment of the shipping and handling charges - for a period of at least 3 years since purchase of the Product. We are liable for the Product including the Open Source Software contained in it pursuant to the license conditions applicable to the Product. Any liability for the Open Source Software beyond the program flow intended for the Product is explicitly excluded. Furthermore any liability for defects resulting from modifications to the Open Source Software by you or third parties is excluded. We do not provide any technical support for the Product if it has been modified.";

    $table = $section->addTable($this->tablestyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen, $rowStyle)->addText(htmlspecialchars($r1c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen, $rowStyle)->addText(htmlspecialchars($r1c2), $rowTextStyleRightBold, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r2c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen);
    $cell->addText(htmlspecialchars($r2c21), $rowTextStyleRight, $paragraphStyle);
    $cell->addText(htmlspecialchars($r2c22), $rowTextStyleRightBold, $paragraphStyle);
    $cell->addText(htmlspecialchars($r2c23),$rowTextStyleRight,$paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r3c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen)->addText(htmlspecialchars($r3c2), $rowTextStyleRight, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r4c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen)->addText(htmlspecialchars($r4c2), $rowTextStyleRight, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen, $rowStyle)->addText(htmlspecialchars($r5c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen, $rowStyle);
    $cell->addText(htmlspecialchars($r5c21), $rowTextStyleRightBold, $paragraphStyle);
    $cell->addText(htmlspecialchars($r5c22), $rowTextStyleRight, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r6c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen)->addText(htmlspecialchars($r6c2), $rowTextStyleRight, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r7c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen);
    $cell->addText(htmlspecialchars($r7c21), $rowTextStyleRight, $paragraphStyle);
    $cell->addText(htmlspecialchars($r7c22), $rowTextStyleRight, $paragraphStyle);
    $cell->addText(htmlspecialchars($r7c23), $rowTextStyleRight, $paragraphStyle);
    $cell->addText(htmlspecialchars($r7c24), $rowTextStyleRight, $paragraphStyle);
    $cell->addText(htmlspecialchars($r7c25), $rowTextStyleRight, $paragraphStyle);
 
    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen, $rowStyle)->addText(htmlspecialchars($r8c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen, $rowStyle)->addText(htmlspecialchars($r8c2), $rowTextStyleRightBold, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r9c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen)->addText(htmlspecialchars($r9c2), $rowTextStyleRight, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r10c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen)->addText(htmlspecialchars($r10c2), $rowTextStyleRight, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r11c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen);
    $cell->addText(htmlspecialchars($r11c2), $rowTextStyleRightBold, $paragraphStyle);
    $cell->addText(htmlspecialchars($r11c21), $rowTextStyleRight, $paragraphStyle);
    $cell->addText(htmlspecialchars($r11c22), $rowTextStyleRight, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen, $rowStyle)->addText(htmlspecialchars($r12c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen, $rowStyle)->addText(htmlspecialchars($r12c2), $rowTextStyleRightBold, $paragraphStyle);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars($r13c1), $rowTextStyleLeft, $paragraphStyle);
    $cell = $table->addCell($secondColLen);
    $cell->addText(htmlspecialchars($r13c21), $rowTextStyleRightBold, $paragraphStyle);
    $cell->addText(htmlspecialchars($r13c22), $rowTextStyleRight, $paragraphStyle);
    $cell->addText(htmlspecialchars($r13c23), $rowTextStyleRight, $paragraphStyle);

    $section->addTextBreak();
  }


  /**
   * @brief Design the todosection of the report
   * @param1 section 
   */ 
  private function todoObliTable($section)
  {
    $firstRowStyle = array("bgColor" => "D2D0CE");
    $firstRowTextStyle = array("size" => 11, "bold" => true);
    $secondRowTextStyle1 = array("size" => 11, "bold" => false);
    $secondRowTextStyle2 = array("size" => 10, "bold" => false);
    $secondRowTextStyle2Bold = array("size" => 10, "bold" => true);
    $firstColStyle = array ("size" => 11 , "bold"=> true, "bgcolor" => "FFFFC2");
    $secondColStyle = array ("size" => 11 , "bold"=> true, "bgcolor"=> "E0FFFF");
    $subHeadingStyle = array("name" => $this->fontFamily, "size" => 14, "italic" => true);
    $subHeading = " 4.2. Additional obligations, restrictions & risks beyond common rules";
    $subHeadingInfoText1 = "  In this chapter you will find the summary of additional license conditions (relevant for development and distribution) for the OSS component.";
    $subHeadingInfoText2 = "  * The following information helps the project to determine the responsibility regarding the To Do’s. But it is not limited to Development or Distribution. ";

    $section->addText(htmlspecialchars($subHeading), $subHeadingStyle);
    $section->addText(htmlspecialchars($subHeadingInfoText1));
    $section->addText(htmlspecialchars($subHeadingInfoText2));

    $rowWidth = 200;
    $firstColLen = 2000;
    $secondColLen = 1500;
    $thirdColLen = 9000;
    $fourthColLen = 1500;
    $fifthColLen = 1500; 
   
    $table = $section->addTable($this->tablestyle);
    
    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstRowStyle)->addText(htmlspecialchars("Obligation"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $firstRowStyle)->addText(htmlspecialchars("License"), $firstRowTextStyle);
    $cell = $table->addCell($thirdColLen, $firstRowStyle)->addText(htmlspecialchars("License section reference and short Description"), $firstRowTextStyle);
    $cell = $table->addCell($fourthColLen, $firstRowStyle)->addText(htmlspecialchars("Focus area for Development "), $firstRowTextStyle);
    $cell = $table->addCell($fifthColLen, $firstRowStyle)->addText(htmlspecialchars("Focus area for Distribution"), $firstRowTextStyle);

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstColStyle)->addText(htmlspecialchars("Additional binaries found"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $secondColStyle)->addText(htmlspecialchars("-"), $firstRowTextStyle);
    $cell = $table->addCell($thirdColLen);
    $cell->addText(htmlspecialchars("In this component additional binaries are found."),$secondRowTextStyle1);
    $cell->addText(htmlspecialchars("If you want to use the binaries distributed with the source/binaries ((where no corresponding source code is part of the distribution of this component) you must do a clearing also for those components (add them to the Mainline Portal). The license conditions of the additional binaries are NOT part of this clearing protocol."), $secondRowTextStyle1);
    $cell = $table->addCell($fourthColLen);
    $cell = $table->addCell($fifthColLen);
    
    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstColStyle)->addText(htmlspecialchars("Dual Licensing (optional obligation, check with license)"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $secondColStyle);
    $cell = $table->addCell($thirdColLen);
    $cell->addText(htmlspecialchars("Add explicit note to Readme_OSS:"), $secondRowTextStyle2);
    $cell->addText(htmlspecialchars("To the extend files may be licensed under <license1> or <license2>, in this context <license1> has been chosen."), $secondRowTextStyle2);
    $cell->addText(htmlspecialchars("This shall not restrict the freedom of future contributors to choose either <license1> or <license2>.”"), $secondRowTextStyle2);
    $cell = $table->addCell($fourthColLen);
    $cell = $table->addCell($fifthColLen);


    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstColStyle)->addText(htmlspecialchars("Do not use the following Files"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $secondColStyle);
    $cell = $table->addCell($thirdColLen);
    $cell->addText(htmlspecialchars("<reason for that>"), $secondRowTextStyle2);
    $cell->addText(htmlspecialchars("Filelist:"), $secondRowTextStyle2Bold, $secondRowTextStyle2);
    $cell = $table->addCell($fourthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));
    $cell = $table->addCell($fifthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));


    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen,$firstColStyle)->addText(htmlspecialchars("Copyleft Effect"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen,$secondColStyle);
    $cell = $table->addCell($thirdColLen);
    $cell = $table->addCell($fourthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));
    $cell = $table->addCell($fifthColLen);


    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen,$firstColStyle)->addText(htmlspecialchars("Restrictions for advertising materials"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen,$secondColStyle);
    $cell = $table->addCell($thirdColLen);
    $cell = $table->addCell($fourthColLen);
    $cell = $table->addCell($fifthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));


    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstColStyle)->addText(htmlspecialchars("Additional Rules for modification"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $secondColStyle)->addText(htmlspecialchars(""), $firstRowTextStyle);
    $cell = $table->addCell($thirdColLen);
    $cell = $table->addCell($fourthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));
    $cell = $table->addCell($fifthColLen)->addText(htmlspecialchars(""), $secondRowTextStyle2Bold);

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstColStyle)->addText(htmlspecialchars("Additional documentation requirements for modifications (e.g. notice file with author’s name)"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $secondColStyle);
    $cell = $table->addCell($thirdColLen);
    $cell = $table->addCell($fourthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));
    $cell = $table->addCell($fifthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstColStyle)->addText(htmlspecialchars("Include special acknowledgments in advertising material"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $secondColStyle);
    $cell = $table->addCell($thirdColLen);
    $cell = $table->addCell($fourthColLen);
    $cell = $table->addCell($fifthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstColStyle)->addText(htmlspecialchars("Specific Risks"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $secondColStyle);
    $cell = $table->addCell($thirdColLen);
    $cell = $table->addCell($fourthColLen)->addText(htmlspecialchars("X"), $secondRowTextStyle2Bold, array("align" => "center"));
    $cell = $table->addCell($fifthColLen);

    $section->addTextBreak();
  }

  /**
   * @brief Design the todosection of the report
   * @param1 section 
   */ 
  private function todoObliList($section)
  {
    
    $firstRowStyle = array("bgColor" => "C0C0C0");
    $firstRowTextStyle = array("size" => 10, "bold" => true);
    
    $subHeadingStyle = array("name" => $this->fontFamily, "size" => 14, "italic" => true);
    $subHeadingStyle1 = array("name" => $this->fontFamily, "size" => 11, "color" => "0000FF");
    $subHeadingStyle2 = array("name" => $this->fontFamily, "size" => 10, "color" => "000000");
    $subHeading = "4.3.	File list with specific obligations ";
    $subHeading1 = 'This is an optional chapter. it is oprional and should be used in special cases. if you just have a simple license as Apache-2.0 you must put in the note "not applicable"';
    $subHeading2 = "Depending of the license additional license conditions to the Common Rules in computer 4.1. exist. Here is the list of all licenses found in this component with additional rules.";

    $section->addText(htmlspecialchars($subHeading), $subHeadingStyle);
    $section->addText(htmlspecialchars($subHeading1), $subHeadingStyle1);
    $section->addListItem(htmlspecialchars("Always if you have remove files"));
    $section->addListItem(htmlspecialchars("Patent Issues"));
    $section->addListItem(htmlspecialchars("...(other conditions will be added later)"));
    $section->addListItem(htmlspecialchars(""));
    $section->addTextBreak(2);
    $section->addText(htmlspecialchars($subHeading2), $subHeadingStyle);

    
    $rowWidth = 500;
    $firstColLen = 4000;
    $secondColLen = 5000;
    
    $table = $section->addTable($this->tablestyle);

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen, $firstRowStyle)->addText(htmlspecialchars("Issues obligations (licenses, patent …) see chapter 4.2"), $firstRowTextStyle);
    $cell = $table->addCell($secondColLen, $firstRowStyle)->addText(htmlspecialchars("Files (embedded document) (optional)"), $firstRowTextStyle);

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars("Sleepycat License"));
    $cell = $table->addCell($secondColLen);

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars("Remove Files due to patent issues"));
    $cell = $table->addCell($secondColLen);

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars("GPL 2"));
    $cell = $table->addCell($secondColLen)->addText(htmlspecialchars("<path-filenames>"));

    $table->addRow($rowWidth);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars("Perl license: Dual license (Artistic or GPL)"));
    $cell = $table->addCell($secondColLen)->addText(htmlspecialchars("<path-filelist>"));

    $section->addTextBreak();
  }    

  /**
   * @brief Design the todosection of the report
   * @param1 section 
   */ 
  private function forOtherTodos($section)
  {
    $paragraphStyle = array("spaceAfter" => 0, "spaceBefore" => 0,"spacing" => 2);          
    $subHeadingStyle = array("name" => $this->fontFamily, "size" => 14, "italic" => true);
    $subSubHeadingStyle = array("name" => $this->fontFamily, "size" => 16, "bold" => true);
    $subSubHeadingInfoTextStyle = array("name" => $this->fontFamily, "size" => 10, "bold"=>false);
    $subSubHeadingInfoTextStyle1 = array("name" => $this->fontFamily, "size" => 10, "bold"=>true);

    $subHeading = "4.4.	Further general obligations, restrictions & risks"; 
    $subSubHeading = "   4.4.1	 Export Restrictions";
    $subSubHeadingInfoText = "Assess potential export restrictions together with your export control agent regarding this software component as defined in your applicable process.";
    $subSubHeadingInfoText1 = "Export Restrictions found in Source Code:";
    $subSubHeadingInfoText2 = "No export restriction notices found in source scan – export restriction clarification is responsibility of Siemens projects/product managers.";

    $subSubHeading1 = "   4.4.2   Security Vulnerabilities";
    $subSubHeadingInfoText3 = "Security Vulnerabilities must be examined in product specific use - project leader is responsible to verify all security issues - as defined in your applicable process";
    $subSubHeadingInfoText4 = "--> Follow the link to show security vulnerabilities reported by CT IT Cert: http://mainline.nbgm.siemens.de/Mainline/SecurityInfo.aspx?Component_ID=000";
    $subSubHeadingInfoText5 = "Remark: This link leads to a site which may only list security vulnerabilities becoming known after the clearing date!";

    $subSubHeading2 = "   4.4.3   Patent Situation";
    $subSubHeadingInfoText6 = "Assess patent situation regarding open source software together with your BU patent strategy manager – we cannot expect the community to have clarified the patent situation. ";
    $subSubHeadingInfoText7 = "Patent Notices found in Source Code:";
    $subSubHeadingInfoText8 = "No patent notices found in source scan – patent clearing is responsibility of Siemens projects";
    $section->addText(htmlspecialchars($subHeading), $subHeadingStyle);
    $section->addText(htmlspecialchars($subSubHeading), $subHeadingStyle);
    $section->addText(htmlspecialchars($subSubHeadingInfoText), $subSubHeadingInfoTextStyle);
    $section->addText(htmlspecialchars($subSubHeadingInfoText1), $subSubHeadingInfoTextStyle1);
    $section->addText(htmlspecialChars($subSubHeadingInfoText2), $subSubHeadingInfoTextStyle,$paragraphStyle);
    
    $section->addTextBreak(2);

    $section->addText(htmlspecialchars($subSubHeading1), $subHeadingStyle);
    $section->addText(htmlspecialChars($subSubHeadingInfoText3), $subSubHeadingInfoTextStyle,$paragraphStyle);
    $section->addText(htmlspecialChars($subSubHeadingInfoText4), $subSubHeadingInfoTextStyle,$paragraphStyle);
    $section->addText(htmlspecialChars($subSubHeadingInfoText5), $subSubHeadingInfoTextStyle,$paragraphStyle);
    $section->addTextBreak(2);

    $section->addText(htmlspecialchars($subSubHeading2), $subHeadingStyle);
    $section->addText(htmlspecialchars($subSubHeadingInfoText7), $subSubHeadingInfoTextStyle1,$paragraphStyle);
    $section->addText(htmlspecialchars($subSubHeadingInfoText8), $subSubHeadingInfoTextStyle,$paragraphStyle);

    $section->addTextBreak();
  }

  /**
   * @brief Design basicForClearingReport section of the report
   * @param1 section 
   */ 
  private function basicForClearingReport($section)
  {
    $paragraphStyle = array("spaceAfter" => 0, "spaceBefore" => 0,"spacing" => 0, "valign" => "center");          
    $heading = "5. Basis for Clearing Report";
    $section->addText(htmlspecialchars($heading), $this->tableHeading);
    
    $table = $section->addTable($this->tablestyle);

    $cellRowContinue = array("vMerge" => "continue");
    $firstRowStyle = array("size" => 12, "bold" => true);
    $rowTextStyle = array("size" => 11, "bold" => false);
    
    $cellRowSpan = array("vMerge" => "restart", "valign" => "top");
    $cellColSpan = array("gridSpan" => 2, "valign" => "center");

    $rowWidth = 200;

    $firstColLen = 3500;
    $secondColLen = 7500;
    $thirdColLen = 4500;

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen, $cellRowSpan)->addText(htmlspecialchars("Preparation basis for OSS"), $firstRowStyle, $paragraphStyle);
    $cell = $table->addCell($secondColLen, $cellColSpan);
    $cell->addCheckBox("chkBox1", htmlspecialchars("According to Siemens Legally relevant Steps from LCR to Clearing Report"), $rowTextStyle);
    $cell->addCheckBox("chkBox2", htmlspecialchars("no"), $rowTextStyle, $paragraphStyle);
    $cell = $table->addCell($thirdColLen);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen, $cellRowContinue);
    $cell = $table->addCell($secondColLen, $cellColSpan);
    $cell->addCheckBox("checkBox1", htmlspecialchars("According to “Common Principles for Open Source License Interpretation” "), $rowTextStyle);
    $cell->addCheckBox("checkBox2", htmlspecialchars("no"), $rowTextStyle, $paragraphStyle);
    $cell = $table->addCell($thirdColLen);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen, $cellRowSpan)->addText(htmlspecialchars("OSS Source Code"), $firstRowStyle, $paragraphStyle);
    $cell = $table->addCell($thirdColLen)->addText(htmlspecialchars("Link to Upload page of component;"), $rowTextStyle, $paragraphStyle);
    $cell = $table->addCell($secondColLen, $cellColSpan);
 
    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen, $cellRowContinue);
    $cell = $table->addCell($thirdColLen)->addText(htmlspecialchars("MD5 hash value of source code"), $rowTextStyle, $paragraphStyle);
    $cell = $table->addCell($secondColLen, $cellColSpan);

    $table->addRow($rowWidth, $paragraphStyle);
    $cell = $table->addCell($firstColLen)->addText(htmlspecialchars("Result of LCR editor" ), $firstRowStyle, $paragraphStyle);
    $cell = $table->addCell($thirdColLen)->addText(htmlspecialchars("Embedded .xml file which can be checked by the LCR Editor is embedded here:"), $rowTextStyle, $paragraphStyle);
    $cell = $table->addCell($secondColLen, $cellColSpan);
  
    $section->addTextBreak();
  }

  /**
   * @brief Design globalLicesnetable section of the report
   * @param1 section 
   */ 
  private function globalLicenseTable($section)
  {
    $rowHeight = 500;
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;
    $firstRowStyle = array("bgColor" => "C0C0C0", "textAlign" => "center");
    $firstRowTextStyle = array("size" => 12, "align" => "center", "bold" => true);

    $section->addText(htmlspecialchars("6. Global Licenses"), $this->tableHeading);

    $table = $section->addTable($this->tablestyle);
    $table->addRow("500");
    $table->addCell($firstColLen, $firstRowStyle)->addText("License", $firstRowTextStyle);
    $table->addCell($secondColLen, $firstRowStyle)->addText("License text", $firstRowTextStyle);
    $table->addCell($thirdColLen, $firstRowStyle)->addText("File path", $firstRowTextStyle);
    $table->addRow($rowHeight);
    $cell1 = $table->addCell($firstColLen); 
    $cell1->addText("");
    $cell2 = $table->addCell($secondColLen); 
    $cell2->addText("");
    $cell3 = $table->addCell($thirdColLen);
    $cell3->addText("");
    
    $section->addTextBreak(); 
  }

  /**
   * @brief Design redOSSLicenseTable section of the report
   * @param1 section 
   */ 
  private function redOSSLicenseTable($section)
  {
    $rowHeight = 500;
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;
    $firstRowStyle = array("bgColor" => "C0C0C0", "textAlign" => "center");
    $firstRowTextStyle = array("size" => 12, "align" => "center", "bold" => true);

    $section->addText(htmlspecialchars("7. Other OSS Licenses (red) - strong copy left Effect or Do not Use Licenses"), $this->tableHeading);

    $table = $section->addTable($this->tablestyle);
    $table->addRow("500");
    $table->addCell($firstColLen, $firstRowStyle)->addText("License", $firstRowTextStyle);
    $table->addCell($secondColLen, $firstRowStyle)->addText("License text", $firstRowTextStyle);
    $table->addCell($thirdColLen, $firstRowStyle)->addText("File path", $firstRowTextStyle);
    $table->addRow($rowHeight);
    $cell1 = $table->addCell($firstColLen); 
    $cell1->addText("");
    $cell2 = $table->addCell($secondColLen); 
    $cell2->addText("");
    $cell3 = $table->addCell($thirdColLen);
    $cell3->addText("");
    
    $section->addTextBreak(); 
  }

  /**
   * @brief Design yellowOSSLicenseTable section of the report
   * @param1 section 
   */ 
  private function yellowOSSLicenseTable($section)
  {
    $rowHeight = 500;
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;
    $firstRowStyle = array("bgColor" => "C0C0C0", "textAlign" => "center");
    $firstRowTextStyle = array("size" => 12, "align" => "center", "bold" => true);

    $section->addText(htmlspecialchars("8. Other OSS Licenses (yellow) - additional obligations to common rules"), $this->tableHeading);

    $table = $section->addTable($this->tablestyle);
    $table->addRow("500");
    $table->addCell($firstColLen, $firstRowStyle)->addText("License", $firstRowTextStyle);
    $table->addCell($secondColLen, $firstRowStyle)->addText("License text", $firstRowTextStyle);
    $table->addCell($thirdColLen, $firstRowStyle)->addText("File path", $firstRowTextStyle);
    $table->addRow($rowHeight);
    $cell1 = $table->addCell($firstColLen); 
    $cell1->addText("");
    $cell2 = $table->addCell($secondColLen); 
    $cell2->addText("");
    $cell3 = $table->addCell($thirdColLen);
    $cell3->addText("");
    $section->addTextBreak(); 
  }
  
  /**
   * @brief Design yellowOSSLicenseTable section of the report
   * @param1 sectionsrc/report/agent/report.php
   * @param2 licesnes 
   */ 
  private function whiteOSSLicenseTable($section, $licenses)
  {
    $rowHeight = 500;
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;
    $firstRowStyle = array("bgColor" => "C0C0C0", "textAlign" => "center");
    $firstRowTextStyle = array("size" => 12, "align" => "center", "bold" => true);

    $section->addText(htmlspecialchars("9. Other OSS Licenses (white) - only common rules"), $this->tableHeading);
    $table = $section->addTable($this->tablestyle);
    $table->addRow("500");
    $table->addCell($firstColLen, $firstRowStyle)->addText("License", $firstRowTextStyle);
    $table->addCell($secondColLen, $firstRowStyle)->addText("License text", $firstRowTextStyle);
    $table->addCell($thirdColLen, $firstRowStyle)->addText("File path", $firstRowTextStyle);
    foreach($licenses as $licenseStatement){
      $table->addRow($rowHeight,$this->paragraphStyle);
      $cell1 = $table->addCell($firstColLen,$paragraphStyle); 
      $cell1->addText(htmlspecialchars($licenseStatement["content"]),null,$this->paragraphStyle);
      $cell2 = $table->addCell($secondColLen,$paragraphStyle); 
      // replace new line character
      $licenseText = str_replace("\n", "<w:br/>", htmlspecialchars($licenseStatement["text"]));
      $cell2->addText($licenseText,null,$this->paragraphStyle);
      $cell3 = $table->addCell($thirdColLen,$this->paragraphStyle);
      foreach($licenseStatement["files"] as $fileName){ 
         $cell3->addText(htmlspecialchars($fileName),null,$this->paragraphStyle);
      }
    }
    $section->addTextBreak(); 
  }
  
  /**
   * @brief Design ycknowledgementTable section of the report
   * @param1 section
   */ 
  private function acknowledgementTable($section)
  {
    $rowHeight = 500;
    $firstColLen = 3000;
    $secondColLen = 8500;
    $thirdColLen = 4000;

    $firstRowStyle = array("bgColor" => "C0C0C0", "textAlign" => "center");
    $firstRowTextStyle = array("size" => 12, "align" => "center", "bold" => true);
    
    $section->addText(htmlspecialchars("10. Acknowledgements"), $this->tableHeading);
    $table = $section->addTable($this->tablestyle);
    $table->addRow($rowHeight);
    $cell1 = $table->addCell($firstColLen,$firstRowStyle); 
    $cell1->addText(htmlspecialchars("ID of acknowledgements"),$firstRowTextStyle);
    $cell2 = $table->addCell($secondColLen,$firstRowStyle); 
    $cell2->addText(htmlspecialchars("Text of acknowledgements"),$firstRowTextStyle);
    $cell3 = $table->addCell($thirdColLen,$firstRowStyle);
    $cell3->addText(htmlspecialchars("Reference to the license"),$firstRowTextStyle);

    $section->addTextBreak(); 
  }


  /**
   * @brief returns the table with copyright or ecc or ip.
   * @param $section, $title, $statementsCEI
   * @$statementsCEI is array of contents.
   */
  private function getRowsAndColumnsForCEI($section, $title, $statementsCEI)
  {
    $rowHeight = 50;
    $firstColLen = 6500;
    $secondColLen = 5000;
    $thirdColLen = 4000;
    $firstRowStyle = array("bgColor" => "C0C0C0", "textAlign" => "center");
    $firstRowTextStyle = array("size" => 12, "align" => "center", "bold" => true);

    $section->addText(htmlspecialchars($title), $this->tableHeading);

    $table = $section->addTable($this->tablestyle);

    $table->addRow("500");
    $table->addCell($firstColLen, $firstRowStyle)->addText("Statements", $firstRowTextStyle);
    $table->addCell($secondColLen, $firstRowStyle)->addText("Comments", $firstRowTextStyle);
    $table->addCell($thirdColLen, $firstRowStyle)->addText("File path", $firstRowTextStyle);

    foreach($statementsCEI as $statements){
      $table->addRow($rowHeight,$this->paragraphStyle);
      $cell1 = $table->addCell($firstColLen); 
      $cell1->addText(htmlspecialchars($statements['content']),null,$this->paragraphStyle);
      $cell2 = $table->addCell($secondColLen);
      $cell2->addText(htmlspecialchars($statements['comments']), null, $this->paragraphStyle);
      $cell3 = $table->addCell($thirdColLen);
      foreach($statements['files'] as $fileName){ 
        $cell3->addText(htmlspecialchars($fileName), null, $this->paragraphStyle);
      }
    }
    $section->addTextBreak(); 
    return true;
  }


  private function getRowsAndColumnsForIrre($section, $title, $licensesIrre)
  {
    $thColor = array("bgColor" => "C0C0C0");
    $thText = array("size" => 12, "bold" => true);
    $rowWidth = 500;
    $firstColLen = 3500;
    $secondColLen = 3500;

    $section->addText(htmlspecialchars($title), $this->tableHeading);

    $table = $section->addTable($this->tablestyle);
    $table->addRow($rowWidth,$this->paragraphStyle);
    $cell = $table->addCell($firstColLen, $thColor)->addText(htmlspecialchars(" Path"), $thText);
    $cell = $table->addCell($secondColLen, $thColor)->addText(htmlspecialchars(" Files"), $thText);
    
    foreach($licensesIrre as $statements){
      $table->addRow($rowWidth,$this->paragraphStyle);
      $cell1 = $table->addCell($firstColLen);
      $cell1->addText(htmlspecialchars($statements['content']),null,$this->paragraphStyle);
      $cell2 = $table->addCell($secondColLen);
      foreach($statements['files'] as $fileName){
        $cell2->addText(htmlspecialchars($fileName),null,$this->paragraphStyle);
      }
    }
    $section->addTextBreak();
    return true;
  }

  /**
   * @brief save the docx file into repository.
   * @param $contents, $uploadId
   * @returns true.
   */
  private function writeReport($contents, $uploadId)
  {
    global $SysConf;

    $packageName = $this->uploadDao->getUpload($uploadId)->getFilename();

    $docLayout = array("orientation" => "landscape", 
                         "marginLeft" => "950", 
                         "marginRight" => "950", 
                         "marginTop" => "950", 
                         "marginBottom" => "950"
                        );

    /* Creating the new DOCX */
    $phpWord = new \PhpOffice\PhpWord\PhpWord();

    /* Setting document properties*/
    $properties = $phpWord->getDocInfo();
    $properties->setCreator("User-name will come here");
    $properties->setCompany("Siemens AG");
    $properties->setTitle("Clearing Report");
    $properties->setDescription("OSS clearing report by FOSSologyNG tool");
    $properties->setSubject("Copyright (C) 2014-2015, Siemens AG");

    /* Creating document layout */
    $section = $phpWord->createSection($docLayout);

    /* Header starts */
    $this->reportHeader($section);

    /* Main heading starts*/
    $this->reportTitle($section);

    /* Summery table */
    $this->summaryTable($section, $packageName);

    /* clearing protocol change log table */
    $this->clearingProtocolChangeLogTable($section);
    
    /* Functionality table */
    $this->functionalityTable($section);

    /* Assessment summery table */
    $this->assessmentSummaryTable($section);

    /* Todoinfo table */
    $this->todoTable($section);

    /* Todoobligation table */
    $this->todoObliTable($section);

    /* Todoobligation list */
    $this->todoObliList($section);
 
    /* For other todolist */
    $this->forOtherTodos($section);

    /* Basic for clearing report */
    $this->basicForClearingReport($section);

    /* Display global licenses */
    $this->globalLicenseTable($section);

    /* Display licenses(red) name,text and files */
    $this->redOSSLicenseTable($section);

    /* Display licenses(yellow) name,text and files */
    $this->yellowOSSLicenseTable($section);

    /* Display licenses(white) name,text and files */
    $this->whiteOSSLicenseTable($section, $contents['licenses']['statements']);

    /* Display acknowledgement */
    $this->acknowledgementTable($section);

    /* Display copyright statements and files */
    $heading = "11. Copyrights";
    $this->getRowsAndColumnsForCEI($section, $heading, $contents['copyrights']['statements']);

    /* Display Ecc statements and files */
    $heading = "12. Export Restrictions";
    $this->getRowsAndColumnsForCEI($section, $heading, $contents['ecc']['statements']);

    /* Display IP statements and files */
    $heading = "13. Intellectual Property";
    $this->getRowsAndColumnsForCEI($section, $heading, $contents['ip']['statements']);

    $heading = "14. Irrelevant Files";
    $this->getRowsAndColumnsForIrre($section, $heading, $contents['licensesIrre']['statements']);
    
    /* Footer starts */
    $this->reportFooter($phpWord, $section);

    $fileBase = $SysConf["FOSSOLOGY"]["path"]."/report/";
    if(!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    $fileName = $fileBase. "$packageName"."_clearing_report_".date("D_M_d_m_Y_h_i_s").".docx" ;  
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, "Word2007");
    $objWriter->save($fileName);

    $this->updateReportTable($uploadId, $this->jobId, $fileName);
    return true;
  }

  /**
   * @brief update database with generated report path.
   * @param $uploadId, $jobId, $filename
   */ 
  private function updateReportTable($uploadId, $jobId, $filename)
  {
    $this->dbManager->getSingleRow("INSERT INTO reportgen(upload_fk, job_fk, filepath) VALUES($1,$2,$3)", array($uploadId, $jobId, $filename), __METHOD__);
  }

}

$agent = new ReportAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
