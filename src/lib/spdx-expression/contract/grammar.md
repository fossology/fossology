# SPDX Expression Grammar Contract

<!--
SPDX-FileCopyrightText: 2026 FOSSology contributors
SPDX-License-Identifier: GPL-2.0-only
-->

This grammar defines the syntax accepted by FOSSology SPDX expression parsers.
It is intentionally shared by all runtime implementations.

```text
expression       = or_expression
or_expression    = and_expression ("OR" and_expression)*
and_expression   = with_expression ("AND" with_expression)*
with_expression  = primary ("WITH" exception_id)?
primary          = license_id | license_ref | document_license_ref | special | "(" expression ")"
special          = "NONE" | "NOASSERTION"
```

Operator precedence, from strongest to weakest:

```text
WITH
AND
OR
```

Rules:

- Operators are case-insensitive in input and canonicalized to uppercase.
- `WITH` binds only to a simple license identifier or license reference.
- `NONE` and `NOASSERTION` must stand alone.
- Parentheses are preserved in canonical output only when needed to preserve
  meaning.
- This parser validates expression syntax. Semantic validation against the SPDX
  license list and exception list belongs to a resolver layer outside this
  parser.
