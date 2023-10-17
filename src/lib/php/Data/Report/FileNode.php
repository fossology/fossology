<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\Lib\Data\Report;

class FileNode
{
  /**
   * @var string[] $comments
   * Comments on file.
   */
  private $comments = [];
  /**
   * @var string[] $acknowledgements
   * Acknowledgements on file.
   */
  private $acknowledgements = [];
  /**
   * @var string[] $concludedLicenses
   * Concluded licenses on file (rf_pk + md5(text)).
   */
  private $concludedLicenses = [];
  /**
   * @var bool $isCleared
   * Is file cleared.
   */
  private $isCleared = false;
  /**
   * @var string[] $scanners
   * Scanner findings (rf_pk + md5(text)).
   */
  private $scanners = [];
  /**
   * @var string[] $copyrights
   * Copyrights on file.
   */
  private $copyrights = [];

  /**
   * Add comment to file.
   *
   * @param string $comment
   * @return FileNode
   */
  public function addComment(string $comment): FileNode
  {
    $this->comments[] = $comment;
    return $this;
  }

  /**
   * Replace comments.
   *
   * @param string[] $comments
   * @return FileNode
   */
  public function setComments(array $comments): FileNode
  {
    $this->comments = $comments;
    return $this;
  }

  /**
   * Add acknowledgement to file.
   *
   * @param string $acknowledgement
   * @return FileNode
   */
  public function addAcknowledgement(string $acknowledgement): FileNode
  {
    $this->acknowledgements[] = $acknowledgement;
    return $this;
  }

  /**
   * Replace acknowledgement array.
   *
   * @param string[] $acknowledgements
   * @return FileNode
   */
  public function setAcknowledgements(array $acknowledgements): FileNode
  {
    $this->acknowledgements = $acknowledgements;
    return $this;
  }

  /**
   * Add concluded license to file.
   *
   * @param string $concludedLicense
   * @return FileNode
   */
  public function addConcludedLicense(string $concludedLicense): FileNode
  {
    $this->concludedLicenses[] = $concludedLicense;
    return $this;
  }

  /**
   * Set if file is cleared.
   *
   * @param bool $isCleared
   * @return FileNode
   */
  public function setIsCleared(bool $isCleared): FileNode
  {
    $this->isCleared = $isCleared;
    return $this;
  }

  /**
   * Add scanner finding to file.
   *
   * @param string $scanner
   * @return FileNode
   */
  public function addScanner(string $scanner): FileNode
  {
    $this->scanners[] = $scanner;
    return $this;
  }

  /**
   * Add copyright to file.
   *
   * @param string $copyright
   * @return FileNode
   */
  public function addCopyright(string $copyright): FileNode
  {
    $this->copyrights[] = $copyright;
    return $this;
  }

  /**
   * @return string[]
   */
  public function getComments(): array
  {
    return $this->comments;
  }

  /**
   * @return string[]
   */
  public function getAcknowledgements(): array
  {
    return $this->acknowledgements;
  }

  /**
   * @return string[]
   */
  public function getConcludedLicenses(): array
  {
    return $this->concludedLicenses;
  }

  /**
   * @return bool
   */
  public function isCleared(): bool
  {
    return $this->isCleared;
  }

  /**
   * @return string[]
   */
  public function getScanners(): array
  {
    return $this->scanners;
  }

  /**
   * @return string[]
   */
  public function getCopyrights(): array
  {
    return $this->copyrights;
  }
}
