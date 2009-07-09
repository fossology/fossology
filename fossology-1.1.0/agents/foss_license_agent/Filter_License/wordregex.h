/**********************************************************
 wordregex.c: functions to perform a personalized regex on sentences.

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
===============
 Terms:
   "%"	skip 0 or more words
   "%5"	skip up to 5 words
   "string*"	match word beginning with string
   "*string"	match word ending with string
   "*string*"	match word containing with string
   "*"		match ONE entire word (same as %1)
   "string1|string1"	match string1 or string2
 **********************************************************/

#ifndef WORDREGEX_H
#define WORDREGEX_H

#include <stdlib.h>
#include <stdint.h>

enum WR_Status_Flags
  {
  WR_STATUS_MATCHING=1,	/* currently matching (have first match) */
  WR_STATUS_SAVING=2,	/* currently saving (have first save) */
  WR_STATUS_SAVE=4,	/* saving enabled */
  WR_STATUS_ALL=7
  };

int	WR_MatchString	(int MatchFlag, char *String, char *Regex, uint32_t Start);
int	WR_MatchString_Init	(char *String, char *Regex, uint32_t Start);
void	WR_GetStartEnd	(uint32_t *Start, uint32_t *End);

#endif

