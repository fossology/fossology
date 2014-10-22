/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include <glib.h>

#include "license.h"

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

GArray* extractLicenses(fo_dbManager* dbManager, PGresult* licensesResult) {
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

  return licenses;
}

static gint lengthInverseComparator(const void * a, const void * b) {
  size_t aLen = ((License*) a)->tokens->len;
  size_t bLen = ((License*) b)->tokens->len;

  return (aLen < bLen) - (aLen > bLen);
}

void sortLicenses(GArray* licenses) {
  g_array_sort(licenses, lengthInverseComparator);
}

void freeLicenseArray(GArray* licenses) {
  for (unsigned int i = 0; i < licenses->len; i++) {
    License license = g_array_index(licenses, License, i);
    g_array_free(license.tokens, TRUE);
  }

  g_array_free(licenses, TRUE);
}
