<?php
/***************************************************************
 Copyright (C) 2024 FOSSology Team

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
 ***************************************************************/

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Db\DbManager;

class TextPhraseDecider extends Agent
{
  /** @var LicenseDao */
  private $licenseDao;
  
  /** @var UploadDao */
  private $uploadDao;
  
  /** @var DbManager */
  private $dbManager;

  public function __construct()
  {
    parent::__construct('textphrase_decider', AGENT_REV, 'Text Phrase Decider');
    $this->licenseDao = $GLOBALS['container']->get('dao.license');
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * @brief Process the upload and make decisions
   * @param int $uploadId
   * @return boolean
   */
  public function processUpload($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $files = $this->uploadDao->getNonArtifactDescendants($uploadId, $uploadTreeTableName);

    foreach ($files as $file) {
      $this->processFile($file);
    }

    return true;
  }

  /**
   * @brief Process a single file and make decisions
   * @param array $file
   */
  private function processFile($file)
  {
    // Get text phrase findings for this file
    $sql = "SELECT tpf.*, tp.acknowledgement, tp.comments 
            FROM text_phrase_findings tpf 
            JOIN text_phrases tp ON tpf.phrase_id = tp.id 
            WHERE tpf.pfile_fk = $1";
    
    $findings = $this->dbManager->getRows($sql, array($file['pfile_fk']));
    
    if (empty($findings)) {
      return;
    }

    // Group findings by license
    $licenseFindings = array();
    foreach ($findings as $finding) {
      $licenseId = $finding['license_fk'];
      if (!isset($licenseFindings[$licenseId])) {
        $licenseFindings[$licenseId] = array();
      }
      $licenseFindings[$licenseId][] = $finding;
    }

    // Make decisions for each license
    foreach ($licenseFindings as $licenseId => $findings) {
      $this->makeDecision($file['uploadtree_pk'], $licenseId, $findings);
    }
  }

  /**
   * @brief Make a decision based on findings
   * @param int $uploadTreeId
   * @param int $licenseId
   * @param array $findings
   */
  private function makeDecision($uploadTreeId, $licenseId, $findings)
  {
    // Get the license
    $license = $this->licenseDao->getLicenseById($licenseId);
    if (!$license) {
      return;
    }

    // Create decision
    $decision = array(
      'uploadtree_pk' => $uploadTreeId,
      'rf_fk' => $licenseId,
      'decision_type' => DecisionTypes::IDENTIFIED,
      'scope' => DecisionScopes::ITEM,
      'user_fk' => Auth::getUserId(),
      'text' => $this->generateDecisionText($findings)
    );

    // Save decision
    $this->licenseDao->insertDecision($decision);
  }

  /**
   * @brief Generate decision text from findings
   * @param array $findings
   * @return string
   */
  private function generateDecisionText($findings)
  {
    $text = "Text phrase matches found:\n\n";
    
    foreach ($findings as $finding) {
      $text .= "- Match: " . $finding['match_text'] . "\n";
      if (!empty($finding['acknowledgement'])) {
        $text .= "  Acknowledgement: " . $finding['acknowledgement'] . "\n";
      }
      if (!empty($finding['comments'])) {
        $text .= "  Comments: " . $finding['comments'] . "\n";
      }
      $text .= "\n";
    }
    
    return $text;
  }
} 