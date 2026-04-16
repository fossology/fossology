<!--
SPDX-FileCopyrightText: © 2025 FOSSology contributors
SPDX-License-Identifier: GPL-2.0-only
-->

# Compatibility Agent

The Compatibility Agent is a FOSSology agent that analyzes license compatibility between different licenses found in files. It checks whether licenses can be legally combined together based on configurable rules and highlights potential compatibility issues in the FOSSology UI.

## Overview

The agent performs compatibility analysis by:
- Collecting all licenses detected in a file by scanner agents
- Comparing each pair of licenses against a configurable rule set (YAML rules in CLI mode, database rules in scheduler mode)
- Storing compatibility results in the database
- Highlighting incompatible license combinations in the UI with red indicators

## Features

- **Multi-threaded processing**: Uses OpenMP to process files in parallel for faster analysis
- **Dual operation modes**: Can run in scheduler mode (integrated with FOSSology) or CLI mode (standalone)
- **Flexible rule system**: Uses YAML-based rules that support license names, license types, and default behaviors
- **JSON output support**: CLI mode can output results in JSON format
- **OSADL integration**: Can import compatibility rules from the OSADL compatibility matrix
- **Database integration**: Stores results in FOSSology database for UI display

## Architecture

### Core Components

- **`CompatibilityAgent`**: Main agent class that processes files and checks compatibility between license pairs
- **`CompatibilityDatabaseHandler`**: Handles database operations for storing/retrieving results and querying rules
- **`CompatibilityUtils`**: Utility functions for CLI parsing, rule processing, and file operations
- **`CompatibilityState`**: Manages agent state and configuration
- **`CompatibilityAgentPlugin`** (`agent-compatibility.php`): PHP-based web interface integration

### Directory Structure

```
src/compatibility/
├── agent/                    # Core agent implementation (C++)
│   ├── compatibility.cc      # Main entry point
│   ├── CompatibilityAgent.*  # Main agent class
│   ├── CompatibilityUtils.*  # Utility functions
│   ├── CompatibilityDatabaseHandler.*  # Database operations
│   ├── CompatibilityState.*  # State management
│   └── CompatibilityStatus.hpp  # Status enum (COMPATIBLE / NOTCOMPATIBLE / UNKNOWN)
├── ui/                       # Web interface
│   └── agent-compatibility.php
├── agent_tests/             # Test suite
│   ├── testdata/           # Example configurations and test files
│   ├── Unit/               # Unit tests
│   └── Functional/         # Functional tests
├── compatibility.conf       # Scheduler configuration
└── CMakeLists.txt          # Build configuration
```

## Usage

### Scheduler Mode (Integrated with FOSSology)

The agent runs as part of FOSSology analysis jobs. It:
1. Depends on license detection agents (`nomos`, `monk`, `ojo`, `ninka`) and `agent_adj2nest`
   to ensure license information is available.
2. Processes each upload and compares found license pairs using rules stored in the `license_rules` database table
3. Stores results in the `comp_result` table
4. Displays results in the FOSSology UI with red highlights for incompatible licenses

To use in the FOSSology UI:
1. Upload a package and select the license scanners to run
2. Check "Compatibility License Analysis" in the optional analysis section
3. The agent will run after the license scanners complete and highlight compatibility issues in the tree view

### CLI Mode (Standalone)

The agent can also run independently from the command line:

```bash
# Basic compatibility check with rule file and license types
./compatibility --types license-map.csv --rules comp-rules.yaml --file input.json --main_license GPL-2.0-only

# JSON output mode
./compatibility --types license-map.csv --rules comp-rules.yaml --file input.json --json

# Verbose mode for debugging
./compatibility -v --types license-map.csv --rules comp-rules.yaml --file input.json
```

#### Command Line Options

| Flag | Description |
|------|-------------|
| `-h, --help` | Show help message |
| `-v, --verbose` | Enable verbose output for debugging |
| `--file arg` (`-f`) | JSON file containing file names and licenses |
| `-J, --json` | Output results in JSON format |
| `--types arg` (`-t`) | CSV file mapping license names to types |
| `--rules arg` (`-r`) | YAML file containing compatibility rules |
| `--main_license arg` | Main license for the package (supports `AND` for multiple) |
| `-c, --config arg` | Path to system configuration directory |
| `--scheduler_start` | Run in scheduler mode (internal use) |
| `--userID arg` | User ID for scheduler mode |
| `--groupID arg` | Group ID for scheduler mode |
| `--jobId arg` | Job ID for scheduler mode |

## Configuration Files

### License Type Mapping (CSV)

Maps license short names to their types for rule matching:

```csv
shortname,licensetype
MIT,Permissive
GPL-2.0-only,Strong Copyleft
Apache-2.0,Permissive
LGPL-2.1-or-later,Weak Copyleft
BSD-3-Clause,Permissive
```

### Compatibility Rules (YAML)

Defines compatibility rules between licenses:

```yaml
---
default: false  # Default compatibility if no rules match
rules:
  # Rule by license type - all permissive licenses are compatible
  - firstname: ~
    secondname: ~
    firsttype: Permissive
    secondtype: Permissive
    compatibility: "true"
    comment: All permissive licenses can be used together

  # Specific license rule
  - firstname: GPL-2.0-only
    secondname: Apache-2.0
    firsttype: ~
    secondtype: ~
    compatibility: "false"
    comment: GPL-2.0 and Apache-2.0 are incompatible

  # Mixed rule - specific license with license type
  - firstname: GPL-2.0-only
    secondname: ~
    firsttype: ~
    secondtype: Permissive
    compatibility: "true"
    comment: GPL-2.0 is compatible with permissive licenses
```

