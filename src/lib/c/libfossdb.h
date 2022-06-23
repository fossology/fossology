/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/
#ifndef LIBFOSSDB_H
#define LIBFOSSDB_H

#include <stdlib.h>
#include <stdio.h>
#include <errno.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <libpq-fe.h>

PGconn* fo_dbconnect(char* DBConfFile, char** ErrorBuf);
int fo_checkPQcommand(PGconn* pgConn, PGresult* result, char* sql, char* FileID, int LineNumb);
int fo_checkPQresult(PGconn* pgConn, PGresult* result, char* sql, char* FileID, int LineNumb);
int fo_tableExists(PGconn* pgConn, const char* tableName);

#endif  /* LIBFOSSDB_H */
