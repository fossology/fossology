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


define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__ . "/version.php");

require_once("$MODDIR/lib/php/Report/getClearedXp.php");
require_once("$MODDIR/lib/php/Report/getClearedProto.php");
class ReadmeOssAgent extends Agent
{

  /** @var  LicenseClearedGetterProto  */
  private $licenseClearedGetter;

  /** @var XpClearedGetter */
  private $cpClearedGetter;
  /** @var XpClearedGetter */
  private $ipClearedGetter;
  /** @var XpClearedGetter */
  private $eccClearedGetter;


  function __construct()
  {
    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement", false, "content ilike 'Copyright%'");
    $this->ipClearedGetter = new XpClearedGetter("ip", null, true);
    $this->eccClearedGetter = new XpClearedGetter("ecc", null, true);
    $this->licenseClearedGetter = new LicenseClearedGetterProto();

    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);


  }

  function processUploadId($uploadId)
  {

    $userId = $this->userId;

    $licenses  = $this->licenseClearedGetter->getCleared($uploadId, $userId);
    $copyrights = $this->cpClearedGetter->getCleared($uploadId,$userId);
    $ecc  = $this->eccClearedGetter ->getCleared($uploadId,$userId);
    $ip  = $this->ipClearedGetter->getCleared($uploadId,$userId);

    $contents = array('licenses' => $licenses,
                      'copyrights' => $copyrights,
                      'ecc' => $ecc,
                      'ip' => $ip
    );

    $this->writeReport($contents, $uploadId);

    return true;
  }

  private function writeReport($contents, $uploadId)
  {
    global $SysConf;

    $fileBase=$SysConf['FOSSOLOGY']['path']."/report/";
    $filename =$fileBase. "ReadMeOss_".$this->jobId.".txt" ;

    if(!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }

    $message = $this->generateReport($contents);

    file_put_contents($filename, $message );

    $this->updateReportTable($uploadId, $this->jobId, $filename );

  }


  private function updateReportTable($uploadId, $jobId, $filename){
   $result=$this->dbManager->getSingleRow("INSERT INTO reportgen(upload_fk, job_fk, filepath) VALUES($1,$2,$3)",array($uploadId, $jobId, $filename),__METHOD__);
   $this->dbManager->freeResult($result);
  }

  private function generateReport($contents)
  {
    //TODO fill output with the correct ReadMeOss format
    $output = print_r($contents, true);

    return $output;
  }


}

$agent = new ReadmeOssAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->bail(0);