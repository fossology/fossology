<!--
SPDX-FileCopyrightText: © 2025 FOSSology contributors
SPDX-License-Identifier: GPL-2.0-only
-->

# Compatibility Agent

The Compatibility Agent is a FOSSology agent that analyzes license compatibility between different licenses found in files. It checks whether licenses can be legally combined together based on configurable rules and highlights potential compatibility issues in the FOSSology UI.

## Overview

The agent performs compatibility analysis by:
- Detecting all licenses present in a file
- Comparing them against a configurable rule set (YAML format)
- Storing compatibility results in the database
- Highlighting incompatible license combinations in the UI with red indicators

## Features

- **Multi-threaded processing**: Creates separate threads for each file for faster analysis
- **Dual operation modes**: Can run in scheduler mode (integrated with FOSSology) or CLI mode (standalone)
- **Flexible rule system**: Uses YAML-based rules that support license names, license types, and default behaviors
- **JSON output support**: CLI mode can output results in JSON format
- **OSADL integration**: Can import compatibility rules from the OSADL compatibility matrix
- **Database integration**: Stores results in FOSSology database for UI display

## Architecture

### Core Components

- **`CompatibilityAgent`**: Main agent class that processes files and checks compatibility
- **`CompatibilityDatabaseHandler`**: Handles database operations for storing/retrieving results
- **`CompatibilityUtils`**: Utility functions for CLI parsing, rule processing, and file operations
- **`CompatibilityState`**: Manages agent state and configuration
- **UI Plugin**: PHP-based web interface integration

### Directory Structure

```
src/compatibility/
├── agent/                    # Core agent implementation
│   ├── compatibility.cc      # Main entry point
│   ├── CompatibilityAgent.*  # Main agent class
│   ├── CompatibilityUtils.*  # Utility functions
│   ├── CompatibilityDatabaseHandler.*  # Database operations
│   └── CompatibilityState.*  # State management
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

The agent runs automatically as part of FOSSology analysis jobs. It:
1. Depends on license detection agents (e.g. `nomos`, `monk`, `ojo`, and `agent_adj2nest`)
   to ensure license information is available.
2. Processes each upload and compares found licenses
3. Stores results in the `compatibility_ars` table
4. Displays results in the FOSSology UI with red highlights for incompatible licenses

To use in the FOSSology UI:
1. Navigate to the license analysis section
2. Enable "Compatibility License Analysis" 
3. The agent will run automatically and highlight compatibility issues

### CLI Mode (Standalone)

The agent can also run independently from the command line:

```bash
# Basic compatibility check with rule file and license types
./compatibility --types license-map.csv --rules comp-rules.yaml --file input.json --main-license GPL-2.0-only

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
| `--file arg` | JSON file containing licenses to analyze |
| `-J, --json` | Output results in JSON format |
| `--types arg` | CSV file mapping license names to types |
| `--rules arg` | YAML file containing compatibility rules |
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
Apache-2.0,Weak Copyleft
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
    
  # Specific license rule - GPL-2.0 and Apache-2.0 are incompatible
  - firstname: GPL-2.0-only
    secondname: Apache-2.0
    firsttype: ~
    secondtype: ~
    compatibility: "false"
    comment: GPL and Apache are incompatible
    
  # Mixed rule - specific license with license type
  - firstname: GPL-2.0-only
    secondname: ~
    firsttype: ~
    secondtype: Permissive
    compatibility: "true"
    comment: GPL-2.0 is compatible with permissive licenses
```

#### Rule Priority

Rules are matched in the following order:
1. Specific license name pairs (`firstname` and `secondname` both specified)
2. License type pairs (`firsttype` and `secondtype` both specified)
3. Mixed rules (license name + license type)
4. Default rule (`~` wildcards for all fields)

### Input JSON Format

For CLI mode, provide license information in JSON format:

```json
{
  "licenses": ["MIT", "GPL-2.0-only", "Apache-2.0"],
  "file": "example.c"
}
```

### Output JSON Format

Results are returned in structured JSON:

```json
{
  "results": [
    {
      "file": "example.c",
      "licenses": ["MIT", "GPL-2.0-only"],
      "compatible": true
    }
  ]
}
```

## OSADL Integration

The agent integrates with the [OSADL License Compatibility Matrix](https://www.osadl.org/Access-to-raw-data.oss-compliance-raw-data-access.0.html) through the OSADL converter utility.

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

The OSADL converter automatically:
- Downloads the latest compatibility matrix
- Maps FOSSology license names to OSADL identifiers
- Generates optimized rules using license grouping
- Reduces rule complexity through type-based matching

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

# Create input
cat > input.json << EOF
{
  "licenses": ["MIT", "GPL-2.0-only"],
  "file": "test.c"
}
EOF

# Run compatibility check
./compatibility --types licenses.csv --rules rules.yaml --file input.json --json
```

### Example 2: Using Test Data

The agent includes test data in `agent_tests/testdata/`:

```bash
cd src/compatibility/agent_tests/testdata

# Run with provided test files
../../../compatibility \
  --types license-map.csv \
  --rules comp-rules-test.yaml \
  --file compatible-output.json \
  --json
```

## Building and Testing

### Build Requirements

- C++11 compatible compiler
- CMake 3.0+
- Boost libraries (program_options)
- yaml-cpp library
- jsoncpp library
- FOSSology development libraries

### Building

```bash
cd src/compatibility
mkdir build && cd build
cmake ..
make
```

### Running Tests

```bash
# Unit tests
make test

# Or run specific test suites
ctest -R Unit
ctest -R Functional
```

## Database Schema

The agent uses the following database tables:

- **`compatibility_ars`**: Agent run status and metadata
- **`compatibility_result`**: License compatibility results per file
- **`license_ref`**: License reference data
- **`license_map`**: License name to type mappings

## Troubleshooting

### Common Issues

1. **"No rules file specified"**: Ensure `--rules` parameter points to valid YAML file
2. **"License type not found"**: Check that all licenses in input are defined in CSV mapping
3. **"Database connection failed"**: Verify FOSSology database configuration
4. **Red highlights not showing**: Ensure agent completed successfully and refresh browser

### Debug Mode

Enable verbose output for detailed logging:

```bash
./compatibility -v --types licenses.csv --rules rules.yaml --file input.json
```

### Log Files

Check FOSSology logs for scheduler mode issues:
- `/var/log/fossology/fossology.log`
- Agent-specific logs in scheduler output

## Related Documentation

- [OSADL Converter Documentation](../../utils/OSADL_CONVERTOR.md)
- [FOSSology Agent Development Guide](https://github.com/fossology/fossology/wiki)
- [License Compatibility Theory](https://www.fossology.org/get-started/basic-workflow/) 
