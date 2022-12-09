<?php
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

/**
 * @dir
 * @brief Utility functions for specific applications
 * @file
 * @brief Utility functions to handle curl requests
 */

/**
 * @namespace Fossology::Lib::Application
 * @brief Utility functions for specific applications
 */
namespace Fossology\Lib\Application;

/**
 * @class CurlRequest
 * @brief Handle curl requests
 */
class CurlRequest
{
  /**
   * @var resource $handle
   * Resource to handle curl requests.
   */
  private $handle = null;

  /**
   * Constructor to initialize curl handler with URL.
   * @param string $url URL to initialize handler with.
   */
  public function __construct($url)
  {
    $this->handle = curl_init($url);
  }

  /**
   * Set curl options for the handle
   * @param array $options Options for curl handler
   */
  public function setOptions($options)
  {
    curl_setopt_array($this->handle, $options);
  }

  /**
   * Execute curl request.
   * @return bool
   */
  public function execute()
  {
    return curl_exec($this->handle);
  }

  /**
   * Get info from curl request.
   * @param int $resource Required info
   * @return mixed
   */
  public function getInfo($resource)
  {
    return curl_getinfo($this->handle, $resource);
  }

  /**
   * Close the curl handle.
   */
  public function close()
  {
    curl_close($this->handle);
  }
}
