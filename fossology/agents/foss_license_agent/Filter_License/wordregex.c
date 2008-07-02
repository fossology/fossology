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
   "string"	match string
   "^string"	do NOT match string
   "string*"	match word beginning with string
   "*string"	match word ending with string
   "*string*"	match word containing with string
   "*^string*"	do NOT match word containing with string
   "*"		match exactly ONE entire word (same as %1)
   "string1|string1"	match string1 or string2
   "< ... >"	place matches in return string
   "\"		quote next character (only for start of match)
 **********************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include "wordregex.h"

uint32_t WR_Start,WR_End;

/**************************************************
 WR_Strstr(): Perform a strstr() operation WITH
 string lengths!
 Return looks like strstr().
 **************************************************/
char *	WR_Strstr	(char *S1, int S1len, char *S2, int S2len)
{
  int i;
  if (S2len <= 0) return(S1);
  if (!S1len && !S2len) return(S1);
  for(i=0; i <= S1len-S2len; i++)
    {
    if (!memcmp(S1,S2,S2len)) return(S1+i);
    }
  return(NULL);
} /* WR_Strstr() */


#ifdef MAIN
char *DebugSave;
#endif

/**************************************************
 WR_ProcessOR(): OR is a list of individual terms.
 Returns 1 if match, 0 if fails.
 **************************************************/
int	WR_ProcessOR	(char *String, int StringWordLen,
			 char *Regex, int RegexWordLen,
			 uint32_t Start)
{
  int rc;
  int SkipCount;
  char *SkipString;

  SkipString = (char *)calloc(1,strlen(Regex)+1);
  SkipCount=0;
  while((Regex[SkipCount] != '\0') && !isspace(Regex[SkipCount]))
	{ SkipCount++; }

  /* SkipCount = length of entire OR phrase: "A|B|C" = 5 */
  /* RegexWordLen = length of OR word: "A|B|C" = 2 */

  sprintf(SkipString,"%.*s%s",RegexWordLen,Regex,Regex+SkipCount);
  rc=WR_MatchString(1,String,SkipString,Start);
  if (!rc)
	{
	rc=WR_MatchString(1,String,Regex+RegexWordLen+1,Start);
	}
  free(SkipString);
  return(rc);
} /* WR_ProcessOR() */

/**************************************************
 WR_ProcessPercent(): Percent can match 0 or more words.
 Returns 1 if match, 0 if fails.
 **************************************************/
int	WR_ProcessPercent	(char *String, int StringWordLen,
				 char *Regex, int RegexWordLen,
				 uint32_t Start)
{
  int rc=0;
  int SkipCount;
  char *SkipString;

  /* check if % matches null */
  rc = WR_MatchString(1,String,Regex+RegexWordLen,Start);
  if (rc) return(rc);
  if (StringWordLen <= 0) return(0); /* misses */

  /* if a solitary '%' */
  if (RegexWordLen == 1)
	{
	/* infinite check */
	if (StringWordLen > 0)
	  {
	  rc = WR_MatchString(1,String,Regex,Start+StringWordLen);
	  }
	} /* if a stand-alone % */
  else if (isdigit(Regex[1])) /* if '%' + number */
    {
    /* parse word skip */
    SkipCount = atoi(Regex+1);
    if (SkipCount > 9999) SkipCount=9999; /* just for sanity; not required */
    SkipCount--;
    if (SkipCount >= 0)
	  {
	  SkipString = (char *)calloc(1,strlen(Regex)+1);
	  sprintf(SkipString,"%%%d%s",SkipCount,Regex+RegexWordLen);
	  rc=WR_MatchString(1,String,SkipString,Start+StringWordLen);
	  free(SkipString);
	  }
    } /* if % with number */

  return(rc);
} /* WR_ProcessPercent() */

/**************************************************
 WR_MatchString(): Parse a regex and see if it
 matches a string.
 Returns: 1=match, 0=miss
 THIS IS RECURSIVE.
  Initializing:
  * MatchFlag = 0 if not matching yet, 1=if matching
  * String must be null-terminated.
  * Start should be set for the beginning of where to look.
 **************************************************/
