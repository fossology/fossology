/*
 Author: Maximilian Huber
 SPDX-FileCopyrightText: © 2018 TNG Technology Consulting GmbH

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "serialize.h"

#include "monk.h"
#include "license.h"
#include "string_operations.h"
#include <stdio.h>
#include <errno.h>

/* tokenType is NOT serialized; re-derived on load for backward compat
 * with old knowledgebases. */
typedef struct {
  unsigned int length;
  unsigned int removedBefore;
  uint32_t     hashedContent;
} TokenOnDisk;

/*
 * serialization
 */

int serializeToFile(Licenses* licenses, char* filename) {
  FILE* fp = fopen(filename, "w+");
  if (fp == NULL) {
    return 0;
  }
  int retCode = serialize(licenses, fp);
  fclose(fp);
  return retCode;
}

int serialize(Licenses* licenses, FILE* fp) {
  return serializeGArray(licenses->licenses, fp);
}

int serializeGArray(GArray* licenses, FILE* fp) {
  int retCode;
  for (guint i = 0; i < licenses->len; i++) {
    retCode = serializeOne(license_index(licenses, i), fp);
    if (retCode == 0) {
      return retCode;
    }
  }
  return 1;
}

int serializeOne(License* license, FILE* fp) {
  return serializeOneMeta(license, fp) &&
    serializeOneShortname(license, fp) &&
    serializeOneTokens(license->tokens, fp);
}

int serializeOneMeta(License* license, FILE* fp) {
  SerializingMeta meta = { .refId = license->refId,
                           .shortnameLen = strlen(license->shortname),
                           .tokensLen = license->tokens->len };

  return fwrite(&meta, sizeof(SerializingMeta), 1, fp) == 1;
}

int serializeOneShortname(License* license, FILE* fp) {
  return fprintf(fp, "%s", license->shortname) > 0;
}

int serializeOneTokens(GArray* tokens, FILE* fp) {
  for (guint i = 0; i < tokens->len; i++) {
    Token* t = tokens_index(tokens, i);
    TokenOnDisk td = { t->length, t->removedBefore, t->hashedContent };
    if (fwrite(&td, sizeof(TokenOnDisk), 1, fp) != 1) {
      return 0;
    }
  }
  return 1;
}

/*
 * deserialization
 */

Licenses* deserializeFromFile(char* filename, unsigned minAdjacentMatches, unsigned maxLeadingDiff) {
  FILE* fp = fopen(filename, "r");
  if(fp == NULL) {
    exit(3);
  }
  Licenses* result = deserialize(fp, minAdjacentMatches, maxLeadingDiff);
  fclose(fp);
  return result;
}

Licenses* deserialize(FILE* fp, unsigned minAdjacentMatches, unsigned maxLeadingDiff) {
  GArray* licenses = g_array_new(TRUE, FALSE, sizeof(License));

  SerializingMeta meta;
  while (fread(&meta, sizeof(SerializingMeta), 1, fp) == 1) {
    License license = { .refId = meta.refId };

    license.shortname = calloc(1,(size_t) meta.shortnameLen + 1);
    if(fread(license.shortname, sizeof(char), meta.shortnameLen, fp) != meta.shortnameLen){
      strerror(errno);
    }

    license.tokens = deserializeTokens(fp, meta.tokensLen);

    g_array_append_vals(licenses, &license, 1);
  }

  return buildLicenseIndexes(licenses, minAdjacentMatches, maxLeadingDiff);
}

GArray* deserializeTokens(FILE* fp, guint tokensLen) {
  TokenOnDisk* freadResult = malloc(sizeof(TokenOnDisk) * tokensLen);
  if (fread(freadResult, sizeof(TokenOnDisk), tokensLen, fp) != tokensLen) {
    strerror(errno);
  }

  GArray* tokens = g_array_new(FALSE, FALSE, sizeof(Token));
  for (guint i = 0; i < tokensLen; i++) {
    Token t;
    t.length        = freadResult[i].length;
    t.removedBefore = freadResult[i].removedBefore;
    t.hashedContent = freadResult[i].hashedContent;
    /* YEAR_CANONICAL_HASH maps to TOKEN_YEAR; activates year-normalization
     * for imported knowledgebases */
    t.tokenType = (t.hashedContent == YEAR_CANONICAL_HASH) ? TOKEN_YEAR : TOKEN_NORMAL;
    g_array_append_vals(tokens, &t, 1);
  }
  free(freadResult);

  return tokens;
}
