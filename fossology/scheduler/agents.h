/*******************************************************
 agents.h: Header file for managing agent requests.

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
 *******************************************************************/
#ifndef CLIENTS_H
#define CLIENTS_H

#include <time.h>

#define MAXHEARTBEAT	(3*60)	/* seconds before child is considered dead */

int	ReadChild	(int Thread);
int	MatchOneAttr	(char *AttrList, char *Attr, int AttrLen);
int	MatchAttr	(char *AttrList, char *Attr);
int	CheckAgent	(char *AgentType);
void	StaleChild	();
void	CheckChildren	(time_t Now);
int	GetChild	(char *Attr, int IsUrgent);
int	SchedulerCommand	(char *Attr, char *Cmd);

#endif
