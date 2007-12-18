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

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <stdint.h>
#include <ctype.h>
#include <string.h>

#include "Filter_License.h"
#include "tokholder.h"

/***********************************************************************/
/** Functions to handle strings and tokens **/
/***********************************************************************/
TokHolder	TH = {NULL,0,0,0,0,NULL,NULL,0,0};

/*******************************************
 TokDump(): Debugging -- list every token.
 *******************************************/
void	TokDump	()
{
  int i;
  fileoffset Pos;
  if (!TH.Raw) return;
  Pos = 0;
  for(i=0; i<TH.PreLineLen; i++)
    {
    while((i<TH.PreLineLen) && (TH.PreLineMap[i] == 0)) i++;
    fprintf(stderr,"TokDump[%d] @ %x '%.*s'\n",i,Pos+TH.Start,TH.PreLineMap[i],TH.Raw+Pos);
    Pos += TH.PreLineMap[i];
    }
} /* TokDump() */

/*******************************************
 TokClear(): Clear the global TH.
 Set Raw to point to a memory location.
 *******************************************/
void	TokClear	(unsigned char *Raw, fileoffset Start, int Show)
{
  /* First process any currently-saved data */
  if (Show && (TH.PreLineLen > 0))
    {
#if 0
    /* debug */
    int i;
    int RealStart,RealEnd;
    fprintf(stderr,"TokClear: %d bytes, Raw=%x\n",TH.PreLineLen,TH.Raw);
    fprintf(stderr,"  ");
    for(i=0; i<TH.PreLineLen; i++) fputc(TH.PreLine[i],stderr);
    fprintf(stderr,"\n  ");
    for(i=0; i<TH.PreLineLen; i++) fputc(TH.PreLineMap[i]+'@',stderr);
    fprintf(stderr,"\n");
    RealStart = TokOffset(0)-TH.Start;
    RealEnd = TokOffset(TH.PreLineLen)-TH.Start;
    fprintf(stderr,"  Offsets: %x - %x\n",RealStart,RealEnd);
    fprintf(stderr,"  ");
    for(i=RealStart; i<RealEnd; i++) fputc(TH.Raw[i],stderr);
    fprintf(stderr,"\n");
#endif
    Prep2bSAM();
    }

  /* Then set the new data range */
  TH.Raw = Raw;
  TH.Start = Start;
  TH.Curr = Start;
  TH.LastOffset = 0;
  TH.LastValue = Start;
  TH.TokCount = 0;

  if (TH.PreLineLen > 0)
    {
    memset(TH.PreLine,   0,TH.PreLineLen * sizeof(char));
    memset(TH.PreLineMap,0,TH.PreLineLen * sizeof(int));
    }
  TH.PreLineLen=0;
} /* TokClear() */

/*******************************************
 TokAddStr(): Add some raw bytes and a string to TH.
 Curr = current offset (compared with TH.Curr to find byte offset)
 *******************************************/
inline void	TokAddStr	(char *Str, int StrLen, fileoffset Curr)
{
#define INCSIZE	10240
  /* add the string -- increase memory as needed */
  if (TH.PreLineLen+StrLen >= TH.PreLineMax)
    {
    TH.PreLineMax += INCSIZE;	/* increase bytes */
    TH.PreLine = (char *)realloc(TH.PreLine,TH.PreLineMax * sizeof(char));
    if (!TH.PreLine)
      {
      fprintf(stderr,"ERROR: realloc failed.\n");
      TokClear(TH.Raw,TH.Start,1);
      return;
      }
    TH.PreLineMap = (int *)realloc(TH.PreLineMap,TH.PreLineMax * sizeof(int));
    if (!TH.PreLineMap)
      {
      fprintf(stderr,"ERROR: realloc failed.\n");
      TokClear(TH.Raw,TH.Start,1);
      return;
      }
    /* zero new bytes */
    memset(&(TH.PreLine[TH.PreLineMax-INCSIZE]),0,INCSIZE*sizeof(char));
    memset(&(TH.PreLineMap[TH.PreLineMax-INCSIZE]),0,INCSIZE*sizeof(int));
    }

  /* store the pre-processed string */
  memcpy(&(TH.PreLine[TH.PreLineLen]),Str,StrLen);

  /* store the length for this new string */
  TH.PreLineMap[TH.PreLineLen] = Curr - TH.Curr;
  TH.PreLineLen += StrLen;
  TH.Curr = Curr;
} /* TokAddStr() */

/*******************************************
 TokAddChr(): Add a raw byte and a string to TH.
 *******************************************/
void	TokAddChr	(char C, fileoffset Curr)
{
  char S[2]={0,0};
  S[0]=C;
  TokAddStr(S,1,Curr);
} /* TokAddChr() */

/*******************************************
 TokOffset(): Determine the real offset for
 the preprocessed value.
 *******************************************/
fileoffset	TokOffset	(fileoffset Index)
{
  fileoffset Len;
  fileoffset j;

  if (Index > TH.PreLineLen) Index=TH.PreLineLen;

#if 1
  /* for speed */
  if (Index > TH.LastOffset)
    {
    /* continue where we left off */
    j = TH.LastOffset;
    Len = TH.LastValue;
    }
  else
    {
    j=0;
    Len = TH.Start;
    }

  for( ; j<Index; j++)
    {
    Len += TH.PreLineMap[j];
    }
#else
  Len = 0;
  for(j=0; j<Index; j++)  Len += TH.PreLineMap[j];
#endif

  /* remember where we left off */
  TH.LastOffset = Index;
  TH.LastValue = Len;
  return(Len);
} /* TokOffset() */

/*******************************************
 TokRevOffset(): Determine the preprocessed offset
 from the real offset.
 *******************************************/
fileoffset	TokRevOffset	(fileoffset Index)
{
  fileoffset Offset;
  fileoffset j;

  Offset = TH.Start;
  j=0;

  while((j < TH.PreLineMax) && (Offset < Index))
    {
    Offset += TH.PreLineMap[j];
    j++;
    }
  return(j);
} /* TokRevOffset() */

