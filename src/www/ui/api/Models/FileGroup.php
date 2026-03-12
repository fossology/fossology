<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Models;

/**
 * @class FileGroup
 * @brief Model class for a file group (Issue #2847).
 *
 * Represents a named collection of files that share the same license
 * and copyright clearing information.
 */
class FileGroup
{
  /** @var int */
  private $id;

  /** @var string */
  private $name;

  /** @var string|null */
  private $curationNotes;

  /** @var bool */
  private $includeInReport;

  /** @var int */
  private $memberCount;

  /** @var string ISO-8601 timestamp */
  private $dateCreated;

  /** @var string|null ISO-8601 timestamp */
  private $dateModified;

  /**
   * @param int         $id
   * @param string      $name
   * @param string|null $curationNotes
   * @param bool        $includeInReport
   * @param int         $memberCount
   * @param string      $dateCreated
   * @param string|null $dateModified
   */
  public function __construct(
    int $id,
    string $name,
    ?string $curationNotes,
    bool $includeInReport,
    int $memberCount,
    string $dateCreated,
    ?string $dateModified = null
  ) {
    $this->id              = $id;
    $this->name            = $name;
    $this->curationNotes   = $curationNotes;
    $this->includeInReport = $includeInReport;
    $this->memberCount     = $memberCount;
    $this->dateCreated     = $dateCreated;
    $this->dateModified    = $dateModified;
  }

  // ─── Getters ───────────────────────────────────────────────────────────────

  /** @return int */
  public function getId(): int { return $this->id; }

  /** @return string */
  public function getName(): string { return $this->name; }

  /** @return string|null */
  public function getCurationNotes(): ?string { return $this->curationNotes; }

  /** @return bool */
  public function isIncludeInReport(): bool { return $this->includeInReport; }

  /** @return int */
  public function getMemberCount(): int { return $this->memberCount; }

  /** @return string */
  public function getDateCreated(): string { return $this->dateCreated; }

  /** @return string|null */
  public function getDateModified(): ?string { return $this->dateModified; }

  // ─── Serialization ─────────────────────────────────────────────────────────

  /**
   * Convert the model to a plain array suitable for JSON output.
   * @return array
   */
  public function getArray(): array
  {
    return [
      'id'              => $this->id,
      'name'            => $this->name,
      'curationNotes'   => $this->curationNotes,
      'includeInReport' => $this->includeInReport,
      'memberCount'     => $this->memberCount,
      'dateCreated'     => $this->dateCreated,
      'dateModified'    => $this->dateModified,
    ];
  }
}
