<!-- SPDX-FileCopyrightText: © Fossology contributors

     SPDX-License-Identifier: GPL-2.0-only
-->

# ScanCode Agent for FOSSology

This agent integrates ScanCode Toolkit into FOSSology for comprehensive license, copyright, email, and URL detection.

## Features

- **License Detection**: Detects licenses in source files and stores them with full text and reference URLs
- **Copyright Detection**: Identifies copyright statements and authorship information
- **Email Detection**: Extracts email addresses from source files
- **URL Detection**: Finds URLs referenced in code and documentation
- **Report Integration**: Copyright findings from ScanCode are included in generated reports (SPDX, Unified Report, etc.)

## Usage

The agent can be scheduled from the FOSSology UI with the following options:
- License scanning (`-l`)
- Copyright scanning (`-r`)  
- Email scanning (`-e`)
- URL scanning (`-u`)

Multiple options can be combined in a single scan.

## Testing

The agent includes comprehensive unit and functional tests:

### Running Unit Tests
```bash
cd /path/to/fossology/build
make test_scancode
./src/scancode/agent_tests/test_scancode
```

### Running Functional Tests
```bash
# CLI tests
cd src/scancode/agent_tests/Functional
bash shunit2 cli_test.sh

# Scheduler tests
phpunit --bootstrap /path/to/phpunit_bootstrap.php schedulerTest.php
```

### Running All Tests
```bash
cd /path/to/fossology/build
ctest -R scancode
```

## Database Tables

The agent creates and populates the following tables:
- `scancode_copyright`: Stores copyright statements
- `scancode_author`: Stores author information
- `scancode_email`: Stores extracted email addresses
- `scancode_url`: Stores found URLs

## Copyright Reports

ScanCode copyright findings are automatically included in generated copyright reports alongside findings from the standard copyright agent. This ensures comprehensive copyright coverage in compliance documentation.
