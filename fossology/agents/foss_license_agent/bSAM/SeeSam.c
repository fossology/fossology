/***************************************************************
 SeeSam: Better, faster, stronger SAM log processor.

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

 ========
 SeeSam is a debug tool for viewing the output from bsam-engine.
 ***************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <fcntl.h>
#include <sys/mman.h>

enum SHOW
  {
  SHOW_XML,
  SHOW_FUNCTION,
  SHOW_FILE
  };
typedef enum SHOW SHOW;

/***************************************************
 SAM Data record.
 Each record contains two components: A & B (or src and cmp).
 They are stored as an array so we can easily swap A/B values.
 This is needed for generating a recursive association tree.
 ***************************************************/
struct SAMDAT
  {
  char *Name[2];  /* file name */
  int NameLen[2]; /* length of file name */

  char *Func[2];  /* function name */
  int FuncLen[2]; /* length of function name */

  int Tokens[2];   /* number of tokens in function */
  int AxBtokens;   /* number of tokens in both */
  float OnPercent[2];	/* AxBtokens/Tokens */

  int Block[2][2]; /* Start and End offsets into file for original candidate */

  int ProcessFlag;	/* has this been processed before? (0=No) */
  int ProcessFlagDir;	/* was A processed (0), or B (1)? */
  };
typedef struct SAMDAT SAMDAT;

#define Min(a,b)	((a) < (b) ? (a) : (b))

/*** GLOBALS (for speed) ***/
int Verbose=0; /* debugging */
int RecMax;
SAMDAT *Rec;
int MinTokens=0;
int MinFunction=0;
int MaxLink=0;
float MinRange=100;
float Ambiguous=0;
int ResultFormat=SHOW_FUNCTION;

int ShowFirst=-1;	/* Should it show the first record? */

/*****************************************************************/
/*****************************************************************/
/*****************************************************************/

unsigned char * Mmap = NULL;  /* mmap */
int     MmapSize=0;     /* size of mmap */

/**********************************************
 OpenFile(): Open and mmap a file.
 Returns file handle, or -1 on failure.
 Sets global Mmap values.
 **********************************************/
int     OpenFile     (char *Fname)
{
  int F;
  struct stat Stat;

  /* open the file (memory map) */
  F = open(Fname,O_RDONLY);
  if (F == -1)
        {
        fprintf(stderr,"ERROR: Unable to open file (%s)\n",Fname);
        exit(-1);
        }
  if (fstat(F,&Stat) == -1)
        {
        fprintf(stderr,"ERROR: Unable to stat file (%s)\n",Fname);
        exit(-1);
        }
  MmapSize = Stat.st_size;
  Mmap = mmap(0,MmapSize,PROT_READ,MAP_PRIVATE,F,0);
  if (Mmap == MAP_FAILED)
        {
        fprintf(stderr,"ERROR: Unable to mmap file (%s)\n",Fname);
        exit(-1);
        }
  return(F);
} /* OpenFile() */

/**********************************************
 CloseFile(): Close a filename.
 **********************************************/
void    CloseFile    (int FileHandle)
{
  if (MmapSize > 0) munmap(Mmap,MmapSize);
  MmapSize = 0;
  Mmap = NULL;
  close(FileHandle);
} /* CloseFile() */

/*****************************************************************/
/*****************************************************************/
/*****************************************************************/

/**********************************************
 CountRecords(): how many log entries are there?
 Assumes mmap is loaded.
 Returns number of records.
 **********************************************/
int	CountRecords	()
{
  int i;
  static char Match[]="***** MATCHED *****";
  int Count=0;

  if (MmapSize < strlen(Match)) return(0);
  /* If MmapSize == 0, then the FOR will continue!
     This is because everything gets converted to an unsigned int. */
  for(i=0; i < MmapSize - strlen(Match)-1; i++)
    {
    if ((Mmap[i]=='*') && !strncmp((char *)(Mmap+i),Match,strlen(Match)))
	{
	Count++;
	}
    }
  return(Count);
} /* CountRecords() */

