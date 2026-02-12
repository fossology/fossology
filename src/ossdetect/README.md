<!-- SPDX-FileCopyrightText: © Fossology contributors

     SPDX-License-Identifier: GPL-2.0-only
-->

## Automatic OSS Component Detection

This agent automatically detects open-source components by analyzing package metadata files commonly found in software projects. It extracts dependency information from these files and attempts to match them against known OSS components.

### Supported Metadata Formats

The agent currently supports the following metadata file formats:

- **pom.xml** - Maven (Java)
- **package.json** - npm (JavaScript/Node.js)
- **requirements.txt** - pip (Python)
- **go.mod** - Go modules
- **Gemfile** - Bundler (Ruby)
- **Cargo.toml** - Cargo (Rust)

### How It Works

1. During the upload scan, the agent searches for supported metadata files
2. Each detected file is parsed to extract dependency information
3. Dependencies are matched against Fossology's known OSS component database
4. Similarity scores are calculated based on name and version matching
5. Results are displayed in a dedicated "OSS Components" tab

### Usage

1. From Fossology main menu, select *Upload* > *From File*
2. Check the *OSS Detection* option under *Select optional analysis*
3. Wait for the scan to complete
4. Browse to a metadata file and select the *OSS Components* tab
5. View detected dependencies with their similarity scores

### Configuration

The similarity matching threshold can be adjusted in the admin panel under *Customize* > *OSS Detection Config*.

Default threshold is 80% - dependencies with similarity scores above this value are highlighted as potential matches.

### Implementation Notes

This agent uses a combination of C++ for efficient database operations and Python for flexible metadata parsing. The similarity matching algorithm considers:

- Exact name matches
- Case-insensitive name variations
- Version proximity (when version info is available)
- Package registry metadata (npm, Maven Central, etc.)

For more technical details, see the [implementation plan](../../.gemini/antigravity/brain/74aaaf55-4630-43ae-bd9c-a8e17ecfef83/implementation_plan.md).
