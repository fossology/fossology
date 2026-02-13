<!-- SPDX-FileCopyrightText: © Fossology contributors
     SPDX-License-Identifier: GPL-2.0-only
-->

# REST API PHPUnit tests

This directory contains PHPUnit tests for the REST API controllers, helpers and models.

## Prerequisites

- PHP with required extensions for Fossology
- Composer dependencies installed in `src/` (so `src/vendor/` exists)

## Run only the REST API test suite

From the `src/` directory:

```sh
php vendor/bin/phpunit -c www/ui_tests/api/tests.xml --testsuite "Fossology PhpUnit REST API"
```
