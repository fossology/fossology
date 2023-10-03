<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Obligation
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Obligation
 * @package Fossology\UI\Api\Models
 * @brief Obligation model to hold obligation related info
 */
class Obligation
{
  /**
   * @var integer $id
   * Obligation id
   */
  private $id;
  /**
   * @var string $topic
   * Topic of the obligation
   */
  private $topic;
  /**
   * @var string $type
   * Type of the obligation
   */
  private $type;
  /**
   * @var string $text
   * The text of the obligation
   */
  private $text;
  /**
   * @var string $classification
   * Classification of the obligation
   */
  private $classification;
  /**
   * @var string $comment
   * Comment on the obligation
   */
  private $comment;
  /**
   * @var bool $modification
   * Applies on modified code
   */
  private $modification;
  /**
   * @var bool $active
   * Obligation active
   */
  private $active;
  /**
   * @var bool $textUpdatable
   * Text updatable
   */
  private $textUpdatable;
  /**
   * @var string $hash
   * Hash of obligation text
   */
  private $hash;
  /**
   * @var array $licenses
   * List of license shortnames associated
   */
  private $licenses;
  /**
   * @var array $candidateLicenses
   * List of candidate license shortnames associated
   */
  private $candidateLicenses;
  /**
   * @var bool $extended
   * Extended info on the obligation
   */
  private $extended;

  /**
   * Obligation constructor.
   *
   * @param integer $id
   * @param string $topic
   * @param string $type
   * @param string $text
   * @param string $classification
   * @param string $comment
   * @param boolean $extended
   */
  public function __construct($id, $topic = "", $type = "", $text = "",
    $classification = "", $comment = "", $extended = false)
  {
    $this->id = intval($id);
    $this->setTopic($topic);
    $this->setType($type);
    $this->setText($text);
    $this->setClassification($classification);
    $this->setComment($comment);
    $this->setExtended($extended);
    $this->setHash(null);
    $this->licenses = [];
    $this->candidateLicenses = [];
  }

  /**
   * JSON representation of the license
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get License element as associative array
   * @return array
   */
  public function getArray()
  {
    $obligation = [
      'id' => $this->id,
      'topic' => $this->getTopic(),
      'type' => $this->getType(),
      'text' => $this->getText(),
      'classification' => $this->getClassification(),
      'comment' => $this->getComment()
    ];
    if ($this->getExtended()) {
      $obligation['modification'] = $this->isModification();
      $obligation['active'] = $this->isActive();
      $obligation['textUpdatable'] = $this->isTextUpdatable();
      $obligation['licenses'] = $this->getLicenses();
      $obligation['candidateLicenses'] = $this->getCandidateLicenses();
      $obligation['hash'] = $this->getHash();
    }
    return $obligation;
  }

  /**
   * Get the topic of the obligation
   * @return string
   */
  public function getTopic()
  {
    if ($this->topic === null) {
      return "";
    }
    return $this->topic;
  }

  /**
   * Get the type of the obligation
   * @return string
   */
  public function getType()
  {
    if ($this->type === null) {
      return "";
    }
    return $this->type;
  }

  /**
   * Get the text of the obligation
   * @return string
   */
  public function getText()
  {
    if ($this->text === null) {
      return "";
    }
    return $this->text;
  }

  /**
   * Get the obligation's classification
   * @return string
   */
  public function getClassification()
  {
    if ($this->classification === null) {
      return "";
    }
    return $this->classification;
  }

  /**
   * Get the comment on the obligation
   * @return string
   */
  public function getComment()
  {
    if ($this->comment === null) {
      return "";
    }
    return $this->comment;
  }

  /**
   * @return bool
   */
  public function isModification()
  {
    return $this->modification == true;
  }

  /**
   * @return bool
   */
  public function isTextUpdatable()
  {
    return $this->textUpdatable == true;
  }

  /**
   * @return bool
   */
  public function isActive()
  {
    return $this->active == true;
  }

  /**
   * @return string
   */
  public function getHash()
  {
    return $this->hash;
  }

  /**
   * @return array
   */
  public function getLicenses()
  {
    return $this->licenses;
  }

  /**
   * @return array
   */
  public function getCandidateLicenses()
  {
    return $this->candidateLicenses;
  }

  /**
   * Get if extended info on obligation
   * @return bool
   */
  public function getExtended()
  {
    return $this->extended;
  }

  /**
   * Set the topic of the obligation
   * @param string $topic
   */
  public function setTopic($topic)
  {
    $this->topic = convertToUTF8($topic, false);
  }

  /**
   * Set the type of the obligation
   * @param string $type
   */
  public function setType($type)
  {
    $this->type = convertToUTF8($type, false);
  }

  /**
   * Set the text of the obligation
   * @param string $text
   */
  public function setText($text)
  {
    $this->text = convertToUTF8($text, false);
  }

  /**
   * Set the obligation's classification
   * @param string $classification
   */
  public function setClassification($classification)
  {
    $this->classification = convertToUTF8($classification, false);
  }

  /**
   * Set the comment on the obligation
   * @param string $comment
   */
  public function setComment($comment)
  {
    $this->comment = convertToUTF8($comment, false);
  }

  /**
   * @param bool $modification
   */
  public function setModification($modification)
  {
    if ($modification == "t") {
      $modification = true;
    }
    $this->modification = filter_var($modification, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param bool $textUpdatable
   */
  public function setTextUpdatable($textUpdatable)
  {
    if ($textUpdatable == "t") {
      $textUpdatable = true;
    }
    $this->textUpdatable = filter_var($textUpdatable, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param bool $active
   */
  public function setActive($active)
  {
    if ($active == "t") {
      $active = true;
    }
    $this->active = filter_var($active, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param string $hash
   */
  public function setHash($hash)
  {
    $this->hash = $hash;
  }

  /**
   * Associate another license
   * @param string $shortname License to add
   */
  public function addLicense($shortname)
  {
    if (empty($shortname)) {
      return;
    }
    $this->licenses[] = $shortname;
  }

  /**
   * Associate another candidate license
   * @param string $shortname Candidate license to add
   */
  public function addCandidateLicense($shortname)
  {
    if (empty($shortname)) {
      return;
    }
    $this->candidateLicenses[] = $shortname;
  }

  /**
   * Set the extended info on Obligation
   * @param bool $extended
   */
  public function setExtended($extended)
  {
    $this->extended = filter_var($extended, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * From DB array to Obligation object
   * @param array $db         Array from DB
   * @param boolean $extended Extended info on obligation
   * @param array $licenses   Array of associated license shortnames
   * @param array $candidateLicenses Array of associated candidate license
   *              shortnames
   * @return Obligation New obligation object
   */
  public static function fromArray($db, $extended, $licenses, $candidateLicenses)
  {
    $obligation = new Obligation($db['ob_pk'], $db['ob_topic'], $db['ob_type'],
      $db['ob_text'], $db['ob_classification'], $db['ob_comment'], $extended);
    if ($extended) {
      $obligation->setModification($db['ob_modifications']);
      $obligation->setActive($db['ob_active']);
      $obligation->setTextUpdatable($db['ob_text_updatable']);
      $obligation->setHash($db['ob_md5']);
      foreach ($licenses as $license) {
        $obligation->addLicense($license);
      }
      foreach ($candidateLicenses as $candidateLicense) {
        $obligation->addCandidateLicense($candidateLicense);
      }
    }
    return $obligation;
  }
}
