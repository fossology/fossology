<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG
 Author:

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

use GuzzleHttp\Client;

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
      'headers' => $headers
    ]);
  }
}
