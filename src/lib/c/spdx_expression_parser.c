/*
 SPDX-FileCopyrightText: (C) 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "spdx_expression_parser.h"

#include <ctype.h>
#include <stdarg.h>
#include <stddef.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

typedef enum TokenType
{
  TOKEN_END,
  TOKEN_IDENTIFIER,
  TOKEN_AND,
  TOKEN_OR,
  TOKEN_WITH,
  TOKEN_LPAREN,
  TOKEN_RPAREN,
  TOKEN_INVALID
} TokenType;

typedef struct Token
{
  TokenType type;
  char* text;
} Token;

typedef enum NodeType
{
  NODE_LICENSE,
  NODE_LICENSEREF,
  NODE_EXCEPTION,
  NODE_SPECIAL,
  NODE_AND,
  NODE_OR,
  NODE_WITH
} NodeType;

typedef struct SpdxNode
{
  NodeType type;
  char* value;
  struct SpdxNode* left;
  struct SpdxNode* right;
} SpdxNode;

typedef struct Parser
{
  const char* input;
  size_t pos;
  Token current;
  const char* error_code;
} Parser;

typedef struct StringBuffer
{
  char* data;
  size_t length;
  size_t capacity;
} StringBuffer;

static char* duplicate_string(const char* value)
{
  size_t length;
  char* copy;

  if (value == NULL)
  {
    return NULL;
  }

  length = strlen(value);
  copy = (char*)malloc(length + 1);
  if (copy == NULL)
  {
    return NULL;
  }

  memcpy(copy, value, length + 1);
  return copy;
}

static char* duplicate_range(const char* start, size_t length)
{
  char* copy = (char*)malloc(length + 1);
  if (copy == NULL)
  {
    return NULL;
  }

  memcpy(copy, start, length);
  copy[length] = '\0';
  return copy;
}

static int text_equals_ignore_case(const char* left, const char* right)
{
  while (*left != '\0' && *right != '\0')
  {
    if (toupper((unsigned char)*left) != toupper((unsigned char)*right))
    {
      return 0;
    }
    ++left;
    ++right;
  }

  return *left == '\0' && *right == '\0';
}

static int identifier_has_valid_chars(const char* value)
{
  const char* cursor = value;

  if (value == NULL || *value == '\0')
  {
    return 0;
  }

  while (*cursor != '\0')
  {
    unsigned char ch = (unsigned char)*cursor;
    if (!isalnum(ch) && ch != '-' && ch != '.' && ch != '+' && ch != ':')
    {
      return 0;
    }
    ++cursor;
  }

  return 1;
}

static int is_license_ref(const char* value)
{
  return strncmp(value, "LicenseRef-", 11) == 0 ||
    strstr(value, ":LicenseRef-") != NULL;
}

static int is_special_license_value(const char* value)
{
  return text_equals_ignore_case(value, "NONE") ||
    text_equals_ignore_case(value, "NOASSERTION");
}

typedef struct CanonicalIdentifier
{
  const char* input;
  const char* canonical;
} CanonicalIdentifier;

static const CanonicalIdentifier canonical_license_ids[] = {
  {"Apache-2.0", "Apache-2.0"},
  {"BSD-2-Clause", "BSD-2-Clause"},
  {"BSD-3-Clause", "BSD-3-Clause"},
  {"CC0-1.0", "CC0-1.0"},
  {"GPL-2.0", "GPL-2.0"},
  {"GPL-2.0-only", "GPL-2.0-only"},
  {"GPL-2.0-or-later", "GPL-2.0-or-later"},
  {"GPL-3.0-only", "GPL-3.0-only"},
  {"GPL-3.0-or-later", "GPL-3.0-or-later"},
  {"ISC", "ISC"},
  {"LGPL-2.1+", "LGPL-2.1+"},
  {"LGPL-2.1-or-later", "LGPL-2.1-or-later"},
  {"MIT", "MIT"},
  {"MPL-1.1+", "MPL-1.1+"},
  {"MPL-2.0", "MPL-2.0"},
  {"MPL-2.0-no-copyleft-exception", "MPL-2.0-no-copyleft-exception"},
  {"X11", "X11"},
  {NULL, NULL}
};

static const CanonicalIdentifier canonical_exception_ids[] = {
  {"Autoconf-exception-2.0", "Autoconf-exception-2.0"},
  {"Autoconf-exception-3.0", "Autoconf-exception-3.0"},
  {"Bison-exception-2.2", "Bison-exception-2.2"},
  {"Classpath-exception-2.0", "Classpath-exception-2.0"},
  {"GCC-exception-3.1", "GCC-exception-3.1"},
  {"LLVM-exception", "LLVM-exception"},
  {NULL, NULL}
};

static const char* canonical_identifier_value(const char* value,
  const CanonicalIdentifier* identifiers)
{
  for (int i = 0; identifiers[i].input != NULL; i++)
  {
    if (text_equals_ignore_case(value, identifiers[i].input))
    {
      return identifiers[i].canonical;
    }
  }

  return value;
}

static const char* canonical_special_value(const char* value)
{
  if (text_equals_ignore_case(value, "NONE"))
  {
    return "NONE";
  }

  return "NOASSERTION";
}

static void free_token(Token* token)
{
  if (token->text != NULL)
  {
    free(token->text);
  }
  token->text = NULL;
  token->type = TOKEN_END;
}

static void parser_set_error(Parser* parser, const char* error_code)
{
  if (parser->error_code == NULL)
  {
    parser->error_code = error_code;
  }
}

static void parser_next(Parser* parser)
{
  const char* start;
  size_t length;

  free_token(&parser->current);

  while (isspace((unsigned char)parser->input[parser->pos]))
  {
    ++parser->pos;
  }

  if (parser->input[parser->pos] == '\0')
  {
    parser->current.type = TOKEN_END;
    return;
  }

  if (parser->input[parser->pos] == '(')
  {
    parser->current.type = TOKEN_LPAREN;
    parser->current.text = duplicate_string("(");
    ++parser->pos;
    return;
  }

  if (parser->input[parser->pos] == ')')
  {
    parser->current.type = TOKEN_RPAREN;
    parser->current.text = duplicate_string(")");
    ++parser->pos;
    return;
  }

  start = parser->input + parser->pos;
  while (parser->input[parser->pos] != '\0' &&
    !isspace((unsigned char)parser->input[parser->pos]) &&
    parser->input[parser->pos] != '(' &&
    parser->input[parser->pos] != ')')
  {
    ++parser->pos;
  }
  length = (size_t)(parser->input + parser->pos - start);

  parser->current.text = duplicate_range(start, length);
  if (parser->current.text == NULL)
  {
    parser->current.type = TOKEN_INVALID;
    parser_set_error(parser, "out_of_memory");
    return;
  }

  if (text_equals_ignore_case(parser->current.text, "AND"))
  {
    parser->current.type = TOKEN_AND;
  }
  else if (text_equals_ignore_case(parser->current.text, "OR"))
  {
    parser->current.type = TOKEN_OR;
  }
  else if (text_equals_ignore_case(parser->current.text, "WITH"))
  {
    parser->current.type = TOKEN_WITH;
  }
  else if (identifier_has_valid_chars(parser->current.text))
  {
    parser->current.type = TOKEN_IDENTIFIER;
  }
  else
  {
    parser->current.type = TOKEN_INVALID;
    parser_set_error(parser, "invalid_token");
  }
}

static SpdxNode* node_new(NodeType type, const char* value, SpdxNode* left,
  SpdxNode* right)
{
  SpdxNode* node = (SpdxNode*)calloc(1, sizeof(SpdxNode));
  if (node == NULL)
  {
    return NULL;
  }

  node->type = type;
  node->left = left;
  node->right = right;
  node->value = duplicate_string(value);
  if (value != NULL && node->value == NULL)
  {
    free(node);
    return NULL;
  }

  return node;
}

static void node_free(SpdxNode* node)
{
  if (node == NULL)
  {
    return;
  }

  node_free(node->left);
  node_free(node->right);
  free(node->value);
  free(node);
}

static SpdxNode* parse_expression(Parser* parser);

static SpdxNode* parse_primary(Parser* parser)
{
  SpdxNode* node;
  char* value;

  if (parser->current.type == TOKEN_LPAREN)
  {
    parser_next(parser);
    node = parse_expression(parser);
    if (node == NULL)
    {
      return NULL;
    }
    if (parser->current.type != TOKEN_RPAREN)
    {
      parser_set_error(parser, "missing_closing_parenthesis");
      node_free(node);
      return NULL;
    }
    parser_next(parser);
    return node;
  }

  if (parser->current.type != TOKEN_IDENTIFIER)
  {
    parser_set_error(parser, "expected_license");
    return NULL;
  }

  value = duplicate_string(parser->current.text);
  if (value == NULL)
  {
    parser_set_error(parser, "out_of_memory");
    return NULL;
  }

  parser_next(parser);
  if (is_special_license_value(value))
  {
    node = node_new(NODE_SPECIAL, canonical_special_value(value), NULL, NULL);
  }
  else if (is_license_ref(value))
  {
    node = node_new(NODE_LICENSEREF, value, NULL, NULL);
  }
  else
  {
    node = node_new(NODE_LICENSE,
      canonical_identifier_value(value, canonical_license_ids), NULL, NULL);
  }
  free(value);

  if (node == NULL)
  {
    parser_set_error(parser, "out_of_memory");
  }

  return node;
}

static int node_can_have_exception(const SpdxNode* node)
{
  return node != NULL &&
    (node->type == NODE_LICENSE || node->type == NODE_LICENSEREF);
}

static SpdxNode* parse_with_expression(Parser* parser)
{
  SpdxNode* left = parse_primary(parser);
  SpdxNode* exception;
  SpdxNode* with_node;
  char* exception_value;

  if (left == NULL)
  {
    return NULL;
  }

  if (parser->current.type != TOKEN_WITH)
  {
    return left;
  }

  if (!node_can_have_exception(left))
  {
    parser_set_error(parser, "with_requires_simple_license");
    node_free(left);
    return NULL;
  }

  parser_next(parser);
  if (parser->current.type != TOKEN_IDENTIFIER)
  {
    parser_set_error(parser, "expected_exception");
    node_free(left);
    return NULL;
  }

  exception_value = duplicate_string(parser->current.text);
  if (exception_value == NULL)
  {
    parser_set_error(parser, "out_of_memory");
    node_free(left);
    return NULL;
  }
  parser_next(parser);

  exception = node_new(NODE_EXCEPTION,
    canonical_identifier_value(exception_value, canonical_exception_ids),
    NULL, NULL);
  free(exception_value);
  if (exception == NULL)
  {
    parser_set_error(parser, "out_of_memory");
    node_free(left);
    return NULL;
  }

  with_node = node_new(NODE_WITH, NULL, left, exception);
  if (with_node == NULL)
  {
    parser_set_error(parser, "out_of_memory");
    node_free(left);
    node_free(exception);
  }

  return with_node;
}

static SpdxNode* parse_and_expression(Parser* parser)
{
  SpdxNode* left = parse_with_expression(parser);

  while (left != NULL && parser->current.type == TOKEN_AND)
  {
    SpdxNode* right;
    SpdxNode* parent;

    parser_next(parser);
    right = parse_with_expression(parser);
    if (right == NULL)
    {
      node_free(left);
      return NULL;
    }

    parent = node_new(NODE_AND, NULL, left, right);
    if (parent == NULL)
    {
      parser_set_error(parser, "out_of_memory");
      node_free(left);
      node_free(right);
      return NULL;
    }
    left = parent;
  }

  return left;
}

static SpdxNode* parse_expression(Parser* parser)
{
  SpdxNode* left = parse_and_expression(parser);

  while (left != NULL && parser->current.type == TOKEN_OR)
  {
    SpdxNode* right;
    SpdxNode* parent;

    parser_next(parser);
    right = parse_and_expression(parser);
    if (right == NULL)
    {
      node_free(left);
      return NULL;
    }

    parent = node_new(NODE_OR, NULL, left, right);
    if (parent == NULL)
    {
      parser_set_error(parser, "out_of_memory");
      node_free(left);
      node_free(right);
      return NULL;
    }
    left = parent;
  }

  return left;
}

static int node_contains_special(const SpdxNode* node)
{
  if (node == NULL)
  {
    return 0;
  }

  if (node->type == NODE_SPECIAL)
  {
    return 1;
  }

  return node_contains_special(node->left) || node_contains_special(node->right);
}

static int buffer_init(StringBuffer* buffer)
{
  buffer->capacity = 128;
  buffer->length = 0;
  buffer->data = (char*)malloc(buffer->capacity);
  if (buffer->data == NULL)
  {
    return 0;
  }
  buffer->data[0] = '\0';
  return 1;
}

static int buffer_reserve(StringBuffer* buffer, size_t extra)
{
  size_t required = buffer->length + extra + 1;
  char* new_data;

  if (required <= buffer->capacity)
  {
    return 1;
  }

  while (buffer->capacity < required)
  {
    buffer->capacity *= 2;
  }

  new_data = (char*)realloc(buffer->data, buffer->capacity);
  if (new_data == NULL)
  {
    return 0;
  }

  buffer->data = new_data;
  return 1;
}

static int buffer_append(StringBuffer* buffer, const char* text)
{
  size_t length = strlen(text);
  if (!buffer_reserve(buffer, length))
  {
    return 0;
  }

  memcpy(buffer->data + buffer->length, text, length + 1);
  buffer->length += length;
  return 1;
}

static int buffer_append_format(StringBuffer* buffer, const char* format, ...)
{
  va_list args;
  va_list args_copy;
  int needed;
  int written;

  va_start(args, format);
  va_copy(args_copy, args);
  needed = vsnprintf(NULL, 0, format, args);
  va_end(args);
  if (needed < 0 || !buffer_reserve(buffer, (size_t)needed))
  {
    va_end(args_copy);
    return 0;
  }

  written = vsnprintf(buffer->data + buffer->length,
    buffer->capacity - buffer->length, format, args_copy);
  va_end(args_copy);
  if (written < 0)
  {
    return 0;
  }

  buffer->length += (size_t)written;
  return 1;
}

static int precedence_for_node(const SpdxNode* node)
{
  if (node == NULL)
  {
    return 0;
  }

  switch (node->type)
  {
    case NODE_OR:
      return 1;
    case NODE_AND:
      return 2;
    case NODE_WITH:
      return 3;
    default:
      return 4;
  }
}

static int append_canonical(StringBuffer* buffer, const SpdxNode* node,
  int parent_precedence)
{
  int precedence = precedence_for_node(node);
  int wrap = precedence < parent_precedence;

  if (wrap && !buffer_append(buffer, "("))
  {
    return 0;
  }

  switch (node->type)
  {
    case NODE_LICENSE:
    case NODE_LICENSEREF:
    case NODE_EXCEPTION:
    case NODE_SPECIAL:
      if (!buffer_append(buffer, node->value))
      {
        return 0;
      }
      break;
    case NODE_WITH:
      if (!append_canonical(buffer, node->left, precedence) ||
        !buffer_append(buffer, " WITH ") ||
        !append_canonical(buffer, node->right, precedence))
      {
        return 0;
      }
      break;
    case NODE_AND:
      if (!append_canonical(buffer, node->left, precedence) ||
        !buffer_append(buffer, " AND ") ||
        !append_canonical(buffer, node->right, precedence))
      {
        return 0;
      }
      break;
    case NODE_OR:
      if (!append_canonical(buffer, node->left, precedence) ||
        !buffer_append(buffer, " OR ") ||
        !append_canonical(buffer, node->right, precedence))
      {
        return 0;
      }
      break;
  }

  if (wrap && !buffer_append(buffer, ")"))
  {
    return 0;
  }

  return 1;
}

static int append_json_escaped(StringBuffer* buffer, const char* value)
{
  const char* cursor = value;

  while (*cursor != '\0')
  {
    if (*cursor == '"' || *cursor == '\\')
    {
      char escaped[3];
      escaped[0] = '\\';
      escaped[1] = *cursor;
      escaped[2] = '\0';
      if (!buffer_append(buffer, escaped))
      {
        return 0;
      }
    }
    else
    {
      char single[2];
      single[0] = *cursor;
      single[1] = '\0';
      if (!buffer_append(buffer, single))
      {
        return 0;
      }
    }
    ++cursor;
  }

  return 1;
}

static const char* json_type_for_node(const SpdxNode* node)
{
  switch (node->type)
  {
    case NODE_LICENSE:
      return "license";
    case NODE_LICENSEREF:
      return "licenseRef";
    case NODE_EXCEPTION:
      return "exception";
    case NODE_SPECIAL:
      return "special";
    case NODE_AND:
      return "AND";
    case NODE_OR:
      return "OR";
    case NODE_WITH:
      return "WITH";
  }

  return "unknown";
}

static int append_ast_json(StringBuffer* buffer, const SpdxNode* node)
{
  if (!buffer_append_format(buffer, "{\"type\":\"%s\"", json_type_for_node(node)))
  {
    return 0;
  }

  switch (node->type)
  {
    case NODE_LICENSE:
    case NODE_LICENSEREF:
    case NODE_EXCEPTION:
    case NODE_SPECIAL:
      if (!buffer_append(buffer, ",\"id\":\"") ||
        !append_json_escaped(buffer, node->value) ||
        !buffer_append(buffer, "\"}"))
      {
        return 0;
      }
      break;
    case NODE_WITH:
      if (!buffer_append(buffer, ",\"license\":") ||
        !append_ast_json(buffer, node->left) ||
        !buffer_append(buffer, ",\"exception\":") ||
        !append_ast_json(buffer, node->right) ||
        !buffer_append(buffer, "}"))
      {
        return 0;
      }
      break;
    case NODE_AND:
    case NODE_OR:
      if (!buffer_append(buffer, ",\"left\":") ||
        !append_ast_json(buffer, node->left) ||
        !buffer_append(buffer, ",\"right\":") ||
        !append_ast_json(buffer, node->right) ||
        !buffer_append(buffer, "}"))
      {
        return 0;
      }
      break;
  }

  return 1;
}

static int input_is_blank(const char* input)
{
  while (*input != '\0')
  {
    if (!isspace((unsigned char)*input))
    {
      return 0;
    }
    ++input;
  }

  return 1;
}

static SpdxExpressionResult result_error(const char* error_code)
{
  SpdxExpressionResult result;

  result.valid = 0;
  result.canonical = NULL;
  result.ast_json = NULL;
  result.error_code = duplicate_string(error_code);

  return result;
}

SpdxExpressionResult spdx_expression_parse(const char* input)
{
  Parser parser;
  SpdxNode* root;
  StringBuffer canonical;
  StringBuffer ast_json;
  SpdxExpressionResult result;

  canonical.data = NULL;
  ast_json.data = NULL;

  if (input == NULL || input_is_blank(input))
  {
    return result_error("empty_expression");
  }

  memset(&parser, 0, sizeof(parser));
  parser.input = input;
  parser_next(&parser);

  root = parse_expression(&parser);
  if (root == NULL)
  {
    const char* error_code = parser.error_code != NULL ?
      parser.error_code : "parse_error";
    free_token(&parser.current);
    return result_error(error_code);
  }

  if (parser.current.type != TOKEN_END)
  {
    const char* error_code = parser.error_code != NULL ?
      parser.error_code : "unexpected_token";
    node_free(root);
    free_token(&parser.current);
    return result_error(error_code);
  }

  if (root->type != NODE_SPECIAL && node_contains_special(root))
  {
    node_free(root);
    free_token(&parser.current);
    return result_error("special_license_must_stand_alone");
  }

  if (!buffer_init(&canonical) || !buffer_init(&ast_json))
  {
    free(canonical.data);
    node_free(root);
    free_token(&parser.current);
    return result_error("out_of_memory");
  }

  if (!append_canonical(&canonical, root, 0) ||
    !append_ast_json(&ast_json, root))
  {
    free(canonical.data);
    free(ast_json.data);
    node_free(root);
    free_token(&parser.current);
    return result_error("out_of_memory");
  }

  result.valid = 1;
  result.canonical = canonical.data;
  result.ast_json = ast_json.data;
  result.error_code = NULL;

  node_free(root);
  free_token(&parser.current);

  return result;
}

void spdx_expression_result_free(SpdxExpressionResult* result)
{
  if (result == NULL)
  {
    return;
  }

  free(result->canonical);
  free(result->ast_json);
  free(result->error_code);

  result->valid = 0;
  result->canonical = NULL;
  result->ast_json = NULL;
  result->error_code = NULL;
}
