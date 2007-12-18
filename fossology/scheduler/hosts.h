/***********************************************************
 hosts.h: Header file for managing number of processes per host.

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
 ***********************************************************/
#ifndef HOSTS_H
#define HOSTS_H

extern int RunCount;	/* total number of running processes */
void	HostAdd	(char *Hostname, int MaxRunning, int MaxUrgent);
int	GetHostFromAttr	(char *Attr);
char *	GetValueFromAttr	(char *Attr, char *Field);
int	CanHostRun	(int HostId, int Urgent);
void	SetHostRun	(int HostId, int Value);

#endif
