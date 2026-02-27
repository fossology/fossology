<?php
/*
 SPDX-FileCopyrightText: © 2025 Sandip Mandal

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

/**
 * @class SpdxLicenseValidator
 * @brief Validate and sanitize SPDX LicenseRef identifiers
 */
class SpdxLicenseValidator
{
  const VALID_IDSTRING_PATTERN = '/^[a-zA-Z0-9.\-]+$/';

  public static function isValidLicenseRef(string $licenseId): bool
  {
    if (empty($licenseId) || !self::isLicenseRef($licenseId)) {
      return false;
    }

    $idstring = self::extractIdstring($licenseId);
    return !empty($idstring) && preg_match(self::VALID_IDSTRING_PATTERN, $idstring);
  }

  public static function isLicenseRef(string $licenseId): bool
  {
    return strpos($licenseId, LicenseRef::SPDXREF_PREFIX) === 0;
  }

  public static function extractIdstring(string $licenseId): string
  {
    if (!self::isLicenseRef($licenseId)) {
      return '';
    }
    return substr($licenseId, strlen(LicenseRef::SPDXREF_PREFIX));
  }

  public static function getValidationErrors(string $licenseId): array
  {
    if (empty($licenseId)) {
      return ["License identifier is empty"];
    }

    if (!self::isLicenseRef($licenseId)) {
      return [];
    }

    $idstring = self::extractIdstring($licenseId);
    if (empty($idstring)) {
      return ["LicenseRef- prefix found but no idstring follows"];
    }

    $invalidChars = [];
    for ($i = 0; $i < strlen($idstring); $i++) {
      $char = $idstring[$i];
      if (!preg_match('/[a-zA-Z0-9.\-]/', $char) && !in_array($char, $invalidChars)) {
        $invalidChars[] = $char;
      }
    }

    if (!empty($invalidChars)) {
      return ["Contains invalid characters: " . implode(', ', array_map(fn($c) => "'$c'", $invalidChars))];
    }

    return [];
  }

  public static function sanitizeLicenseRef(string $licenseId): string
  {
    if (empty($licenseId) || !self::isLicenseRef($licenseId)) {
      return $licenseId;
    }

    $idstring = self::extractIdstring($licenseId);
    if (empty($idstring)) {
      return $licenseId;
    }

    $sanitized = preg_replace('/[^a-zA-Z0-9.\-]/', '-', $idstring);
    $sanitized = preg_replace('/-+/', '-', $sanitized);
    $sanitized = trim($sanitized, '-');

    return empty($sanitized) ? $licenseId : LicenseRef::SPDXREF_PREFIX . $sanitized;
  }
}