int	WR_MatchString	(int MatchFlag,
			 char *String, char *Regex, uint32_t Start)
{
  int StringWordLen;
  int RegexWordLen;
  int rc;
  int NotFlag;
  char *RegexOrig;

  RegexOrig=Regex;

  /* idiot checking (should never happen) */
  if (!String || !Regex) return(0); /* error */

Rescan: /* reduce cost from recursion */
  if (Regex[0]=='\0') return(1); /* empty regex misses */
  NotFlag=0;
  rc=0;

  /****************************************/
  /* space reductions */
  while((String[Start] != '\0') && isspace(String[Start])) { Start++; }
  /* find the length of the word to match */
  StringWordLen=0;
  while( (String[Start+StringWordLen] != '\0') &&
         !isspace(String[Start+StringWordLen]))
	{ StringWordLen++; }

  /****************************************/
RemoveSpacesRegex:
  /* space reductions */
  while((Regex[0] != '\0') && (isspace(Regex[0]) || (Regex[0]=='|')))
	{ Regex++; }
  /* base cases */
  if (Regex[0]=='\0') { return(1); } /* null matches */

  RegexWordLen=0;
  while( (Regex[RegexWordLen] != '\0') && !isspace(Regex[RegexWordLen]) &&
	 (Regex[RegexWordLen] != '|'))
	{ RegexWordLen++; }
  if (RegexWordLen == 0) { return(1); } /* null matches */

  if ((String[Start+StringWordLen] == '\0') && (Regex[0] != '%')) return(0);

  /* check for saving stuff */
  if (Regex[0]=='<')
    {
    WR_Start = Start;
    Regex++;
    goto RemoveSpacesRegex;
    }

  if (Regex[0]=='>')
    {
    Regex++;
    WR_End = Start;
    while((WR_End > 1) && isspace(String[WR_End-1])) WR_End--;
    goto RemoveSpacesRegex;
    }


  /****************************************/

  /****************************************/
  /** PROCESS REGEX ***********************/
  /****************************************/
  /* process word skips */
  if (Regex[0] == '%')
	{
	rc = WR_ProcessPercent(String,StringWordLen,Regex,RegexWordLen,Start);
	if (rc) return(rc);
	goto Endscan;
	}

  /****************************************/
  /* Still have regex and no string? Fail! */
  if (StringWordLen <= 0)	return(0); /* bad string */

  /****************************************/
  /* process OR tags */
  if (Regex[RegexWordLen] == '|')
	{
	rc = WR_ProcessOR(String,StringWordLen,Regex,RegexWordLen,Start);
	if (rc) return(rc);
	goto Endscan;
	}

  /****************************************/
  /* determine the type of Word */
  if (Regex[0] == '*') /* begins with '*' and match end */
    {
    /* handle NOT flags */
    if ((Regex[0] == '\\')) { Regex++; RegexWordLen--; }
    else if (!NotFlag && (Regex[0] == '^')) { NotFlag=1; }

    if (Regex[RegexWordLen-1] == '*') /* substr */
      {
      if ((WR_Strstr(String+Start,StringWordLen,Regex+1+NotFlag,RegexWordLen-2-NotFlag) != NULL) != NotFlag)
	{
	/* Matched! Recurse! */
	rc = (WR_MatchString(1,String,Regex+RegexWordLen,Start+StringWordLen));
	if (rc) return(rc);
	}
      } /* if substr */
    else /* if begins with */
	{
	if ((RegexWordLen <= StringWordLen) &&
	    (!strncmp(String+Start+StringWordLen-RegexWordLen+NotFlag,Regex+1,RegexWordLen-1-NotFlag) != NotFlag))
	  {
	  rc = (WR_MatchString(1,String,Regex+RegexWordLen,Start+StringWordLen));
	  if (rc) return(rc);
	  }
	}
    /* missed! */
    } /* if begins with '*' */

  else /* not begin with '*' */
    {
    if (!NotFlag && (Regex[0] == '^')) { NotFlag=1; Regex++; RegexWordLen--; }
    if ((Regex[0] == '\\')) { Regex++; RegexWordLen--; }
    if (RegexWordLen <= 0) return(NotFlag); /* never match */

    if (Regex[RegexWordLen-1] == '*') /* ends with */
      {
      /* no way to match */
      if ((RegexWordLen <= StringWordLen) &&
          (!memcmp(String+Start,Regex+NotFlag,RegexWordLen-1) != NotFlag))
        {
	/* Matched! Recurse! */
	rc = WR_MatchString(1,String,Regex+RegexWordLen,Start+StringWordLen);
	return(rc);
	}
      } /* if ends with '*' */
    else /* exact match (no wild cards) */
      {
      /* remove initial quoted chars */
      if ((RegexWordLen == StringWordLen) &&
          (!memcmp(String+Start,Regex+NotFlag,RegexWordLen-NotFlag) != NotFlag))
	  {
	  /* Matched! Recurse! */
	  rc = WR_MatchString(1,String,Regex+RegexWordLen,Start+StringWordLen);
	  return(rc);
	  }
      } /* if exact match */
    /* missed! */
    } /* if not begin with '*' */

  /* missed... check next word */
Endscan:
  if (!MatchFlag && (StringWordLen > 0))
    {
    /* it's the first term, so ok to skip */
    Start += StringWordLen;
    Regex = RegexOrig;
    goto Rescan;
    }
  return(NotFlag); /* missed! */
} /* WR_MatchString() */

/**************************************************
 WR_MatchString_Init(): Simple parent to WR_MatchString().
 **************************************************/
int	WR_MatchString_Init	(char *String, char *Regex, uint32_t Start)
{
  int rc;
  WR_Start = 0;
  WR_End = 0;
  rc=WR_MatchString(0,String,Regex,Start);
#if 0
  {
  uint32_t i;
  if (rc)
    {
    fprintf(stderr,"WR Check: '%s'\n",String);
    fprintf(stderr,"WR Match: '");
    for(i=WR_Start; i<WR_End; i++) fputc(String[i],stderr);
    fprintf(stderr,"'\n");
    }
  }
#endif
  return(rc);
} /* WR_MatchString_Init() */


/**************************************************
 WR_GetStartEnd(): Return the global match values.
 **************************************************/
void	WR_GetStartEnd	(uint32_t *Start, uint32_t *End)
{
  (*Start) = WR_Start;
  (*End) = WR_End;
} /* WR_GetStartEnd() */


#ifdef MAIN
/***************************************************************************/
/***************************************************************************/
/***************************************************************************/
int	main	(int argc, char *argv[])
{
  char Results[1024];
  uint32_t Start,End,i;

  memset(Results,'\0',sizeof(Results));
  DebugSave=Results;
  if (argc != 3)
    {
    printf("Usage: %s string regex\n",argv[0]);
    }
  if (WR_MatchString_Init(argv[1],argv[2],0))
    {
    WR_GetStartEnd(&Start,&End);
    printf("MATCH!  %d-%d::  '",WR_Start,WR_End);
    for(i=Start; i<End; i++) fputc(argv[1][i],stdout);
    printf("'\n");
    }
  else
    printf("MISS!\n");
  return(0);
} /* main() */
#endif

