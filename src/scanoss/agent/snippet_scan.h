// SPDX-License-Identifier: GPL-2.0-only
/*!
 * snippet_scan.h
 *
 * SCANOSS Scanner interface for SCANOSS Agent for Fossology 
 *
 * Copyright (C) 2018-2022 SCANOSS.COM
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
/**
 * \file
 * \brief scanoss header
 */
#ifndef _SNIPPET_SCAN_H
#define _SNIPPET_SCAN_H 1

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <libgen.h>
#include <time.h>

#include <sys/wait.h>


#include <libfossology.h>

#define MAXCMD 5000
#define MAXLENGTH 256


extern PGconn* db_conn;         ///< the connection to Database

int ProcessUpload(long upload_pk,char *tempFolder);
void Usage(char *Name);
int runScan(char *path,  char **licenses, unsigned char *purl,unsigned char *url, unsigned char *matchType, unsigned char *oss_lines, unsigned char *filePath);
void loadDeprecated();

#endif /*  _SNIPPET_SCAN_H */
