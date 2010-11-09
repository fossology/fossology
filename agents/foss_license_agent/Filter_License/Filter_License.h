/*********************************************************************
 Filter_License: Given a file, generate a bSAM cached license file.

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

 This uses the DB and repository.
 All output is written to the repository.
 *********************************************************************/

#ifndef FILTER_LICENSE_H
#define FILTER_LICENSE_H

extern int Verbose;	/* how verbose? (for debugging) */

typedef uint16_t	tokentype;
typedef uint32_t	fileoffset;

#define Max(x,y)	((x) > (y) ? (x) : (y))
#define Min(x,y)	((x) < (y) ? (x) : (y))

#define MAX_TOKEN       32767 /* value for 2 bytes and max bytes=65535 */
#define MAX_TOKEN_LOAD  (MAX_TOKEN*4) /* max tokens to load at a time */
#define MAX_WORDS       (MAX_TOKEN_LOAD*20)

/***********************************************************************/
/** Functions to tokenize the temp file and generate output data. **/
/***********************************************************************/

tokentype	StringToToken	(char *S, int LenS);
int	Prep2bSAM	();

#endif
