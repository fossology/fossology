<?php
/*
 Author: Daniele Fognini, Shaheem Azmal, anupam.ghosh@siemens.com
 Copyright (C) 2015, Siemens AG

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
 */

define("REPORT_AGENT_NAME", "report");

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Report\LicenseClearedGetter;
use Fossology\Lib\Report\LicenseIrrelevantGetter;
use Fossology\Lib\Report\BulkMatchesGetter;
use Fossology\Lib\Report\XpClearedGetter;
use Fossology\Lib\Report\LicenseMainGetter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;

include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/reportStatic.php");
include_once(__DIR__ . "/sw360Licenses.php");
include_once(__DIR__ . "/sw360Component.php");

class ReportAgent extends Agent
{
  /** @var LicenseClearedGetter  */
  private $licenseClearedGetter;

  /** @var LicenseMainGetter  */
  private $licenseMainGetter;
  /** @var LicenseClearedGetter  */
  
  /** @var cpClearedGetter */
  private $cpClearedGetter;

  /** @var ipClearedGetter */
  private $ipClearedGetter;

  /** @var eccClearedGetter */
  private $eccClearedGetter;

  /** @var LicenseIrrelevantGetter*/
  private $licenseIrrelevantGetter;

  /** @var BulkMatchesGetter  */
  private $bulkMatchesGetter;

  /** @var UploadDao */
  private $uploadDao;

  /** @var LicenseDao */
  private $licenseDao;

  /** @var ClearingDao */
  private $clearingDao;

  /** @var UserDao */
  private $userDao;

  /** @var rowHeight */
  private $rowHeight = 500;

  /** @var tablestyle */
  private $tablestyle = array("borderSize" => 2,
                              "name" => "Arial",
                              "borderColor" => "000000",
                              "cellSpacing" => 5
                             );  

  /** @var thTextStyle */
  private $thStyle = array("bgColor" => "E0E0E0", 
                           "valign" => "center"
                          );

  /** @var thTextStyle */
  private $thTextStyle = array("size" => 12, 
                               "align" => "center", 
                               "bold" => true
                              );

  /** @var licenseColumn */
  private $licenseColumn = array("size" => "10", 
                                 "bold" => true
                                );

  /** @var licenseTextColumn */
  private $licenseTextColumn = array("name" => "Courier New", 
                                     "size" => 10, 
                                     "bold" => false
                                    );

  /** @var filePathColumn */
  private $filePathColumn = array("size" => "10", 
                                  "bold" => false
                                 );
  private $groupBy;
  
