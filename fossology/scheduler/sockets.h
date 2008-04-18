/*******************************************************
 sockets.h: Header file for managing spawned sockets.

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
#ifndef SOCKETS_H
#define SOCKETS_H

#include <stdlib.h>
#include <stdio.h>

void	Pause	(char *Message);
int	ReadCmdFD	(int Fin, char *Cmd, int MaxCmd);
int	ReadCmd	(FILE *Fin, char *Cmd, int MaxCmd);
int	SelectAnyData	(int HasFin, int Fin);

#endif
