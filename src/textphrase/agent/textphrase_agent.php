<?php
/**
 * SPDX-FileCopyrightText: © 2022 Siemens AG
 * SPDX-License-Identifier: GPL-2.0-only AND LGPL-2.1-only
 */

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
use Fossology\Lib\Data\TextFragment;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Data\AgentRef;

class TextPhraseAgent extends Agent
{
  /** @var LicenseDao */
  private $licenseDao;
  
  /** @var UploadDao */
  private $uploadDao;
  
  /** @var DbManager */
  private $dbManager;
  
  /** @var array */
  private $textPhrases = [];

  public function __construct()
  {
    parent::__construct('textphrase', AGENT_REV, 'Text Phrase Scanner');
    $this->licenseDao = $GLOBALS['container']->get('dao.license');
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * @brief Initialize the agent
   * @return boolean
   */
  public function initialize()
  {
    $this->loadTextPhrases();
    return true;
  }

  /**
   * @brief Load text phrases from database
   */
  private function loadTextPhrases()
  {
    $sql = "SELECT * FROM text_phrases WHERE is_active = true";
    $this->textPhrases = $this->dbManager->getRows($sql);
  }

  /**
   * @brief Process the upload ID (required by Agent base class)
   * @param int $uploadId
   * @return boolean
   */
  protected function processUploadId($uploadId)
  {
    $this->initialize();
    
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $files = $this->uploadDao->getNonArtifactDescendants($uploadId, $uploadTreeTableName);

    $processed = 0;
    foreach ($files as $file) {
      $this->processFile($file);
      $processed++;
      
      // Send heartbeat every 10 files
      if ($processed % 10 == 0) {
        $this->heartbeat($processed);
      }
    }

    return true;
  }

  /**
   * @brief Process a single file
   * @param array $file
   */
  private function processFile($file)
  {
    $content = $this->getFileContent($file['pfile_fk']);
    if (empty($content)) {
      return;
    }

    foreach ($this->textPhrases as $phrase) {
      $matches = $this->findPhraseMatches($content, $phrase);
      if (!empty($matches)) {
        $this->saveFindings($file['pfile_fk'], $phrase, $matches);
      }
    }
  }

  /**
   * @brief Get file content
   * @param int $pfileId
   * @return string
   */
  private function getFileContent($pfileId)
  {
    $sql = "SELECT filepath FROM pfile WHERE pfile_pk = $1";
    $result = $this->dbManager->getSingleRow($sql, array($pfileId));
    
    if (!$result) {
      return '';
    }
    
    $filepath = $result['filepath'];
    if (!file_exists($filepath)) {
      return '';
    }
    
    return file_get_contents($filepath);
  }

  /**
   * @brief Find matches for a text phrase
   * @param string $content
   * @param array $phrase
   * @return array
   */
  private function findPhraseMatches($content, $phrase)
  {
    $matches = [];
    $pattern = $this->prepareSearchPattern($phrase['text']);
    
    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
      return $matches[0];
    }
    
    return [];
  }

  /**
   * @brief Save findings to database
   * @param int $pfileId
   * @param array $phrase
   * @param array $matches
   */
  private function saveFindings($pfileId, $phrase, $matches)
  {
    foreach ($matches as $match) {
      $sql = "INSERT INTO text_phrase_findings (pfile_fk, phrase_id, license_fk, 
              match_text, match_offset, match_length) 
              VALUES ($1, $2, $3, $4, $5, $6)";
      
      $params = [
        $pfileId,
        $phrase['id'],
        $phrase['license_fk'],
        $match[0],
        $match[1],
        strlen($match[0])
      ];
      
      $this->dbManager->prepare($sql, __METHOD__);
      $this->dbManager->execute($sql, $params);
    }
  }

  /**
   * @brief Prepare search pattern
   * @param string $text
   * @return string
   */
  private function prepareSearchPattern($text)
  {
    $pattern = preg_quote($text, '/');
    if (!$this->getOption('CASE_SENSITIVE')) {
      $pattern = '(?i)' . $pattern;
    }
    return '/' . $pattern . '/';
  }

  /**
   * @brief Get agent reference
   * @return AgentRef
   */
  public function getAgentRef()
  {
    return new AgentRef($this->agentName, $this->agentRev, $this->agentDesc);
  }
} 