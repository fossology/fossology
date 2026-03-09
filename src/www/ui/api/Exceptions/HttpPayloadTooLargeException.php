<?php
/*
 SPDX-FileCopyrightText: © 2026 Divyam Agrawal
 SPDX-FileContributor: Divyam Agrawal <ludicrouslytrue@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\UI\Api\Exceptions;

use Throwable;

/**
 * Exception for HTTP 413 Payload Too Large errors.
 */
class HttpPayloadTooLargeException extends HttpErrorException
{
  public function __construct(string $message, ?Throwable $previous = null)
  {
    parent::__construct($message, 413, $previous);
  }
}
