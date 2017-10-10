<?php
/*
 Copyright (C) 2017, Siemens AG

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

use PhpOffice\PhpWord\Element\Section;
use \PhpOffice\PhpWord\Style;

class ReportSummary
{
  /** @var uploadDao */
  private $uploadDao;

  /** @var tablestyle */
  private $tablestyle = array("borderSize" => 2,
                              "name" => "Arial",
                              "borderColor" => "000000",
                              "cellSpacing" => 5
                             );  

  /** @var subHeadingStyle */
  private $subHeadingStyle = array("size" => 9, 
                                   "align" => "center",
                                   "bold" => true
                                  );
  
  public function __construct()
  {
    global $container; 
    $this->uploadDao = $container->get('dao.upload');
  }

  /**
  * @brief accumulateLicenses() remove the duplicate licenses.
  * @param array $licenses.
  * @return comma seaparated license shortname.
  */        
  private function accumulateLicenses($licenses)
  {
    if(!empty($licenses)){
      $licenses = array_unique(array_column($licenses, 'content'));
      foreach($licenses as $otherLicenses){
        $allOtherLicenses .= $otherLicenses.", ";
      }
      $allOtherLicenses = rtrim($allOtherLicenses, ", ");
    }
    return $allOtherLicenses;
  }
    
   /**
   * @brief Design the summaryTable of the report
   * @param Section $section
   * @param string $uploadId 
   * @param string $userName
   * @param array mainLicenses
   * @param int $timestamp
   */        
  function summaryTable(Section $section, $uploadId, $userName, $mainLicenses, $licenses, $histLicenses, $otherStatement, $timestamp, $groupName, $packageUri)
  {
    $cellRowContinue = array("vMerge" => "continue");
    $firstRowStyle = array("size" => 14, "bold" => true);
    $firstRowStyle1 = array("size" => 12, "bold" => true);

    $cellRowSpan = array("vMerge" => "restart", "valign" => "top");
    $cellColSpan = array("gridSpan" => 3, "valign" => "center");

    $rowWidth = 200;
    $rowWidth2 = 400;
    $cellFirstLen = 2500;
    $cellSecondLen = 3800;
    $cellThirdLen = 5500; 

    $allMainLicenses = $this->accumulateLicenses($mainLicenses);
    $allOtherLicenses = $this->accumulateLicenses($licenses);

    if(!empty($histLicenses)){
      foreach($histLicenses as $histLicense){
        $allHistLicenses .= $histLicense["licenseShortname"].", ";
      }
      $allHistLicenses = rtrim($allHistLicenses, ", ");
    }
    
    $newSw360Component = array();
    $table = $section->addTable($this->tablestyle);
    
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellColSpan)->addText(htmlspecialchars(" OSS Component Clearing report"), $firstRowStyle, "pStyle");
    
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" Clearing Information"), $firstRowStyle, "pStyle");
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Department"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars(" FOSSology Generation"), null, "pStyle");
    
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Prepared by"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars(" ".date("Y/m/d", $timestamp)."  ".$userName." (".$groupName.") "), null, "pStyle");      
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Reviewed by (opt.)"),$firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars($otherStatement['ri_reviewed']), null, "pStyle");

    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Report release date"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars($otherStatement['ri_report_rel']), null, "pStyle");

    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" Component Information"), $firstRowStyle, "pStyle");
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Community"), $firstRowStyle1, "pStyle");
    if(!empty($newSw360Component["Community"])){
      $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Community"]), null, "pStyle");
    }
    else{
      $table->addCell($cellThirdLen)->addText(htmlspecialchars($otherStatement['ri_community']), null, "pStyle");
    }
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Component"), $firstRowStyle1, "pStyle");

    if(!empty($newSw360Component["Component"])){
      $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Component"]), null, "pStyle");
    }
    else{
      $table->addCell($cellThirdLen)->addText(htmlspecialchars($otherStatement['ri_component']), null, "pStyle");
    }
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Version"), $firstRowStyle1, "pStyle");
    
    if(!empty($newSw360Component["Version"])){
      $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Version"]), null, "pStyle");
    }
    else{
      $table->addCell($cellThirdLen)->addText(htmlspecialchars($otherStatement['ri_version']), null, "pStyle");
    }
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Component hash (SHA-1)"), $firstRowStyle1, "pStyle");
      
    $componentHash = $this->uploadDao->getUploadHashes($uploadId);

    $table->addCell($cellThirdLen)->addText(htmlspecialchars($componentHash["sha1"]), null, "pStyle");
     
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Release date"), $firstRowStyle1, "pStyle");
    
    if(!empty($newSw360Component["Release date"])){
      $table->addCell($cellThirdLen)->addText(htmlspecialchars($newSw360Component["Release date"]), null, "pStyle");
    }
    else{
      $table->addCell($cellThirdLen)->addText(htmlspecialchars($otherStatement['ri_release_date']), null, "pStyle");
    }
    
    $table->addRow($rowWidth);
    $table->addCell($cellFirstLen, $cellRowContinue);
    $table->addCell($cellSecondLen)->addText(htmlspecialchars(" Main license(s)"), $firstRowStyle1, "pStyle");
    if(!empty($allMainLicenses)){
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("$allMainLicenses."), null, "pStyle");
    }
    else{
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("Main License(s) Not selected."), null, "pStyle");
    }

    $table->addRow($rowWidth2);
    $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" "), $firstRowStyle, "pStyle");
    $table->addCell($cellSecondLen)->addText(htmlspecialchars("Other license(s)"), $firstRowStyle1, "pStyle");
    if(!empty($allOtherLicenses)){
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("$allOtherLicenses."), null, "pStyle");
    }
    else{
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("License(s) Not Identified."), null, "pStyle");
    }

    $table->addRow($rowWidth2);
    $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" "), $firstRowStyle, "pStyle");
    $table->addCell($cellSecondLen)->addText(htmlspecialchars("Fossology Upload/Package Link"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars(" ".$packageUri.""), null, "pStyle");

    $table->addRow($rowWidth2);
    $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" "), $firstRowStyle, "pStyle");
    $table->addCell($cellSecondLen)->addText(htmlspecialchars("SW360 Portal Link"), $firstRowStyle1, "pStyle");
    $table->addCell($cellThirdLen)->addText(htmlspecialchars($otherStatement['ri_sw360_link']), null, "pStyle");
    
    $table->addRow($rowWidth2);
    $table->addCell($cellFirstLen, $cellRowSpan)->addText(htmlspecialchars(" "), $firstRowStyle, "pStyle");
    $table->addCell($cellSecondLen)->addText(htmlspecialchars("Result of License Scan"), $firstRowStyle1, "pStyle");
    if(!empty($allHistLicenses))
    {
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("$allHistLicenses."), null, "pStyle");
    }
    else{
      $table->addCell($cellThirdLen)->addText(htmlspecialchars("No License found by the Scanner"), null, "pStyle");
    }

    $section->addTextBreak();
    $section->addTextBreak();
  } 

}
