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

#ifndef TOKHOLDER_H
#define TOKHOLDER_H

#include "Filter_License.h"

/***********************************************************************/
/** Functions to handle strings and tokens **/
/***********************************************************************/
struct TokHolder
  {
  unsigned char *Raw;	/* the raw line (pointer to mmap string) */
  fileoffset Start; /* starting Raw offset (Start+PreLineMap = file offset) */
  fileoffset Curr; /* current Raw offset */
  int PreLineMax;
  int PreLineLen;
  char *PreLine;  /* pre-processed line for text comparisons -- TokAddStr() */
  int *PreLineMap; /* number raw of characters per PreLine character */
  int TokCount;	/* how many tokens loaded? */
  /* for speed */
  fileoffset LastOffset;
  fileoffset LastValue;
  };
typedef struct TokHolder TokHolder;
extern TokHolder	TH;

void	TokDump		();
void	TokClear	(unsigned char *Raw, fileoffset Start, int Show);
inline void	TokAddStr	(char *Str, int StrLen, fileoffset Curr);
inline void	TokAddChr	(char C, fileoffset Curr);
fileoffset	TokOffset	(fileoffset Index);
fileoffset	TokRevOffset	(fileoffset Index);

#define TokVal(i)	(TH.PreLine[i])

#endif