/**********************************************
 DebugRecord(): Display a full record (for debugging).
 **********************************************/
void	DebugRecord	(int r)
{
  printf("=== Record %d ===\n",r);
  printf("  Aname=%.*s\n",Rec[r].NameLen[0],Rec[r].Name[0]);
  printf("  Afunc=%.*s (%d : %.0f)\n",Rec[r].FuncLen[0],Rec[r].Func[0],
  	Rec[r].Tokens[0],Rec[r].OnPercent[0]);
  printf("  Bname=%.*s\n",Rec[r].NameLen[1],Rec[r].Name[1]);
  printf("  Bfunc=%.*s (%d : %.0f)\n",Rec[r].FuncLen[1],Rec[r].Func[1],
  	Rec[r].Tokens[1],Rec[r].OnPercent[1]);
  printf("  ProcessFlag = %d  Dir=%d\n",Rec[r].ProcessFlag,Rec[r].ProcessFlagDir);
  printf("\n");
} /* DebugRecord() */

/**********************************************
 SaveFunctionOffsets(): Give a string in the format
   "name (0x1,0x2)"
 Save the start and end offsets (0x1 and 0x2 respectively).
 Sets length of string without the offsets.
 **********************************************/
void	SaveFunctionOffsets	(int r, int AB)
{
  char *String;
  int StringLen;
  int i;

  /* init */
  String = Rec[r].Func[AB];
  StringLen = Rec[r].FuncLen[AB];
  Rec[r].Block[AB][0] = 0;
  Rec[r].Block[AB][1] = 0;

  /* idiot checking */
  if (StringLen < 1) return;
  if (String[StringLen-1] != ')') return;

  /* scan backwards */
  i=StringLen-2;
  while((i > 0) && (isxdigit(String[i]) || strchr("x,",String[i])))
	i--;

  if (i < 1) return;
  if (String[i] != '(') return;
  if (String[i-1] != ' ') return;

  /* save the number! */
  sscanf(String+i,"(0x%x,0x%x)",&(Rec[r].Block[AB][0]),&(Rec[r].Block[AB][1]));
  i--;
  Rec[r].FuncLen[AB] = i;
} /* SaveFunctionOffsets() */

/**********************************************
 LoadRecords(): Populate an index for records.
 Assumes mmap is loaded.
 Returns: # records loaded, or -1 on error.
 **********************************************/
