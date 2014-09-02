/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#define _GNU_SOURCE
#include <libfossology.h>
#include <string.h>

#include "bulk.h"
#include "file_operations.h"
#include "database.h"
#include "license.h"
#include "match.h"
#include "monk.h"

long insertNewBulkLicense(fo_dbManager* dbManager, const char* shortName, const char* fullName, const char* refText,
                          long uploadId, int userId, const char* group) {
  const char* fullLicenseNameReal =
    fullName == NULL
    ? shortName
    : fullName;

  //TODO build unique shortname and insert it
  if (0) {
    fo_dbManager_Exec_printf(dbManager,
      "INSERT",
      shortName, fullName, refText
    );
  } else {
    printf("adding bulk license to db: sn='%s', fn='%s', txt='%s', uid=%d, gr='%s', upl=%ld\n", shortName, fullLicenseNameReal, refText, userId, group, uploadId);
  }

  return 1;
}

void bulk_identification(MonkState* state, long uploadId, char* refText, char* licenseName, int userId, char* group, char* fullLicenseName) {
  long licenseId = insertNewBulkLicense(state->dbManager,
                                    licenseName, fullLicenseName, refText,
                                    uploadId, userId, group);
  if (licenseId > 0) {
    License license = (License){
      .refId = licenseId,
      .shortname = licenseName,
    };
    license.tokens = tokenize(refText, DELIMITERS);

    GArray* licenses = g_array_new(TRUE, FALSE, sizeof (License));
    g_array_append_val(licenses, license);

    PGresult* filesResult = queryFileIdsForUpload(state->dbManager, uploadId);

    if (filesResult != NULL) {
      for (int i = 0; i<PQntuples(filesResult); i++) {
        long fileId = atol(PQgetvalue(filesResult, i, 0));

        // this will call onFullMatch_Bulk if it finds matches
        matchPFileWithLicenses(state, fileId, licenses);
        fo_scheduler_heart(1);
      }
      PQclear(filesResult);
    }

    freeLicenseArray(licenses);
  }
}

int tokenizeBulkArguments(char* arguments, char* targs[8], int* argumentCount) {
  char* delimiters = "\31";
  char* remainder = NULL;

  unsigned int index = 0;
  char* tokenString = strtok_r(arguments, delimiters, &remainder);
  while (tokenString != NULL && index < sizeof(targs)) {
    targs[index++] = tokenString;
    tokenString = strtok_r(NULL, delimiters, &remainder);
  }
  *argumentCount = index;

  return (tokenString == NULL) && (strcmp(targs[0], "B") == 0) && (index >= 6);
}

int parsableAsBulk(int argc, char** argv) {
  if (argc < 1)
    return 0;

  char* targs[8];
  int argumentCount;

  char* arguments = g_strdup(argv[1]);
  int result = tokenizeBulkArguments(arguments, targs, &argumentCount);
  g_free(arguments);
  return result;
}

int handleBulkMode(MonkState* state, int argc, char** argv) {
  /* arg 0=> monk exec name, 1=>"B", 2=>uploadid, 3=>uploadtree_pk, 4=>new lic name,5=>userID 6 onwards "lic text selected"*/

  if (argc < 1)
    return 0;

  char* targs[8];
  int argumentCount;

  char* arguments = g_strdup(argv[1]);
  tokenizeBulkArguments(arguments, targs, &argumentCount);

  /* start bulk mode scan */

  long uploadId = atol(targs[1]);
  int arsId = fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                          0, uploadId, state->agentId, AGENT_ARS, NULL, 0);

  //long uploadTreeId = atol(targs[2]);
  char* licenseName = targs[3];
  int userId = atoi(targs[4]);
  char* refText = targs[5];
  char* groupId = targs[6];
  char* fullLicenseName = argumentCount > 7 ? targs[7] : NULL;

  bulk_identification(state, uploadId, refText, licenseName, userId, groupId, fullLicenseName);

  fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
              arsId, uploadId, state->agentId, AGENT_ARS, NULL, 1);

  g_free(arguments);
  return 1;
}

void onFullMatch_Bulk(File* file, License* license, DiffMatchInfo* matchInfo) {
  // TODO write to db
  printf("found bulk match: fileId=%ld, licId=%ld, ", file->id, license->refId);
  printf("start: %zu, length: %zu\n", matchInfo->text.start, matchInfo->text.length);
}