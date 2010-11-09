/*********************************************************************
 WordCheck: Functions to determine if a string contains a likely license.

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
#include "wordcheck.h"

/***********************************************************************/
/** Functions to check for good token ranges **/
/***********************************************************************/

/* Potential licenses should contain at least one of these words */
tokentype GoodWordList[256]; /* 256 should be >= elements in GoodWords */
char GoodWordListOR[256]; /* 256 should be >= elements in GoodWords */
char *GoodWords[] =
  {
  "acknowledgement",
  "agreement",
  "agreements",
  "cdl",
  "cddl",
  "cpl",
  "cecill",
  "condition",
  "conditions",
  "copyleft",
  "copyright",
  "damages",
  "derivative",
  "disclaimer",
  "distribute",
  "distributed",
  "distribution",
  "distributions",
  "gpl",
  "gfdl",
  "legal",
  "liability",
  "licencable",
  "licence",
  "licenced",
  "licencee",
  "licencor",
  "licencing",
  "licensable",
  "license",
  "licensed",
  "licensee",
  "licensing",
  "licensor",
  "lgpl",
  "mpl",
  "opl",
  "patent",
  "patents",
  "preamble",
  "permission",
  "permissions",
  "proprietary",
  "redistributed",
  "redistribution",
  "restriction",
  "restrictions",
  "rights",
  "trademark",
  "trademarks",
  "warrant",
  "warrants",
  "warranties",
  "warrantee",
  "warranty",
  NULL
  };


#if 0
/* Dictionaries, thesauruses, and wordlists will match key words but not
   be licenses.  This list contains words that are used to identify
   generic wordlists. */
tokentype BadWordList[256]; /* 256 should be >= elements in BadWords */
char *BadWords[] =
  {
  NULL, /* disable badword list... */
  /* disabled because too many words matched (2 bytes for a token has too
     few unique combinations). */
  "aardvark",
  "aborigin",
  "apparition",
  "clairvoyan",
  "consecrat",
  "corpuscle",
  "cursive",
  "exhuberan",
  "genuflect",
  "kidney",
  "syncopat",
  "viscera",
  "xylopho",
  NULL
  };
#endif

/* A valid block must be within range of a GoodWord token. */
#define GOODWORDDIST	400
  /** About GOODWORDDIST...
      Bigger numbers = slower processing and more "Ambiguous" matches.
      Smaller numbers = more false-negative matches.
   **/

/*********************************************
 WordCheckInit(): Initialize word lists.
 *********************************************/
void	WordCheckInit	()
{
  int i;

  /* setup word lists */
  for(i=0; GoodWords[i] != NULL; i++)
    {
    GoodWordList[i] = StringToToken(GoodWords[i],strlen(GoodWords[i]));
    }
#if 0
  for(i=0; BadWords[i] != NULL; i++)
    {
    BadWordList[i] = StringToToken(BadWords[i],strlen(BadWords[i]));
    }
#endif
} /* WordCheckInit() */

/*********************************************
 GetGoodWordRange(): Identify the first range of
 tokens that are in range of a good word.
 Returns: 0=no good range, 1=range set!
 *********************************************/
int	GetGoodWordRange	(tokentype *Token, int TokenCount,
				 fileoffset *Start, fileoffset *End)
{
  int w; /* word index */
  int t; /* token index */
  int StopFlag;

  /* idiot checking */
  if (TokenCount <= 0) return(0);

  /* Find the first GoodWord token */
  StopFlag=0;
  for(t=0; (t<TokenCount) && !StopFlag; t++)
    {
    for(w=0; (GoodWords[w] != NULL) && !StopFlag; w++)
      { if (Token[t] == GoodWordList[w]) StopFlag=1; }
    }
  if (StopFlag)
    {
    /* found a word -- set the minimal range */
    *Start = Max(0,t-GOODWORDDIST);
    *End = TokenCount; /* Min(TokenCount,t+GOODWORDDIST); */
    if (*End == TokenCount)
	{
	return(1); /* all done! (no range to check) */
	}
    if (Verbose > 1)
      fprintf(stderr,"First word: %s at %d ; range is at least: %u - %u\n",
		GoodWords[w],t,*Start,*End);
    }
  else
    {
    /* No good words?  Abort! */
    *Start = *End = 0;
    return(0);
    }

#if 0
  /* For each block of tokens at the limit of the range, find the
     furthest good word token.
     Stop if there is no good word within range. */
  /** t already set at the start **/
  for(t++; (t < *End); t++)
    {
    StopFlag=0;
    for(w=0; (GoodWords[w] != NULL) && !StopFlag; w++)
      { if (Token[t] == GoodWordList[w]) StopFlag=1; }
    if (StopFlag)
      {
      StopFlag=0; /* reset flag */
      /* found a good word!  Move *End out further! */
      *End = Min(TokenCount,t+GOODWORDDIST);
      if (Verbose > 1)
        fprintf(stderr,"New word: %s at %d ; range is at least: %d - %d\n",
	  GoodWords[w],t,*Start,*End);
      if (*End == TokenCount) return(1); /* all done! (no range to check) */
      }
    }
#else
  /* Go until the end of the tokens. */
#endif

  /* check for max range */
  if (*End - *Start > MAX_TOKEN)
    {
    *End = *Start + MAX_TOKEN;
    }

  /* Ok, range is set! */
  return(1);
} /* GetGoodWordRange() */

/*********************************************
 GetOrList(): Generate the list of OR words.
 Returns number of unique OR words found.
 Also sets OR array.
 *********************************************/
int	GetOrList	(tokentype *Token, int TokenCount)
{
  int i,j;
  int TotalOr;

#if 0
  int TotalBad=0;
  /* first check for bad words */
  for(i=0; BadWords[i] != NULL; i++)
    {
    for(j=0; j<TokenCount; j++)
      {
      if (BadWordList[i] == Token[j])
	{
	TotalBad++; /* not valid */
	if (TotalBad > 2) return(0); /* too many bad words */
	}
      }
    }
#endif

  /* reset OR list */
  for(i=0; GoodWords[i] != NULL; i++)
	GoodWordListOR[i]=0;

  /* first check for good words */
  TotalOr=0;
  for(i=0; GoodWords[i] != NULL; i++)
    {
    for(j=0; j<TokenCount; j++)
      {
      if ((GoodWordListOR[i] == 0) && (GoodWordList[i] == Token[j]))
	{
	GoodWordListOR[i] = 1;
	TotalOr++;
	}
      }
    }
  return(TotalOr);
} /* GetOrList() */

