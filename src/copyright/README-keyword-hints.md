<!--
SPDX-FileCopyrightText: © 2026 DenishShiroya22 <denishshiroya22@gmail.com>

SPDX-License-Identifier: GPL-2.0-only
-->

# Keyword analysis guidance

FOSSology can show optional auditor guidance beside each detected keyword in
**Browse → Keyword**. This guidance is separate from the detection patterns,
so it can be edited without rerunning scans. The keyword scanner and existing
database entries are unchanged.

The configuration is installed at:

```text
<sysconfdir>/mods-enabled/keyword/agent/keyword-hints.json
```

The sample file is `src/copyright/agent/keyword-hints.json`. Administrators can
edit their installed copy to add or change groups:

```json
{
  "groups": [
    {
      "keywords": ["patent", "patented"],
      "text": "Review any patent-related restrictions."
    },
    {
      "keywords": ["licensed under"],
      "text": "Verify the referenced license and its conditions."
    }
  ]
}
```

Each `keywords` entry lists the **detected text**, not the regular expression
in the scanner's `keyword.conf`. Multiple keywords can share one text message.
Matching is case-insensitive and treats consecutive whitespace as one space,
including surrounding Unicode whitespace, so `licensed    under` also matches
`licensed under`. If the same keyword appears in multiple groups, the first
group takes precedence. An empty text in the first group deliberately suppresses
guidance from later groups.

Matching uses the whole normalized finding, not a substring or a regular
expression. For example, add `licensedunder` explicitly if your scanner patterns
allow it without spaces. The table search continues to search keyword findings;
it does not search guidance text.

Unconfigured keywords appear with an empty Suggested Action cell. The same
column is present for activated and deactivated findings. If the optional
configuration file is missing, the existing keyword browser continues to work.
Invalid JSON is logged to the PHP error log and produces empty hints. Invalid
group entries are ignored. The optional file must be readable by the web server.

Messages are plain UTF-8 text: HTML and JavaScript are displayed literally.
The messages are recommendations for auditors, not legal conclusions. Changing
this file affects the displayed hints for existing scans as well as new scans.
The file is read once per keyword table request, so no scanner restart is
required to apply changes.

Keep a backup of site-specific guidance before reinstalling or upgrading the
keyword component, just as for custom `keyword.conf` detection patterns.

## Verification

After installing updated UI files, refresh the site's Twig template cache
(configured by `FO_TWIG_CACHE`). An old compiled `histTable.js.twig` can otherwise
retain the previous column layout while the AJAX response uses the new layout.

From a configured CMake build with test dependencies, run:

```sh
php build/vendor/bin/phpunit --configuration src/phpunit.xml --filter Keyword
ctest --test-dir build -R copyright_ --output-on-failure
```

In Browse → Keyword, verify that both activated and deactivated tables have
Suggested Action immediately after Keyword Analysis. Check that unmatched
keywords have an empty hint, and that guidance cannot be edited by double-click.
Verify sorting and searching, checkbox selection, deactivate/undo, and keyword
editing. Editing a finding updates its guidance when the table reloads.
The Copyright, Email/URL/Author, ECC, and IPRA tables retain their column layout.
