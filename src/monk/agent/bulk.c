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
#include <stddef.h>

#include "bulk.h"
#include "file_operations.h"
#include "database.h"
#include "license.h"
#include "match.h"
#include "monk.h"

int parseBulkArguments(int argc, char** argv, MonkState* state) {
  if (argc < 1)
    return 0;
  /* TODO use normal cli arguments instead of this indian string
   *
   * at the moment it is separated by '\31' == \x19
   *  B or N, uploadId, uploadTreeId, licenseName, userId, refText, groupName, fullLicenseName
   *
   *  for example run
   *  monk $'B\x191\x19a\x19\x19shortname\x192\x19copyrights\x19group\x19fullname'
   */

  // TODO remove magics from here
  char* delimiters = "\31";
  char* remainder = NULL;
  char* argumentsCopy = g_strdup(argv[1]);

  char* tempargs[8];
  unsigned int index = 0;

  char* tokenString = strtok_r(argumentsCopy, delimiters, &remainder);
  while (tokenString != NULL && index < sizeof(tempargs)) {
    tempargs[index++] = tokenString;
    tokenString = strtok_r(NULL, delimiters, &remainder);
  }

  int result = 0;
  // TODO remove magics from here
  if ((tokenString == NULL) &&
    ((strcmp(tempargs[0], "B") == 0) || (strcmp(tempargs[0], "N") == 0)) &&
    (index >= 6))
  {
    BulkArguments* bulkArguments = malloc(sizeof(BulkArguments));

    bulkArguments->sign = tempargs[0][0] == 'N' ? 1 : -1;
    bulkArguments->uploadId = atol(tempargs[1]);
    //not using targs[2] (uploadTreeId)
    bulkArguments->licenseName = g_strdup(tempargs[3]);
    bulkArguments->userId = atoi(tempargs[4]);
    bulkArguments->refText = g_strdup(tempargs[5]);
    bulkArguments->groupId = atoi(tempargs[6]);
    bulkArguments->fullLicenseName = index > 7 ? g_strdup(tempargs[7]) : NULL;

    state->bulkArguments = bulkArguments;

    result = 1;
  }

  g_free(argumentsCopy);
  return result;
}

void bulkArguments_contents_free(BulkArguments* bulkArguments) {
  if (bulkArguments->fullLicenseName)
    g_free(bulkArguments->fullLicenseName);
  g_free(bulkArguments->licenseName);
  g_free(bulkArguments->refText);

  free(bulkArguments);
}

long insertNewBulkLicense(fo_dbManager* dbManager, const char* shortName, const char* fullName, const char* refText,
                          long uploadId, int userId, int groupId) {
  const char* fullNameNotNull =
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
    printf("adding bulk license to db: sn='%s', fn='%s', txt='%s', uid=%d, gid=%d, upl=%ld\n", shortName, fullNameNotNull, refText, userId, groupId, uploadId);
  }

  return 1;
}

void bulk_identification(MonkState* state) {
  BulkArguments* bulkArguments = state->bulkArguments;

  if (bulkArguments->sign == -1)
    state->scanMode = MODE_BULK_NEGATIVE;

  long licenseId = insertNewBulkLicense(state->dbManager,
                                        bulkArguments->licenseName,
                                        bulkArguments->fullLicenseName,
                                        bulkArguments->refText,
                                        bulkArguments->uploadId,
                                        bulkArguments->userId,
                                        bulkArguments->groupId);

  if (licenseId > 0) {
    License license = (License){
      .refId = licenseId,
      .shortname = bulkArguments->licenseName,
    };
    license.tokens = tokenize(bulkArguments->refText, DELIMITERS);

    GArray* licenses = g_array_new(TRUE, FALSE, sizeof (License));
    g_array_append_val(licenses, license);

    PGresult* filesResult = queryFileIdsForUpload(state->dbManager, bulkArguments->uploadId);

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

int handleBulkMode(MonkState* state) {
  BulkArguments* bulkArguments = state->bulkArguments;

  int arsId = fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
                          0, bulkArguments->uploadId, state->agentId, AGENT_ARS, NULL, 0);

  bulk_identification(state);

  fo_WriteARS(fo_dbManager_getWrappedConnection(state->dbManager),
              arsId, bulkArguments->uploadId, state->agentId, AGENT_ARS, NULL, 1);

  return 1;
}

void onFullMatch_Bulk(MonkState* state, File* file, License* license, DiffMatchInfo* matchInfo) {
  int removed = state->scanMode == MODE_BULK_NEGATIVE ? 1 : 0;

// TODO remove debug
#define DEBUG_BULK
#ifdef DEBUG_BULK
  printf("found bulk match: fileId=%ld, licId=%ld, ", file->id, license->refId);
  printf("start: %zu, length: %zu, ", matchInfo->text.start, matchInfo->text.length);
  printf("removed: %d\n", removed);
#endif

  /* TODO write correct query after changing the db format */
  //TODO we also want to save sign and highlights

  /* we add a clearing decision for each uploadtree_fk corresponding to this pfile_fk
   * For each bulk scan scan we only have a n the other hand we have only one license per clearing decision
   */
  PGresult* insertResult = fo_dbManager_ExecPrepared(
    fo_dbManager_PrepareStamement(
      state->dbManager,
      "saveBulkResult",
      "WITH clearingIds AS ("
      " INSERT INTO clearing_decision(uploadtree_fk, pfile_fk, user_fk, type_fk, scope_fk)"
      "  SELECT uploadtree_pk, $1, $2, type_pk, scope_pk"
      "  FROM uploadtree, clearing_decision_types, clearing_decision_scopes"
      "  WHERE upload_fk = $3 AND pfile_fk = $1 "
      "  AND clearing_decision_types.meaning = '" BULK_DECISION_TYPE "'"
      "  AND clearing_decision_scopes.meaning = '" BULK_DECISION_SCOPE "'"
      " RETURNING clearing_pk "
      ")"
      "INSERT INTO clearing_licenses(clearing_fk, rf_fk, removed) "
      "SELECT clearing_pk,$4,$5 FROM clearingIds",
      long, long, int, long, int
    ),
    file->id,
    state->bulkArguments->userId,
    state->bulkArguments->uploadId,
    license->refId,
    removed
  );

  /* ignore errors */
  if (insertResult)
    PQclear(insertResult);
}