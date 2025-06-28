<?php
/*
 SPDX-FileCopyrightText: © 2025 Vaibhav Sahu <sahusv4527@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class OsselotLookupHelper
{
  private Client $client;
  private string $cacheDir;
  private int $cacheTtl = 86400;

  public function __construct()
  {
      $this->client = new Client([
          'timeout'         => 300,
          'connect_timeout' => 5,
      ]);

      $baseCache = $GLOBALS['SysConf']['DIRECTORIES']['cache'] ?? sys_get_temp_dir();
      $this->cacheDir = rtrim($baseCache, '/\\') . '/util/osselot';

    if (!is_dir($this->cacheDir)) {
        @mkdir($this->cacheDir, 0755, true);
    }
  }

    /**
     * Fetches the list of versions for a given package from the OSSelot curated API.
     *
     * @param string $pkgName Package identifier to look up (e.g., "angular").
     * @return array List of version strings; empty if none found or on error.
     */
  public function getVersions(string $pkgName): array
  {
    if (empty($pkgName)) {
        return [];
    }

      global $SysConf;
      $sysConfig = $SysConf['SYSCONFIG'];
      $curatedUrl = $sysConfig['OsselotCuratedUrl'];

      $apiUrl = $curatedUrl . "?" . rawurlencode($pkgName);

    try {
        $response = $this->client->get($apiUrl, [
            'headers' => [
                'Accept'     => 'text/plain, text/html, */*',
                'User-Agent' => 'Fossology-OsselotHelper',
            ],
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

      if ($response->getStatusCode() !== 200) {
        return [];
      }

        $responseBody = (string)$response->getBody();
      if (empty($responseBody)) {
          return [];
      }

        $versions = [];
        $lines = explode("\n", trim($responseBody));

      foreach ($lines as $line) {
          $line = trim($line);
        if (empty($line)) {
            continue;
        }

        if (preg_match('/^' . preg_quote($pkgName, '/') . '\/version-(.+)$/', $line, $matches)) {
            $version = trim($matches[1]);
          if (!empty($version)) {
              $versions[] = $version;
          }
        }
      }

        $versions = array_unique($versions);
        sort($versions, SORT_NATURAL);

        return $versions;

    } catch (\Exception $e) {
        return [];
    }
  }

    /**
     * Downloads (or retrieves from cache) the SPDX RDF/XML file for a
     * given package/version and returns the local cache pathname,
     * or null if all attempts fail.
     *
     * @param string $pkgName Package identifier
     * @param string $version Version string
     * @return string|null Path to cached file or null on failure
     * @throws \InvalidArgumentException When package name or version is invalid
     * @throws \RuntimeException When cache operations fail
     */
  public function fetchSpdxFile(string $pkgName, string $version): ?string
  {
    if (empty($pkgName) || empty($version)) {
        throw new \InvalidArgumentException('Package name and version cannot be empty');
    }

      global $SysConf;
      $sysConfig = $SysConf['SYSCONFIG'];
      $githubRoot = $sysConfig['OsselotPackageAnalysisUrl'];
      $primaryDomain = $sysConfig['OsselotPrimaryDomain'];
      $fallbackDomain = $sysConfig['OsselotFallbackDomain'];

      $safeName  = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $pkgName);
      $safeVer   = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $version);
      $cacheFile = "{$this->cacheDir}/{$safeName}_{$safeVer}.rdf";

    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheTtl)) {
        return $cacheFile;
    }

      $relPath = sprintf(
          '%s/version-%s/%s-%s.spdx.rdf.xml',
          rawurlencode($pkgName),
          rawurlencode($version),
          rawurlencode($pkgName),
          rawurlencode($version)
      );

      $candidates = [
          "{$githubRoot}/{$relPath}",
          "{$githubRoot}/{$relPath}.gz",
          str_replace($primaryDomain, $fallbackDomain, "{$githubRoot}/{$relPath}"),
      ];

      $options = [
          RequestOptions::HEADERS => [
              'Accept'     => 'application/rdf+xml, application/xml, text/xml',
              'User-Agent' => 'Fossology-OsselotHelper',
          ],
          RequestOptions::HTTP_ERRORS => false,
          RequestOptions::CONNECT_TIMEOUT => 10,
          RequestOptions::TIMEOUT => 30,
      ];

      foreach ($candidates as $url) {
        try {
            $response = $this->client->get($url, $options);

          if ($response->getStatusCode() !== 200) {
            continue;
          }

            $body = (string) $response->getBody();
          if (empty($body)) {
              continue;
          }

          if (str_ends_with($url, '.gz')) {
              $decompressed = @gzdecode($body);
            if ($decompressed === false) {
                continue;
            }
              $body = $decompressed;
          }

          if (!$this->isValidXml($body)) {
              continue;
          }

          if (!is_dir($this->cacheDir)) {
              @mkdir($this->cacheDir, 0755, true);
          }

          if (file_put_contents($cacheFile, $body) !== false) {
              return $cacheFile;
          }

        } catch (\Exception $e) {
            continue;
        }
      }

      return null;
  }
    /**
     * Basic XML validation
     */
  private function isValidXml(string $content): bool
  {
      $previousUseInternalErrors = libxml_use_internal_errors(true);
      libxml_clear_errors();

      $doc = simplexml_load_string($content);
      $errors = libxml_get_errors();

      libxml_use_internal_errors($previousUseInternalErrors);
      libxml_clear_errors();

      return $doc !== false && empty($errors);
  }

    /**
     * Clears the cache directory.
     *
     * @return bool True on success, false on failure.
     */
  public function clearCache(): bool
  {
    if (!is_dir($this->cacheDir)) {
        return true;
    }

    foreach (glob($this->cacheDir . '/*.rdf') as $file) {
      if (is_file($file)) {
          unlink($file);
      }
    }

      return true;
  }
}