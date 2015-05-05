/**************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License version 2.1 as published by the Free Software Foundation.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this library; if not, write to the Free Software Foundation, Inc.0
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
**************************************************************/
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
