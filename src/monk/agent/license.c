/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <glib.h>

#include "license.h"
#include "string_operations.h"
#include "monk.h"

static char* ignoredLicenseNames[] = {"Void", "No_license_found"};
static char* ignoredLicenseTexts[] = {"License by Nomos.", "License by Ninka."};

int isIgnoredLicense(const License* license) {

  int ignoredLicenseNamesCount = sizeof(ignoredLicenseNames)/sizeof(char*);
  for (int i = 0; i < ignoredLicenseNamesCount; i++) {
    if (strcmp(license->shortname, ignoredLicenseNames[i]) == 0)
      return 1;
  }

  int ignoredLicenseTextsCount = sizeof(ignoredLicenseTexts)/sizeof(char*);
  for (int i = 0; i < ignoredLicenseTextsCount; i++) {
    GArray* ignoredTokens = tokenize(ignoredLicenseTexts[i], DELIMITERS);
    if (tokensEquals(license->tokens, ignoredTokens)) {
      tokens_free(ignoredTokens);
      return 1;
    }
    tokens_free(ignoredTokens);
  }

  return 0;
}

Licenses* extractLicenses(fo_dbManager* dbManager, PGresult* licensesResult, unsigned minAdjacentMatches, unsigned maxLeadingDiff) {
  GArray* licenses = g_array_new(TRUE, FALSE, sizeof (License));

  for (int j = 0; j < PQntuples(licensesResult); j++) {
    long refId = atol(PQgetvalue(licensesResult, j, 0));
    char* licShortName = PQgetvalue(licensesResult, j, 1);

    License license;
    license.refId = refId;
    license.shortname = g_strdup(licShortName);

    char* licenseText = getLicenseTextForLicenseRefId(dbManager, refId);
    GArray* licenseTokens = tokenize(licenseText, DELIMITERS);

    free(licenseText);
    license.tokens = licenseTokens;

    if (!isIgnoredLicense(&license))
      g_array_append_val(licenses, license);
    else {
      tokens_free(license.tokens);
      g_free(license.shortname);
    }
  }

  return buildLicenseIndexes(licenses, minAdjacentMatches, maxLeadingDiff);
}

void licenses_free(Licenses* licenses) {
  if (licenses) {
    GArray* licenseArray = licenses->licenses;
    for (guint i = 0; i < licenseArray->len; i++) {
      License* license = license_index(licenseArray, i);
      tokens_free(license->tokens);
      if (license->shortname) {
        g_free(license->shortname);
      }
    }

    g_array_free(licenseArray, TRUE);

    g_array_free(licenses->shortLicenses, TRUE);

    GArray* indexes = licenses->indexes;
    for (guint i = 0; i < indexes->len; i++) {
      GHashTable* index = g_array_index(indexes, GHashTable*, i);
      g_hash_table_unref(index);
    }
    g_array_free(indexes, TRUE);

    free(licenses);
  }
}

guint uint32_hash (gconstpointer v) {
  uint32_t u = *(uint32_t*)v;
  return u;
}

gboolean uint32_equal (gconstpointer  v1, gconstpointer  v2) {
  uint32_t u1 = *(uint32_t*)v1;
  uint32_t u2 = *(uint32_t*)v2;

  return u1 == u2;
}

static void g_array_free_true(void* ptr) {
  g_array_free(ptr, TRUE);
}

uint32_t getKey(const GArray* tokens, unsigned minAdjacentMatches, unsigned searchedStart) {
  uint32_t result = 1;
  for (guint i = 0; (i < minAdjacentMatches) && (i+searchedStart < tokens->len); i++)
  {
    Token* nToken = tokens_index(tokens, i+searchedStart);
    result = (result << 1) + nToken->hashedContent;
  }

  return result;
}

Licenses* buildLicenseIndexes(GArray* licenses, unsigned minAdjacentMatches, unsigned maxLeadingDiff) {
  Licenses* result = malloc(sizeof(Licenses));
  if (!result)
    return NULL;

#define is_short(license) ( (license)->tokens->len <= minAdjacentMatches )
  GArray* shortLicenses = g_array_new(FALSE, FALSE, sizeof(License));
  for (guint i = 0; i < licenses->len; i++) {
    License* license = license_index(licenses, i);
    if (is_short(license)) {
      g_array_append_val(shortLicenses, *license);
    }
  }

  GArray* indexes = g_array_new(FALSE, FALSE, sizeof(GHashTable*));

  for (unsigned sPos = 0; sPos <= maxLeadingDiff; sPos++) {
    GHashTable* index = g_hash_table_new_full(uint32_hash, uint32_equal, free, g_array_free_true);
    g_array_append_val(indexes, index);

    for (guint i = 0; i < licenses->len; i++) {
      License* license = license_index(licenses, i);
      if (!is_short(license)) {
        uint32_t* key = malloc(sizeof(uint32_t));
        *key = getKey(license->tokens, minAdjacentMatches, sPos);

        GArray* indexedLicenses = g_hash_table_lookup(index, key);
        if (!indexedLicenses)
        {
          indexedLicenses = g_array_new(FALSE, FALSE, sizeof(License));
          g_hash_table_replace(index, key, indexedLicenses);
        } else {
          free(key);
        }
        g_array_append_val(indexedLicenses, *license);
      }
    }
  }
#undef is_short

  result->licenses = licenses;
  result->shortLicenses = shortLicenses;
  result->indexes = indexes;
  result->minAdjacentMatches = minAdjacentMatches;

  return result;
}

const GArray* getShortLicenseArray(const Licenses* licenses) {
  return licenses->shortLicenses;
}

const GArray* getLicenseArrayFor(const Licenses* licenses, unsigned searchPos, const GArray* searchedTokens, unsigned searchedStart) {
  const GArray* indexes = licenses->indexes;

  guint minAdjacentMatches = licenses->minAdjacentMatches;

  if (indexes->len <= searchPos) {
    return licenses->licenses;
  }

  GHashTable* index = g_array_index(indexes, GHashTable*, searchPos);
  uint32_t key = getKey(searchedTokens, minAdjacentMatches, searchedStart);
  GArray* result = g_hash_table_lookup(index, &key);
  return result;
}
