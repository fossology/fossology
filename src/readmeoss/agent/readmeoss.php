<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("AGENT_NAME", "readmeoss");

use Fossology\Lib\Agent\Agent;
use Fossology\Reportgen\LicenseClearedGetterProto;
use Fossology\Reportgen\XpClearedGetter;


include_once(__DIR__ . "/version.php");

require_once("$MODDIR/lib/php/Report/getClearedXp.php");
require_once("$MODDIR/lib/php/Report/getClearedProto.php");

class ReadmeOssAgent extends Agent
{

  /** @var  LicenseClearedGetterProto  */
  private $licenseClearedGetter;

  /** @var XpClearedGetter */
  private $cpClearedGetter;


  function __construct()
  {
    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement", false, "content ilike 'Copyright%'");
    $this->licenseClearedGetter = new LicenseClearedGetterProto();

    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);
  }

  function processUploadId($uploadId)
  {
    $userId = $this->userId;

    $licenses = $this->licenseClearedGetter->getCleared($uploadId, $userId);
    $copyrights = $this->cpClearedGetter->getCleared($uploadId, $userId);

    $contents = array('licenses' => $licenses,
                      'copyrights' => $copyrights
    );

    $this->writeReport($contents, $uploadId);

    return true;
  }

  private function writeReport($contents, $uploadId)
  {
    global $SysConf;

    // TODO use uploadDao
    $row = $this->dbManager->getSingleRow("SELECT upload_filename FROM upload WHERE upload_pk = $1",array($uploadId),__METHOD__);
    $packageName = $row['upload_filename'];

    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";
    $fileName = $fileBase. "ReadMe_OSS_".$packageName.".txt" ;

    if(!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }

    $message = $this->generateReport($contents, $packageName);

    file_put_contents($fileName, $message);

    $this->updateReportTable($uploadId, $this->jobId, $fileName);
  }

  private function updateReportTable($uploadId, $jobId, $filename){
   $this->dbManager->getSingleRow("INSERT INTO reportgen(upload_fk, job_fk, filepath) VALUES($1,$2,$3)", array($uploadId, $jobId, $filename), __METHOD__);
  }

  private function generateReport($contents, $Package_Name)
  {
    $i = 0;
    $separator1 = "=======================================================================================================================";
    $separator2 = "-----------------------------------------------------------------------------------------------------------------------";
    $Break = "\r\n\r\n";

    $output  = "";
    $output .= $separator1;
    $output .= $Break;
    $output .= $Package_Name;
    $output .= $Break;
    foreach($contents['licenses']['statements'] as $licenseStatement){
      $output .= $licenseStatement['text'];
      $output .= $Break;
      $output .= $separator2;
      $output .= $Break;
      $i++;
    }
    $j = 0;
    $copyrights = "";
    foreach($contents['copyrights']['statements'] as $copyrightStatement){
      $copyrights .= $copyrightStatement['content']."\r\n";
      $j++;
    }
    if(empty($j)){
      $output .= "<Copyright notices>";
      $output .= $Break;
      $output .= "<notices>";
    }else{
       $output .= "Copyright notices";
       $output .= $Break; 
       $output .= $copyrights;
    }
    return $output;
  }


}

$agent = new ReadmeOssAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->bail(0);
