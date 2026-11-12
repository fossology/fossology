# SRAC (Safety Relevance Assertion Capability) Sidecar Correlation

<!--
SPDX-FileCopyrightText: © 2026 Siemens AG
SPDX-License-Identifier: FSFAP
-->

## Overview

The **SRAC (Safety Relevance Assertion Capability) Sidecar Correlation** module provides an optional, read-only FOSSology workflow to import, validate, and correlate an externally authored SRAC JSON sidecar document with software components declared in a Software Bill of Materials (SBOM) in **SPDX 2.x** or **CycloneDX** format.

### Prototype Status Notice
The SRAC format supported by this implementation is based on the **Eclipse S-CORE prototype specification (`0.2-draft`)**. As an emerging development draft rather than an established normative standard, schema versions and mapping conventions may evolve in future S-CORE specifications.

---

## Fundamental Safety Boundary & Invariants

> **IMPORTANT: FOSSology consumes and reports externally authored safety assertions but does NOT create or approve safety decisions.**

The SRAC module strictly adheres to the following principles:

1. **Isolation from License & Clearing Conclusions**:
   - The SRAC module is completely isolated from FOSSology's license scanning, copyright clearing (`clearingDao`), and vulnerability databases.
   - It will **never** alter, influence, or override any license conclusion or clearing status.

2. **No Safety Inference**:
   - The system **never** infers safety relevance for a component.
   - The system **never** creates, modifies, or approves a safety classification.
   - Missing SRAC data is **never** interpreted as "not safety-related" or "safe" (missing data is recorded as `unmatched` / absence of assertion).

3. **No Component Mutation**:
   - Neither the source SBOM nor the input SRAC sidecar is mutated.
   - External assertion values, rationale, evidence pointers, and review statuses are preserved verbatim.

4. **Fail-Closed Behavior**:
   - If an assertion or input is invalid, ambiguous, or fails integrity verification, it is flagged as `invalid` or `ambiguous`. The tool will never make "best-effort guesses" or silent fallbacks.

---

## Supported Input Formats

### 1. SRAC Sidecars
- **Schema**: Eclipse S-CORE `srac.schema.json` version `0.2-draft`.
- **Top-Level Root Structure**:
  - `srac_version`: `"0.2-draft"`
  - `assertion_collection`: Array of assertion objects:
    - `element_id`: Unique identifier for the assertion.
    - `element_assertion`: Object containing:
      - `subject`: References target component (`spdx_id`, `purl`, or `bom_ref`).
      - `assessment`: Target safety relevance assessment (`safety_relevant` boolean, `rationale`, `evidence`).
      - `review`: Review status object (`state`, `reviewer`, `date`).
      - `provenance`: Origin tracking metadata (`author`, `timestamp`, `tool`).
      - `integrity`: Cryptographic hashes (e.g. SHA-256) of referenced artifacts or source components.

### 2. Supported SBOM Formats
- **SPDX 2.x (JSON)**:
  - Top-level `packages` array.
  - Matches against `SPDXID`, `checksums` (e.g., `SHA256`), and `externalRefs` (where `referenceType == "purl"`).
  - External reference to SRAC sidecar detected in `externalRefs` with `referenceType == "OTHER"` and `comment` containing `"srac"`.
- **CycloneDX 1.x (JSON)**:
  - Top-level `components` array.
  - Matches against `bom-ref`, `hashes` (e.g., `SHA-256`), and `purl`.
  - External reference to SRAC sidecar detected in `externalReferences` with `type == "other"` and `comment` containing `"SRAC sidecar"`.

---

## Deterministic Correlation Rules

Components and assertions are matched using an explicit, deterministic 3-tier precedence order:

1. **Artifact Digest Match (`artifact_digest`)**:
   - If the SRAC assertion specifies an artifact integrity digest (e.g. SHA-256), it matches against the SBOM component's package checksums (`SPDX: SHA256` or `CycloneDX: SHA-256`).
2. **PURL + Version Match (`purl_version`)**:
   - If the SRAC assertion specifies a `purl` (Package URL), it matches against the SBOM component's declared PURL. Both component and version must match deterministically.
3. **SBOM Identifier Match (`sbom_id`)**:
   - Matches SRAC `spdx_id` against SPDX `SPDXID`, or SRAC `bom_ref` against CycloneDX `bom-ref`.

### Strict Matching Invariants:
- **No Name Matching**: Matching by component or package name alone is **strictly prohibited** to prevent false correlations across versions or unrelated libraries.
- **Ambiguity Detection**: If multiple components in the target SBOM satisfy a matching rule, the assertion is marked as `ambiguous`, listing all candidate component IDs. It is **never** automatically assigned to a candidate.

---

## Assertion Result States

Every processed assertion is classified into exactly one mutually exclusive result state:

| State | Description |
| :--- | :--- |
| `matched` | The assertion matched exactly one SBOM component via digest, PURL, or SBOM ID. |
| `unmatched` | No matching component was found in the target SBOM for this assertion. |
| `ambiguous` | Multiple SBOM components matched the assertion criteria; requires manual audit. |
| `invalid` | Assertion failed schema validation, malformed structure, missing required fields, or failed cryptographic digest verification. |
| `stale` | Only emitted when defensible freshness signals are present (e.g. reviewed date precedes component update timestamp or expiry threshold). |

---

## Integrity & Verification

1. **SRAC Sidecar Document Digest**:
   - Upon reading the SRAC JSON sidecar, its full SHA-256 digest is calculated and recorded in the audit report to prove document provenance and immutability.
2. **Sidecar Reference Digest Verification**:
   - If the source SBOM contains an external reference to an SRAC sidecar including a checksum/digest, the actual sidecar file's SHA-256 is validated against the SBOM's declared checksum. A mismatch flags the entire correlation as `invalid`.
3. **Artifact Integrity Verification**:
   - Where SRAC assertions declare component digests, they are verified against the corresponding SBOM component checksums.

---

## Usage

### Command-Line Interface (CLI)

The SRAC agent can run directly via the command-line without requiring a scheduler or live database connection:

```bash
php /usr/share/fossology/srac/agent/srac.php \
    --sbom path/to/sbom.json \
    --srac path/to/sidecar.srac.json \
    --format json \
    --output path/to/report.json
```

#### CLI Options:
- `--sbom <file>`: Path to SPDX 2.x or CycloneDX JSON SBOM file.
- `--srac <file>`: Path to SRAC JSON sidecar file. If omitted, the agent searches for an external reference declared inside the SBOM.
- `--artifact-root <dir>`: Optional base directory to resolve relative sidecar paths declared in the SBOM.
- `--format <json|txt>`: Output report format (`json` or `txt`, default: `json`).
- `--output <file>`: Path to write the output correlation report (default: standard output).
- `--help`, `-h`: Show usage information.

### Report Output

The generated report contains:
- **Report Metadata**: Processing timestamp, FOSSology tool version, input SBOM file and type (SPDX/CycloneDX), SRAC file path, and calculated SRAC document SHA-256 digest.
- **Summary Counters**: Counts of total assertions, `matched`, `unmatched`, `ambiguous`, `invalid`, and `stale`.
- **Detailed Assertion Records**: For each assertion, records the assertion ID, subject, correlation state, rule applied, matched component, assessment details (`safety_relevant`, `rationale`, `evidence`), review status, and provenance.