int	LoadRecords	(int RecMax, SAMDAT *Rec)
{
  int i;
  int Len;
  static char Match[]="***** MATCHED *****";
  int Count=0;

  for(i=0; (i < MmapSize - strlen(Match)-1) && (Count < RecMax); i++)
    {
    if ((Mmap[i]=='*') && !strncmp((char *)(Mmap+i),Match,strlen(Match)))
	{
	/* Ok, we found a record.  Now populate it */

	/* skip to the first record */
	while((i<MmapSize) && (Mmap[i] != '\n')) i++;
	i++;

	/* Load the A file name */
	i += 4; /* skip the heading */
	if (i >= MmapSize) return(Count);
	Rec[Count].Name[0] = (char *)(Mmap+i);
	for(Len=0; (Len+i < MmapSize) && (Mmap[Len+i] != '\n'); Len++) ;
	Rec[Count].NameLen[0] = Len;
	i += Len+1; /* skip name and newline */

	/* Load the A function name */
	i += 4; /* skip the heading */
	if (i >= MmapSize) return(Count);
	Rec[Count].Func[0] = (char *)(Mmap+i);
	for(Len=0; (Len+i < MmapSize) && (Mmap[Len+i] != '\n'); Len++) ;
	Rec[Count].FuncLen[0] = Len;
	i += Len+1; /* skip name and newline */
	SaveFunctionOffsets(Count,0);

	/* Load the B file name */
	i += 4; /* skip the heading */
	if (i >= MmapSize) return(Count);
	Rec[Count].Name[1] = (char *)(Mmap+i);
	for(Len=0; (Len+i < MmapSize) && (Mmap[Len+i] != '\n'); Len++) ;
	Rec[Count].NameLen[1] = Len;
	i += Len+1; /* skip name and newline */

	/* Load the B function name */
	i += 4; /* skip the heading */
	if (i >= MmapSize) return(Count);
	Rec[Count].Func[1] = (char *)(Mmap+i);
	for(Len=0; (Len+i < MmapSize) && (Mmap[Len+i] != '\n'); Len++) ;
	Rec[Count].FuncLen[1] = Len;
	i += Len+1; /* skip name and newline */
	SaveFunctionOffsets(Count,1);

	/* Skip Atotal */
	while((i<MmapSize) && (Mmap[i] != '\n')) i++;
	i++;
	/* Skip Btotal */
	while((i<MmapSize) && (Mmap[i] != '\n')) i++;
	i++;

	/* Load the A token size */
	i += 6;
	if (i >= MmapSize) return(Count);
	Rec[Count].Tokens[0] = 0;
	while((i<MmapSize) && isdigit(Mmap[i]))
		{
		Rec[Count].Tokens[0] = Rec[Count].Tokens[0]*10 + Mmap[i]-'0';
		i++;
		}
	i++;	/* skip CR */

	/* Load the B token size */
	i += 6;
	if (i >= MmapSize) return(Count);
	Rec[Count].Tokens[1] = 0;
	while((i<MmapSize) && isdigit(Mmap[i]))
		{
		Rec[Count].Tokens[1] = Rec[Count].Tokens[1]*10 + Mmap[i]-'0';
		i++;
		}
	i++;	/* skip CR */

	/* Load the AxB token size */
	i += 11;
	if (i >= MmapSize) return(Count);
	Rec[Count].AxBtokens = 0;
	while((i<MmapSize) && isdigit(Mmap[i]))
		{
		Rec[Count].AxBtokens = Rec[Count].AxBtokens*10 + Mmap[i]-'0';
		i++;
		}
	i++;	/* skip CR */

	/* compute percents (faster than reading) */
	Rec[Count].OnPercent[0] = (float)(Rec[Count].AxBtokens)/(float)(Rec[Count].Tokens[0])*100.0;
	Rec[Count].OnPercent[1] = (float)(Rec[Count].AxBtokens)/(float)(Rec[Count].Tokens[1])*100.0;

	Rec[Count].ProcessFlag = 0;
	Rec[Count].ProcessFlagDir = 0;

	/* debug! */
	if (Verbose)
	  {
	  fprintf(stderr,"A=%.*s (%.*s) = (%d | %f)\n",
		Rec[Count].NameLen[0],Rec[Count].Name[0],
		Rec[Count].FuncLen[0],Rec[Count].Func[0],
		Rec[Count].Tokens[0],Rec[Count].OnPercent[0]);
	  fprintf(stderr,"B=%.*s (%.*s) = (%d | %f)\n",
		Rec[Count].NameLen[1],Rec[Count].Name[1],
		Rec[Count].FuncLen[1],Rec[Count].Func[1],
		Rec[Count].Tokens[1],Rec[Count].OnPercent[1]);
	  fprintf(stderr,"\n");
	  }

	/* next record! */
	Count++;
	}
    }
  return(Count);
} /* LoadRecords() */

/*******************************************
 CompRecords(): Yes, a sorting function.
 For use with qsort().
 *******************************************/
int	CompRecords	(const void *vA, const void *vB)
{
  SAMDAT *A, *B;
  int rc;

  A = (SAMDAT *)vA;
  B = (SAMDAT *)vB;

  /* null cases */
  if (!A && !B) return(0);
  if (!A) return(1);
  if (!B) return(-1);

  /* ordering */
  rc = strncmp(A->Name[0],B->Name[0],1+Min(A->NameLen[0],B->NameLen[0]));
  if (rc != 0) return (rc);
  rc = A->NameLen[0] - B->NameLen[0];
  if (rc != 0) return (rc);
  rc = strncmp(A->Func[0],B->Func[0],1+Min(A->FuncLen[0],B->FuncLen[0]));
  if (rc != 0) return (rc);
  rc = A->FuncLen[0] - B->FuncLen[0];
  if (rc != 0) return (rc);

  rc = strncmp(A->Name[1],B->Name[1],1+Min(A->NameLen[1],B->NameLen[1]));
  if (rc != 0) return (rc);
  rc = A->NameLen[1] - B->NameLen[1];
  if (rc != 0) return (rc);
  rc = strncmp(A->Func[1],B->Func[1],1+Min(A->FuncLen[1],B->FuncLen[1]));
  if (rc != 0) return (rc);
  rc = A->FuncLen[1] - B->FuncLen[1];
  if (rc != 0) return (rc);

  return(0);
} /* CompRecords() */

