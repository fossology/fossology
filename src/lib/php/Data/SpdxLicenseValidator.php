<?php
/*
 SPDX-FileCopyrightText: © 2025 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class SpdxLicenseValidator
{
  const SPDXREF_PREFIX = "LicenseRef-";
  const VALID_IDSTRING_PATTERN = '/^[a-zA-Z0-9.\-]+$/';

  public static function isValidLicenseRef(string $licenseId): bool
  {
    if (empty($licenseId)) {
      return false;
    }

    if (!self::isLicenseRef($licenseId)) {
      return false;
    }

    $idstring = self::extractIdstring($licenseId);
    if (empty($idstring)) {
      return false;
    }

    return (bool) preg_match(self::VALID_IDSTRING_PATTERN, $idstring);
  }

  public static function isLicenseRef(string $licenseId): bool
  {
    return strpos($licenseId, self::SPDXREF_PREFIX) === 0;
  }

  public static function extractIdstring(string $licenseId): string
  {
    if (!self::isLicenseRef($licenseId)) {
      return '';
    }
    return substr($licenseId, strlen(self::SPDXREF_PREFIX));
  }

  public static function getValidationErrors(string $licenseId): array
  {
    $errors = [];

    if (empty($licenseId)) {
      $errors[] = "License identifier is empty";
      return $errors;
    }

    if (!self::isLicenseRef($licenseId)) {
      return $errors;
    }

    $idstring = self::extractIdstring($licenseId);
    if (empty($idstring)) {
      $errors[] = "LicenseRef- prefix found but no idstring follows";
      return $errors;
    }

    $invalidChars = [];
    for ($i = 0; $i < strlen($idstring); $i++) {
      $char = $idstring[$i];
      if (!preg_match('/[a-zA-Z0-9.\-]/', $char)) {
        if (!in_array($char, $invalidChars)) {
          $invalidChars[] = $char;
        }
      }
    }

    if (!empty($invalidChars)) {
      $errors[] = "Contains invalid characters: " . implode(', ', $invalidChars);
    }

    return $errors;
  }

  public static function sanitizeLicenseRef(string $licenseId): string
  {
    if (empty($licenseId)) {
      return $licenseId;
    }

    if (!self::isLicenseRef($licenseId)) {
      return $licenseId;
    }

    $idstring = self::extractIdstring($licenseId);
    if (empty($idstring)) {
      return $licenseId;
    }

    $sanitized = preg_replace('/[^a-zA-Z0-9.\-]/', '-', $idstring);
    $sanitized = preg_replace('/-+/', '-', $sanitized);
    $sanitized = trim($sanitized, '-');

    if (empty($sanitized)) {
      return $licenseId;
    }

    return self::SPDXREF_PREFIX . $sanitized;
  }

  public static function getSuggestion(string $licenseId): string
  {
    return self::sanitizeLicenseRef($licenseId);
  }
}

