# SPDX Expression AST Contract

<!--
SPDX-FileCopyrightText: 2026 FOSSology contributors
SPDX-License-Identifier: GPL-2.0-only
-->

Every parser implementation must produce the same JSON AST shape for the same
input expression.

Leaf nodes:

```json
{"type":"license","id":"MIT"}
{"type":"licenseRef","id":"LicenseRef-Custom"}
{"type":"exception","id":"Classpath-exception-2.0"}
{"type":"special","id":"NONE"}
```

Compound nodes:

```json
{"type":"WITH","license":{...},"exception":{...}}
{"type":"AND","left":{...},"right":{...}}
{"type":"OR","left":{...},"right":{...}}
```

Contract fields returned by an implementation:

```text
valid       boolean/integer success marker
canonical   canonical expression text, when valid
ast_json    JSON AST string, when valid
error_code  stable machine-readable error code, when invalid
```

Stable error codes:

```text
empty_expression
invalid_token
expected_license
expected_exception
missing_closing_parenthesis
unexpected_token
with_requires_simple_license
special_license_must_stand_alone
out_of_memory
```