/*******************************************
 FileStatsRecord(): Summarize file-based match.
 r = record number to compare against.
 AB = 0 for [0]=A and [1]=B
 AB = 1 for [0]=B and [1]=A
 *******************************************/
void	FileStatsRecord	(int r, int AB, int *TotalTok, int *TotalFile,
			 float *TotalPercent)
{
  int i;

  *TotalTok = 0;
  *TotalFile = 0;
  *TotalPercent = 0;

  for(i=0; i<RecMax; i++)
    {
    /* only look at the right items */
    if (Rec[i].ProcessFlag != Rec[r].ProcessFlag) continue;

    /* find all records that match */
    if ((Rec[i].NameLen[0] == Rec[r].NameLen[0]) &&
	(Rec[i].NameLen[1] == Rec[r].NameLen[1]))
	{
	if (strncmp(Rec[r].Name[0],Rec[i].Name[0],Rec[r].NameLen[0])) continue;
	if (strncmp(Rec[r].Name[1],Rec[i].Name[1],Rec[r].NameLen[1])) continue;

	/* ok: both records refer to the same function match */
	*TotalFile += 1;
	*TotalTok += Rec[i].Tokens[!AB];
	*TotalPercent += Rec[i].OnPercent[!AB];
	}
    }
  *TotalPercent = *TotalPercent / *TotalFile;
} /* FileStatsRecord() */

/*******************************************
 TaintToXML(): Given a string, print an XML-safe string.
 *******************************************/
void	TaintToXML	(char *S, int Slen)
{
  int i;
  for(i=0; i<Slen; i++)
    {
    switch(S[i])
      {
      case '&': fputs("&amp;",stdout);	break;
      case '<': fputs("&lt;",stdout);	break;
      case '>': fputs("&gt;",stdout);	break;
      case '"': fputs("&quot;",stdout);	break;
      default:	fputc(S[i],stdout);	break;
      }
    }
} /* TaintToXML() */

/*******************************************
 ShowXML(): Print the record name with nice indent.
 *******************************************/
void	ShowXML	(int r, int AB, int Depth, int AmbiguousFlag, int IncludeText)
{
  if (!strncmp("Phrase: ",Rec[r].Name[AB],8))
    {
    fputs("<item phrase=\"",stdout);
    }
  else
    {
    fputs("<item source=\"",stdout);
    TaintToXML(Rec[r].Name[AB],Rec[r].NameLen[AB]);
    fputs("\"",stdout);
    fputs(" section=\"",stdout);
    }
  TaintToXML(Rec[r].Func[AB],Rec[r].FuncLen[AB]);
  fputs("\"",stdout);

  if (AmbiguousFlag) fputs(" ambiguous=\"1\"",stdout);

  fprintf(stdout," tokens=\"%d\"",Rec[r].Tokens[AB]);
  if (Depth > 0)
    {
    if (Rec[r].Block[!AB][0] != Rec[r].Block[!AB][1])
      {
      fprintf(stdout," sectionstartA=\"%d\" sectionendA=\"%d\"",
	Rec[r].Block[!AB][0],Rec[r].Block[!AB][1]);
      }
    if (Rec[r].Block[AB][0] != Rec[r].Block[AB][1])
      {
      fprintf(stdout," sectionstartB=\"%d\" sectionendB=\"%d\"",
	Rec[r].Block[AB][0],Rec[r].Block[AB][1]);
      }
    fprintf(stdout," match=\"%d\"",Rec[r].AxBtokens);
    fprintf(stdout," match_AB=\"%.2f%%\"",Rec[r].OnPercent[!AB]);
    fprintf(stdout," match_BA=\"%.2f%%\"",Rec[r].OnPercent[AB]);
    }

  fputs(">\n",stdout);
} /* ShowXML() */

