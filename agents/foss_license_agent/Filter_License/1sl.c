/*********************************************************************
 1SL: functions to handle one-sentence license phrases.

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
 *********************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <stdint.h>
#include <ctype.h>
#include <string.h>

#include <libfossrepo.h> /* repository functions */
#include "Filter_License.h"
#include "tokholder.h"
#include "1sl.h"
#include "wordregex.h"


/***********************************************************************/
/** Functions to check for single-sentence licenses **/
/***********************************************************************/

struct Type1SL
  {
  char *Name;
  char *Regex;
  };
typedef struct Type1SL Type1SL;

/* MAX1SL is used to prevent long lists (e.g., dictionaries) from
   matching a 1SL.  (300 characters is a very long sentence...) */
#define	MAX1SL	300


/* For speed, don't start a regex with a "%" phrase.  '%' are expensive! */
Type1SL List1SL[] = {
	{"1SL: %s", "< license|copyright : * %EOL >"},
	{"1SL: %s", "< %2 is free|freely %EOS >"},
	{"1SL: %s", "< %2 is not free|freely * %EOS >"},
	{"1SL: %s", "< %2 provide|provided|distribute|distributed|redistributed|release|released freely %EOS >"},
	{"1SL: %s", "< %2 is|be provided|distributed|redistributed|released|licensed|licenced|covered|adheres as|under|by|in|from %EOS >"},
	{"1SL: %s", "< %2 provided|distributed|redistributed|released|licensed|licenced|covered|adheres as|under|by|in|from %EOS >"},
	{"1SL: %s", "< %2 provide|distribute|redistribute|release|copy %1 license|licence|software|program|library|file %EOS >"},
	{"1SL: %s", "< %4 under the terms of %EOS >"},
	{"1SL: %s", "< %2 distribute|redistribute|modify it under %EOS >"},
	{"1SL: %s", "< %4 \"|' as is \"|' %EOS >"},
	{"1SL: %s", "< %4 \"|' as - is \"|' %EOS >"},
	{"1SL: %s", "< %2 proprietary %EOS >"},
	{"1SL: %s", "< %2 public domain %EOS >"},
	{"1SL: %s", "< %2 special exception %EOS >"},
	{"1SL: %s", "< %2 accept the|this license %EOS >"},
	{"1SL: %s", "< %2 governs use of %EOS >"},
	{"1SL: %s", "< %2 governs the use of %EOS >"},
	{"1SL: %s", "< %2 non - commercial %EOS >"},
	{NULL,NULL}
	};

/*********************************************
 Check1SL(): Check for one-sentence licenses (1SL).
 This assumes that the global TH has been loaded.
 Input: global TH containing pre-processed text.
   Begin/Finish = offsets into TH.PreLine.
 Output: sends output to Fout.
 Returns: number of 1SL matches.
 THIS IS RECURSIVE!
 *********************************************/
int	Check1SL	(fileoffset Begin, fileoffset Finish, FILE *Fout)
{
  int Match=0;
  int i,j;
  fileoffset Start,End,Len;
  fileoffset BestStart=0,BestEnd=0;
  int BestMatchId;
  char EndChar;

  if (Verbose) fprintf(stderr,"  Check1SL: %x - %x (0x%x - 0x%x)\n",Begin,Finish,TokOffset(Begin),TokOffset(Finish));
  EndChar = TH.PreLine[Finish];
  TH.PreLine[Finish] = '\0'; /* null terminate string */
  if (Verbose > 2) fprintf(stderr,"Check1SL: Line: '%s'\n",TH.PreLine+Begin);

  /* Find the best (earliest) 1SL match */
  BestMatchId=-1;
  for(i=0; List1SL[i].Name != NULL; i++)
    {
    if (WR_MatchString_Init(TH.PreLine,List1SL[i].Regex,Begin))
	{
	/* it matched, see if it was best */
	WR_GetStartEnd(&Start,&End);
	if ((End-Start < MAX1SL) && ((BestMatchId < 0) || (Start < BestStart)))
	  {
	  BestMatchId = i;
	  BestStart = Start;
	  BestEnd = End+1;
	  }
	}
    }

  /* display the best result */
  if (BestMatchId >= 0)
	{
	Start = TokOffset(BestStart);
	End = TokOffset(BestEnd);
	/* remove spaces */
	while(isspace(TH.Raw[Start-TH.Start])) Start++;

	Len = End - Start + 1; /* 1 for end of string */

	/* write the start and stop offsets for the 1SL */
	fputc(0x01,Fout); fputc(0x31,Fout); /* tag 0x0131 = start */
	fputc(0x00,Fout); fputc(0x04,Fout); /* 4 bytes */
	fputc((Start >> 24) & 0xff,Fout);
	fputc((Start >> 16) & 0xff,Fout);
	fputc((Start >> 8) & 0xff,Fout);
	fputc((Start) & 0xff,Fout);

	fputc(0x01,Fout); fputc(0x32,Fout); /* tag 0x0132 = end */
	fputc(0x00,Fout); fputc(0x04,Fout); /* 4 bytes */
	fputc((End >> 24) & 0xff,Fout);
	fputc((End >> 16) & 0xff,Fout);
	fputc((End >> 8) & 0xff,Fout);
	fputc((End) & 0xff,Fout);

	/* write the 1SL */
	fputc(0x01,Fout); fputc(0x40,Fout); /* tag 0x0140 = string */
	fputc((Len>>8)&0xff,Fout);
	fputc(Len&0xff,Fout);
	/* move Start/End to match Raw offsets */
	Start -= TH.Start;
	End -= TH.Start;
	for(j=Start; j<End; j++)
	  {
	  fputc(TH.Raw[j],Fout);
	  }
	fputc(0x00,Fout); /* write end-of-string */
	if (Len & 0x01) fputc(0xff,Fout); /* 2-byte boundary */
	Match++;
	}

  TH.PreLine[Finish] = EndChar; /* replace null terminator */

  /* recurse */
  if (BestMatchId > 0)
    {
    Match += Check1SL(BestEnd,Finish,Fout);
    }
  return(Match);
} /* Check1SL() */

