/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
 */

/* Adapter implementations and thin wrappers around libfossdb functions.
 * This file is intentionally small: it allows maintagent2 to link a different
 * symbol namespace if we later want to change behavior without affecting the
 * global libfossdb interface.
 */

#include "libfossdb_adapter.h"
#include "libfossdb.h"

#include <libpq-fe.h>
#include <stdio.h>
#include <string.h>

/* Minimal implementation of fo_copy_ids_to_temp_table used by the maintagent2
 * adapter. It creates a session-local temp table and COPYs the newline-separated
 * `ids` string into it. Returns 0 on success, non-zero on error (error message
 * placed in errbuf if provided).
 */
int fo_copy_ids_to_temp_table(PGconn* pgConn, const char* ids, const char* tempTableName, char* errbuf, size_t errlen)
{
    PGresult *res = NULL;
    int ret = 1;

    if (!pgConn || !tempTableName) {
        if (errbuf && errlen) snprintf(errbuf, errlen, "invalid parameters");
        return ret;
    }

    /* Create temp table */
    char createSql[512];
    snprintf(createSql, sizeof(createSql), "CREATE TEMP TABLE %s (id bigint);", tempTableName);
    res = PQexec(pgConn, createSql);
    if (!res || PQresultStatus(res) != PGRES_COMMAND_OK) {
        if (errbuf && errlen) snprintf(errbuf, errlen, "CREATE TEMP TABLE failed: %s", PQerrorMessage(pgConn));
        if (res) PQclear(res);
        return ret;
    }
    PQclear(res);

    /* Begin COPY FROM STDIN */
    char copySql[256];
    snprintf(copySql, sizeof(copySql), "COPY %s (id) FROM STDIN;", tempTableName);
    res = PQexec(pgConn, copySql);
    if (!res || PQresultStatus(res) != PGRES_COPY_IN) {
        if (errbuf && errlen) snprintf(errbuf, errlen, "COPY failed to start: %s", PQerrorMessage(pgConn));
        if (res) PQclear(res);
        return ret;
    }
    PQclear(res);

    /* Send the ids string as COPY data; ids is expected newline-separated */
    const char *p = ids;
    size_t len = ids ? strlen(ids) : 0;
    if (len > 0) {
        if (PQputCopyData(pgConn, p, (int)len) <= 0) {
            if (errbuf && errlen) snprintf(errbuf, errlen, "PQputCopyData failed: %s", PQerrorMessage(pgConn));
            PQputCopyEnd(pgConn, "failed");
            return ret;
        }
    }

    if (PQputCopyEnd(pgConn, NULL) == -1) {
        if (errbuf && errlen) snprintf(errbuf, errlen, "PQputCopyEnd failed: %s", PQerrorMessage(pgConn));
        return ret;
    }

    /* Finish and check result */
    res = PQgetResult(pgConn);
    if (!res || PQresultStatus(res) != PGRES_COMMAND_OK) {
        if (errbuf && errlen) snprintf(errbuf, errlen, "COPY did not complete: %s", PQerrorMessage(pgConn));
        if (res) PQclear(res);
        return ret;
    }
    PQclear(res);

    return 0;
}

int fo_copy_ids_to_temp_table_adapter(PGconn* pgConn, const char* ids, const char* tempTableName, char* errbuf, size_t errlen)
{
    return fo_copy_ids_to_temp_table(pgConn, ids, tempTableName, errbuf, errlen);
}
