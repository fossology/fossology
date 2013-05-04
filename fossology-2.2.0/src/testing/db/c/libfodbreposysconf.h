/* **************************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
************************************************************** */
#ifndef LIBFOCUNIT_H
#define LIBFOCUNIT_H
#endif

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define ARRAY_LENGTH 256

int create_db_repo_sysconf(int type, char *agent_name);

void drop_db_repo_sysconf(char *DBName);

char *get_sysconfdir();

char *get_test_name();

char *get_dbconf();

char *get_db_name();

char *get_repodir();
