/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include <glib.h>

#include "license.h"
#include "string_operations.h"

int ignoredLicenseNamesCount = 2;
char* ignoredLicenseNames[] = {"Void", "No_license_found"};

int ignoredLicenseTextsCount = 2;
char* ignoredLicenseTexts[] = {"License by Nomos.", "License by Ninka."};

int isIgnoredLicense(License* license) {
  int ignored = 0;
  for (int i = 0; (i < ignoredLicenseNamesCount) && (!ignored); i++) {
    if (strcmp(license->shortname, ignoredLicenseNames[i]) == 0)
      ignored = 1;
  }
  for (int i = 0; (i < ignoredLicenseTextsCount) && (!ignored); i++) {
    char* ignoredText = g_strdup(ignoredLicenseTexts[i]);
    GArray* ignoredTokens = tokenize(ignoredText, DELIMITERS);
    if (tokensEquals(license->tokens, ignoredTokens))
      ignored = 1;
    g_array_free(ignoredTokens, TRUE);
    g_free(ignoredText);
  }

  return ignored;
}

Licenses* extractLicenses(fo_dbManager* dbManager, PGresult* licensesResult, unsigned minAdjacentMatches, unsigned maxLeadingDiff) {
  GArray* licenses = g_array_new(TRUE, FALSE, sizeof (License));

  for (int j = 0; j < PQntuples(licensesResult); j++) {
    long refId = atol(PQgetvalue(licensesResult, j, 0));
    char* licShortName = PQgetvalue(licensesResult, j, 1);

    License license;
    license.refId = refId;
    license.shortname = licShortName;

    char* licenseText = getLicenseTextForLicenseRefId(dbManager, refId);
    GArray* licenseTokens = tokenize(licenseText, DELIMITERS);

    free(licenseText);
    license.tokens = licenseTokens;

    if (!isIgnoredLicense(&license))
      g_array_append_val(licenses, license);
    else
      g_array_free(license.tokens, TRUE);
  }

  return buildLicenseIndexes(licenses, minAdjacentMatches, maxLeadingDiff);
}

void licenses_free(Licenses* licenses) {
  if (licenses) {
    GArray* licenseArray = licenses->licenses;
    for (guint i = 0; i < licenseArray->len; i++) {
      License license = g_array_index(licenseArray, License, i);
      g_array_free(license.tokens, TRUE);
    }

    g_array_free(licenseArray, TRUE);

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

uint32_t getKey(GArray* tokens, unsigned minAdjacentMatches, unsigned searchedStart) {
  uint32_t result = 1;
  for (guint i = 0; (i < minAdjacentMatches) && (i+searchedStart < tokens->len); i++)
  {
    Token* nToken = &g_array_index(tokens, Token, i+searchedStart);
    result = (result << 1) + nToken->hashedContent;
  }

  return result;
}

Licenses* buildLicenseIndexes(GArray* licenses, unsigned minAdjacentMatches, unsigned maxLeadingDiff) {
  Licenses* result = malloc(sizeof(Licenses));
  if (!result)
    return NULL;

  GArray* indexes = g_array_new(FALSE, FALSE, sizeof(GHashTable*));

  for (unsigned sPos = 0; sPos <= maxLeadingDiff; sPos++) {
    GHashTable* index = g_hash_table_new_full(uint32_hash, uint32_equal, free, g_array_free_true);
    g_array_append_val(indexes, index);

    for (guint i = 0; i < licenses->len; i++) {
      License license = g_array_index(licenses, License, i);
      uint32_t* key = malloc(sizeof(uint32_t));
      *key = getKey(license.tokens, minAdjacentMatches, sPos);

      GArray* indexedLicenses = g_hash_table_lookup(index, key);
      if (!indexedLicenses)
      {
        indexedLicenses = g_array_new(FALSE, FALSE, sizeof(License));
        g_hash_table_replace(index, key, indexedLicenses);
      } else {
        free(key);
      }
      g_array_append_val(indexedLicenses, license);
    }
  }

  result->licenses = licenses;
  result->indexes = indexes;
  result->minAdjacentMatches = minAdjacentMatches;

  return result;
}


const GArray* getLicenseArrayFor(Licenses* licenses, unsigned searchPos, GArray* searchedTokens, unsigned searchedStart) {
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