  function __construct($groupBy = "content")
  {
    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement", false, "content ilike 'Copyright%'");
    $this->ipClearedGetter = new XpClearedGetter("ip", "skipcontent", true);
    $this->eccClearedGetter = new XpClearedGetter("ecc", "skipcontent", true);
    $this->licenseClearedGetter = new LicenseClearedGetter();
    $this->licenseMainGetter = new LicenseMainGetter();
    $this->bulkMatchesGetter = new BulkMatchesGetter();
    $this->licenseIrrelevantGetter = new LicenseIrrelevantGetter();

    parent::__construct(REPORT_AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get("dao.upload");
    $this->licenseDao = $this->container->get("dao.license");
    $this->clearingDao = $this->container->get("dao.clearing");
    $this->userDao = $this->container->get("dao.user");
    $this->groupBy = $groupBy;
  }

  private function groupStatements(&$ungrupedStatements, $extended, $agentcall)
  {
    $statements = array();
    $findings = array();
    $countLoop = 0;
    $thousandLoop = 0; 
    foreach($ungrupedStatements as $statement) {
      $content = convertToUTF8(htmlspecialchars($statement['content'], ENT_DISALLOWED), false);
      $comments = convertToUTF8($statement['comments'], false);
      $fileName = $statement['fileName'];

      if (!array_key_exists('text', $statement))
      {
        $description = $statement['description'];
        $textfinding = $statement['textfinding'];

        if ($description === null) {
          $text = "";
        } else {
          //$agentcall only have copyright so making it empty for other agents
          if(!empty($textfinding) && empty($agentcall)) 
            $content = $textfinding;
          $text = $description;
        }
      }
      else
      {
        $text = $statement['text'];
      }

      $groupBy = $statement[$this->groupBy];

      if(empty($comments) && array_key_exists($groupBy, $statements))
      {
          $currentFiles = &$statements[$groupBy]['files'];
          if (!in_array($fileName, $currentFiles))
            $currentFiles[] = $fileName;
      }
      else {
        $singleStatement = array(
            "content" => convertToUTF8($content, false),
            "text" => convertToUTF8($text, false),
            "files" => array($fileName)
          );
        if ($extended) {
          $singleStatement["comments"] = convertToUTF8($comments, false);
          $singleStatement["risk"] =  $statement['risk'];
        }

        if (empty($comments)) {
          $statements[$groupBy] = $singleStatement;
        }
        else {
          $statements[] = $singleStatement;
        }
      }
      if(!empty($statement['textfinding']) && !empty($agentcall)){
        $findings[$fileName] = array(
            "content" => convertToUTF8($statement['textfinding'], false),
            "text" => convertToUTF8($text, false),
            "files" => array($fileName)
          );
        if ($extended) {
          $findings[$fileName]["comments"] = convertToUTF8($comments, false);
        }
      }
      //To keep the schedular alive for large files 
      $countLoop += 1;
      if(is_int($countLoop/10000)) {
        $thousandLoop++;
        $this->heartbeat(1);
      }
    }
    if(!empty($findings)){
      $statements = array_merge($findings, $statements);
    }

    arsort($statements);
    $actualHeartbeat = count($statements) - $thousandLoop ; 
    $this->heartbeat($actualHeartbeat);
    return array("statements" => array_values($statements));
  }

  function processUploadId($uploadId)
  {
    $groupId = $this->groupId;
    $userId = $this->userId; 

    $ungrupedStatements = $this->licenseClearedGetter->getUnCleared($uploadId, $groupId);
    $licenses = $this->groupStatements($ungrupedStatements, $extended, $agentCall);
    
    $licensesMain = $this->licenseMainGetter->getCleared($uploadId, $groupId);
    
    $ungrupedStatements = $this->bulkMatchesGetter->getUnCleared($uploadId, $groupId);
    $bulkLicenses = $this->groupStatements($ungrupedStatements, $extended,$agentCall);
    
    $this->licenseClearedGetter->setOnlyComments(true);
    $ungrupedStatements = $this->licenseClearedGetter->getUnCleared($uploadId, $groupId);
    $licenseComments = $this->groupStatements($ungrupedStatements, $extended,$agentCall);
    
    $ungrupedStatements = $this->licenseIrrelevantGetter->getUnCleared($uploadId, $groupId);
    $licensesIrre = $this->groupStatements($ungrupedStatements, $extended,$agentCall); 
    
    $ungrupedStatements = $this->cpClearedGetter->getUnCleared($uploadId, $groupId, true, "copyright");
    $copyrights = $this->groupStatements($ungrupedStatements, true, "copyright");
    
    $ungrupedStatements = $this->eccClearedGetter->getUnCleared($uploadId, $groupId);
    $ecc = $this->groupStatements($ungrupedStatements, $extended,$agentCall);
    
    $ungrupedStatements = $this->ipClearedGetter->getUnCleared($uploadId, $groupId);
    $ip = $this->groupStatements($ungrupedStatements, $extended,$agentCall);

    $contents = array("licenses" => $licenses,
                      "bulkLicenses" => $bulkLicenses,
                      "licenseComments" => $licenseComments,
                      "copyrights" => $copyrights,
                      "ecc" => $ecc,
                      "ip" => $ip,
                      "licensesIrre" => $licensesIrre,
                      "licensesMain" => $licensesMain
                     );
    $this->writeReport($contents, $uploadId, $groupId, $userId);
    return true;
  }

  /**
   * @brief setting default heading styles and paragraphstyles
   * @param PhpWord $phpWord
   * @param int $timestamp
   * @param int $userId
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
    $properties->setCompany("Siemens AG");
    $properties->setTitle("Clearing Report");
    $properties->setDescription("OSS clearing report by FOSSologyNG tool");
    $properties->setSubject("Copyright (C) ".date("Y", $timestamp).", Siemens AG");
  }

  /**
   * @brief identifiedGlobalLicenses() copy identified global licenses
   * @param array $contents 
   * @return array $contents with identified global license path
   */        
  function identifiedGlobalLicenses($contents)
  {
    $lenLicenses = count($contents["licenses"]["statements"]);
    $lenLicensesMain = count($contents["licensesMain"]["statements"]) ;
    for($j=0; $j<$lenLicensesMain; $j++){
      for($i=0; $i<$lenLicenses; $i++){
        if(!strcmp($contents["licenses"]["statements"][$i]["content"], $contents["licensesMain"]["statements"][$j]["content"]))
        {
          $contents["licensesMain"]["statements"][$j]["files"] = $contents["licenses"]["statements"][$i]["files"];
          unset($contents["licenses"]["statements"][$i]);
        }
      }
    }
    return $contents;
  }

  /**
   * @brief Design the summaryTable of the report
   * @param Section $section
   * @param string $uploadId 
   * @param string $userName
   * @param array mainLicenses
   * @param int $timestamp
   */        
  private function summaryTable(Section $section, $uploadId, $userName, $mainLicenses, $timestamp)
  {         
    $cellRowContinue = array("vMerge" => "continue");
    $firstRowStyle = array("size" => 14, "bold" => true);
    $firstRowStyle1 = array("size" => 12, "bold" => true);
    $firstRowStyle2 = array("size" => 12, "bold" => false);
    $checkBoxStyle = array("size" => 10, "bold" => false);

    $cellRowSpan = array("vMerge" => "restart", "valign" => "top");
    $cellColSpan = array("gridSpan" => 3, "valign" => "center");

    $rowWidth = 200;
    $cellFirstLen = 2500;
    $cellSecondLen = 3800;
    $cellThirdLen = 5500; 

    if(!empty($mainLicenses)){
      foreach($mainLicenses as $mainLicense){
        $allMainLicenses .= $mainLicense["content"].", ";
      }
      $allMainLicenses = rtrim($allMainLicenses, ", ");
    }
    
    $cComponent = new Sw360Component();
    $newSw360Component= $cComponent->processGetComponent($uploadId);
    
    $table = $section->addTable($this->tablestyle);
    
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellColSpan)->addText(htmlspecialchars(" Clearing report for OSS component"), $firstRowStyle, "pStyle");
    
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" Clearing Information"), $firstRowStyle, "pStyle");
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Department"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars(" FOSSologyNG Generation"), $firstRowStyle2, "pStyle");
    
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Type"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars(" OSS clearing only"), $firstRowStyle2, "pStyle");

    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Prepared by"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars(" ".date("Y/m/d", $timestamp)."  ".$userName."  <department>"), $firstRowStyle2, "pStyle");
      
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Reviewed by (opt.)"),$firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars(" <date> <last name, first name> <department>"), $firstRowStyle2, "pStyle");

    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Released by"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars(" FOSSologyNG Generation"), $firstRowStyle2, "pStyle");

    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Clearing Status"), $firstRowStyle1, "pStyle");
    $cell = $table->addCell($cellThirdLen);
    $cell->addCheckBox("inprogress", htmlspecialchars(" in progress"), $checkBoxStyle, "pStyle");
    $cell->addCheckBox("release", htmlspecialchars(" release"), $checkBoxStyle, "pStyle");

    if(!empty($newSw360Component)){
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" Component Information"), $firstRowStyle, "pStyle");
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Community"), $firstRowStyle1, "pStyle");
      if(!empty($newSw360Component["Community"])){
        $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Community"]), null, "pStyle");
      }
      else{
        $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), $firstRowStyle2, "pStyle");
      }
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowContinue);
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Component"), $firstRowStyle1, "pStyle");

      if(!empty($newSw360Component["Component"])){
        $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Component"]), null, "pStyle");
      }
      else{
        $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), null, "pStyle");
      }
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowContinue);
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Version"), $firstRowStyle1, "pStyle");
    
      if(!empty($newSw360Component["Version"])){
        $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Version"]), null, "pStyle");
      }
      else{
        $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), null, "pStyle");
      }
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowContinue);
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Source URL"), $firstRowStyle1, "pStyle");
   
      if(!empty($newSw360Component["Source URL"])){
        $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Source URL"]), null, "pStyle");
      }
      else{
        $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), null, "pStyle");
      }
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowContinue);
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Release date"), $firstRowStyle1, "pStyle");
    
      if(!empty($newSw360Component["Release date"])){
        $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Release date"]), null, "pStyle");
      }
      else{
        $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), null, "pStyle");
      }
    }
    else{
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowContinue);
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Component"), $firstRowStyle1, "pStyle");
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), null, "pStyle");
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowContinue);
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Version"), $firstRowStyle1, "pStyle");
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), null, "pStyle");
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowContinue);
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Source URL"), $firstRowStyle1, "pStyle");
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), null, "pStyle");
      $table->addRow($rowWidth);
      $table->addCell($cellFirstLen, $cellRowContinue);
      $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Release date"), $firstRowStyle1, "pStyle");
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("N.A"), null, "pStyle");
    }

    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Main license(s)"), $firstRowStyle1, "pStyle");
    if(!empty($allMainLicenses)){
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("$allMainLicenses."), $firstRowStyle2, "pStyle");
    }
    else{
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("Main License(s) Not selected."), $firstRowStyle2, "pStyle");
    }
    $section->addTextBreak();
  }

  /**
   * @param Section $section 
   * @param array $mainLicenses 
   */ 
  private function globalLicenseTable(Section $section, $mainLicenses)
  {
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;

    $section->addTitle(htmlspecialchars("Main Licenses"), 2);

    $table = $section->addTable($this->tablestyle);
    $table->addRow($this->rowHeight);
    $table->addCell($firstColLen, $this->thStyle)->addText("License", $this->thTextStyle);
    $table->addCell($secondColLen, $this->thStyle)->addText("License text", $this->thTextStyle);
    $table->addCell($thirdColLen, $this->thStyle)->addText("File path", $this->thTextStyle);
    
    if(!empty($mainLicenses)){
      foreach($mainLicenses as $licenseMain){
        if($licenseMain["risk"] == "4" || $licenseMain["risk"] == "5")
          $styleColumn = array("bgColor" => "F9A7B0");
        elseif($licenseMain["risk"] == "2" || $licenseMain["risk"] == "3")
          $styleColumn = array("bgColor" => "FEFF99");
        else
          $styleColumn = array("bgColor" => "FFFFFF");
        $table->addRow($this->rowHeight);
        $cell1 = $table->addCell($firstColLen, $styleColumn);
        $cell1->addText(htmlspecialchars($licenseMain["content"], ENT_DISALLOWED), $this->licenseColumn, "pStyle");
        $cell2 = $table->addCell($secondColLen);
        // replace new line character
        $licenseText = str_replace("\n", "<w:br/>", htmlspecialchars($licenseMain["text"], ENT_DISALLOWED));
        $cell2->addText($licenseText, $this->licenseTextColumn, "pStyle");
        if(!empty($licenseMain["files"])){
          $cell3 = $table->addCell($thirdColLen, $styleColumn);
          foreach($licenseMain["files"] as $fileName){
            $cell3->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
          }
        }else{
          $cell3 = $table->addCell($thirdColLen, $styleColumn)->addText("");
        }
      }
    }
    else{
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
   * @param Section section
   * @param $title
   * @param $licenses
   * @param $rowHead
   */ 
  private function bulkLicenseTable(Section $section, $title, $licenses, $rowHead="")
  {
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;  

    $section->addTitle(htmlspecialchars($title), 2);
    $table = $section->addTable($this->tablestyle);
    $table->addRow($this->rowHeight);
    $table->addCell($firstColLen, $this->thStyle)->addText("License", $this->thTextStyle);
    if(empty($rowHead)){
      $table->addCell($secondColLen, $this->thStyle)->addText("License text", $this->thTextStyle);
    }else{
      $table->addCell($secondColLen, $this->thStyle)->addText($rowHead, $this->thTextStyle);
    } 
    $table->addCell($thirdColLen, $this->thStyle)->addText("File path", $this->thTextStyle);

    if(!empty($licenses)){
      foreach($licenses as $licenseStatement){
        $table->addRow($this->rowHeight);
        $cell1 = $table->addCell($firstColLen, null, "pStyle"); 
        $cell1->addText(htmlspecialchars($licenseStatement["content"], ENT_DISALLOWED), $this->licenseColumn, "pStyle");
        $cell2 = $table->addCell($secondColLen, "pStyle"); 
        // replace new line character
        $licenseText = str_replace("\n", "<w:br/>", htmlspecialchars($licenseStatement["text"], ENT_DISALLOWED));
        $cell2->addText($licenseText, $this->licenseTextColumn, "pStyle");
        $cell3 = $table->addCell($thirdColLen, null, "pStyle");
        foreach($licenseStatement["files"] as $fileName){ 
          $cell3->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
        }
      }
    }else{
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText("");
      $table->addCell($secondColLen)->addText("");
      $table->addCell($thirdColLen)->addText("");
    }
    $section->addTextBreak(); 
  }
  
  /**
   * @brief This function lists out the red, white & yellow licenses
   * @param Section section
   * @param $title
   * @param $licenses
   * @param $riskarray
   */ 
  private function licensesTable(Section $section, $title, $licenses, $riskarray)
  {
    $firstColLen = 2000;
    $secondColLen = 9500;
    $thirdColLen = 4000;

    $section->addTitle(htmlspecialchars($title), 2);

    $table = $section->addTable($this->tablestyle);
    $table->addRow($this->rowHeight);
    $table->addCell($firstColLen, $this->thStyle)->addText("License", $this->thTextStyle);

    if(empty($rowHead)){  
      $table->addCell($secondColLen, $this->thStyle)->addText("License text", $this->thTextStyle);
    }
    else{  
      $table->addCell($secondColLen, $this->thStyle)->addText($rowHead, $this->thTextStyle);
    }
    $table->addCell($thirdColLen, $this->thStyle)->addText("File path", $this->thTextStyle);

    if(!empty($licenses)){
      foreach($licenses as $licenseStatement){
        if(in_array($licenseStatement['risk'], $riskarray['riskLevel'])){ 
          $table->addRow($this->rowHeight);
          $cell1 = $table->addCell($firstColLen, $riskarray['color']); 
          $cell1->addText(htmlspecialchars($licenseStatement["content"], ENT_DISALLOWED), $this->licenseColumn, "pStyle");
          $cell2 = $table->addCell($secondColLen); 
          // replace new line character
          $licenseText = str_replace("\n", "<w:br/>", htmlspecialchars($licenseStatement["text"], ENT_DISALLOWED));
          $cell2->addText($licenseText, $this->licenseTextColumn, "pStyle");
          $cell3 = $table->addCell($thirdColLen, $riskarray['color']);
          foreach($licenseStatement["files"] as $fileName){ 
            $cell3->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
          }
        }else{ continue; }
      }
    }else{
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText("");
      $table->addCell($secondColLen)->addText("");
      $table->addCell($thirdColLen)->addText("");
    }
    $section->addTextBreak(); 
  }
  
  /**
   * @param Section $section
   */ 
  private function acknowledgementTable(Section $section)
  {
    $firstColLen = 3500;
    $secondColLen = 8000;
    $thirdColLen = 4000;
    
    $section->addTitle(htmlspecialchars("Acknowledgements"), 2);
    $table = $section->addTable($this->tablestyle);
    $table->addRow($this->rowHeight);
    $cell1 = $table->addCell($firstColLen, $this->thStyle); 
    $cell1->addText(htmlspecialchars("ID of acknowledgements"), $this->thTextStyle);
    $cell2 = $table->addCell($secondColLen, $this->thStyle); 
    $cell2->addText(htmlspecialchars("Text of acknowledgements"), $this->thTextStyle);
    $cell3 = $table->addCell($thirdColLen, $this->thStyle);
    $cell3->addText(htmlspecialchars("Reference to the license"), $this->thTextStyle);

    $table->addRow($this->rowHeight);
    $table->addCell($firstColLen)->addText("");
    $table->addCell($secondColLen)->addText("");
    $table->addCell($thirdColLen)->addText("");

    $section->addTextBreak(); 
  }


  /**
   * @brief copyright or ecc or ip table.
   * @param Section $section 
   * @param string $title 
   * @param array $statementsCEI
   */
  private function getRowsAndColumnsForCEI(Section $section, $title, $statementsCEI)
  {
    $smallRowHeight = 50;
    $firstColLen = 6500;
    $secondColLen = 5000;
    $thirdColLen = 4000;

    $section->addTitle(htmlspecialchars($title), 2);

    $table = $section->addTable($this->tablestyle);

    $table->addRow($this->rowHeight);
    $table->addCell($firstColLen, $this->thStyle)->addText("Statements", $this->thTextStyle);
    $table->addCell($secondColLen, $this->thStyle)->addText("Comments", $this->thTextStyle);
    $table->addCell($thirdColLen, $this->thStyle)->addText("File path", $this->thTextStyle);
    if(!empty($statementsCEI)){
      foreach($statementsCEI as $statements){
        $table->addRow($smallRowHeight);
        $cell1 = $table->addCell($firstColLen); 
        $cell1->addText(htmlspecialchars($statements['content'], ENT_DISALLOWED), $this->licenseTextColumn, "pStyle");
        $cell2 = $table->addCell($secondColLen);
        $cell2->addText(htmlspecialchars($statements['comments'], ENT_DISALLOWED), $this->licenseTextColumn, "pStyle");
        $cell3 = $table->addCell($thirdColLen);
        foreach($statements['files'] as $fileName){ 
          $cell3->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
        }
      }
    }else{
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText("");
      $table->addCell($secondColLen)->addText("");
      $table->addCell($thirdColLen)->addText("");
    }
    $section->addTextBreak(); 
  }

  /**
   * @brief irrelavant files in report.
   * @param Section $section 
   * @param String $title 
   * @param array $licensesIrre
   */
  private function getRowsAndColumnsForIrre(Section $section, $title, $licensesIrre)
  {
    $firstColLen = 6500;
    $secondColLen = 9000;

    $section->addTitle(htmlspecialchars($title), 2);

    $table = $section->addTable($this->tablestyle);
    $table->addRow($this->rowHeight);
    $cell = $table->addCell($firstColLen, $this->thStyle)->addText("Path", $this->thTextStyle);
    $cell = $table->addCell($secondColLen, $this->thStyle)->addText("Files", $this->thTextStyle);
    if(!empty($licensesIrre)){    
      foreach($licensesIrre as $statements){
        $table->addRow($rowWidth, "pStyle");
        $cell1 = $table->addCell($firstColLen);
        $cell1->addText(htmlspecialchars($statements['content']),null, "pStyle");
        $cell2 = $table->addCell($secondColLen);
        foreach($statements['files'] as $fileName){
          $cell2->addText(htmlspecialchars($fileName), $this->filePathColumn, "pStyle");
        }
      }
    }else{
      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen, "pStyle")->addText("");
      $table->addCell($secondColLen, "pStyle")->addText("");
    }
    $section->addTextBreak();
  }

  /**
   * @brief license histogram into report.
   * @param Section $section 
   * @param ItemTreeBounds $parentItem 
   * @param int $groupId
   */
  private function licenseHistogram(Section $section, $parentItem, $groupId)
  {
    $firstColLen = 2000;
    $secondColLen = 2000;
    $thirdColLen = 5000;

    $section->addTitle(htmlspecialchars("Results of License Scan"), 2);

    $table = $section->addTable($this->tablestyle);
    $table->addRow($this->rowHeight);

    $table->addCell($firstColLen, $this->thStyle)->addText("Scanner count", $this->thTextStyle);
    $table->addCell($secondColLen, $this->thStyle)->addText("Edited count", $this->thTextStyle);
    $table->addCell($thirdColLen, $this->thStyle)->addText("License name", $this->thTextStyle);

    $scannerLicenseHistogram = $this->licenseDao->getLicenseHistogram($parentItem);
    $editedLicensesHist = $this->clearingDao->getClearedLicenseIdAndMultiplicities($parentItem, $groupId);
    $totalLicenses = array_unique(array_merge(array_keys($scannerLicenseHistogram), array_keys($editedLicensesHist)));

    foreach($totalLicenses as $licenseShortName){
      $count = $scannerLicenseHistogram[$licenseShortName]['unique'];
      $editedCount = array_key_exists($licenseShortName, $editedLicensesHist) ? $editedLicensesHist[$licenseShortName]['count'] : 0;

      $table->addRow($this->rowHeight);
      $table->addCell($firstColLen)->addText($count, "pStyle");
      $table->addCell($secondColLen)->addText($editedCount, "pStyle");
      $table->addCell($thirdColLen)->addText(htmlspecialchars($licenseShortName), "pStyle");
    }
    $section->addTextBreak();
  }


  /**
   * @param array $contents
   * @param int $uploadId
   * @param int $groupId
   * @param int $userId
   */
  private function writeReport($contents, $uploadId, $groupId, $userId)
  {
    global $SysConf;

    $packageName = $this->uploadDao->getUpload($uploadId)->getFilename();
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
    $jobInfo = $this->dbManager->getSingleRow("SELECT extract(epoch from jq_starttime) ts FROM jobqueue WHERE jq_pk=$1", array($this->jobId));
    $timestamp = $jobInfo['ts'];
    $userName = $this->userDao->getUserName($userId);

    /* Applying document properties and styling */
    $this->documentSettingsAndStyles($phpWord, $timestamp, $userName);

    /* Creating document layout */
    $section = $phpWord->addSection($docLayout);

    $sR = new ReportStatic($timestamp);
    
    $licenseObli = new Sw360License();
    
    $groupName = $this->userDao->getGroupNameById($groupId);
    
    $results = $licenseObli->sw360GetLicense($uploadId, $groupName, $contents['licenses']['statements']);

    /* Header starts */
    $sR->reportHeader($section);

    /* Main heading starts*/
    $sR->reportTitle($section);

    $contents = $this-> identifiedGlobalLicenses($contents);
    
    /* Summery table */
    $this->summaryTable($section, $uploadId, $userName, $contents['licensesMain']['statements'], $timestamp);

    /* clearing protocol change log table */
    $sR->clearingProtocolChangeLogTable($section);
    
    /* Functionality table */
    $sR->functionalityTable($section);

    /* Assessment summery table */
    $sR->assessmentSummaryTable($section);

    /* Todoinfo table */
    $sR->todoTable($section);

    /* Todoobligation table */
    $sR->todoObliTable($section, $results);

    /* Todoobligation list */
    $sR->todoObliList($section);
 
    /* For other todolist */
    $sR->forOtherTodos($section, count($contents['ecc']['statements']));

    /* Basic for clearing report */
    //$sR->basicForClearingReport($section);

    /* Display scan results and edited results */
    $this->licenseHistogram($section, $parentItem, $groupId);

    /* Display global licenses */
    $this->globalLicenseTable($section, $contents['licensesMain']['statements']);

    /* Display licenses(red) name,text and files */
    $heading = "Other OSS Licenses (red) - strong copy left Effect or Do not Use Licenses";
    $redLicense = array("color" => array("bgColor" => "F9A7B0"), "riskLevel" => array("5", "4")); 
    $this->licensesTable($section, $heading, $contents['licenses']['statements'], $redLicense);

    /* Display licenses(yellow) name,text and files */
    $heading = "Other OSS Licenses (yellow) - additional obligations to common rules";
    $yellowLicense = array("color" => array("bgColor" => "FEFF99"), "riskLevel" => array("3", "2"));
    $this->licensesTable($section, $heading, $contents['licenses']['statements'], $yellowLicense);

    /* Display licenses(white) name,text and files */
    $heading = "Other OSS Licenses (white) - only common rules";  
    $whiteLicense = array("color" => array("bgColor" => "FFFFFF"), "riskLevel" => array("", "0", "1"));
    $this->licensesTable($section, $heading, $contents['licenses']['statements'], $whiteLicense);

    /* Display acknowledgement */
    $this->acknowledgementTable($section);

    /* Display copyright statements and files */
    $heading = "Copyrights";
    $this->getRowsAndColumnsForCEI($section, $heading, $contents['copyrights']['statements']);

    /* Display Ecc statements and files */
    $heading = "Export Restrictions";
    $section->addBookmark("eccInternalLink");
    $this->getRowsAndColumnsForCEI($section, $heading, $contents['ecc']['statements']);

    /* Display IP statements and files */
    $heading = "Intellectual Property";
    $this->getRowsAndColumnsForCEI($section, $heading, $contents['ip']['statements']);

    /* Display Bulk findings name,text and files */
    $heading = "Bulk Findings";
    $this->bulkLicenseTable($section, $heading, $contents['bulkLicenses']['statements']);

    /* Display NON Functional Licenses license files */
    $heading = "Non Functional Licenses";
    $sR->getNonFunctionalLicenses($section, $heading);


    /* Display irrelavant license files */
    $heading = "Irrelevant Files";
    $this->getRowsAndColumnsForIrre($section, $heading, $contents['licensesIrre']['statements']);

    /* Display comments entered for report */
    $heading = "Notes";  
    $rowHead = "Comment Entered";
    $this->bulkLicenseTable($section, $heading, $contents['licenseComments']['statements'], $rowHead);


    /* Footer starts */
    $sR->reportFooter($phpWord, $section);

    $fileBase = $SysConf["FOSSOLOGY"]["path"]."/report/";
    if(!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    $fileName = $fileBase. "$packageName"."_clearing_report_".date("D_M_d_m_Y_h_i_s").".docx" ;  
    $objWriter = IOFactory::createWriter($phpWord, "Word2007");
    $objWriter->save($fileName);

    $this->updateReportTable($uploadId, $this->jobId, $fileName);
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
