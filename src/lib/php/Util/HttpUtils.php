<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG
 Author:

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class HttpUtils
{
  /**
   * Create a preconfigured Guzzle Client.
   * @param array $SysConf SysConf array
   * @param string $baseUri Base URI of the client
   * @param string $token Authentication token. Leave empty if not needed.
   * @return Client Guzzle Client configured with proxy
   */
  public static function getGuzzleClient(array $SysConf, string $baseUri, string $token = "")
  {
    $proxy = [];
    if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
      !empty($SysConf['FOSSOLOGY']['http_proxy'])) {
      $proxy['http'] = $SysConf['FOSSOLOGY']['http_proxy'];
    }
    if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
      !empty($SysConf['FOSSOLOGY']['https_proxy'])) {
      $proxy['https'] = $SysConf['FOSSOLOGY']['https_proxy'];
    }
    if (array_key_exists('no_proxy', $SysConf['FOSSOLOGY']) &&
      !empty($SysConf['FOSSOLOGY']['no_proxy'])) {
      $proxy['no'] = explode(',', $SysConf['FOSSOLOGY']['no_proxy']);
    }

    $version = $SysConf['BUILD']['VERSION'];
    $headers = ['User-Agent' => "fossology/$version"];
    if (!empty($token)) {
      $headers['Authorization'] = 'Bearer ' . $token;
    }

    return new Client([
      'http_errors' => false,
      'proxy' => $proxy,
      'base_uri' => $baseUri,
      'headers' => $headers,
    ]);
  }

  /**
   * Checks the health status of the license database by sending a GET request to a specified health endpoint.
   * Returns a boolean result indicating whether the database is reachable and healthy.
   * Implements error handling for HTTP request failures.
   *
   * @return bool True if the database health check succeeds with an HTTP 200 response, false otherwise.
   */
  public static function checkLicenseDBHealth(string $getHealth, $guzzleClient)
  {
    try {
      $response = $guzzleClient->get($getHealth);
      if ($response->getStatusCode() === 200) {
        return true;
      }
    } catch (RequestException|GuzzleException $e) {
      return false;
    }

    return false;
  }
}
