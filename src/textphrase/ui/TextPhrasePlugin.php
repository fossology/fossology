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

use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Db\DbManager;

class TextPhrasePlugin extends DefaultPlugin
{
  const NAME = 'textphrase';
  
  /** @var LicenseDao */
  private $licenseDao;
  
  /** @var DbManager */
  private $dbManager;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => _("Text Phrase Management"),
      self::MENU_LIST => "Admin::License::Text Phrases",
      self::REQUIRES_LOGIN => true,
      self::PERMISSION => Auth::PERM_ADMIN,
      self::PLUGIN_LEVEL => 1
    ));
    
    $this->licenseDao = $GLOBALS['container']->get('dao.license');
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * @brief Display the plugin content
   * @param array $vars
   */
  protected function handle($vars)
  {
    $action = GetParm('action', PARM_STRING);
    
    switch ($action) {
      case 'add':
        $this->handleAdd();
        break;
      case 'edit':
        $this->handleEdit();
        break;
      case 'delete':
        $this->handleDelete();
        break;
      case 'bulk_import':
        $this->handleBulkImport();
        break;
      default:
        $this->displayList();
    }
  }

  /**
   * @brief Display the list of text phrases
   */
  private function displayList()
  {
    $vars = array();
    
    // Get all text phrases
    $sql = "SELECT tp.*, l.rf_shortname 
            FROM text_phrases tp 
            JOIN license_ref l ON tp.license_fk = l.rf_pk 
            ORDER BY l.rf_shortname, tp.text";
    $vars['phrases'] = $this->dbManager->getRows($sql);
    
    // Get all licenses for the dropdown
    $vars['licenses'] = $this->licenseDao->getAllLicenseRefs();
    
    $this->render('list.html.twig', $vars);
  }

  /**
   * @brief Handle adding a new text phrase
   */
  private function handleAdd()
  {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $text = GetParm('text', PARM_TEXT);
      $licenseId = GetParm('license_id', PARM_INTEGER);
      $acknowledgement = GetParm('acknowledgement', PARM_TEXT);
      $comments = GetParm('comments', PARM_TEXT);
      
      $sql = "INSERT INTO text_phrases (text, license_fk, acknowledgement, comments, created_by) 
              VALUES ($1, $2, $3, $4, $5)";
      
      $params = array(
        $text,
        $licenseId,
        $acknowledgement,
        $comments,
        Auth::getUserId()
      );
      
      $this->dbManager->prepare($sql, __METHOD__);
      $this->dbManager->execute($sql, $params);
      
      $this->redirect(self::NAME);
    }
    
    $vars = array(
      'licenses' => $this->licenseDao->getAllLicenseRefs()
    );
    
    $this->render('add.html.twig', $vars);
  }

  /**
   * @brief Handle editing a text phrase
   */
  private function handleEdit()
  {
    $id = GetParm('id', PARM_INTEGER);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $text = GetParm('text', PARM_TEXT);
      $licenseId = GetParm('license_id', PARM_INTEGER);
      $acknowledgement = GetParm('acknowledgement', PARM_TEXT);
      $comments = GetParm('comments', PARM_TEXT);
      $isActive = GetParm('is_active', PARM_BOOLEAN);
      
      $sql = "UPDATE text_phrases 
              SET text = $1, license_fk = $2, acknowledgement = $3, 
                  comments = $4, is_active = $5, updated_by = $6 
              WHERE id = $7";
      
      $params = array(
        $text,
        $licenseId,
        $acknowledgement,
        $comments,
        $isActive,
        Auth::getUserId(),
        $id
      );
      
      $this->dbManager->prepare($sql, __METHOD__);
      $this->dbManager->execute($sql, $params);
      
      $this->redirect(self::NAME);
    }
    
    $sql = "SELECT * FROM text_phrases WHERE id = $1";
    $phrase = $this->dbManager->getSingleRow($sql, array($id));
    
    $vars = array(
      'phrase' => $phrase,
      'licenses' => $this->licenseDao->getAllLicenseRefs()
    );
    
    $this->render('edit.html.twig', $vars);
  }

  /**
   * @brief Handle deleting a text phrase
   */
  private function handleDelete()
  {
    $id = GetParm('id', PARM_INTEGER);
    
    $sql = "DELETE FROM text_phrases WHERE id = $1";
    $this->dbManager->prepare($sql, __METHOD__);
    $this->dbManager->execute($sql, array($id));
    
    $this->redirect(self::NAME);
  }

  /**
   * @brief Handle bulk import of text phrases
   */
  private function handleBulkImport()
  {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $file = $_FILES['bulk_file'];
      
      if ($file['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($file['tmp_name']);
        $phrases = json_decode($content, true);
        
        if (is_array($phrases)) {
          foreach ($phrases as $phrase) {
            $sql = "INSERT INTO text_phrases (text, license_fk, acknowledgement, comments, created_by) 
                    VALUES ($1, $2, $3, $4, $5)";
            
            $params = array(
              $phrase['text'],
              $phrase['license_id'],
              $phrase['acknowledgement'] ?? null,
              $phrase['comments'] ?? null,
              Auth::getUserId()
            );
            
            $this->dbManager->prepare($sql, __METHOD__);
            $this->dbManager->execute($sql, $params);
          }
        }
      }
      
      $this->redirect(self::NAME);
    }
    
    $this->render('bulk_import.html.twig');
  }
} 