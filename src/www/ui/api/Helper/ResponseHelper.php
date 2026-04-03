<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Helper for simpler Slim responses
 */
namespace Fossology\UI\Api\Helper;

use Slim\Psr7\Response;

/**
 * @class ResponseHelper
 * @brief Override Slim response for withJson function
 */
class ResponseHelper extends Response
{
  /**
   * Create a standardized error response payload.
   *
   * @param array $arr   Original payload
   * @param int   $stat  HTTP status code
   *
   * @return array
   */
  private function normalizeErrorPayload(array $arr, int $stat): array
  {
    if (isset($arr['status'], $arr['error'], $arr['message'])) {
      return $arr;
    }

    $reasonPhrase = (new self($stat))->getReasonPhrase();
    $message = $arr['message'] ?? $arr['error'] ?? $reasonPhrase;
    $error = $arr['error'] ?? $reasonPhrase;

    $payload = [
      'status' => $stat,
      'error' => $error,
      'message' => $message
    ];

    foreach ($arr as $key => $value) {
      if (in_array($key, ['code', 'type', 'status', 'error', 'message'], true)) {
        continue;
      }
      $payload[$key] = $value;
    }

    return $payload;
  }

  /**
   * Create a JSON response from Info objects
   *
   * @param array $arr  Array to return
   * @param int   $stat Return status
   */
  public function withJson($arr, int $stat=200)
  {
    if ($stat >= 400 && is_array($arr)) {
      $arr = $this->normalizeErrorPayload($arr, $stat);
    }
    $this->getBody()->write(json_encode($arr));
    return $this->withHeader("Content-Type", "application/json")
      ->withStatus($stat);
  }
}
