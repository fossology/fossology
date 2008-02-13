/*******************************************************
 dbstatus: Functions for updating the DB status.

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
 
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
 *******************************************************/
#ifndef DBSTATUS_H
#define DBSTATUS_H

extern	char *Hostname;

void	DBSetHostname	();
void	DBMkArgCols	(void *DB, int Row, char *Arg, int MaxArg);

int	DBLockAccess	(void *VDB, char *SQL);
void	DBUpdateJob	(int JobId, int UpdateType, char *Message);
int	DBstrcatTaint	(char *V, char *S, int MaxS);

void	DBCheckSchedulerUnique	();
void	DBCheckStatus	();
void	DBSaveSchedulerStatus	(int Thread, char *StateName);
void	DBSaveJobStatus	(int Thread, int MSQid); /* if Thread != -1 then use it, otherwise use MSQid */
void	DBkillschedulers	();

void	DebugMSQ	();
#endif