/*******************************************
 ShowRecord(): Print the record name with nice indent.
 *******************************************/
void	ShowRecord	(int r, int AB, int Depth, int AmbiguousFlag)
{
  int j;

  if (Depth > 0)
	{
	/* indent result */
	for(j=0; j<Depth; j++) fputs("  ",stdout);
	fputs("| ",stdout);
	}
  if (AmbiguousFlag) printf("Ambiguous:");
  fwrite(Rec[r].Name[AB],Rec[r].NameLen[AB],1,stdout);

  if (ResultFormat == SHOW_FUNCTION)
    {
    fputs(" ",stdout);
    fwrite(Rec[r].Func[AB],Rec[r].FuncLen[AB],1,stdout);
    if (Rec[r].Block[AB][0] != Rec[r].Block[AB][1])
      {
      fprintf(stdout," (0x%x,0x%x)",Rec[r].Block[AB][0],Rec[r].Block[AB][1]);
      }
    }
} /* ShowRecord() */

/*******************************************
 ProcessRecords(): Access and chain a list of
 records.
 Sends results to stdout.
 This uses ProcessFlag as a state machine:
   0 = never seen before
   1 = seen and should recurse only once (break infinite loop)
   2 = don't show!
   >= 10 = seen and should recurse (infinite loop)
 r = record to process
 AB = which side of the record to process
 Depth & MaxDepth = how for to indent
  Returns: 1=something printed, 0=nothing printed.
 *******************************************/
