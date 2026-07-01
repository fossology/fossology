/*
 SPDX-FileCopyrightText: (C) 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "spdx_expression_nomos.h"

#include "nomos.h"
#include "spdx_expression_parser.h"

#include <ctype.h>
#include <string.h>

extern struct curScan cur;

static int isExpressionChar(char c)
{
  return isalnum((unsigned char)c) || c == '_' || c == '.' || c == '+' ||
    c == '-' || c == ':' || c == '(' || c == ')' ||
    isspace((unsigned char)c);
}

static int isComplexExpression(const SpdxExpressionResult* result)
{
  if (!result->valid || result->canonical == NULL)
  {
    return 0;
  }

  return strstr(result->canonical, " AND ") != NULL ||
    strstr(result->canonical, " OR ") != NULL ||
    strstr(result->canonical, " WITH ") != NULL;
}

static int containsSpdxExamplePlaceholder(const SpdxExpressionResult* result)
{
  if (result->canonical == NULL)
  {
    return 0;
  }

  return strstr(result->canonical, "foo-short-name") != NULL ||
    strstr(result->canonical, "bar-short-name") != NULL;
}

static char* copyRange(const char* start, int len)
{
  char* value = g_malloc((gsize)len + 1);
  memcpy(value, start, (size_t)len);
  value[len] = '\0';
  return value;
}

static int looksLikeSpdxLicenseDeclaration(const char* marker,
    const char* colon)
{
  int len = (int)(colon - marker);
  char* declaration = copyRange(marker, len);
  int accepted = 0;

  for (int i = 0; declaration[i] != '\0'; i++)
  {
    declaration[i] = (char)tolower((unsigned char)declaration[i]);
  }

  accepted = strncmp(declaration, "spdx-licen", 10) == 0 &&
    (strstr(declaration, "identifier") != NULL ||
     strstr(declaration, "id") != NULL);

  g_free(declaration);
  return accepted;
}

static void maskRange(char* workingFileText, int start, int end)
{
  for (int i = start; i < end; i++)
  {
    workingFileText[i] = ' ';
  }
}

static void addExpressionMatch(const SpdxExpressionResult* result, int start,
    int end)
{
  LicenseExpressionMatch match;
  match.canonical = g_strdup(result->canonical);
  match.astJson = g_strdup(result->ast_json);
  match.start = start;
  match.end = end;
  match.licenseFileId = -1;
  g_array_append_val(cur.expressionMatches, match);
}

static int parseCandidate(char* originalFileText, char* workingFileText,
    int start, int end)
{
  int accepted = 0;
  char* candidate = copyRange(originalFileText + start, end - start);
  SpdxExpressionResult result = spdx_expression_parse(candidate);

  if (isComplexExpression(&result) &&
      !containsSpdxExamplePlaceholder(&result))
  {
    addExpressionMatch(&result, start, end);
    maskRange(workingFileText, start, end);
    accepted = 1;
  }

  spdx_expression_result_free(&result);
  g_free(candidate);
  return accepted;
}

typedef enum
{
  SPDX_EXPR_RELATION_OR,
  SPDX_EXPR_RELATION_AND,
  SPDX_EXPR_RELATION_WITH
} SpdxExpressionRelation;

typedef struct
{
  const char* alias;
  const char* spdxId;
} SpdxAlias;

static const SpdxAlias licenseAliases[] = {
  {"Apache License, Version 2.0", "Apache-2.0"},
  {"Apache License Version 2.0", "Apache-2.0"},
  {"Apache License 2.0", "Apache-2.0"},
  {"Apache-2.0", "Apache-2.0"},
  {"MIT License", "MIT"},
  {"MIT", "MIT"},
  {"BSD 2-Clause License", "BSD-2-Clause"},
  {"BSD 2-Clause", "BSD-2-Clause"},
  {"BSD-2-Clause", "BSD-2-Clause"},
  {"BSD 3-Clause License", "BSD-3-Clause"},
  {"BSD 3-Clause", "BSD-3-Clause"},
  {"BSD-3-Clause", "BSD-3-Clause"},
  {"GPL-2.0-only", "GPL-2.0-only"},
  {"GPL-2.0-or-later", "GPL-2.0-or-later"},
  {"GPL-3.0-only", "GPL-3.0-only"},
  {"GPL-3.0-or-later", "GPL-3.0-or-later"},
  {"MPL-2.0", "MPL-2.0"},
  {"ISC License", "ISC"},
  {"ISC", "ISC"},
  {"CC0-1.0", "CC0-1.0"},
  {NULL, NULL}
};

static const SpdxAlias exceptionAliases[] = {
  {"Classpath-exception-2.0", "Classpath-exception-2.0"},
  {"Classpath exception 2.0", "Classpath-exception-2.0"},
  {"Classpath exception", "Classpath-exception-2.0"},
  {"Autoconf-exception-2.0", "Autoconf-exception-2.0"},
  {"Autoconf exception 2.0", "Autoconf-exception-2.0"},
  {"Autoconf-exception-3.0", "Autoconf-exception-3.0"},
  {"Autoconf exception 3.0", "Autoconf-exception-3.0"},
  {"Bison-exception-2.2", "Bison-exception-2.2"},
  {"Bison exception 2.2", "Bison-exception-2.2"},
  {"GCC-exception-3.1", "GCC-exception-3.1"},
  {"GCC exception 3.1", "GCC-exception-3.1"},
  {"LLVM-exception", "LLVM-exception"},
  {"LLVM exception", "LLVM-exception"},
  {NULL, NULL}
};

static int isAliasBoundary(char c)
{
  return c == '\0' || !isalnum((unsigned char)c);
}

static const char* findCaseInsensitiveInRange(const char* start,
    const char* end, const char* needle)
{
  size_t needleLen = strlen(needle);

  if (needleLen == 0 || start >= end)
  {
    return NULL;
  }

  for (const char* cursor = start; cursor + needleLen <= end; cursor++)
  {
    size_t offset;
    for (offset = 0; offset < needleLen; offset++)
    {
      if (tolower((unsigned char)cursor[offset]) !=
          tolower((unsigned char)needle[offset]))
      {
        break;
      }
    }
    if (offset == needleLen)
    {
      return cursor;
    }
  }

  return NULL;
}

static int aliasHasValidBoundaries(const char* rangeStart, const char* found,
    const char* aliasEnd, const char* rangeEnd)
{
  return (found == rangeStart || isAliasBoundary(*(found - 1))) &&
    (aliasEnd == rangeEnd || isAliasBoundary(*aliasEnd));
}

static const char* findAliasInRange(const char* start, const char* end,
    const SpdxAlias* aliases)
{
  for (int i = 0; aliases[i].alias != NULL; i++)
  {
    const char* found = findCaseInsensitiveInRange(start, end,
        aliases[i].alias);
    if (found != NULL)
    {
      const char* aliasEnd = found + strlen(aliases[i].alias);
      if (aliasEnd <= end &&
          aliasHasValidBoundaries(start, found, aliasEnd, end))
      {
        return aliases[i].spdxId;
      }
    }
  }

  return NULL;
}

static const char* findLicenseInRange(const char* start, const char* end)
{
  return findAliasInRange(start, end, licenseAliases);
}

static const char* findExceptionInRange(const char* start, const char* end)
{
  return findAliasInRange(start, end, exceptionAliases);
}

static int parseGeneratedExpression(const char* expression,
    char* workingFileText, int start, int end)
{
  int accepted = 0;
  SpdxExpressionResult result = spdx_expression_parse(expression);

  if (isComplexExpression(&result) &&
      !containsSpdxExamplePlaceholder(&result))
  {
    addExpressionMatch(&result, start, end);
    maskRange(workingFileText, start, end);
    accepted = 1;
  }

  spdx_expression_result_free(&result);
  return accepted;
}

static int buildAndParseBinaryExpression(const char* firstStart,
    const char* firstEnd, const char* secondStart, const char* secondEnd,
    SpdxExpressionRelation relation, char* workingFileText, int start, int end)
{
  int accepted;
  const char* left = findLicenseInRange(firstStart, firstEnd);
  const char* right = findLicenseInRange(secondStart, secondEnd);
  const char* operatorText;
  char* expression;

  if (left == NULL || right == NULL || strcmp(left, right) == 0)
  {
    return 0;
  }

  operatorText = relation == SPDX_EXPR_RELATION_AND ? "AND" : "OR";
  expression = g_strdup_printf("%s %s %s", left, operatorText, right);
  accepted = parseGeneratedExpression(expression, workingFileText, start, end);
  g_free(expression);
  return accepted;
}

static int buildAndParseWithExpression(const char* licenseStart,
    const char* licenseEnd, const char* exceptionStart,
    const char* exceptionEnd, char* workingFileText, int start, int end)
{
  int accepted;
  const char* license = findLicenseInRange(licenseStart, licenseEnd);
  const char* exception = findExceptionInRange(exceptionStart, exceptionEnd);
  char* expression;

  if (license == NULL || exception == NULL)
  {
    return 0;
  }

  expression = g_strdup_printf("%s WITH %s", license, exception);
  accepted = parseGeneratedExpression(expression, workingFileText, start, end);
  g_free(expression);
  return accepted;
}

static int rangeContainsInsensitive(const char* start, const char* end,
    const char* needle)
{
  return findCaseInsensitiveInRange(start, end, needle) != NULL;
}

static int rangeAlreadyMasked(const char* workingFileText, int start, int end)
{
  for (int i = start; i < end; i++)
  {
    if (!isspace((unsigned char)workingFileText[i]))
    {
      return 0;
    }
  }

  return 1;
}

static int tryBinaryPattern(const char* lineStart, const char* lineEnd,
    char* workingFileText, int lineStartOffset, const char* prefix,
    const char* separator, SpdxExpressionRelation relation,
    int requireAtYourOption)
{
  const char* firstStart = findCaseInsensitiveInRange(lineStart, lineEnd,
      prefix);
  const char* separatorPos;
  const char* secondStart;

  if (firstStart == NULL || firstStart >= lineEnd)
  {
    return 0;
  }

  firstStart += strlen(prefix);
  separatorPos = findCaseInsensitiveInRange(firstStart, lineEnd, separator);
  if (separatorPos == NULL || separatorPos >= lineEnd)
  {
    return 0;
  }

  if (requireAtYourOption &&
      !rangeContainsInsensitive(separatorPos, lineEnd, "at your option"))
  {
    return 0;
  }

  secondStart = separatorPos + strlen(separator);
  return buildAndParseBinaryExpression(firstStart, separatorPos, secondStart,
      lineEnd, relation, workingFileText, lineStartOffset,
      lineStartOffset + (int)(lineEnd - lineStart));
}

static int tryWithPattern(const char* lineStart, const char* lineEnd,
    char* workingFileText, int lineStartOffset, const char* prefix,
    const char* separator)
{
  const char* licenseStart = findCaseInsensitiveInRange(lineStart, lineEnd,
      prefix);
  const char* separatorPos;
  const char* exceptionStart;

  if (licenseStart == NULL || licenseStart >= lineEnd)
  {
    return 0;
  }

  licenseStart += strlen(prefix);
  separatorPos = findCaseInsensitiveInRange(licenseStart, lineEnd, separator);
  if (separatorPos == NULL || separatorPos >= lineEnd)
  {
    return 0;
  }

  exceptionStart = separatorPos + strlen(separator);
  return buildAndParseWithExpression(licenseStart, separatorPos,
      exceptionStart, lineEnd, workingFileText, lineStartOffset,
      lineStartOffset + (int)(lineEnd - lineStart));
}

static int parseHeuristicLine(const char* lineStart, const char* lineEnd,
    char* workingFileText, int lineStartOffset)
{
  int lineEndOffset = lineStartOffset + (int)(lineEnd - lineStart);

  if (rangeAlreadyMasked(workingFileText, lineStartOffset, lineEndOffset))
  {
    return 0;
  }

  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "licensed under either ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "licensed under ", " or ", SPDX_EXPR_RELATION_OR, 1))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "available under either ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "available under your choice of ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "available under the terms of either ", " or ",
        SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "distributed under either ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "distributed under ", " or ", SPDX_EXPR_RELATION_OR, 1))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "released under either ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "released under ", " or ", SPDX_EXPR_RELATION_OR, 1))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "dual licensed under either ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "dual-licensed under either ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "dual licensed under ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "dual-licensed under ", " or ", SPDX_EXPR_RELATION_OR, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "licensed under both ", " and ", SPDX_EXPR_RELATION_AND, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "distributed under both ", " and ", SPDX_EXPR_RELATION_AND, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "released under both ", " and ", SPDX_EXPR_RELATION_AND, 0))
  {
    return 1;
  }
  if (tryBinaryPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "subject to the terms of ", " and ", SPDX_EXPR_RELATION_AND, 0))
  {
    return 1;
  }
  if (tryWithPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "licensed under ", " with "))
  {
    return 1;
  }
  if (tryWithPattern(lineStart, lineEnd, workingFileText, lineStartOffset,
        "licensed under ", " plus the "))
  {
    return 1;
  }

  return 0;
}

static int extractHeuristicExpressionFindings(char* originalFileText,
    char* workingFileText, int size)
{
  char* cursor = originalFileText;
  char* endOfBuffer = originalFileText + size;
  int accepted = 0;

  while (cursor < endOfBuffer)
  {
    char* lineEnd = memchr(cursor, '\n', (size_t)(endOfBuffer - cursor));
    if (lineEnd == NULL)
    {
      lineEnd = endOfBuffer;
    }

    accepted += parseHeuristicLine(cursor, lineEnd, workingFileText,
        (int)(cursor - originalFileText));
    cursor = lineEnd + (lineEnd < endOfBuffer ? 1 : 0);
  }

  return accepted;
}

int extractSpdxExpressionFindings(char* originalFileText, char* workingFileText,
    int size)
{
  char* cursor = originalFileText;
  char* endOfBuffer = originalFileText + size;
  int accepted = 0;

  while (cursor < endOfBuffer)
  {
    char* lineEnd = memchr(cursor, '\n', (size_t)(endOfBuffer - cursor));
    char* marker;
    char* colon;
    char* valueStart;
    char* valueEnd;
    int startOffset;
    int endOffset;

    if (lineEnd == NULL)
    {
      lineEnd = endOfBuffer;
    }

    marker = findCaseInsensitiveInRange(cursor, lineEnd, "spdx-licen");
    if (marker == NULL || marker >= lineEnd)
    {
      cursor = lineEnd + (lineEnd < endOfBuffer ? 1 : 0);
      continue;
    }

    colon = memchr(marker, ':', (size_t)(lineEnd - marker));
    if (colon == NULL || !looksLikeSpdxLicenseDeclaration(marker, colon))
    {
      cursor = lineEnd + (lineEnd < endOfBuffer ? 1 : 0);
      continue;
    }

    valueStart = colon + 1;
    while (valueStart < lineEnd && isspace((unsigned char)*valueStart))
    {
      valueStart++;
    }

    valueEnd = valueStart;
    while (valueEnd < lineEnd && isExpressionChar(*valueEnd))
    {
      valueEnd++;
    }
    while (valueEnd > valueStart &&
        isspace((unsigned char)*(valueEnd - 1)))
    {
      valueEnd--;
    }

    if (valueEnd > valueStart)
    {
      startOffset = (int)(valueStart - originalFileText);
      endOffset = (int)(valueEnd - originalFileText);
      accepted += parseCandidate(originalFileText, workingFileText,
          startOffset, endOffset);
    }

    cursor = lineEnd + (lineEnd < endOfBuffer ? 1 : 0);
  }

  accepted += extractHeuristicExpressionFindings(originalFileText,
      workingFileText, size);

  return accepted;
}
