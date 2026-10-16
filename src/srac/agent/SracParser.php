<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac;

/**
 * @class SracParser
 * @brief Safely parses and validates JSON documents (SRAC sidecars and SBOMs).
 *
 * Enforces size limits, depth restrictions, and strictly checks for decoding errors.
 * Designed to fail closed when processing untrusted external inputs.
 */
class SracParser
{
  /** Maximum allowed input size in bytes (50 MB) */
  const MAX_FILE_SIZE = 52428800;

  /** Maximum JSON nesting depth */
  const MAX_JSON_DEPTH = 512;

  /**
   * Parse a JSON file from disk safely.
   *
   * @param string $filePath Absolute or relative file path
   * @return array Decoded JSON array
   * @throws \InvalidArgumentException If file cannot be read, exceeds size limits, or has invalid JSON
   */
  public function parseFile($filePath)
  {
    if (!file_exists($filePath)) {
      throw new \InvalidArgumentException("File does not exist: $filePath");
    }

    if (!is_readable($filePath)) {
      throw new \InvalidArgumentException("File is not readable: $filePath");
    }

    $size = filesize($filePath);
    if ($size === false || $size > self::MAX_FILE_SIZE) {
      throw new \InvalidArgumentException(
        "File exceeds maximum allowed size of " . self::MAX_FILE_SIZE . " bytes: $filePath"
      );
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
      throw new \InvalidArgumentException("Failed to read file contents: $filePath");
    }

    return $this->parseString($content, $filePath);
  }

  /**
   * Parse a JSON string safely.
   *
   * @param string $content Raw JSON string
   * @param string|null $sourceName Optional label for error reporting
   * @return array Decoded JSON array
   * @throws \InvalidArgumentException If JSON is invalid or does not represent an object/array
   */
  public function parseString($content, $sourceName = null)
  {
    $label = $sourceName ? "in $sourceName" : "in JSON content";

    if (trim($content) === '') {
      throw new \InvalidArgumentException("Empty input provided $label");
    }

    // Strip optional UTF-8 Byte Order Mark (BOM)
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
      $content = substr($content, 3);
    }

    $decoded = json_decode($content, true, self::MAX_JSON_DEPTH, JSON_BIGINT_AS_STRING);
    $jsonError = json_last_error();

    if ($jsonError !== JSON_ERROR_NONE) {
      $errorMsg = json_last_error_msg();
      throw new \InvalidArgumentException("Malformed JSON $label: $errorMsg (code $jsonError)");
    }

    if (!is_array($decoded)) {
      throw new \InvalidArgumentException(
        "Expected JSON object or array at root $label, got " . gettype($decoded)
      );
    }

    return $decoded;
  }
}