int	ProcessRecords	(int Start, int r, int AB, int Depth, int MaxDepth)
{
  int i;
  int MatchFlag=0;
  int rc=0;

  /* check if record needs to be displayed */
  if (Rec[r].ProcessFlag == 2)	return(0);

  /* display the record */
  if (ResultFormat == SHOW_FUNCTION)
	{
	rc=1;
	ShowRecord(r,AB,Depth,0);
	if (Depth == 0)
	  {
	  /* show parent */
	  printf("(%d)\n",Rec[r].Tokens[AB]);
	  }
	else
	  {
	  /* show relations */
	  printf("(%d;%.2f%%)\n",Rec[r].Tokens[AB],Rec[r].OnPercent[AB]);
	  }
	}
  else if (ResultFormat == SHOW_FILE)
	{
	if (Depth != 0)
	  {
	  int TotalTok=0;
	  int TotalFile=0;
	  float TotalPercent=0;
	  float BestPercent=0;
	  float LocalBestPercent=0;

	  /* compute best match against all files */
	  for(i=Start; i<RecMax; i++)
	    {
	    if ((Rec[r].NameLen[!AB] == Rec[i].NameLen[AB]) &&
	        !strncmp(Rec[r].Name[!AB],Rec[i].Name[AB],Rec[r].NameLen[!AB]))
		{
		if (BestPercent < Rec[i].OnPercent[!AB])
			BestPercent = Rec[i].OnPercent[!AB];
		}
	    else if ((Rec[r].NameLen[!AB] == Rec[i].NameLen[!AB]) &&
	        !strncmp(Rec[r].Name[!AB],Rec[i].Name[!AB],Rec[r].NameLen[!AB]))
		{
		if (BestPercent < Rec[i].OnPercent[AB])
			BestPercent = Rec[i].OnPercent[AB];
		}

	    /* compute average matches per file */
	    if ((Rec[r].NameLen[AB] == Rec[i].NameLen[AB]) &&
		(Rec[r].NameLen[!AB] == Rec[i].NameLen[!AB]) &&
	        !strncmp(Rec[r].Name[AB],Rec[i].Name[AB],Rec[r].NameLen[AB]) &&
		!strncmp(Rec[r].Name[!AB],Rec[i].Name[!AB],Rec[r].NameLen[!AB]))
		{
	        TotalFile++;
	        TotalTok += Rec[i].Tokens[AB];
	        TotalPercent += Rec[i].OnPercent[AB];
		Rec[i].ProcessFlag=2;
		if (LocalBestPercent < Rec[i].OnPercent[AB])
			LocalBestPercent = Rec[i].OnPercent[AB];
		}
	    else if ((Rec[r].NameLen[AB] == Rec[i].NameLen[!AB]) &&
		(Rec[r].NameLen[!AB] == Rec[i].NameLen[AB]) &&
	        !strncmp(Rec[r].Name[AB],Rec[i].Name[!AB],Rec[r].NameLen[AB]) &&
		!strncmp(Rec[r].Name[!AB],Rec[i].Name[AB],Rec[r].NameLen[!AB]))
		{
	        TotalFile++;
	        TotalTok += Rec[i].Tokens[!AB];
	        TotalPercent += Rec[i].OnPercent[!AB];
		Rec[i].ProcessFlag=2;
		if (LocalBestPercent < Rec[i].OnPercent[!AB])
			LocalBestPercent = Rec[i].OnPercent[!AB];
		}
	    }
	  if (TotalFile > 0) TotalPercent = TotalPercent / TotalFile;

#if 0
	  printf("DEBUG: %.*s : %f >= %f + %f\n",
		Rec[r].NameLen[AB],Rec[r].Name[AB],
		BestPercent,LocalBestPercent,MinRange);
#endif

	  /* skip if too few matches */
	  if ((TotalTok >= MinTokens) &&
	      (TotalFile >= MinFunction) &&
	      (BestPercent <= LocalBestPercent + MinRange))
		{
	  	/* show relations */
		rc=1;
		if (ShowFirst >= 0)
		  {
		  ShowRecord(ShowFirst,!AB,Depth-1,0);
		  fputc('\n',stdout);
		  ShowFirst=-1;
		  }
	  	ShowRecord(r,AB,Depth,BestPercent < Ambiguous);
	  	printf(" (%d : %d : %.2f%%)\n",TotalFile,TotalTok,TotalPercent);
		}
	  else return(0);
	  } /* if Depth != 0 */
	}
  else if (ResultFormat == SHOW_XML)
	{
	if (Depth != 0)
	  {
	  int TotalTok=0;
	  int TotalFile=0;
	  float TotalPercent=0;
	  float BestPercent=0;
	  float LocalBestPercent=0;

	  /* compute best match against all files */
	  for(i=Start; i<RecMax; i++)
	    {
	    if ((Rec[r].NameLen[!AB] == Rec[i].NameLen[AB]) &&
	        !strncmp(Rec[r].Name[!AB],Rec[i].Name[AB],Rec[r].NameLen[!AB]))
		{
		if (BestPercent < Rec[i].OnPercent[!AB])
			BestPercent = Rec[i].OnPercent[!AB];
		}
	    else if ((Rec[r].NameLen[!AB] == Rec[i].NameLen[!AB]) &&
	        !strncmp(Rec[r].Name[!AB],Rec[i].Name[!AB],Rec[r].NameLen[!AB]))
		{
		if (BestPercent < Rec[i].OnPercent[AB])
			BestPercent = Rec[i].OnPercent[AB];
		}

	    /* compute average matches per file */
	    if ((Rec[r].NameLen[AB] == Rec[i].NameLen[AB]) &&
		(Rec[r].NameLen[!AB] == Rec[i].NameLen[!AB]) &&
	        !strncmp(Rec[r].Name[AB],Rec[i].Name[AB],Rec[r].NameLen[AB]) &&
		!strncmp(Rec[r].Name[!AB],Rec[i].Name[!AB],Rec[r].NameLen[!AB]))
		{
	        TotalFile++;
	        TotalTok += Rec[i].Tokens[AB];
	        TotalPercent += Rec[i].OnPercent[AB];
		Rec[i].ProcessFlag=2;
		if (LocalBestPercent < Rec[i].OnPercent[AB])
			LocalBestPercent = Rec[i].OnPercent[AB];
		}
	    else if ((Rec[r].NameLen[AB] == Rec[i].NameLen[!AB]) &&
		(Rec[r].NameLen[!AB] == Rec[i].NameLen[AB]) &&
	        !strncmp(Rec[r].Name[AB],Rec[i].Name[!AB],Rec[r].NameLen[AB]) &&
		!strncmp(Rec[r].Name[!AB],Rec[i].Name[AB],Rec[r].NameLen[!AB]))
		{
	        TotalFile++;
	        TotalTok += Rec[i].Tokens[!AB];
	        TotalPercent += Rec[i].OnPercent[!AB];
		Rec[i].ProcessFlag=2;
		if (LocalBestPercent < Rec[i].OnPercent[!AB])
			LocalBestPercent = Rec[i].OnPercent[!AB];
		}
	    }
	  if (TotalFile > 0) TotalPercent = TotalPercent / TotalFile;

	  /* skip if too few matches */
	  if ((TotalTok >= MinTokens) &&
	      (TotalFile >= MinFunction) &&
	      (BestPercent <= LocalBestPercent + MinRange))
		{
	  	/* show relations */
		rc=1;
		if (ShowFirst >= 0)
		  {
		  ShowXML(ShowFirst,!AB,Depth-1,0,0);
		  ShowFirst=-1;
		  }
	  	else ShowXML(r,AB,Depth,BestPercent < Ambiguous,0);
		}
	  else return(0);
	  } /* if Depth != 0 */
	else
	  { /* if Depth == 0 */
	  rc=1;
	  ShowXML(r,AB,Depth,0,1);
	  }
	}


  /* see if it needs to be processed */
  if ((Rec[r].ProcessFlag >= 10) /* already done! */ ||
      ((MaxDepth >=0) && (Depth > MaxDepth))) /* too deep */
	{
	if (rc && (ResultFormat == SHOW_XML)) printf("</item>\n");
	return(rc);
	}

  /* Mark this record as being processed */
  if (Rec[r].ProcessFlag == 0) Rec[r].ProcessFlag = r+10;

  /* Mark all records at this depth */
  for(i=Start; i<RecMax; i++)
    {
    if ((Rec[i].ProcessFlag != 0) && (Rec[i].ProcessFlag != r+10))
	continue; /* skip */

    /* Related records will have the same Name[0] and Func[0] */
    if (Rec[r].NameLen[AB] != Rec[i].NameLen[AB]) continue;
    if (strncmp(Rec[r].Name[AB],Rec[i].Name[AB],Rec[r].NameLen[AB])) continue;
    if (ResultFormat == SHOW_FUNCTION)
      {
      if (Rec[r].FuncLen[AB] != Rec[i].FuncLen[AB]) continue;
      if (strncmp(Rec[r].Func[AB],Rec[i].Func[AB],Rec[r].FuncLen[AB])) continue;
      }

    /* Ok, they are related, mark it for processing */
    Rec[i].ProcessFlag = r+10; /* unique ID */
    Rec[i].ProcessFlagDir = AB;
    MatchFlag++;
    }

  /* display sub-records */
  if (MatchFlag == 0) return(rc);
  Depth++;
  for(i=Start; i<RecMax; i++)
	{
	if (Rec[i].ProcessFlag != r+10)	continue; /* wrong entry */
	if (Rec[i].ProcessFlagDir != AB) continue; /* wrong entry */
	if (i==r) Rec[i].ProcessFlag = 1; /* parent: don't infinite recurse */
	/* recurse! */
	rc = rc | ProcessRecords(Start,i,!AB,Depth,MaxDepth);
	}

  if (rc && (ResultFormat == SHOW_XML)) printf("</item>\n");
  return(rc);
} /* ProcessRecords() */

