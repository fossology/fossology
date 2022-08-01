<?php
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

namespace Fossology\Lib\Application;

/**
 * @file
 * @brief Service to create new curl request handler.
 */

/**
 * @class CurlRequestService
 * Service to create new curl request handler.
 */
class CurlRequestService
{
  /**
   * Create and return a new curl request handler.
   * @param string $url URL to access.
   * @return Fossology::Lib::Application::CurlRequest
   */
  public function create($url)
  {
    return new CurlRequest($url);
  }
}
