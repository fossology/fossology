<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG
 Author: Shaheem Azmal M MD <shaheem.azmal@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Report;

/**
 * @class CycloneDxSettings
 * @brief CycloneDX export settings of a user (defaults) or of an upload.
 *
 * users.cyclonedx_settings holds the CSV
 * "osselot,comment,ignore[,namespace,specVersion]" (the last two are optional
 * for rows written before they existed); report_info.ri_cyclonedx_selection
 * holds the JSON produced by toReportInfo().
 */
final class CycloneDxSettings
{
  const SUPPORTED_VERSIONS = ['1.4', '1.5', '1.6', '1.7'];
  const DEFAULT_VERSION = '1.7';
  const DEFAULT_NAMESPACE = 'fossology:';
  const MAX_NAMESPACE_LENGTH = 64;
  /** Colon-separated segments ending in a colon; never contains a comma. */
  const NAMESPACE_PATTERN = '/^[A-Za-z0-9._-]+(:[A-Za-z0-9._-]+)*:$/';

  /** @var bool */
  private $licenseComment;
  /** @var bool */
  private $ignoreFilesWithoutInfo;
  /** @var bool */
  private $osselotExport;
  /** @var string */
  private $tagNamespace;
  /** @var string */
  private $specVersion;

  /**
   * Invalid namespace or version values fall back to the defaults.
   */
  public function __construct(bool $licenseComment = false,
    bool $ignoreFilesWithoutInfo = false, bool $osselotExport = false,
    ?string $tagNamespace = null, ?string $specVersion = null)
  {
    $this->licenseComment = $licenseComment;
    $this->ignoreFilesWithoutInfo = $ignoreFilesWithoutInfo;
    $this->osselotExport = $osselotExport;
    $this->tagNamespace = self::normaliseNamespace($tagNamespace) ?? self::DEFAULT_NAMESPACE;
    $this->specVersion = self::normaliseSpecVersion($specVersion) ?? self::DEFAULT_VERSION;
  }

  /**
   * @param string|null $namespace User input, with or without the trailing colon
   * @return string|null The namespace ending in ':', or null if not acceptable
   */
  public static function normaliseNamespace(?string $namespace): ?string
  {
    $namespace = trim((string) $namespace);
    if ($namespace === '') {
      return null;
    }
    if (substr($namespace, -1) !== ':') {
      $namespace .= ':';
    }
    if (strlen($namespace) > self::MAX_NAMESPACE_LENGTH ||
        !preg_match(self::NAMESPACE_PATTERN, $namespace)) {
      return null;
    }
    return $namespace;
  }

  /**
   * @param string|null $version
   * @return string|null The version if supported, else null
   */
  public static function normaliseSpecVersion(?string $version): ?string
  {
    return in_array($version, self::SUPPORTED_VERSIONS, true) ? $version : null;
  }

  /**
   * @param string|null $csv Value of users.cyclonedx_settings
   * @return CycloneDxSettings
   */
  public static function fromUserSettings(?string $csv): self
  {
    $fields = array_pad(explode(',', (string) $csv), 5, null);
    return new self($fields[1] === 'checked', $fields[2] === 'checked',
      $fields[0] === 'checked', $fields[3], $fields[4]);
  }

  /**
   * @return string Value for users.cyclonedx_settings
   */
  public function toUserSettings(): string
  {
    return implode(',', [self::flag($this->osselotExport),
      self::flag($this->licenseComment), self::flag($this->ignoreFilesWithoutInfo),
      $this->tagNamespace, $this->specVersion]);
  }

  /**
   * @param string|null $json Value of report_info.ri_cyclonedx_selection
   * @return CycloneDxSettings|null Null when nothing usable is stored
   */
  public static function fromReportInfo(?string $json): ?self
  {
    $data = json_decode((string) $json, true);
    if (!is_array($data)) {
      return null;
    }
    return new self(!empty($data['licenseComment']),
      !empty($data['ignoreFilesWithoutInfo']), !empty($data['osselotExport']),
      isset($data['tagNamespace']) ? (string) $data['tagNamespace'] : null,
      isset($data['specVersion']) ? (string) $data['specVersion'] : null);
  }

  /**
   * @return string Value for report_info.ri_cyclonedx_selection
   */
  public function toReportInfo(): string
  {
    return json_encode([
      'licenseComment' => $this->licenseComment,
      'ignoreFilesWithoutInfo' => $this->ignoreFilesWithoutInfo,
      'osselotExport' => $this->osselotExport,
      'tagNamespace' => $this->tagNamespace,
      'specVersion' => $this->specVersion
    ]);
  }

  /**
   * @return bool True if license comments go into the report. OSSelot export
   *         requires them.
   */
  public function isLicenseCommentEnabled(): bool
  {
    return $this->licenseComment || $this->osselotExport;
  }

  /**
   * @return bool The stored checkbox value, ignoring the OSSelot implication
   */
  public function isLicenseCommentChecked(): bool
  {
    return $this->licenseComment;
  }

  public function isIgnoreFilesWithoutInfo(): bool
  {
    return $this->ignoreFilesWithoutInfo;
  }

  public function isOsselotExport(): bool
  {
    return $this->osselotExport;
  }

  public function getTagNamespace(): string
  {
    return $this->tagNamespace;
  }

  public function getSpecVersion(): string
  {
    return $this->specVersion;
  }

  private static function flag(bool $value): string
  {
    return $value ? 'checked' : 'unchecked';
  }
}
