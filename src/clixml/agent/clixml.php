<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\CliXml;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Package\ComponentType;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Report\LicenseClearedGetter;
use Fossology\Lib\Report\LicenseDNUGetter;
use Fossology\Lib\Report\LicenseIrrelevantGetter;
use Fossology\Lib\Report\LicenseMainGetter;
use Fossology\Lib\Report\ObligationsGetter;
use Fossology\Lib\Report\OtherGetter;
use Fossology\Lib\Report\XpClearedGetter;
use Fossology\Lib\Report\ReportUtils;
use Twig\Environment;

include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/services.php");

class CliXml extends Agent
{

  const OUTPUT_FORMAT_KEY = "outputFormat";
  const DEFAULT_OUTPUT_FORMAT = "clixml";
  const AVAILABLE_OUTPUT_FORMATS = "xml";
  const UPLOAD_ADDS = "uploadsAdd";

  /** @var array $additionalUploads
   * Array of addtional uploads
   */
  private $additionalUploads = [];

  /** @var UploadDao */
  private $uploadDao;

  /** @var DbManager */
  protected $dbManager;

  /** @var LicenseDao */
  protected $licenseDao;

  /** @var Environment */
  protected $renderer;

  /** @var string */
  protected $uri;

  /** @var string */
  protected $packageName;

  /** @var XpClearedGetter $cpClearedGetter
   * Copyright clearance object
   */
  private $cpClearedGetter;

  /** @var XpClearedGetter $ipraClearedGetter
   * IPRA clearance object
   */
  private $ipraClearedGetter;

  /** @var XpClearedGetter $eccClearedGetter
   * ECC clearance object
   */
  private $eccClearedGetter;
  /** @var LicenseDNUGetter $licenseDNUGetter
   * LicenseDNUGetter object
   */
  private $licenseDNUGetter;

  /** @var ReportUtils $reportutils
   * ReportUtils object
   */
  private $reportutils;

  /** @var string */
  protected $outputFormat = self::DEFAULT_OUTPUT_FORMAT;

