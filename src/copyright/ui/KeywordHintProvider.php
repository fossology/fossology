<?php
/*
 SPDX-FileCopyrightText: © 2026 DenishShiroya22 <denishshiroya22@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Agent\Copyright\UI;

/**
 * Resolves auditor guidance for keyword findings using site configuration.
 *
 * The hints are presentation metadata. Existing scanner findings and database
 * records remain unchanged, so older scans also benefit from the configured hints.
 */
class KeywordHintProvider
{
  /** @var array<string, string> */
  private $hints = array();

  /**
   * @param string|null $configPath Optional override for the installed configuration
   */
  public function __construct(?string $configPath = null)
  {
    if ($configPath === null) {
      $sysconfDir = $GLOBALS['SYSCONFDIR'] ?? '';
      if ($sysconfDir === '') {
        return;
      }
      $configPath = rtrim($sysconfDir, '/') . '/mods-enabled/keyword/agent/keyword-hints.json';
    }

    if (!is_file($configPath) || !is_readable($configPath)) {
      return;
    }

    $content = file_get_contents($configPath);
    if ($content === false) {
      return;
    }

    $configuration = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE ||
        !is_array($configuration) ||
        !isset($configuration['groups']) ||
        !is_array($configuration['groups'])) {
      error_log('Invalid FOSSology keyword hints configuration: ' . $configPath);
      return;
    }

    foreach ($configuration['groups'] as $group) {
      if (!is_array($group) ||
          !isset($group['keywords'], $group['text']) ||
          !is_array($group['keywords']) ||
          !is_string($group['text'])) {
        continue;
      }

      foreach ($group['keywords'] as $keyword) {
        if (!is_string($keyword)) {
          continue;
        }
        $key = self::normalize($keyword);
        if ($key !== null && $key !== '' && !array_key_exists($key, $this->hints)) {
          $this->hints[$key] = $group['text'];
        }
      }
    }
  }

  /**
   * @param string $keyword Detected keyword text, rather than a scanner expression
   * @return string Configured plain text; HTML escaping belongs to the renderer
   */
  public function getHint(string $keyword): string
  {
    $key = self::normalize($keyword);
    return $key === null ? '' : ($this->hints[$key] ?? '');
  }

  /**
   * @param string $keyword Keyword text to normalize
   * @return string|null Normalized text, or null for invalid UTF-8
   */
  private static function normalize(string $keyword): ?string
  {
    $normalized = preg_replace('/\\s+/u', ' ', $keyword);
    if ($normalized === null) {
      return null;
    }
    return mb_strtolower(trim($normalized), 'UTF-8');
  }
}
