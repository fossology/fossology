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
   * Obligation constructor.
   *
   * @param integer $id
   * @param string $topic
   * @param string $type
   * @param string $text
   * @param string $classification
   * @param string $comment
   */
  public function __construct($id, $topic = "", $type = "", $text = "",
    $classification = "", $comment = "")
  {
    $this->id = intval($id);
    $this->setTopic($topic);
    $this->setType($type);
    $this->setText($text);
    $this->setClassification($classification);
    $this->setComment($comment);
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
    return [
      'id' => $this->id,
      'topic' => $this->getTopic(),
      'type' => $this->getType(),
      'text' => $this->getText(),
      'classification' => $this->getClassification(),
      'comment' => $this->getComment()
    ];
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
}