  function __construct()
  {
    $args = getopt("", array(self::UPLOAD_ADDS.'::'));

    if (array_key_exists(self::UPLOAD_ADDS, $args)) {
      $uploadsString = $args[self::UPLOAD_ADDS];
      if (!empty($uploadsString)) {
          $this->additionalUploads = explode(',', $uploadsString);
      }
    }

    parent::__construct('clixml', AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->dbManager = $this->container->get('db.manager');
    $this->licenseDao = $this->container->get('dao.license');
    $this->renderer = $this->container->get('twig.environment');
    $this->renderer->setCache(false);

    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement");
    $this->eccClearedGetter = new XpClearedGetter("ecc", "ecc");
    $this->ipraClearedGetter = new XpClearedGetter("ipra", "ipra");
    $this->licenseIrrelevantGetter = new LicenseIrrelevantGetter();
    $this->licenseIrrelevantGetterComments = new LicenseIrrelevantGetter(false);
    $this->licenseDNUGetter = new LicenseDNUGetter();
    $this->licenseDNUCommentGetter = new LicenseDNUGetter(false);
    $this->licenseClearedGetter = new LicenseClearedGetter();
    $this->licenseMainGetter = new LicenseMainGetter();
    $this->obligationsGetter = new ObligationsGetter();
    $this->otherGetter = new OtherGetter();
    $this->reportutils = new ReportUtils();
    $this->agentSpecifLongOptions[] = self::UPLOAD_ADDS.':';
    $this->agentSpecifLongOptions[] = self::OUTPUT_FORMAT_KEY.':';
  }

  /**
   * @param string[] $args
   * @param string $key1
   * @param string $key2
   *
   * @return string[] $args
   */
  protected function preWorkOnArgsFlp($args,$key1,$key2)
  {
    $needle = ' --'.$key2.'=';
    if (array_key_exists($key1,$args) && strpos($args[$key1],$needle) !== false) {
      $exploded = explode($needle,$args[$key1]);
      $args[$key1] = trim($exploded[0]);
      $args[$key2] = trim($exploded[1]);
    }
    return $args;
  }

  /**
   * @param string[] $args
   *
   * @return string[] $args
   */
  protected function preWorkOnArgs($args)
  {
    if ((!array_key_exists(self::OUTPUT_FORMAT_KEY,$args)
         || $args[self::OUTPUT_FORMAT_KEY] === "")
        && array_key_exists(self::UPLOAD_ADDS,$args)) {

        $args = $this->preWorkOnArgsFlp($args,self::UPLOAD_ADDS,self::OUTPUT_FORMAT_KEY);
    } else {
      if (!array_key_exists(self::UPLOAD_ADDS,$args) || $args[self::UPLOAD_ADDS] === "") {
        $args = $this->preWorkOnArgsFlp($args,self::UPLOAD_ADDS,self::OUTPUT_FORMAT_KEY);
      }
    }
    return $args;
  }

  function processUploadId($uploadId)
  {
    $groupId = $this->groupId;

    $args = $this->preWorkOnArgs($this->args);

    if (array_key_exists(self::OUTPUT_FORMAT_KEY,$args)) {
      $possibleOutputFormat = trim($args[self::OUTPUT_FORMAT_KEY]);
      if (in_array($possibleOutputFormat, explode(',',self::AVAILABLE_OUTPUT_FORMATS))) {
        $this->outputFormat = $possibleOutputFormat;
      }
    }
    $this->computeUri($uploadId);

    $contents = $this->renderPackage($uploadId, $groupId);

    $additionalUploadIds = array_key_exists(self::UPLOAD_ADDS,$args) ? explode(',',$args[self::UPLOAD_ADDS]) : array();
    $packageIds = array($uploadId);
    foreach ($additionalUploadIds as $additionalId) {
      $contents .= $this->renderPackage($additionalId, $groupId);
      $packageIds[] = $additionalId;
    }

    $this->writeReport($contents, $packageIds, $uploadId);
    return true;
  }

  protected function getTemplateFile($partname)
  {
    $prefix = $this->outputFormat . "-";
    $postfix = ".twig";
    $postfix = ".xml" . $postfix;
    return $prefix . $partname . $postfix;
  }

  protected function getUri($fileBase)
  {
    if (count($this->additionalUploads) > 0) {
      $fileName = $fileBase . "multifile" . "_" . strtoupper($this->outputFormat);
    } else {
      // Check if packageName contains non-ASCII characters and create ASCII-safe fallback
      if (preg_match('/[^\x20-\x7E]/', $this->packageName)) {
        $safeName = "upload_" . time() . "_clixml";
        $fileName = $fileBase. strtoupper($this->outputFormat)."_".$safeName;
      } else {
        $fileName = $fileBase. strtoupper($this->outputFormat)."_".$this->packageName;
      }
    }

    return $fileName .".xml";
  }

  protected function renderPackage($uploadId, $groupId)
  {
    $this->heartbeat(0);

    $otherStatement = $this->otherGetter->getReportData($uploadId);
    $this->heartbeat(empty($otherStatement) ? 0 : count($otherStatement));

    if (!empty($otherStatement['ri_clixmlcolumns'])) {
      $clixmlColumns = json_decode($otherStatement['ri_clixmlcolumns'], true);
    } else {
      $clixmlColumns = UploadDao::CLIXML_REPORT_HEADINGS;
    }

    $licenses = $this->licenseClearedGetter->getCleared($uploadId, $this, $groupId, true, "license", false);
    $this->heartbeat(empty($licenses) ? 0 : count($licenses["statements"]));

    $licensesMain = $this->licenseMainGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    $this->heartbeat(empty($licensesMain) ? 0 : count($licensesMain["statements"]));

    if (array_values($clixmlColumns['irrelevantfilesclixml'])[0]) {
      $licensesIrre = $this->licenseIrrelevantGetter->getCleared($uploadId, $this, $groupId, true, null, false);
      $irreComments = $this->licenseIrrelevantGetterComments->getCleared($uploadId, $this, $groupId, true, null, false);
    } else {
      $licensesIrre = array("statements" => array());
      $irreComments = array("statements" => array());
    }
    $this->heartbeat(empty($licensesIrre) ? 0 : count($licensesIrre["statements"]));
    $this->heartbeat(empty($irreComments) ? 0 : count($irreComments["statements"]));

    if (array_values($clixmlColumns['dnufilesclixml'])[0]) {
      $licensesDNU = $this->licenseDNUGetter->getCleared($uploadId, $this, $groupId, true, null, false);
      $licensesDNUComment = $this->licenseDNUCommentGetter->getCleared($uploadId, $this, $groupId, true, null, false);
    } else {
      $licensesDNU = array("statements" => array());
      $licensesDNUComment = array("statements" => array());
    }
    $this->heartbeat(empty($licensesDNU) ? 0 : count($licensesDNU["statements"]));
    $this->heartbeat(empty($licensesDNUComment) ? 0 : count($licensesDNUComment["statements"]));

    if (array_values($clixmlColumns['copyrightsclixml'])[0]) {
      $copyrights = $this->cpClearedGetter->getCleared($uploadId, $this, $groupId, true, "copyright", false);
    } else {
      $copyrights = array("statements" => array());
    }
    $this->heartbeat(empty($copyrights["statements"]) ? 0 : count($copyrights["statements"]));

    if (array_values($clixmlColumns['exportrestrictionsclixml'])[0]) {
      $ecc = $this->eccClearedGetter->getCleared($uploadId, $this, $groupId, true, "ecc", false);
    } else {
      $ecc = array("statements" => array());
    }
    $this->heartbeat(empty($ecc) ? 0 : count($ecc["statements"]));

    if (array_values($clixmlColumns['intellectualPropertyclixml'])[0]) {
      $ipra = $this->ipraClearedGetter->getCleared($uploadId, $this, $groupId, true, "ipra", false);
    } else {
      $ipra = array("statements" => array());
    }
    $this->heartbeat(empty($ipra) ? 0 : count($ipra["statements"]));

    if (array_values($clixmlColumns['notesclixml'])[0]) {
      $notes = htmlspecialchars($otherStatement['ri_ga_additional'], ENT_DISALLOWED);
    } else {
      $notes = "";
    }

    $countAcknowledgement = 0;
    $includeAcknowledgements = array_values($clixmlColumns['acknowledgementsclixml'])[0];
    $licenses["statements"] = $this->addLicenseNames($licenses["statements"]);
    $licensesWithAcknowledgement = $this->removeDuplicateAcknowledgements(
      $licenses["statements"], $countAcknowledgement, $includeAcknowledgements);

    if (array_values($clixmlColumns['allobligations'])[0]) {
      $obligations = $this->obligationsGetter->getObligations(
        $licenses['statements'], $licensesMain['statements'], $uploadId, $groupId)[0];
      $obligations = array_values($obligations);
    } else {
      $obligations = array();
    }

    if (array_values($clixmlColumns['mainlicensesclixml'])[0]) {
      $mainLicenses = $licensesMain["statements"];
    } else {
      $mainLicenses = array();
    }
    $componentHash = $this->uploadDao->getUploadHashes($uploadId);
    $contents = array(
      "licensesMain" => $mainLicenses,
      "licenses" => $licensesWithAcknowledgement,
      "obligations" => $obligations,
      "copyrights" => $copyrights["statements"],
      "ecc" => $ecc["statements"],
      "ipra" => $ipra["statements"],
      "licensesIrre" => $licensesIrre["statements"],
      "irreComments" => $irreComments["statements"],
      "licensesDNU" => $licensesDNU["statements"],
      "licensesDNUComment" => $licensesDNUComment["statements"],
      "countAcknowledgement" => $countAcknowledgement
    );
    $contents = $this->reArrangeMainLic($contents, $includeAcknowledgements);
    $contents = $this->reArrangeContent($contents);
    $fileOperations = array(
      "licensepath" => array_values($clixmlColumns['licensepath'])[0],
      "licensehash" => array_values($clixmlColumns['licensehash'])[0],
      "copyrightpath" => array_values($clixmlColumns['copyrightpath'])[0],
      "copyrighthash" => array_values($clixmlColumns['copyrighthash'])[0],
      "eccpath" => array_values($clixmlColumns['eccpath'])[0],
      "ecchash" => array_values($clixmlColumns['ecchash'])[0],
      "iprapath" => array_values($clixmlColumns['iprapath'])[0],
      "iprahash" => array_values($clixmlColumns['iprahash'])[0]
    );
    list($generalInformation, $assessmentSummary) = $this->getReportSummary($uploadId);
    $generalInformation['componentHash'] = $componentHash['sha1'];
    return $this->renderString($this->getTemplateFile('file'),array(
      'documentName' => $this->packageName,
      'version' => "1.6",
      'uri' => $this->uri,
      'userName' => $this->container->get('dao.user')->getUserName($this->userId),
      'organisation' => '',
      'componentHash' => strtolower($componentHash['sha1']),
      'contents' => $contents,
      'commentAdditionalNotes' => $notes,
      'externalIdLink' => htmlspecialchars($otherStatement['ri_sw360_link']),
      'generalInformation' => $generalInformation,
      'assessmentSummary' => $assessmentSummary,
      'fileOperations' => $fileOperations
    ));
  }

  protected function removeDuplicateAcknowledgements($licenses, &$countAcknowledgement, $includeAcknowledgements)
  {
    if (empty($licenses)) {
      return $licenses;
    }

    foreach ($licenses as $ackKey => $ackValue) {
      if (!$includeAcknowledgements) {
        $licenses[$ackKey]['acknowledgement'] = null;
      } else if (isset($ackValue['acknowledgement'])) {
        $licenses[$ackKey]['acknowledgement'] = array_unique(array_filter($ackValue['acknowledgement']));
        $countAcknowledgement += count($licenses[$ackKey]['acknowledgement']);
      }
    }
    return $licenses;
  }

  protected function riskMapping($licenseContent)
  {
    foreach ($licenseContent as $riskKey => $riskValue) {
      if (!array_key_exists('risk', $riskValue)) {
        $riskValue['risk'] = 0;
      }
      if ($riskValue['risk'] == '2' || $riskValue['risk'] == '3') {
        $licenseContent[$riskKey]['risk'] = 'otheryellow';
      } else if ($riskValue['risk'] == '4' || $riskValue['risk'] == '5') {
        $licenseContent[$riskKey]['risk'] = 'otherred';
      } else {
        $licenseContent[$riskKey]['risk'] = 'otherwhite';
      }
    }
    return $licenseContent;
  }

  protected function reArrangeMainLic($contents, $includeAcknowledgements)
  {
    $mainlic = array();
    $lenTotalLics = count($contents["licenses"]);
    // both of this variables have same value but used for different operations
    $lenMainLics = count($contents["licensesMain"]);
    for ($i=0; $i<$lenMainLics; $i++) {
      $count = 0 ;
      for ($j=0; $j<$lenTotalLics; $j++) {
        if (!strcmp($contents["licenses"][$j]["licenseId"], $contents["licensesMain"][$i]["licenseId"])) {
          $count = 1;
          $mainlic[] =  $contents["licenses"][$j];
          unset($contents["licenses"][$j]);
        }
      }
      if ($count != 1) {
        $mainlic[] = $contents["licensesMain"][$i];
      }
      unset($contents["licensesMain"][$i]);
    }
    $contents["licensesMain"] = $mainlic;

    $lenMainLicenses=count($contents["licensesMain"]);
    for ($i=0; $i<$lenMainLicenses; $i++) {
      $contents["licensesMain"][$i]["contentMain"] = $contents["licensesMain"][$i]["content"];
      $contents["licensesMain"][$i]["nameMain"] = $contents["licensesMain"][$i]["name"];
      $contents["licensesMain"][$i]["textMain"] = $contents["licensesMain"][$i]["text"];
      $contents["licensesMain"][$i]["riskMain"] = $contents["licensesMain"][$i]["risk"];
      if (array_key_exists('acknowledgement', $contents["licensesMain"][$i])) {
        if ($includeAcknowledgements) {
          $contents["licensesMain"][$i]["acknowledgementMain"] = $contents["licensesMain"][$i]["acknowledgement"];
        }
        unset($contents["licensesMain"][$i]["acknowledgement"]);
      }
      unset($contents["licensesMain"][$i]["content"]);
      unset($contents["licensesMain"][$i]["text"]);
      unset($contents["licensesMain"][$i]["risk"]);
    }
    return $contents;
  }

  protected function reArrangeContent($contents)
  {
    $contents['licensesMain'] = $this->riskMapping($contents['licensesMain']);
    $contents['licenses'] = $this->riskMapping($contents['licenses']);

    $contents["obligations"] = array_map(function($changeKey) {
      return array(
        'obliText' => $changeKey['text'],
        'topic' => $changeKey['topic'],
        'license' => $changeKey['license']
      );
    }, $contents["obligations"]);

    $contents["copyrights"] = array_map(function($changeKey) {
      $content = htmlspecialchars_decode($changeKey['content']);
      $content = str_replace("]]>", "]]&gt;", $content);
      $comments = htmlspecialchars_decode($changeKey['comments']);
      $comments = str_replace("]]>", "]]&gt;", $comments);
      return array(
        'contentCopy' => $content,
        'comments' => $comments,
        'files' => $changeKey['files'],
        'hash' => $changeKey['hash']
      );
    }, $contents["copyrights"]);

    $contents["ecc"] = array_map(function($changeKey) {
      $content = htmlspecialchars_decode($changeKey['content']);
      $content = str_replace("]]>", "]]&gt;", $content);
      $comments = htmlspecialchars_decode($changeKey['comments']);
      $comments = str_replace("]]>", "]]&gt;", $comments);
      return array(
        'contentEcc' => $content,
        'commentsEcc' => $comments,
        'files' => $changeKey['files'],
        'hash' => $changeKey['hash']
      );
    }, $contents["ecc"]);

    $contents["ipra"] = array_map(function($changeKey) {
      $content = htmlspecialchars_decode($changeKey['content']);
      $content = str_replace("]]>", "]]&gt;", $content);
      $comments = htmlspecialchars_decode($changeKey['comments']);
      $comments = str_replace("]]>", "]]&gt;", $comments);
      return array(
        'contentIpra' => $content,
        'commentsIpra' => $comments,
        'files' => $changeKey['files'],
        'hash' => $changeKey['hash']
      );
    }, $contents["ipra"]);

    $contents["irreComments"] = array_map(function($changeKey) {
      return array(
        'contentIrre' => $changeKey['content'],
        'textIrre' => $changeKey['text']
      );
    }, $contents["irreComments"]);

    $contents["licensesIrre"] = array_map(function($changeKey) {
      return array(
        'filesIrre' => $changeKey['fullPath']
      );
    }, $contents["licensesIrre"]);

    $contents["licensesDNUComment"] = array_map(function($changeKey) {
      return array(
        'contentDNU' => $changeKey['content'],
        'textDNU' => $changeKey['text']
      );
    }, $contents["licensesDNUComment"]);

    $contents["licensesDNU"] = array_map(function($changeKey) {
      return array(
        'filesDNU' => $changeKey['fullPath']
      );
    }, $contents["licensesDNU"]);

    return $contents;
  }

  protected function computeUri($uploadId)
  {
    global $SysConf;
    $upload = $this->uploadDao->getUpload($uploadId);
    $this->packageName = $upload->getFilename();

    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";

    $this->uri = $this->getUri($fileBase);
  }

  protected function writeReport($contents, $packageIds, $uploadId)
  {
    $fileBase = dirname($this->uri);

    if (!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);

    $message = $this->renderString($this->getTemplateFile('document'),
      array('content' => $contents));

    // To ensure the file is valid, replace any non-printable characters with a question mark.
    // 'Non-printable' is ASCII < 0x20 (excluding \r, \n and tab) and 0x7F - 0x9F.
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u','?',$message);
    file_put_contents($this->uri, $message);
    $this->updateReportTable($uploadId, $this->jobId, $this->uri);
  }

  protected function updateReportTable($uploadId, $jobId, $fileName)
  {
    $this->reportutils->updateOrInsertReportgenEntry($uploadId, $jobId, $fileName);
  }

  /**
   * @param string $templateName
   * @param array $vars
   * @return string
   */
  protected function renderString($templateName, $vars)
  {
    return $this->renderer->load($templateName)->render($vars);
  }

  /**
   * Generate the GeneralInformation and AssessmentSummary components for the
   * report.
   * @param int $uploadId Upload ID
   * @return array First element as associative array for GeneralInformation
   *               and second as associative array for AssessmentSummary
   */
  private function getReportSummary($uploadId)
  {
    global $SysConf;
    $row = $this->uploadDao->getReportInfo($uploadId);

    $review = htmlspecialchars($row['ri_reviewed']);
    if ($review == 'NA') {
      $review = '';
    }
    $critical = 'None';
    $dependency = 'None';
    $ecc = 'None';
    $usage = 'None';
    if (!empty($row['ri_ga_checkbox_selection'])) {
      $listURCheckbox = explode(',', $row['ri_ga_checkbox_selection']);
      if ($listURCheckbox[0] == 'checked') {
        $critical = 'None';
      }
      if ($listURCheckbox[1] == 'checked') {
        $critical = 'Found';
      }
      if ($listURCheckbox[2] == 'checked') {
        $dependency = 'None';
      }
      if ($listURCheckbox[3] == 'checked') {
        $dependency = 'SourceDependenciesFound';
      }
      if ($listURCheckbox[4] == 'checked') {
        $dependency = 'BinaryDependenciesFound';
      }
      if ($listURCheckbox[5] == 'checked') {
        $ecc = 'None';
      }
      if ($listURCheckbox[6] == 'checked') {
        $ecc = 'Found';
      }
      if ($listURCheckbox[7] == 'checked') {
        $usage = 'None';
      }
      if ($listURCheckbox[8] == 'checked') {
        $usage = 'Found';
      }
    }
    $componentType = $row['ri_component_type'];
    if (!empty($componentType)) {
      $componentType = ComponentType::TYPE_MAP[$componentType];
    } else {
      $componentType = ComponentType::TYPE_MAP[ComponentType::PURL];
    }
    $componentId = $row['ri_component_id'];
    if (empty($componentId) || $componentId == "NA") {
      $componentId = "";
    }

    $parentItem = $this->uploadDao->getUploadParent($uploadId);

    $uploadLink = $SysConf['SYSCONFIG']['FOSSologyURL'];
    if (substr($uploadLink, 0, 4) !== "http") {
      $uploadLink = "http://" . $uploadLink;
    }
    $uploadLink .= "?mod=browse&upload=$uploadId&item=$parentItem";

    return [[
      'reportId' => uuid_create(UUID_TYPE_TIME),
      'reviewedBy' => $review,
      'componentName' => htmlspecialchars($row['ri_component']),
      'community' => htmlspecialchars($row['ri_community']),
      'version' => htmlspecialchars($row['ri_version']),
      'componentHash' => '',
      'componentReleaseDate' => htmlspecialchars($row['ri_release_date']),
      'linkComponentManagement' => htmlspecialchars($row['ri_sw360_link']),
      'linkScanTool' => $uploadLink,
      'componentType' => htmlspecialchars($componentType),
      'componentId' => htmlspecialchars($componentId)
    ], [
      'generalAssessment' => $row['ri_general_assesment'],
      'criticalFilesFound' => $critical,
      'dependencyNotes' => $dependency,
      'exportRestrictionsFound' => $ecc,
      'usageRestrictionsFound' => $usage,
      'additionalNotes' => $row['ri_ga_additional']
    ]];
  }

  /**
   * Add license shortname to the list of license statements.
   * @param array $licenses License statements from
   * @return array License statements with name filed
   */
  private function addLicenseNames($licenses)
  {
    $statementsWithNames = [];
    foreach ($licenses as $license) {
      $allLicenseCols = $this->licenseDao->getLicenseById($license["licenseId"],
        $this->groupId);
      $license["name"] = $allLicenseCols->getShortName();
      $statementsWithNames[] = $license;
    }
    return $statementsWithNames;
  }
}

$agent = new CliXml();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
