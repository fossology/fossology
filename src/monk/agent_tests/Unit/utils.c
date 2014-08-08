/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include "libfossdb.h"

PGconn * dbRealConnect() {
  char * errorBuf = NULL;
  PGconn * dbConn = fo_dbconnect(NULL, &errorBuf);

  if (errorBuf != NULL) {
    dbConn = NULL;
    printf("Could not connect to real database for test. Error: %s\n", errorBuf);
    free(dbConn);
  }

  return dbConn;
}

void dbRealDisconnect(PGconn * dbConn) {
  PQfinish(dbConn);
}
