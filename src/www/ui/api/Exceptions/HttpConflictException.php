<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\UI\Api\Exceptions;

use Throwable;

/**
 * Exception for HTTP 409 Conflict errors.
 */
class HttpConflictException extends HttpErrorException
{
  public function __construct(string $message, ?Throwable $previous = null)
  {
    parent::__construct($message, 409, $previous);
  }
}
