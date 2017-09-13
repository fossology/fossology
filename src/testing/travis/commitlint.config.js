/*
Conventional Changelog Enforcer Script
Copyright Siemens AG 2017

Copying and distribution of this file, with or without modification,
are permitted in any medium without royalty provided the copyright
notice and this notice are preserved.  This file is offered as-is,
without any warranty.

Description: Commitlint Config
Defines rules for the conventional changelog based on this info:
https://github.com/fossology/fossology/blob/master/CONTRIBUTING.md#user-content-git-commit-conventions

Rules are made up by a name and a configuration array. The configuration array contains:

Level [0..2]: 0 disables the rule. For 1 it will be considered a warning for 2 an error.
Applicable always|never: never inverts the rule.
Value: value to use for this rule.

from https://github.com/marionebl/commitlint/blob/master/docs/reference-rules.md
*/

module.exports = {
  extends: ['@commitlint/config-angular'], // => use angular standard as default
  rules: {
    'header-max-length': [1, "always", 72], // 72 chars commit limit -> just warning
    'scope-case': [0, "always", 0] //scope should not be lowercase -> disabled
  }
};

