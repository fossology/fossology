/*******************************************************
 Scheduler: Spawn off processes in parallel, feed them data
 as they request it.

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
#ifndef SCHEDULER_H
#define SCHEDULER_H

#include <stdlib.h>
#include <stdio.h>

#define MAXCMD	8192
#define MAXBUFF	256
#define MAXARG	256
#define MAXATTR	256
#define MAXCTIME	48

extern int Verbose;
extern char BuildVersion[];
extern int ShowState;
extern int SLOWDEATH;	/* set to "1" to exit gracefully */
extern int IgnoreHost;  /* flag for ignoring host in host-specific requests */
extern void *DB;	/* global DB handle for accessing the DB */

void	Usage	(char *Name);
int	MatchOneAttr	(char *AttrList, char *Attr, int AttrLen);
int	MatchAttr	(char *AttrList, char *Attr);
int	SchedulerCommand	(char *Attr, char *Cmd);
int StopScheduler(int killsched);

#define CheckClose(x)   { if (x) close(x); }

#endif
