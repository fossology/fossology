<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG
 Author:

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

use Fossology\Lib\Exceptions\HttpClientException;
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

  /**
   * Processes an HTTP response and handles it based on the status code.
   *
   * This function evaluates the HTTP response status code and performs the following:
   * - For a 200 status code, it decodes the JSON response body and validates the data.
   * - For other status codes, it throws exceptions with appropriate error messages.
   *
   * @param \Psr\Http\Message\ResponseInterface $response The HTTP response object to process.
   *
   * @return mixed Decoded JSON data from the response body if the status code is 200.
   *
   * @throws HttpClientException If the response contains errors, invalid JSON, or unexpected status codes.
   */
  public static function processHttpResponse($response)
  {
    $statusCode = $response->getStatusCode();
    switch ($statusCode) {
      case 200:
        $data = json_decode($response->getBody()->getContents());
        if ($data === null) {
          if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpClientException("Error decoding JSON: " . json_last_error_msg());
          }
          throw new HttpClientException("No Data Found");
        }
        if (empty($data)) {
            throw new HttpClientException("There is no Data Present in the Database");
        }
        return $data;
      case 401:
        throw new HttpClientException("Unauthorized access. Please check your credentials or token.");
      case 403:
        throw new HttpClientException("Access forbidden. You may not have the necessary permissions.");
      case 404:
        throw new HttpClientException("Resource not found. The requested URL may be incorrect.");
      case 500:
        throw new HttpClientException("Internal Server Error. Please try again later.");
      default:
        throw new HttpClientException("Unexpected status code: $statusCode");
    }
  }
}