/*****************************************************************/
/*****************************************************************/
/*****************************************************************/

void	Usage	(char *Name)
{
  printf("Usage: %s [options] file\n",Name);
  printf("  -t f :: display matches at the function level (default)\n");
  printf("  -t F :: display matches at the file level\n");
  printf("  -t X :: display matches at the file level with XML\n");
  printf("  -L # :: specify depth of linking (0=nolink, -1=infinite; default=0)\n");
  printf("  -F # :: specify minimum number of functions to match (default: 0)\n");
  printf("  -T # :: specify minimum number of tokens to match (default: 0)\n");
  printf("  -P # :: specify percent range to display (default: 100)\n");
  printf("            0 = only show best match(es)\n");
  printf("          100 = show anything within 100%% of best match (i.e., all)\n");
  printf("  -A # :: percent cutoff for denoting ambiguious license (default: 0)\n");
  printf("  -v   :: debug/verbose (-v -v = more verbose)\n");
} /* Usage() */

/*********************************************************************/
int	main	(int argc, char *argv[])
{
  int Fin;
  int c;
  int rc;

  while((c = getopt(argc,argv,"A:F:L:P:T:t:v")) != -1)
    {
    switch(c)
	{
	case 'A':
		Ambiguous = atof(optarg);
		break;
	case 'F':
		MinFunction = atoi(optarg);
		break;
	case 'L':
		MaxLink = atoi(optarg);
		break;
	case 'P':
		MinRange = atof(optarg);
		break;
	case 'T':
		MinTokens = atoi(optarg);
		break;
	case 't':
		if (optarg[0]=='f')	ResultFormat=SHOW_FUNCTION;
		else if (optarg[0]=='F')	ResultFormat=SHOW_FILE;
		else if (optarg[0]=='X')	ResultFormat=SHOW_XML;
		else
			{
			Usage(argv[0]);
			exit(-1);
			}
		break;
	case 'v':
		Verbose++;
		break;
	default:
		Usage(argv[0]);
		exit(-1);
	}
    } /* getopt() */

  if (optind != argc-1)
	{
	Usage(argv[0]);
	exit(-1);
	}

  /*** Load data file ***/
  Fin = OpenFile(argv[optind]);
  RecMax = CountRecords();

  if (Verbose)
    {
    fprintf(stderr,"Total records: %d (array = %ld bytes)\n",
      RecMax,(long)(RecMax*sizeof(SAMDAT)));
    }

  if (RecMax < 1) goto Cleanup;  /* nothing to do */
  Rec = (SAMDAT *)calloc(sizeof(SAMDAT),RecMax);
  if (!Rec)
	{
	fprintf(stderr,"ERROR: Unable to allocate memory.\n");
	exit(-1);
	}
  rc = LoadRecords(RecMax,Rec);
  if (rc != RecMax)
	{
	fprintf(stderr,"ERROR: Unable to load all records. %d out of %d\n",
		rc,RecMax);
	/* let it continue with the records it has loaded. */
	RecMax = rc;
	}

  qsort(Rec,RecMax,sizeof(SAMDAT),CompRecords);

  /*** Process data file ***/
  if (ResultFormat==SHOW_XML) printf("<xml>\n");
  for(c=0; c<RecMax; c++)
    {
    if (Verbose)
      {
      fprintf(stderr,"%.*s (%.*s) :: %.*s (%.*s)\n",
	Rec[c].NameLen[0],Rec[c].Name[0],
	Rec[c].FuncLen[0],Rec[c].Func[0],
	Rec[c].NameLen[1],Rec[c].Name[1],
	Rec[c].FuncLen[1],Rec[c].Func[1]);
      }
#if 0
    printf("=== DEBUG TOP===\n");
    DebugRecord(c);
#endif
    if (Rec[c].ProcessFlag == 0)
	{
	if (ResultFormat==SHOW_FILE) ShowFirst=c;
	else ShowFirst=-1;
	if (ProcessRecords(c,c,0,0,MaxLink) && (ResultFormat!=SHOW_XML))
		printf("\n");
	Rec[c].ProcessFlag = 2;
	}
    } /* for each record */
  if (ResultFormat==SHOW_XML) printf("</xml>\n");

  /*** clean up ***/
Cleanup:
  free(Rec);
  RecMax=0;
  CloseFile(Fin);
  return(0);
} /* main() */

