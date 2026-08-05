/*
 SPDX-FileCopyrightText: (C) 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef FOSSOLOGY_SPDX_EXPRESSION_PARSER_H
#define FOSSOLOGY_SPDX_EXPRESSION_PARSER_H

#ifdef __cplusplus
extern "C" {
#endif

typedef struct SpdxExpressionResult
{
  /**
   * True when the input is syntactically valid SPDX expression text.
   */
  int valid;
  /**
   * Canonical expression text with normalized operator casing and spacing.
   */
  char* canonical;
  /**
   * Parser contract output used by other runtime implementations.
   */
  char* ast_json;
  /**
   * Stable machine-readable error code when valid is false.
   */
  char* error_code;
} SpdxExpressionResult;

/**
 * Parse SPDX expression text into canonical text and a JSON AST contract.
 *
 * The parser validates SPDX expression syntax. Semantic checks against the
 * license list and exception list are intentionally kept outside this parser.
 * Keep this API aligned with src/lib/spdx-expression/contract.
 */
SpdxExpressionResult spdx_expression_parse(const char* input);
void spdx_expression_result_free(SpdxExpressionResult* result);

#ifdef __cplusplus
}
#endif

#endif /* FOSSOLOGY_SPDX_EXPRESSION_PARSER_H */