#### Rule Priority

Rules are matched in the following order (first match wins):
1. **Specific license name pairs** (`firstname` and `secondname` both specified) — checked in both directions
2. **License type pairs** (`firsttype` and `secondtype` both specified) — checked in both directions
3. **Mixed rules** (license name + license type) — checked in all four combinations
4. **Default rule** (`~` wildcards for all fields)

### Input JSON Format (CLI Mode)

Provide license information as a JSON file with a `results` array. Each entry contains a file name and its detected licenses:

```json
{
  "results": [
    {
      "file": "src/example.c",
      "licenses": ["MIT", "GPL-2.0-only", "Apache-2.0"]
    },
    {
      "file": "src/utils.c",
      "licenses": ["BSD-3-Clause", "MIT"]
    }
  ]
}
```

> **Note:** Licenses named `Dual-license`, `No_license_found`, and `Void` are automatically excluded from compatibility checks.

### Output JSON Format (CLI Mode)

When using `--json`, results are written to stdout as a JSON array. Each entry contains a file name and an array of pairwise compatibility results:

```json
[
  {
    "file": "src/example.c",
    "results": [
      {
        "license": ["MIT", "GPL-2.0-only"],
        "compatibility": true
      },
      {
        "license": ["MIT", "Apache-2.0"],
        "compatibility": true
      },
      {
        "license": ["Apache-2.0", "GPL-2.0-only"],
        "compatibility": false
      }
    ]
  },
  {
    "package-level-result": [
      {
        "license": ["MIT", "GPL-2.0-only"],
        "compatibility": true
      }
    ]
  }
]
```

The final entry with `"package-level-result"` contains the compatibility results across all licenses found in the entire package.

## OSADL Integration

The agent integrates with the [OSADL License Compatibility Matrix](https://www.osadl.org/Access-to-raw-data.oss-compliance-raw-data-access.0.html) through the OSADL converter utility located in the `utils/` directory at the repository root.

### Importing OSADL Rules

1. **Install dependencies**:
   ```bash
   python3 -m pip install -r utils/requirements.osadl.txt
   ```

2. **Generate YAML rules from OSADL matrix**:
   ```bash
   python3 utils/osadl_convertor.py \
     --user USERNAME \
     --password PASSWORD \
     --database fossology \
     --yaml osadl_rules.yaml
   ```

3. **Import rules in FOSSology UI**:
   - Navigate to Admin → License Compatibility
   - Upload the generated YAML file

## Examples

### Example 1: Basic Compatibility Check

```bash
# Create license mapping
cat > licenses.csv << EOF
shortname,licensetype
MIT,Permissive
GPL-2.0-only,Strong Copyleft
EOF

# Create rules
cat > rules.yaml << EOF
---
default: false
rules:
- firstname: ~
  secondname: ~
  firsttype: Strong Copyleft
  secondtype: Permissive
  compatibility: "true"
EOF

# Create input (note the "results" wrapper)
cat > input.json << EOF
{
  "results": [
    {
      "file": "test.c",
      "licenses": ["MIT", "GPL-2.0-only"]
    }
  ]
}
EOF

# Run compatibility check
./compatibility --types licenses.csv --rules rules.yaml --file input.json --json
```

### Example 2: Using Test Data

The agent includes test data in `agent_tests/testdata/`:

```bash
cd src/compatibility

# Run with provided test files
./compatibility \
  --types agent_tests/testdata/license-map.csv \
  --rules agent_tests/testdata/comp-rules-test.yaml \
  --file agent_tests/testdata/compatible-output.json \
  --json
```

## Building and Testing

### Build Requirements

- C++ compiler with C++17 and OpenMP support
- CMake 3.18+
- Boost libraries (program_options)
- yaml-cpp library
- jsoncpp library
- FOSSology C/C++ libraries (libfossologyCPP, libfossology)

### Building

FOSSology uses a top-level CMake build system. To build the compatibility agent:

```bash
cd /path/to/fossology
mkdir build && cd build
cmake ..
make compatibility_exec
```

### Running Tests

```bash
cd /path/to/fossology/build

# Run all compatibility tests
ctest -R compatibility

# Or run specific test suites
ctest -R compatibility_unit
ctest -R compatibility_functional
```

## Database Schema

The agent uses the following database tables:

- **`comp_result`**: Stores pairwise license compatibility results per file (columns: `pfile_fk`, `agent_fk`, `first_rf_fk`, `second_rf_fk`, `result`)
- **`compatibility_ars`**: Agent run status tracking (standard FOSSology ARS table)
- **`license_rules`**: Stores compatibility rules with columns for license IDs (`first_rf_fk`, `second_rf_fk`), license types (`first_type`, `second_type`), and a `compatibility` boolean
- **`license_ref`**: Standard FOSSology license reference table (used to look up license types via `rf_licensetype`)

## Troubleshooting

### Common Issues

1. **Agent not running**: Ensure license scanner agents (nomos, monk, ojo) have completed first — compatibility depends on their results
2. **No compatibility results**: Compatibility is only computed for files with 2 or more detected licenses
3. **Red highlights not showing**: Ensure the agent completed successfully (check job status) and refresh the browser
4. **Missing license types**: In scheduler mode, license types come from the `rf_licensetype` column in `license_ref`. Ensure licenses have types assigned

### Debug Mode

Enable verbose output for detailed logging:

```bash
./compatibility -v --types licenses.csv --rules rules.yaml --file input.json
```

### Log Files

Check FOSSology logs for scheduler mode issues:
- `/var/log/fossology/fossology.log`
- Agent-specific logs in scheduler output
