/***************************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

***************************************************************/
#ifndef _DBCOMMON_H
#define _DBCOMMON_H 1
#include <stdio.h>
#include <stdlib.h>

#include "libfossdb.h"
#define FUNCTION

int checkPQresult(PGresult *result, char *sql, char *FcnName, int LineNumb);
#endif /* _DBCOMMON_H */
