<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\UI\Api\Exceptions;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Base exception class for HTTP error status in API.
 */
class HttpErrorException extends Exception
{
  /**
   * @var ServerRequestInterface $request
   * HTTP Slim Request
   */
  protected ServerRequestInterface $request;
  /**
   * @var array $headers
   * HTTP headers to be sent with the error response
   */
  protected array $headers = [];

  public function __construct(string $message, int $code,
                              ?Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }

  /**
   * @return ServerRequestInterface
   */
  public function getRequest(): ServerRequestInterface
  {
    return $this->request;
  }

  /**
   * @param array $headers
   * @return HttpErrorException
   */
  public function setHeaders(array $headers): HttpErrorException
  {
    $this->headers = $headers;
    return $this;
  }

  /**
   * @return array
   */
  public function getHeaders(): array
  {
    return $this->headers;
  }
}
