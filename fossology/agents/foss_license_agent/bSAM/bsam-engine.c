/**************************************************************
 bSAM: Binary Symbolic Alignment Matrix
 
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

 =======
 Difference between SAM and bSAM:
 SAM is based on string comparisions.
 bSAM uses a binary data format.
 Each bSAM data chunk is in the following format:
 	- 2 bytes: length of function name
	- string: function name (null terminated!)
	- 4 bytes: length of function data
	- data: 2 bytes per data point.
 Each data point is the 16-bit sum of the original data string.

 =======
 About SAM:
 Fri May 27 10:54:02 MDT 2005
 SAM is based on the Protein Alignment Matrix (PAM) by Dayhoff, and the
 Program Alignment Matrix (pam) by Neal Krawetz.

 Schwartz, R.M. & Dayhoff, M.O. (1978) "Matrices for detecting distant
 relationships." In "Atlas of Protein Sequence and Structure, vol. 5,
 suppl. 3," M.O. Dayhoff (ed.), pp. 353-358, Natl. Biomed. Res. Found.,
 Washington, DC.

 Dayhoff, M. O., Schwartz, R. M. & Orcutt, B. C. (1978).  "A model of
 evolutionary change in proteins: matrices for detecting distant
 relationships."  In Atlas of protein sequence and structure, (Dayhoff, M.
 O., ed.), vol. 5, pp. 345-358. National biomedical research foundation
 Washington DC.

 This code uses the same concept behind PAM250 and other systems, with
 the following differences:

   - PAM only permitted 20 different elements (20 amino acids).
     pam extends PAM to support 256 different binary characters.
     SAM extends the pam concept to support arbitrary strings (symbols)
     instead of characters.

   - PAM was very inefficient: iterating 3 times through the matrix
     in order to set values.
     pam and SAM are efficient: they both iterate once through the matrix.
     PAM, pam, and SAM all generate the same matrix given the same data.

   - PAM used static array sizes.  PAM250 was limited to blocks of
     250 amino acid sequences.
     pam uses a segmented approach with a sliding window, permitting
     optimal alignment and fast processing for very large data files.
     SAM uses an allocated matrix, but does not segment data.  Thus, very
     large data files may result in very slow processing.

 Because the PAM algorithm is slow, this program has been extremely
 optimized in order to mitigate the impact from large loops.
 This program runs in O(n*m) where the files being compared are n and m
 elements.

 The basic 3-step algorithm:

 Step 1: Align symbols to compare in a matrix.
 For example, let's say the symbols are the letters in "hello" and "cheloe".
 These are usually called the "A" and "B" strings.
 The matrix is AxB.

 Step 2: Identify all the identical characters:
      c   h   e   l   o   e
   h  0   1   0   0   0   0
   e  0   0   1   0   0   1
   l  0   0   0   1   0   0
   l  0   0   0   1   0   0
   o  0   0   0   0   1   0

 Step 3: Identify alignment.
 Each square (i,j) is added to the maximum value of the proper
 parent subregion: ( [0,i-1] , [0,j-1] ).
 Thus:
   Value of (i,j) = (i,j) + max_of_region{(0,0) , (i-1,j-1)}

      c   h   e   l   o   e
   h  0   1   0   0   0   0
   e  0   0   2   1   1   2
   l  0   0   1   3   2   2
   l  0   0   1   3   3   3
   o  0   0   1   2   4   3

 The maximum value on the outer edge (4) indicates the maximum
 number of aligned characters (there are 4 characters aligned).
 The path that leads to the maximum character shows which characters
 were aligned.
   "4" comes from "o"
   "3" comes from "l" -- two choices for "hello", but one choice for "cheloe".
   "2" comes from "e" (the 1st "e" in "cheloe")
   "1" comes from "h"
 The horizontal and vertical gaps show exactly which align:
   cheloe aligns with "_helo_"
   hello  aligns with "hel_o" or "he_lo"
 For percentage of alignment:
   cheloe aligns with (4/5 = ) 80% of hello.
   hello aligns with (4/6 = ) 67% of cheloe.
 If sizeof(A) is much larger than sizeof(B), then B will likely have
 a very large percentage, while A will have a very lower percentage.
 If A and B are both non-trivial (both not small) and both have a
 high degree of similarity, then they are likely variants of each other.

 The threshold for saying "they are the same" is called "homology".
 A is homologous to B if the degree of similarity is greater than
 a fixed percent.  The threshold is arbitrary based on your needs.

 NOTE #1: Homology is boolean.  There is no "60% homologous".
 Homology is a qualitative description.
 Similarity is the quantitative description.
 NOTE #2: Homology is asymetrical.  "A" may be homologous to "B", when
 "B" is not homologous to "A".
 NOTE #3: Subsets may be homologous.  "A" may not be homologous to "B",
 but the subset "a" may be homologous to "b".


 Optimizations:
 This can be a very slow algorithm when the matrix is large.
 But there are things we can do to speed it up.

 - String compares.
   Each symbol is a string.  Using "strcmp()" is slow.
   Instead, I store a simple hash of the string (8-bit checksum).
   If the checksums don't match, then I don't bother comparing the strings.
   NOTE: SAM uses strings.  For bSAM, I don't even use strings at all.
   bSAM only uses a 16-bit checksum for much faster speed.

 - Memory layout.
   The matrix is processed linearly: foreach A do { foreach B }.
   The matrix is aligned so the B-vectors are sequential memory.
   AxB[a][b] becomes AxB[a*MaxB + b].
   This way, sequential memory gets cached and pipelined.

 - Reduce matrix scope.
   If I require a minimum matrix value of "M" to match, then I don't
   need to check matrix elements than can never lead to a value of M.
   This appears as unprocessed elements in the upper right and lower left
   of the matrix.
   I use two loops: foreach A do { foreach B }
   - I start the B loop at the first place where there is a chance
     of matching M.  If the best-case diagonal from (0,0) to (a,b)
     could never lead to a match of M, then I skip the comparison.
   - I end the B loop at the last place where there is a change of
     matching.
   The result: I only process a swath down the diagonal of the matrix.
   And only those elements can ever lead to a match.
   While this doesn't do much for small matrices, this is a huge
   performance gain for large matrices.

 - Reduce comparison scope.
   Similar to "Reduce matrix scope": see how many distinct tokens in A
   appear in B (and vice versa). If they are less than the required
   percentage, then don't even bother comparing.
   For example, if B has 100 tokens and the match must include 20% of B,
   then at least 20 tokens in B must appear in A.
   This check totally omits comparisons where there are not enough tokens
   in A for matching B.

 - External optimizations.
   The preprocessing option ("-p program") can be slower than the
   matrix comparison.  when comparing many programs against each other
   (e.g., comparing all kernel device driver files), consider preprocessing
   the file first and storing them in a cache.
   For bSAM, the preprocessing has been moved into the Filter_License program.

 - Remove non-matches.
   The matrix takes the most time.  However, if the starting tokens in
   A have no matches in B, then there is no reason to process those
   columns.  Similarly, there is no need to check unmatched token at the
   end of A, or at the start or end of B.  In the worst case, this will
   remove nothing.  In the best case, the entire comparison can be skipped
   (skipping the whole matrix!).  In the average case, large sections of A
   and B can be skipped.

 A note about other PAM variations:

 There is another (very different) variation of this algorithm called "pam".
 While I used PAM for my dissertation (many years ago), I had rewritten
 PAM as "pam" for binary file analysis (program alignment matrix).  The pam
 code and copyright are held by Neal Krawetz and not HP.

 Then came SAM (written by Neal Krawetz for HP) to do symbolic alignment.
 SAM and pam use very different code and very different optimizations.  In
 particular, pam is faster for generic binary comparisions and for very very
 very large comparisons; SAM is optimized for a different problem space.  In
 order to speed up SAM, I created another variation: bSAM for binary symbolic
 alignment matrix.  The only difference between SAM and bSAM: bSAM uses a
 tokenized binary file rather than the actual data file.  There are dozens
 of ways to optimize this code for a particular problem space.  I am
 certain that this isn't the last wildly-different variation of the PAM
 algorithm.

 A note about the license analysis:
 Thu Jul 12 15:10:46 MDT 2007
 This code is 90% ready for use with the future SAM agent.  SAM compares
 files to files and is designed to detect source code reuse.  All it needs:
   - The DB schema for storing the data needs to be designed.
   - This code must be modified to use the new DB schema for storing
     results.
   - The various Filter code agents need to be converted to an agent
     rather than the current stand-alone scrips.
 However, this code is functional and does work as a stand-alone SAM
 comparison engine.
 **************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <stdint.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <errno.h>
#include <sys/mman.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <sys/resource.h>  /* for rlimit */
#include <time.h> /* for time() used when debugging performance */

#include "libfossrepo.h"
#include "libfossdb.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
char Version[]=SVN_REV;
#endif

#if 0
#define DEBUG 1
#define DEBUG_RECURSION 0
#endif
#define BEGIN_COMMIT 1

#define MAXLINE	2048
#define PATHINC 128	/* how much to increment the Path.PathString */

/************************************************************
 Globals: used for speed!
 ************************************************************/
int OutputFormat='s';	/* default: SAM output */

/* Repository data */
char	RepFilename[2][1024]={"",""};
RepMmapStruct	*RepFile[2]={NULL,NULL};
char *RepDEFAULT[]={"files","license","sam"};
char	*RepType=NULL;
long	Pfile[2] = {-1,-1};	/* pfile keys */

#if DEBUG
int	Verbose=0;	/* debugging via '-v' */
int	ShowStage1Flag=0;	/* debugging: show "same" matrix */
int	ShowStage2Flag=0;	/* debugging: show "aligned" matrix */
#endif
int	ExhaustiveSearch=0;	/* should it do an exhaustive search? */


/****************************************************************
 The entire bsam system is based on subsets of data.
   0 - MmapSize :: The file being analyzed
   SectionStart - SectionEnd :: function within Mmap (abs file positions)
   0 - SymbolMax :: relative locations of SectionStart - SectionEnd
   0 - SymbolEnd :: scan window within Section (relative location, offset)
     SymbolStart = offset value
   MatrixMinMatch - MatrixMaxMatch :: location of license within Symbol range
 ****************************************************************/
typedef	uint16_t MTYPE;	/* data type for matrix */
MTYPE	*Matrix=NULL;	/* the alignment matrix: A*Bsize+B */
			/* looping over B as the inner loop is optimal! */
int MatrixSize=0;	/* the allocated size of the matrix */

/* for matching */
int	MatchThreshold[2]={90,90};
int	MatchGap[2]={5,5};	/* maximum gap between sequences */
int	MatchLen[2]={10,10};	/* minimum sequences to check */
int	MatchSeq[2]={10,10};	/* minimum sequences to match */

struct path
    {
    int MatrixPathMax;		/* for both MatrixPath arrays */
    int *MatrixPath[2];		/* best path (allocated by GetSeqRange) */
    int PathStringMax[2];	/* allocated size of PathString[] */
    char *PathString[2];	/* allocated text string describing path */
    };

struct label
    {
    /* for multiple datasets per file... */
    char *Filename;
    char *Sectionname;
    /* "long" because these are file positions */
    long MmapOffset;  /* index into memory map (instead of file with ftell) */
    long SectionStart;
    long SectionEnd;
    int SectionUniqueKey;
    char *SectionUnique;
    int TokentypeLen;
    char *Tokentype;

    /* optimize based on "must have these" -- these are pointes to mmap */
    int SymbolORMax;		/* size of the OR lists */
    uint16_t *SymbolOR;	/* set of OR symbols that must exist */
    int SymbolANDMax;		/* size of the AND lists */
    uint16_t *SymbolAND;	/* set of AND symbols that must exist */
    };

/* the symbols: SymbolBase is absolute, Symbol is offset */
struct symbols
    {
    int SymbolMax;		/* size of the Symbol lists (65535 max) */
    uint16_t *Symbol;		/* the set of symbols: [0]=A, [1]=B */
    uint16_t *SymbolBase;	/* base set of symbols: [0]=A, [1]=B */
    int SymbolRealSizeLen;
    unsigned char *SymbolRealSize; /* real size (bytes) of each symbol */

    /* optimize scan range based on "must contain these" */
    /** Real range is [SymbolStart, SymbolStart+SymbolEnd] **/
    /** SymbolStart+SymbolEnd <= SymbolMax **/
    int SymbolStart;
    int SymbolEnd;
    };

struct matrix
    {
    int MatrixMax;	/* max value in the matrix */
    int MatrixMinPos[2]; /* (a,b) position of min value in the matrix */
    int MatrixMaxPos[2]; /* (a,b) position of max value in the matrix */

    /* MatrixMax may not be the best value -- track the best match subrange */
    /** These values are set by FindBestMatch **/
    int MatrixBestMax;	/* (a,b) position of best alignment in the matrix */
    int MatrixBestMin;	/* (a,b) position of best alignment in the matrix */
    };

struct matrixstate
  {
  struct matrix Matrix; /* information about the matrix */
  struct path Path; /* best path intofmration */
  struct symbols Symbols[2]; /* information about symbols */
  struct label Label[2]; /* section information */
  }; /* matrixstate */
typedef struct matrixstate matrixstate;
matrixstate MS;

#define	Max(a,b)	((a) > (b) ? (a) : (b))
#define	Min(a,b)	((a) < (b) ? (a) : (b))

/* for DB */
void	*DB=NULL;
char	SQL[65536];	/* generic string buffer */
char	SQL2[65536];	/* generic string buffer */
int	Agent_pk=-1;	/* agent identifier */
long	DBInsertCount=0;	/* how many inserts were done? */

#if 0
  /* Massive debugging */
  #define MyDBaccess(a,b)	DebugDBaccess(a,b)
#else
  /* No debugging */
  #define MyDBaccess(a,b)	DBaccess(a,b)
#endif

/**********************************************
 DebugDBaccess(): For debugging.
 **********************************************/
int	DebugDBaccess	(void *a, char *b)
{
  int rc;
  rc = DBaccess(a,b);
  fprintf(stderr,"DEBUG[%d] = %d: '%s'\n",getpid(),rc,b);
  return(rc);
} /* DebugDBaccess() */

long    HeartbeatValue=-1;
long	LastHeartbeatValue=-1;

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void    ShowHeartbeat   (int Sig)
{

  /* IF we are tracking hearbeat values AND it has not changed,
     THEN don't display a heartbeat message.
     This can happen if I/O is hung, but alarms are still being processed.
   */
  if ((HeartbeatValue == -1) || (HeartbeatValue != LastHeartbeatValue))
    {
    LastHeartbeatValue = HeartbeatValue;
    printf("Heartbeat\n");
    fflush(stdout);
    }

  /* re-schedule itself */
  alarm(60);
} /* ShowHeartbeat() */


/************************************************************/
/************************************************************/
/** Memory management functions **/
/************************************************************/
/************************************************************/

#if DEBUG
/**********************************************
 PrintRanges(): display the memory ranges for debugging.
 **********************************************/
void	PrintRanges	(char *S, int Which, int ShowMatch)
{
  int i;
  int S1,S2,S3;

  printf("%s Range[%d]:\n",S,Which);
  printf("  Mmap: 0 - %x :: Offset %lx :: %s\n",RepFile[Which]->MmapSize,MS.Label[Which].MmapOffset,MS.Label[Which].Filename);
  printf("  Section: %lx - %lx :: %s\n",MS.Label[Which].SectionStart,MS.Label[Which].SectionEnd,MS.Label[Which].Sectionname);
  printf("  Symbol: 0 - %d (tokens)\n",MS.Symbols[Which].SymbolMax);
  printf("  Scan Range tokens: %d - %d (length: %d)\n",MS.Symbols[Which].SymbolStart,MS.Symbols[Which].SymbolStart+MS.Symbols[Which].SymbolEnd,MS.Symbols[Which].SymbolEnd);

  /* Convert scan range to actual byte offsets in the file */
  S1=MS.Label[Which].SectionStart;
  for(i=0; i < MS.Symbols[Which].SymbolStart; i++)
    {
    S1 += MS.Symbols[Which].SymbolRealSize[i];
    }
  for(S2=S1; i < MS.Symbols[Which].SymbolEnd; i++)
    {
    S2 += MS.Symbols[Which].SymbolRealSize[i];
    }
  for(S3=S2; i < MS.Symbols[Which].SymbolMax; i++)
    {
    S3 += MS.Symbols[Which].SymbolRealSize[i];
    }
  printf("  Scan Range bytes: %d - %d - %d (length: %d - %d)\n",S1,S2,S3,S2-S1,S3-S1);

  if (ShowMatch)
    {
    printf("  Match tokens: %d - %d (length: %d)\n",
    	MS.Symbols[Which].SymbolStart+MS.Matrix.MatrixMinPos[Which],
	MS.Symbols[Which].SymbolStart+MS.Matrix.MatrixMaxPos[Which],
	MS.Matrix.MatrixMaxPos[Which]-MS.Matrix.MatrixMinPos[Which]);

    /* Convert match range to actual byte offsets */
    S1=MS.Label[Which].SectionStart;
    for(i=0,S1=MS.Label[Which].SectionStart; i < MS.Matrix.MatrixMinPos[Which]; i++)
      {
      S1 += MS.Symbols[Which].SymbolRealSize[i];
      }
    for(S2=S1; i <= MS.Matrix.MatrixMaxPos[Which]; i++)
      {
      S2 += MS.Symbols[Which].SymbolRealSize[i];
      }
    printf("  Match bytes: %d - %d (length: %d)\n",S1,S2,S2-S1);
    }
} /* PrintRanges() */

/**********************************************
 PrintMatrix(): display the matrix for debugging.
 **********************************************/
void	PrintMatrix	(int A1, int A2, int B1, int B2)
{
  int a,b,aoffset;

  /* display header across */
  printf("   ");
  for(b=B1; b<Min(MS.Symbols[1].SymbolEnd,B2+1); b++)
    {
    printf(" %02x ",MS.Symbols[1].Symbol[b] % 256);
    }
  printf("\n");

  for(a=A1; a<Min(MS.Symbols[0].SymbolEnd,A2+1); a++)
    {
    printf(" %02x ",MS.Symbols[0].Symbol[a] % 256); /* header */
    aoffset = a * MS.Symbols[1].SymbolEnd;
    for(b=B1; b<Min(MS.Symbols[1].SymbolEnd,B2+1); b++)
      {
      printf("%3d ",Matrix[aoffset+b]);
      }
    printf("\n");
    }
  fflush(stdout);
} /* PrintMatrix() */
#endif

/**********************************************
 FreeMatrixState(): Deallocate a matrix state structure.
 **********************************************/
inline void	FreeMatrixState	(matrixstate *M)
{
  if (M->Path.PathString[0]) { free(M->Path.PathString[0]); }
  if (M->Path.PathString[1]) { free(M->Path.PathString[1]); }
  if (M->Path.MatrixPath[0]) { free(M->Path.MatrixPath[0]); }
  if (M->Path.MatrixPath[1]) { free(M->Path.MatrixPath[1]); }
} /* FreeMatrixState() */

/**********************************************
 InitMatrixState(): Initialize a matrix state structure.
 **********************************************/
inline void	InitMatrixState	(matrixstate *M)
{
#if 0
  if (M->Path.PathString[0]) free(M->Path.PathString[0]);
  if (M->Path.PathString[1]) free(M->Path.PathString[1]);
#endif
  memset(M,0,sizeof(matrixstate));
  M->Path.PathString[0] = (char *)calloc(PATHINC,1);
  M->Path.PathString[1] = (char *)calloc(PATHINC,1);
  M->Path.PathStringMax[0] = PATHINC;
  M->Path.PathStringMax[1] = PATHINC;
} /* InitMatrixState() */

/**********************************************
 CopyMatrixState(): Transfer a matrix state structure
 to a second structure.  M1 -> M2
 This copies everything EXCEPT the Path information.
 Path information is only moved on request.
 **********************************************/
inline void	CopyMatrixState	(matrixstate *M1, matrixstate *M2,
				 int MovePath)
{
  struct path P[2];

  /* Save the Path information */
  memcpy(P+0,&M1->Path,sizeof(struct path));
  memcpy(P+1,&M2->Path,sizeof(struct path));

  memcpy(M2,M1,sizeof(matrixstate));

  /* Put back the path strings */
  if (MovePath)
    {
    /* move it */
    memcpy(&M2->Path,P+0,sizeof(struct path));
    memcpy(&M1->Path,P+1,sizeof(struct path));
    }
  else
    {
    /* put it back */
    memcpy(&M1->Path,P+0,sizeof(struct path));
    memcpy(&M2->Path,P+1,sizeof(struct path));
    }
} /* CopyMatrixState() */

/**********************************************
 ShowSQLERROR(): Ok, SQL reported a problem.
 Dump everything for debugging later.
 **********************************************/
void	ShowSQLERROR	(char *SQL, int Which)
{
  fprintf(stderr,"SQL ERROR[%d]: %s:%s\n  %s\n",
	getpid(),MS.Label[Which].Filename,MS.Label[Which].Sectionname,SQL);
} /* ShowSQLERROR() */

/**********************************************
 FreeMatrix(): deallocate the matrix.
 **********************************************/
inline void	FreeMatrix	()
{
  if (Matrix)
    {
    free(Matrix);
    }
  Matrix = NULL;
  MatrixSize=0;
} /* FreeMatrix() */

/**********************************************
 SetMatrix(): allocate the matrix.
 If it is already allocated and is big enough,
 then don't reallocate.
 **********************************************/
inline	void	SetMatrix	()
{
  int NewSize;

  NewSize = (MS.Symbols[0].SymbolEnd) * (MS.Symbols[1].SymbolEnd) + 1;
  if (NewSize <= 0) return;

#if DEBUG
  if (Verbose > 1)
    {
    printf("Matrix is %d x %d = %d\n",
	(int)MS.Symbols[0].SymbolEnd,(int)MS.Symbols[1].SymbolEnd,
	(int)(MS.Symbols[0].SymbolEnd*MS.Symbols[1].SymbolEnd));
    }
#endif
  if (NewSize > MatrixSize)
    {
#if 1
    FreeMatrix();
    MatrixSize = NewSize;
    Matrix = (MTYPE *)malloc(MatrixSize * sizeof(MTYPE));
#else
    /* realloc is slower than free/malloc */
    Matrix = (MTYPE *)realloc(Matrix,MatrixSize * sizeof(MTYPE));
#endif
    if (!Matrix)
    	{
	printf("FATAL: Unable to allocate %d bytes\n",(int)MatrixSize);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
    }

  /* only clear what we need to use */
  memset(Matrix,0,sizeof(MTYPE)*NewSize);
  memset(&MS.Matrix,0,sizeof(struct matrix));
} /* SetMatrix() */

/**********************************************
 StrstrSetChr(): Convert a character for Strstr().
 **********************************************/
inline char	StrstrSetChr	(char C)
{
  if (isupper(C)) return(tolower(C));
  if (isalnum(C)) return(C);
  if (C=='\0') return(C);
  return(' ');
} /* StrstrSetChr() */

/**********************************************
 Strstr(): My case-insensitive and space-less
 string compare function.
 (H=haystack, N=needle)
 Returns start of match, or NULL on failure.
 NOTE: Needle must start at a space.
 **********************************************/
char *	Strstr	(char *H, char *N)
{
  char nc,hc; /* converted characters for n and h */
  int ni, hi; /* i indexes for n and h */
  int GotStart=1; /* indicate the start of a word */
  /** GotStart prevents "LGPL" from matching "GPL" **/

  while(isspace(N[0])) N++; /* skip initial whitespace */
  while(isspace(H[0])) H++; /* skip initial whitespace */

  /* find the first matching char */
  nc = StrstrSetChr(N[0]);
  for( ; H[0] != '\0'; H++)
    {
    hc = StrstrSetChr(H[0]);
    if (isspace(hc)) { GotStart=1; continue; }
    if (!GotStart) { continue; }
    GotStart=0;
    if (hc != nc) { continue; } /* no match */

    /* Ok, matched first letter.  Now see if the rest matches. */
    /** ni and hi may not be equal if there are multiple spaces **/
    hi=1;
    ni=1;
    while( (N[ni] != '\0') && (H[hi] != '\0') )
      {
      nc = StrstrSetChr(N[ni]);
      hc = StrstrSetChr(H[hi]);
      if (nc != hc)
	{
	nc = StrstrSetChr(N[0]);
	break;
	}
      /* ok, they matched */
      ni++;
      hi++;
      if (isspace(nc)) /* skip spaces */
	{
	while(isspace( StrstrSetChr(N[ni]) )) ni++;
	while(isspace( StrstrSetChr(H[hi]) )) hi++;
	}
      }
    if (N[ni] == '\0') return(H);
    }
  return(NULL);
} /* Strstr() */

/**********************************************
 CompSymbols(): Compare symbols
 This macro assumes two uint16_t arrays.
 Returns: 1 if same, 0 if different.
 **********************************************/
#define CompSymbols(a,b) (MS.Symbols[0].Symbol[a] == MS.Symbols[1].Symbol[b])

/************************************************************/
/************************************************************/
/** Matrix functions **/
/************************************************************/
/************************************************************/

#if DEBUG
/**********************************************
 SameInitMatrix(): Simple debugging init function
 Identifies all "same" values.
 **********************************************/
inline void	SameInitMatrix	()
{
  int a,b;

  for(a=0; a<MS.Symbols[0].SymbolEnd; a++)
  for(b=0; b<MS.Symbols[1].SymbolEnd; b++)
    {
    Matrix[a * MS.Symbols[1].SymbolEnd + b] =
	(MS.Symbols[0].Symbol[a] == MS.Symbols[1].Symbol[b]);
    }
} /* SameInitMatrix() */
#endif

/**********************************************
 ReadOK(): Read stdin until I see "OK\n".
 The scheduler sends this after every DB: line.
 **********************************************/
void	ReadOK	()
{
  int i;
  int c;
  char OK[]="OK\n";
  i=0;
  c='@';
  while((OK[i]!='\n') && (c >= 0))
    {
    c=fgetc(stdin);
    if (c==OK[i]) i++;
    else i=0;
    }
} /* ReadOK() */

/**********************************************
 Hex2Ascii(): Convert a hex digit to an ascii char.
 v MUST be a number between 0 and 15
 **********************************************/
inline char	Hex2Ascii	(int v)
{
  if (v < 10) return(v+'0');
  return(v-10+'A');
} /* Hex2Ascii() */

/**********************************************
 DBSetPhrase(): Create a phrase license if it
 does not exist.
 **********************************************/
void	DBSetPhrase	()
{
  int rc;
  rc = DBaccess(DB,"SELECT lic_pk from agent_lic_raw WHERE lic_name = 'Phrase';");
  if ((rc < 0) || (DBdatasize(DB) <= 0))
    {
    DBaccess(DB,"INSERT INTO agent_lic_raw (lic_name,lic_unique,lic_text,lic_version,lic_section,lic_id) VALUES ('Phrase','1','Phrase','1',1,1);");
#if 0
    /** Disabled: Database will take care of this **/
    DBaccess(DB,"ANALYZE agent_lic_raw;");
#endif
    }
} /* DBSetPhrase() */

/**********************************************
 DBquote(): quote a string for a DB insert.
   - Single quotes are quoted.
   - Backslashes are quoted.
 Writes up to Len characters to Dst.
 **********************************************/
void	DBquote	(char *S, int Len, char *Dst)
{
  int i, out;
  out=0;
  for(i=0; (out<Len) && (S[i] != '\0'); i++)
    {
    switch(S[i])
      {
      case '\'':
	Dst[0] = '\\'; Dst[1]='\'';
	Dst += 2;
	out += 2;
	break;
      case '\\':
	Dst[0] = '\\'; Dst[1]='\\';
	Dst += 2;
	out += 2;
	break;
      case '\n':
	Dst[0] = '\\'; Dst[1]='n';
	Dst += 2;
	out += 2;
	break;
      case '\r':
	Dst[0] = '\\'; Dst[1]='r';
	Dst += 2;
	out += 2;
	break;
      case '\t':
	Dst[0] = '\\'; Dst[1]='t';
	Dst += 2;
	out += 2;
	break;
      case '\f':
	Dst[0] = '\\'; Dst[1]='f';
	Dst += 2;
	out += 2;
	break;
      case '\v':
	Dst[0] = '\\'; Dst[1]='v';
	Dst += 2;
	out += 2;
	break;
      default:
	if (!isprint(S[i]))
	  {
	  Dst[0] = '\\';
	  Dst[1] = 'x';
	  Dst[2] = Hex2Ascii((S[i]/16) & 0x0f);
	  Dst[3] = Hex2Ascii(S[i] & 0x0f);
	  Dst += 4;
	  out += 4;
	  }
	else
	  {
	  Dst[0] = S[i];
	  Dst += 1;
	  out += 1;
	  }
	break;
      }
    }
} /* DBquote() */

/**********************************************
 FindSeqPos(): Scan for a sequential match.
 Start at the furthest position (determined by Gap).
 Find the first sequential matrix value and position.
 Returns:
   0 = no match.  New A and B positions are furthest point.
   1 = match.  New A and B positions are set to the match.
   Also sets the best path in the global MS structure.
   If returns 0, then best path is "corrupt".
   If returns 1, then best path is set.
 (Technically, sets best path from last to first.)
 NOTE: This uses a greedy algorithm that preferences clusters in "A")!
 **********************************************/
inline int	FindSeqPos	(int V, int A, int B, int *NewA, int *NewB)
{
  int a,b;
  int aoffset;
  /* for speed to reduce indirect indexing */

  MS.Symbols[1].SymbolEnd = MS.Symbols[1].SymbolEnd;

FindSeqPosReCheck:
  /* default return to furthest position */
  *NewA = 0;
  *NewB = 0;

  /* idiot checking */
  if ((V<1) || (A<=V-1) || (B<=V-1))
	{
	return(0);
	}

  /* look for best across (best=furthest right) */
  aoffset = (A-1)*MS.Symbols[1].SymbolEnd;
#if 0
  printf("Range: A[%d]=[%d,%d][%d,%d]  B[%d]=[%d,%d][%d,%d]\n",A,MS.Matrix.MatrixMinPos[0],MS.Matrix.MatrixMaxPos[0],0,MS.Symbols[0].SymbolEnd,B,MS.Matrix.MatrixMinPos[1],MS.Matrix.MatrixMaxPos[1],0,MS.Symbols[1].SymbolEnd);
#endif
  /** Scan from furthest to nearest to find the smallest match **/
  for(b=B-1; b >= V-1; b--)
    {
    if (Matrix[aoffset+b] == V)
	{
	*NewA = A-1;
	*NewB = b;
	if (CompSymbols(*NewA,*NewB))
	  {
	  MS.Path.MatrixPath[0][V] = *NewA;
	  MS.Path.MatrixPath[1][V] = *NewB;
	  return(1);
	  }
	}
    }

  /* not best from across? Try down. (best=closest to top) */
  aoffset=A*MS.Symbols[1].SymbolEnd;
  /** Scan from furthest to nearest to find the smallest match **/
  for(a=(V-1)*MS.Symbols[1].SymbolEnd + B-1; a<aoffset; a=a+MS.Symbols[1].SymbolEnd)
    {
    if (Matrix[a] == V)
	{
	*NewA = a/MS.Symbols[1].SymbolEnd; /* this will mod-out the B-1 offset */
	*NewB = B-1;
	if (CompSymbols(*NewA,*NewB))
	  {
	  MS.Path.MatrixPath[0][V] = *NewA;
	  MS.Path.MatrixPath[1][V] = *NewB;
	  return(1);
	  }
	}
    }

  /* if it gets here then the max value is not on the outside edge */
  /* no recursive penalty, but still takes a while */
  A=A-1;
  B=B-1;
  goto FindSeqPosReCheck;
} /* FindSeqPos() */

/**********************************************
 GetPathString(): Create a string that describes
 the best path.  This uses static memory in MS.
 Uses the global MS structure's Path.MatrixPath for the
 offsets -- set by FindSeqPos().
 **********************************************/
inline void	GetPathString	(int Which)
{
  long Pos,PosStart,PosEnd; /* current file position, and range start/end */
  int Sym; /* current symbol */
  int InRange=0; /* am I doing a ##-## range? */
  int ThisIsTheEnd=0; /* am I at the end of a sequence? */
  int V; /* matrix values for computing the full path */
  int Len;
  int i;
  int BaseOffset;

  BaseOffset = MS.Symbols[Which].SymbolStart;

  /* clear memory */
  if (MS.Path.PathStringMax[Which] > 0)
	{
	// memset(MS.Path.PathString[Which],'\0',MS.Path.PathStringMax[Which]);
	MS.Path.PathString[Which][0] = '\0';
	}

  /* find the path */
  Pos=MS.Label[Which].SectionStart;

#if 0
  /* Debugging */
  {
  int i,Sum;
  long Start;
  printf("A:%s:%s  B:%s:%s\n",
    MS.Label[0].Filename,MS.Label[0].Sectionname,
    MS.Label[1].Filename,MS.Label[1].Sectionname);
  printf("Offset[%d] = %ld\n",Which,Pos);
  /* Display the path offsets */
  // printf("Before:");
  for(i=0, Sum=0; i<MS.Path.MatrixPath[Which][MS.Matrix.MatrixBestMin]; i++)
    {
    // printf(" %d",MS.Symbols[Which].SymbolRealSize[i]);
    Sum += MS.Symbols[Which].SymbolRealSize[i];
    }
  // printf("\n");
  Start=Sum+Pos;
  printf("Total before: %ld\n",Sum+Pos);

  // printf("After:");
  for( ; i<=MS.Path.MatrixPath[Which][MS.Matrix.MatrixBestMax]; i++)
    {
    // printf(" %d",MS.Symbols[Which].SymbolRealSize[i]);
    Sum += MS.Symbols[Which].SymbolRealSize[i];
    }
  // printf("\n");
  printf("Total after: %ld\n",Sum+Pos);
  printf("Total size: %ld\n",Sum+Pos - Start+1);

  printf("Offsets[%d]:",Which);
  for(i=MS.Matrix.MatrixBestMin; i<= MS.Matrix.MatrixBestMax; i++)
    {
    if (MS.Path.MatrixPath[Which][i-1]+1 == MS.Path.MatrixPath[Which][i]) printf("-");
    else printf(" ");
    printf("%d",MS.Path.MatrixPath[Which][i]);
    }
  printf("\n");
  }
#endif

  if (MS.Matrix.MatrixBestMin == 0) MS.Matrix.MatrixBestMin=1;
  V=MS.Matrix.MatrixBestMin;
  for(Sym=0; Sym < MS.Path.MatrixPath[Which][V]; Sym++)
    {
    Pos += MS.Symbols[Which].SymbolRealSize[Sym];
    }
  PosStart=Pos;
  PosEnd=0;
  ThisIsTheEnd=0;
  InRange=0;

  /*** TBD NAK: Rewrite this code!  It is functional, but looks ugly. ***/
  {
  /* Compute the path offsets before the first match */
  Pos=MS.Label[Which].SectionStart;
  for(i=0; i < BaseOffset + MS.Path.MatrixPath[Which][MS.Matrix.MatrixBestMin]; i++)
    {
    Pos += MS.Symbols[Which].SymbolRealSize[i];
    }

  /* Compute path offsets within the path */
  InRange=0;
  PosStart = Pos;

  for(V=MS.Matrix.MatrixBestMin; V <= MS.Matrix.MatrixBestMax; V++)
    {
    /* Add in the length of this segment */
    for( ; i <= BaseOffset + MS.Path.MatrixPath[Which][V]; i++)
      {
      Pos += MS.Symbols[Which].SymbolRealSize[i];
      }
    PosEnd = Pos;

    /* Display the range */
    if (V >= MS.Matrix.MatrixBestMin)
    if ((V==MS.Matrix.MatrixBestMax) || (MS.Path.MatrixPath[Which][V]+1 != MS.Path.MatrixPath[Which][V+1]))
	{
	/* make sure there is enough memory allocated */
	Len = strlen(MS.Path.PathString[Which]);
	if (Len+40 >= MS.Path.PathStringMax[Which])
	  {
	  char *NewPath;
	  MS.Path.PathStringMax[Which] += PATHINC;
	  NewPath = (char *)calloc(MS.Path.PathStringMax[Which],1);
	  if (NewPath)
	    {
	    if (MS.Path.PathString[Which])
	    	{
	    	strcpy(NewPath,MS.Path.PathString[Which]);
		free(MS.Path.PathString[Which]);
		MS.Path.PathString[Which] = NULL;
		}
	    MS.Path.PathString[Which] = NewPath;
	    }
	  else
	    {
	    printf("FATAL: Unable to reallocate %d bytes\n",MS.Path.PathStringMax[Which]);
	    fflush(stdout);
	    DBclose(DB);
	    exit(-1);
	    }
	  }
	/* Save the string */
	if (Len > 0) MS.Path.PathString[Which][Len++] = ',';
	if (PosEnd == PosStart)
	  {
	  snprintf(MS.Path.PathString[Which]+Len,40,"%ld",PosStart);
	  }
	else
	  {
	  snprintf(MS.Path.PathString[Which]+Len,40,"%ld-%ld",PosStart,PosEnd);
	  }
	/* Skip the part that is not in range */
	if (V < MS.Matrix.MatrixBestMax)
	  {
	  for( ; i < BaseOffset + MS.Path.MatrixPath[Which][V+1]; i++)
	    {
	    Pos += MS.Symbols[Which].SymbolRealSize[i];
	    }
	  }
	PosStart = Pos;
	}
    }
  }
} /* GetPathString() */

/**********************************************
 GetStartEnd(): Compute the real file offsets.
 **********************************************/
void	GetStartEnd	(int Which, long *RealStart, long *RealEnd)
{
  int i;

#if DEBUG
  if (Verbose) PrintRanges("GetStartEnd",Which,1);
#endif
  if (MS.Label[Which].SectionEnd > 0)
	{
	*RealStart = MS.Label[Which].SectionStart;
	for(i=0; i < MS.Symbols[Which].SymbolStart + MS.Matrix.MatrixMinPos[Which]; i++)
	  {
	  *RealStart += MS.Symbols[Which].SymbolRealSize[i];
	  }
	*RealEnd = *RealStart;
	for( ; i < MS.Symbols[Which].SymbolStart + MS.Matrix.MatrixMaxPos[Which]; i++)
	  {
	  *RealEnd += MS.Symbols[Which].SymbolRealSize[i];
	  }
	}
    else
	{
	*RealStart=MS.Label[Which].SectionStart;
	*RealEnd=MS.Label[Which].SectionEnd;
	}
#if DEBUG
  if (Verbose)
    {
    printf("GetStartEnd[%d]: %d - %d maps to file %lx - %lx\n",Which,
      MS.Matrix.MatrixMinPos[Which],MS.Matrix.MatrixMaxPos[Which],*RealStart,*RealEnd);
    }
#endif
} /* GetStartEnd() */

/**********************************************
 DBSaveLicense(): Save a license record.
 First determine the license identifier (lic_pk).
 Then check if the license needs to be inserted.
 If it needs to be inserted, then insert it.
 Flag1SL: Is this a one-sentence license phrase? (1SL)
 Use Unique=="1" for Phrases.
 **********************************************/
void	DBSaveLicense	(int Flag1SL, char *Unique,
			 long RealStart[2], long RealEnd[2])
{
  char *V=NULL;

  memset(SQL,'\0',sizeof(SQL));
  sprintf(SQL,"SELECT lic_pk FROM agent_lic_raw WHERE lic_unique = '%s' ORDER BY lic_version DESC LIMIT 1;",Unique);
  if (MyDBaccess(DB,SQL) > 0)
      {
      V = DBgetvalue(DB,0,0);
      if (V)
	{
	MS.Label[1].SectionUniqueKey = atoi(V);
	}
      else
	{
	printf("FATAL: lic_unique not found (%s) from '%s' : '%s'\n",
		MS.Label[1].SectionUnique,MS.Label[1].Filename,MS.Label[1].Sectionname);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      }
  else
      {
      printf("FATAL: Database license query failed\n");
      printf("LOG: SELECT failed (%s)\n",SQL);
      fflush(stdout);
      DBclose(DB);
      exit(-1);
      }

  /** The problem: the SQL statement is complex and we're inside a
      BEGIN/END block.  If the insert fails, then everything fails.
      Solution: Construct a SELECT and INSERT at the same time.
      Test the SELECT, then do the INSERT.
      And use SAVEPOINT to handle any race condition inserts.
   **/
  /* The DB insert */
  {
  memset(SQL,'\0',sizeof(SQL));
  memset(SQL2,'\0',sizeof(SQL2));

  /* Start populating the INSERT into SQL[] */
  strcpy(SQL,"INSERT INTO agent_lic_meta");
  strcat(SQL," (pfile_fk,tok_pfile,tok_license,tok_match,tok_pfile_start,tok_pfile_end,tok_license_start,tok_license_end,phrase_text,version,lic_fk,pfile_path,license_path)");
  sprintf(SQL+strlen(SQL)," VALUES (%ld,%d,%d,%d",
    Pfile[0],
    MS.Matrix.MatrixMaxPos[0] - MS.Matrix.MatrixMinPos[0] + 1,
    MS.Matrix.MatrixMaxPos[1] - MS.Matrix.MatrixMinPos[1] + 1,
    MS.Matrix.MatrixMax);

  /* Start populating the SELECT into SQL2[] */
  sprintf(SQL2+strlen(SQL2),"SELECT * from agent_lic_meta WHERE pfile_fk='%ld' AND tok_pfile='%d' AND tok_license = %d AND tok_match = %d",
    Pfile[0],
    MS.Matrix.MatrixMaxPos[0] - MS.Matrix.MatrixMinPos[0] + 1,
    MS.Matrix.MatrixMaxPos[1] - MS.Matrix.MatrixMinPos[1] + 1,
    MS.Matrix.MatrixMax);

  if (RealStart[0] < RealEnd[0])
	{
	sprintf(SQL+strlen(SQL),",%ld,%ld",RealStart[0],RealEnd[0]);
	sprintf(SQL2+strlen(SQL2)," AND tok_pfile_start='%ld' AND tok_pfile_end='%ld'",RealStart[0],RealEnd[0]);
	}
  else
	{
	strcat(SQL,",NULL,NULL");
	strcat(SQL2," AND tok_pfile_start IS NULL AND tok_pfile_end IS NULL");
	}
  if (RealStart[1] < RealEnd[1])
	{
	sprintf(SQL+strlen(SQL),",%ld,%ld",RealStart[1],RealEnd[1]);
	sprintf(SQL2+strlen(SQL2)," AND tok_license_start='%ld' AND tok_license_end='%ld'",RealStart[1],RealEnd[1]);
	}
  else
	{
	strcat(SQL,",NULL,NULL");
	strcat(SQL2," AND tok_license_start IS NULL AND tok_license_end IS NULL");
	}

  /* store phrase_text */
  if (Flag1SL) /* if 1SL */
    {
    strcat(SQL,",'");
    DBquote(MS.Label[1].Sectionname,250,SQL+strlen(SQL));
    strcat(SQL,"'");
    /* set the range for 1SL phrases */
    memset(MS.Path.PathString[0],'\0',MS.Path.PathStringMax[0]);
    sprintf(MS.Path.PathString[0],"%ld-%ld",RealStart[0],RealEnd[0]);
    }
  else
    {
    strcat(SQL,",null");
    sprintf(SQL2+strlen(SQL2)," AND phrase_text is NULL");
    }

  strcat(SQL,",'0.1'"); /* version string */
  strcat(SQL2," AND version='0.1'"); /* version string */
  /* store lic_fk (MS.Label[1].SectionUniqueKey was set by the SELECT) */
  sprintf(SQL+strlen(SQL),",%d",MS.Label[1].SectionUniqueKey);
  sprintf(SQL2+strlen(SQL2)," AND lic_fk='%d'",MS.Label[1].SectionUniqueKey);

  /* store best path */
  strcat(SQL,",'");
  strcat(SQL,MS.Path.PathString[0]);
  strcat(SQL,"'");
  strcat(SQL,",'");
  strcat(SQL,MS.Path.PathString[1]);
  strcat(SQL,"'");

  strcat(SQL,");");
  strcat(SQL2,";");
  }

  /* Now write the output */
  if (OutputFormat == 'N')
	{
	/* send SQL to the scheduler */
	printf("DB: %s\n",SQL);
	fflush(stdout);
	ReadOK();
	}
  else /* (OutputFormat == 'n') */
	{
	int rc;
	/* Only insert if the select returns no values */
	rc = MyDBaccess(DB,SQL2);
	if (rc < 0) ShowSQLERROR(SQL2,0);
	else if (DBdatasize(DB) <= 0)
	  {
	  DBaccess(DB,"SAVEPOINT bsamrollback;");
	  rc = MyDBaccess(DB,SQL);
	  if (rc < 0)
		{
		ShowSQLERROR(SQL,0);
		DBaccess(DB,"ROLLBACK TO SAVEPOINT bsamrollback;");
		}
	  else
		{
	  	DBInsertCount++;
		}
	  DBaccess(DB,"RELEASE SAVEPOINT bsamrollback;");
	  }
	}
} /* DBSaveLicense() */

/**********************************************
 VerboseStats(): show matrix results.
 This is usually used for when a match is found,
 but is also useful for debugging.
 If Flag1SL then it's a 1SL record.
 NOTE: I used to use multiple printf statements.
 Turns out, the buffer may be flushed at any time,
 leading to a partial-read() by the parent.
 Solution: Create the SQL string first, then print it once.
 **********************************************/
inline void	VerboseStats	(int Flag1SL)
{
  long RealStart[2], RealEnd[2];  /* these are the real offsets into the file */
  int i;
  float Alen, Blen;

  if (!Flag1SL)
    {
    for(i=0; i<2; i++)
      {
      GetStartEnd(i,&(RealStart[i]),&(RealEnd[i]));
      }
    }
  else
    {
    for(i=0; i<2; i++)
      {
      RealStart[i] = MS.Label[i].SectionStart;
      RealEnd[i] = MS.Label[i].SectionEnd;
      }
    }

  Alen = MS.Matrix.MatrixMaxPos[0] - MS.Matrix.MatrixMinPos[0] + 1;
  Blen = MS.Matrix.MatrixMaxPos[1] - MS.Matrix.MatrixMinPos[1] + 1;

  switch(OutputFormat)
    {
    case 'n':	/* normal DB output */
    case 'N':	/* normal DB output */
    /***
     If Label.SectionUnique is defined, then do a different insert...
     INSERT INTO agent_lic_meta (fields) SELECT 'constants',lic_pk FROM \
      agent_lic_raw WHERE lic_unique = 'string' order by and asc limit 1;
     ***/

      MS.Label[1].SectionUniqueKey = -1;

      /* Process regular licenses */
      if (MS.Label[1].SectionUnique != NULL)
	{
	DBSaveLicense(Flag1SL,MS.Label[1].SectionUnique,RealStart,RealEnd);
	}
      else if (Flag1SL)
	{
	DBSaveLicense(Flag1SL,"1",RealStart,RealEnd);
	}
      break;

    case 's':	/* SAM output */
	/* TBD */
    default: /* text */
	fputs("***** MATCHED *****\n",stdout);
	fputs("A = ",stdout);
	fputs(MS.Label[0].Filename,stdout);
	fputs("\n    ",stdout);
	fputs(MS.Label[0].Sectionname,stdout);
	if (RealStart[0] < RealEnd[0])
	  {
	  fprintf(stdout," (0x%lx,0x%lx)",RealStart[0],RealEnd[0]);
	  }
	fputs("\n",stdout);

	fputs("B = ",stdout);
	fputs(MS.Label[1].Filename,stdout);
	fputs("\n    ",stdout);
	fputs(MS.Label[1].Sectionname,stdout);
	if (RealStart[1] < RealEnd[1])
	  {
	  fprintf(stdout," (0x%lx,0x%lx)",RealStart[1],RealEnd[1]);
	  }
	fputs("\n",stdout);

	printf("|A| = %d\n|B| = %d\nmax(AxB) = %d\n",
	  (int)Alen, (int)Blen, MS.Matrix.MatrixMax);
	if ((MS.Symbols[0].SymbolEnd==0) || (MS.Symbols[1].SymbolEnd==0))
	  {
	  fputs("A->B = 0%\n",stdout);
	  fputs("B->A = 0%\n",stdout);
	  }
	else
	  {
	  printf("A->B = %.2f%%\nB->A = %.2f%%\n",
	  (float)MS.Matrix.MatrixMax*100.0/(float)(Alen),
	  (float)MS.Matrix.MatrixMax*100.0/(float)(Blen));
	  }
	printf("Apath = %s\n",MS.Path.PathString[0]);
	printf("Bpath = %s\n",MS.Path.PathString[1]);
	fputs("\n",stdout);
	break;
    } /* switch(OutputFormat) */
  fflush(stdout);
} /* VerboseStats() */

/**********************************************
 GetSeqRange(): Determine the range in Which for the match.
 The range is [start,end].  (end *is* the last position.)
 Also check if it covers the known-best range.
 This uses the MS.Path.MatrixPath[][] (set by FindSeqPos) array for analysis.
 Returns: Number of matches within the best range.
 **********************************************/
inline int	GetSeqRange	()
{
  int a,b;	/* position in matrix */
  int v;	/* next desired value in the matrix */
  int aNew,bNew;	/* next position in the matrix */
  int vStart,vEnd;
  int vBestStart,vBestEnd;
  int Seq[2],SeqBest[2]={0,0};	/* track the best sequential match size */

  v=MS.Matrix.MatrixMax;
  a=MS.Matrix.MatrixMaxPos[0];
  b=MS.Matrix.MatrixMaxPos[1];
  aNew=0;
  bNew=0;

  /* Allocate memory for the match */
    {
    if (MS.Path.MatrixPath[0]) free(MS.Path.MatrixPath[0]);
    if (MS.Path.MatrixPath[1]) free(MS.Path.MatrixPath[1]);
    MS.Path.MatrixPathMax = v+1;
    MS.Path.MatrixPath[0] = (int *)calloc(v+1,sizeof(int));
    MS.Path.MatrixPath[1] = (int *)calloc(v+1,sizeof(int));
    }

  /* Calling FindSeqPos() fills out the MS.Path.MatrixPath[] structure */
  /* Find the best path */
  while((a>0) && (b>0) && (v>0))
    {
    if (FindSeqPos(v,a,b,&aNew,&bNew) == 0)
      {
      /* should never happen */
      return(0);
      }
    a=aNew;
    b=bNew;
    v--;
    } /* while(a && b) */

  /* Now find the best big chunk */
  vStart = 0;
  vEnd = -1;
  vBestStart = 0;
  vBestEnd = 0;
  Seq[0]=0;
  Seq[1]=0;
  for(v=0; v <= MS.Matrix.MatrixMax; v++)
    {
    /* only scan within the selected range */
    if (MS.Path.MatrixPath[0][v] < MS.Matrix.MatrixMinPos[0]) continue;
    if (MS.Path.MatrixPath[0][v] > MS.Matrix.MatrixMaxPos[0]) continue;

    if (vEnd < vStart) { vStart=v; vEnd=v; } /* if new sequence range */
    else
      {
      /* Ok, we've started a sequence. */

      /* Count the sequential stuff */
      if (MS.Path.MatrixPath[0][v-1]+1 == MS.Path.MatrixPath[0][v])
	{
	Seq[0]++;
	if (Seq[0] > SeqBest[0]) SeqBest[0] = Seq[0];
	}
      else Seq[0]=1;
      if (MS.Path.MatrixPath[1][v-1]+1 == MS.Path.MatrixPath[1][v])
	{
	Seq[1]++;
	if (Seq[1] > SeqBest[1]) SeqBest[1] = Seq[1];
	}
      else Seq[1]=1;

      /* See if they match the gap range */
      if ((MS.Path.MatrixPath[0][v] - MS.Path.MatrixPath[0][v-1] <= MatchGap[0]) &&
	  (MS.Path.MatrixPath[1][v] - MS.Path.MatrixPath[1][v-1] <= MatchGap[1]))
	{
	/* good sequence! */
	vEnd = v;
	if (vEnd-vStart > vBestEnd-vBestStart)
	  {
	  vBestStart = vStart;
	  vBestEnd = vEnd;
	  }
	}
      else
	{
	/* Bad sequence. See if it is the best so far */
	if (vEnd-vStart > vBestEnd-vBestStart)
	  {
	  vBestStart = vStart;
	  vBestEnd = vEnd;
	  }
	/* reset the sequence */
	vStart = v;
	vEnd = v;
	}
      }
    }

  /* Check if the BestSeq is good enough (this is "-M") */
  if (SeqBest[0] < MatchSeq[0]) return(0); /* no match */
  if (SeqBest[1] < MatchSeq[1]) return(0); /* no match */

  /* Check if the length thresholds match (this is "-L") */
  MS.Matrix.MatrixMinPos[0] = MS.Path.MatrixPath[0][vBestStart];
  MS.Matrix.MatrixMaxPos[0] = MS.Path.MatrixPath[0][vBestEnd];
  if (MS.Matrix.MatrixMaxPos[0] - MS.Matrix.MatrixMinPos[0] < MatchLen[0]) return(0);

  MS.Matrix.MatrixMinPos[1] = MS.Path.MatrixPath[1][vBestStart];
  MS.Matrix.MatrixMaxPos[1] = MS.Path.MatrixPath[1][vBestEnd];
  if (MS.Matrix.MatrixMaxPos[1] - MS.Matrix.MatrixMinPos[1] < MatchLen[1]) return(0);

  MS.Matrix.MatrixMax = vBestEnd-vBestStart;
  MS.Matrix.MatrixBestMin = vBestStart;
  MS.Matrix.MatrixBestMax = vBestEnd;

  /* check if threshold matches (this is -A and -B) */
  a = MS.Matrix.MatrixMaxPos[0] - MS.Matrix.MatrixMinPos[0] + 1;
  b = MS.Matrix.MatrixMaxPos[1] - MS.Matrix.MatrixMinPos[1] + 1;
  if ((MS.Matrix.MatrixMax*100 < a*MatchThreshold[0]) ||
      (MS.Matrix.MatrixMax*100 < b*MatchThreshold[1]))
	{
	return(0);
	}

  /* compute result */
  return(MS.Matrix.MatrixMax);
} /* GetSeqRange() */

/************************************************************/
/************************************************************/
/** Data loading and processing **/
/************************************************************/
/************************************************************/

/**********************************************
 SetData(): Reset the mmap offset.
 **********************************************/
#define	SetData(x)	(MS.Label[x].MmapOffset = 0)

/**********************************************
 OptimizeMatrixRange(): Rather than comparing
 all symbols with all symbols, we can skip everything
 at the beginning and end that has no matches.
 This function identifies the range of available
 tokens and moves the min/max ranges accordingly.
 Returns 0 if no possible match, 1 if there is a match.
 **********************************************/
#define ByteMask(src,byte,mask)	{ (byte) = (src)/8; (mask) = 1<<(src)%8; }
inline int	OptimizeMatrixRange	(int *MinA, int *MaxA, int *MinB, int *MaxB)
{
  int a,b;
  uint16_t Byte,Mask;
  /* List of known symbols.
     Two lists: A and B
     Since a token is 2 bytes, that means 65536 max */
  /** For space, I am converting bytes to bits:
      2*65536 = 128K.  But as bits, it is 16K. **/
  static uint8_t Symbol[2][8192];
  float Count,Total;

  // printf("Was: [%d,%d] [%d,%d]\n",*MinA,*MaxA,*MinB,*MaxB);
  memset(Symbol,0,sizeof(uint8_t)*2*8192);

  /* Populate the known symbols */
  for(a = *MinA; a < *MaxA; a++)
    {
    ByteMask(MS.Symbols[0].Symbol[a],Byte,Mask);
    Symbol[0][Byte] |= Mask;
    }
  for(b = *MinB; b < *MaxB; b++)
    {
    ByteMask(MS.Symbols[1].Symbol[b],Byte,Mask);
    Symbol[1][Byte] |= Mask;
    }

  /********** Check for possible match *********************/
  /* Determine how many symbols in A are in B and vice versa. */
  Count=0; Total=0;
  for(a = 0; a < 8192; a++)
    {
    if (Symbol[0][a] & 0x01) { Total++; if (Symbol[1][a] & 0x01) Count++; }
    if (Symbol[0][a] & 0x02) { Total++; if (Symbol[1][a] & 0x02) Count++; }
    if (Symbol[0][a] & 0x04) { Total++; if (Symbol[1][a] & 0x04) Count++; }
    if (Symbol[0][a] & 0x08) { Total++; if (Symbol[1][a] & 0x08) Count++; }
    if (Symbol[0][a] & 0x10) { Total++; if (Symbol[1][a] & 0x10) Count++; }
    if (Symbol[0][a] & 0x20) { Total++; if (Symbol[1][a] & 0x20) Count++; }
    if (Symbol[0][a] & 0x40) { Total++; if (Symbol[1][a] & 0x04) Count++; }
    if (Symbol[0][a] & 0x80) { Total++; if (Symbol[1][a] & 0x80) Count++; }
    }
  if (Count*100.0/Total < MatchThreshold[1]) return(0); /* no match */

  Count=0; Total=0;
  for(b = 0; b < 8192; b++)
    {
    if (Symbol[1][b] & 0x01) { Total++; if (Symbol[0][b] & 0x01) Count++; }
    if (Symbol[1][b] & 0x02) { Total++; if (Symbol[0][b] & 0x02) Count++; }
    if (Symbol[1][b] & 0x04) { Total++; if (Symbol[0][b] & 0x04) Count++; }
    if (Symbol[1][b] & 0x08) { Total++; if (Symbol[0][b] & 0x08) Count++; }
    if (Symbol[1][b] & 0x10) { Total++; if (Symbol[0][b] & 0x10) Count++; }
    if (Symbol[1][b] & 0x20) { Total++; if (Symbol[0][b] & 0x20) Count++; }
    if (Symbol[1][b] & 0x40) { Total++; if (Symbol[0][b] & 0x04) Count++; }
    if (Symbol[1][b] & 0x80) { Total++; if (Symbol[0][b] & 0x80) Count++; }
    }
  if (Count*100.0/Total < MatchThreshold[0]) return(0); /* no match */

  /********** Find start and end *********************/
  /* Find the first symbol that matches */
  for(a = *MinA; a < *MaxA; a++)
    {
    ByteMask(MS.Symbols[0].Symbol[a],Byte,Mask);
    if ((Symbol[1][Byte] & Mask) != 0) { break; }
    }
  *MinA = a;
  for(a = *MaxA - 1; a > *MinA; a--)
    {
    ByteMask(MS.Symbols[0].Symbol[a],Byte,Mask);
    if ((Symbol[1][Byte] & Mask) != 0) { break; }
    }
  *MaxA = a+1;
  if (*MaxA - *MinA <= MatchLen[0]) { return(0); }

  for(b = *MinB; b < *MaxB; b++)
    {
    ByteMask(MS.Symbols[1].Symbol[b],Byte,Mask);
    if ((Symbol[0][Byte] & Mask) != 0) { break; }
    }
  *MinB = b;
  for(b = *MaxB - 1; b > *MinB; b--)
    {
    ByteMask(MS.Symbols[1].Symbol[b],Byte,Mask);
    if ((Symbol[0][Byte] & Mask) != 0) { break; }
    }
  *MaxB = b+1;
  if (*MaxB - *MinB <= MatchLen[1]) { return(0); }

  // printf("Now: [%d,%d] [%d,%d]\n",*MinA,*MaxA,*MinB,*MaxB);
  return(1);
} /* OptimizeMatrixRange() */

/**********************************************
 ComputeMatrix(): Fill the matrix based on the
 stored symbols.
 This can get very slow for vary large matricies.
 To speed things up, we check whether there are
 too many misses.  If so, reduce the scan area.
 Returns:
  1 = Matrix completed
  0 = Matrix failed (won't ever match)
 Also sets Matrix.MatrixMinPos and Matrix.MatrixMaxPos
 showing the range of the best match.
 **********************************************/
int	ComputeMatrix	()
{
  register int a,b;	/* matrix is (a,b) = a*bMax + b */
  int a1offset,a2offset;	/* quick offset for a*bMax */
  int MinA,MinB;	/* what is the minimum start needed to match? */
  int MaxA,MaxB;	/* what is the maximum end needed to match? */
  int SkipA,SkipB;	/* how many can we skip before a certain miss? */
  int Bstart,Bend;	/* for speeding searches */
  int SubMax;	/* maximum value along submatrix (for optimization) */
  int rc;
  /* for speed to reduce indirect indexing */

  /* prepare the matrix */
  SetMatrix();

#if 0
  printf("\n");
  printf("Loaded:\n  A: %s (%s: %ld)\n  B: %s (%s %ld)\n",
    MS.Label[0].Filename,MS.Label[0].Sectionname,MS.Symbols[0].SymbolMax,
    MS.Label[1].Filename,MS.Label[1].Sectionname,MS.Symbols[1].SymbolMax);
    printf("Matrix is %d x %d = %d\n",
	(int)MS.Symbols[0].SymbolEnd,(int)MS.Symbols[1].SymbolEnd,
	(int)(MS.Symbols[0].SymbolEnd*MS.Symbols[1].SymbolEnd));
    if ((MS.Symbols[0].SymbolEnd > MS.Symbols[0].SymbolMax) ||
	(MS.Symbols[1].SymbolEnd > MS.Symbols[1].SymbolMax))
	printf("*** BAD MATRIX\n");
#endif


  /* set range */
  SkipA = MS.Symbols[0].SymbolEnd - ((MatchThreshold[0] * MS.Symbols[0].SymbolEnd) / 100);
  SkipB = MS.Symbols[1].SymbolEnd - ((MatchThreshold[1] * MS.Symbols[1].SymbolEnd) / 100);
  MinA = MS.Symbols[0].SymbolStart;
  MinB = MS.Symbols[1].SymbolStart;
  MaxA = MS.Symbols[0].SymbolEnd;
  MaxB = MS.Symbols[1].SymbolEnd;

#if 0
  printf("\n");
  printf("Loaded:\n  A: %s (%s: %ld) :: %d - %d\n  B: %s (%s %ld) :: %d - %d\n",
    MS.Label.Filename[0],MS.Label.Sectionname[0],MS.Symbols.SymbolMax[0],MinA,MaxA,
    MS.Label.Filename[1],MS.Label.Sectionname[1],MS.Symbols.SymbolMax[1],MinB,MaxB);
    printf("Matrix is %d x %d = %d   Using %d x %d\n",
	(int)MS.Symbols.SymbolEnd[0],(int)MS.Symbols.SymbolEnd[1],
	(int)(MS.Symbols.SymbolEnd[0]*MS.Symbols.SymbolEnd[1]),
	MaxA-MinA,MaxB-MinB
	);
    if ((MS.Symbols.SymbolEnd[0] > MS.Symbols.SymbolMax[0]) ||
	(MS.Symbols.SymbolEnd[1] > MS.Symbols.SymbolMax[1]))
	printf("*** BAD MATRIX\n");
#endif
#if 0
  printf("A=[%d : %ld]=[%d : %d]   B=[%d : %ld]=[%d : %d]\n",
	  0,MS.Symbols[0].SymbolEnd,MinA,MaxA, 0,MS.Symbols[1].SymbolEnd,MinB,MaxB);
  printf("  MaxA=%d  MaxB=%d  SkipA=%d  SkipB=%d\n",MaxA,MaxB,SkipA,SkipB);
#endif

#if DEBUG
  /* debugging */
  if (ShowStage1Flag)
	{
	printf("Stage 1:\n  A: %s (%s: %d)\n  B: %s (%s: %d)\n",
	  MS.Label[0].Filename,MS.Label[0].Sectionname,MS.Symbols[0].SymbolMax,
	  MS.Label[1].Filename,MS.Label[1].Sectionname,MS.Symbols[1].SymbolMax);
	SameInitMatrix();
	PrintMatrix(0,65536,0,65536);
	}
#endif

  /* Offset symbols, so the first is "zero" */
  /***********************************************/
  /*** NOTE: Move Symbols back before exiting! ***/
  /***********************************************/
  MS.Symbols[0].Symbol = MS.Symbols[0].SymbolBase + MS.Symbols[0].SymbolStart;
  MS.Symbols[1].Symbol = MS.Symbols[1].SymbolBase + MS.Symbols[1].SymbolStart;
  MinA = 0;
  MinB = 0;
  MaxA -= MS.Symbols[0].SymbolStart;
  MaxB -= MS.Symbols[1].SymbolStart;

  if ((MaxA <= 0) || (MaxB <= 0) /* no symbols */
      || !OptimizeMatrixRange(&MinA,&MaxA,&MinB,&MaxB)) /* no range */
    {
    /* Return offset symbols, so the first is "zero" */
    MS.Symbols[0].Symbol = MS.Symbols[0].SymbolBase;
    MS.Symbols[1].Symbol = MS.Symbols[1].SymbolBase;
    return(0);
    }

#if 0
  printf("\n");
  printf("Loaded:\n  A: %s (%s: %ld) :: %d - %d\n  B: %s (%s %ld) :: %d - %d\n",
    MS.Label[0].Filename,MS.Label[0].Sectionname,MS.Symbols[0].SymbolMax,MinA,MaxA,
    MS.Label[1].Filename,MS.Label[1].Sectionname,MS.Symbols[1].SymbolMax,MinB,MaxB);
    printf("Matrix is %d x %d = %d   Using %d x %d\n",
	(int)MS.Symbols[0].SymbolEnd,(int)MS.Symbols[1].SymbolEnd,
	(int)(MS.Symbols[0].SymbolEnd*MS.Symbols[1].SymbolEnd),
	MaxA-MinA,MaxB-MinB
	);
#endif

  /* fill out the outer edge for init */
  for(a=MinA; a < MaxA; a++)
    Matrix[a*MS.Symbols[1].SymbolEnd] = CompSymbols(a,0);
  for(b=MinB; b < MaxB; b++)
    Matrix[b] = CompSymbols(0,b);

  /* Neal says: pam was heavily optimized.
     One of the optimizations removes the two LocalMaxMatrix loops.
     The following code looks similar to pam, but is not identical to pam.
     - pam only needs to check 2 cases of neighboring cells in the matrix.
     - SAM checks 3 neighboring cells since it does not scan everything.
     And since an algorithm cannot be patented, this isn't a problem.
     (See http://www.cyberlaw.com/rsa.html -- however, I am not a lawyer.)
     Also, pam has a few other VERY efficient optimizations that
     cannot be easily applied to SAM, so I don't see a conflict here.
     But just in case: pam used this style of optimization three years
     before SAM was ever created.  And Dayhoff used the general algorithm
     28 years before SAM was created. */
  /* Init quick indexes */
  a1offset = (MinA-1)*MS.Symbols[1].SymbolEnd;
  a2offset = (MinA)*MS.Symbols[1].SymbolEnd;
  for(a=MinA+1; a < MaxA; a++)
    {
    /* only start 'b' where it can lead to a match */
    Bstart=Max(MinB+1,a - MaxA + MaxB - SkipB);
    Bend = Min(MaxB,SkipB + a+1);

    /* Quick indexes */
    a1offset += MS.Symbols[1].SymbolEnd;
    a2offset += MS.Symbols[1].SymbolEnd;

    SubMax=Matrix[a1offset + 0]; /* base case: the node above and behind me */
    for(b=Bstart; b<Bend; b++)
      {
      /* for SAM, we only compute the diagonal, so we need to check two
	 adjacent cases */
      /* check node above and behind */
      if (SubMax < Matrix[a1offset + b-1]) SubMax = Matrix[a1offset + b-1];
      /* check node above */
      if (SubMax < Matrix[a1offset + b] - CompSymbols(a-1,b))
	{
	SubMax = Matrix[a1offset + b] - CompSymbols(a-1,b);
	}
      /* check node behind */
      if (SubMax < Matrix[a2offset + b-1] - CompSymbols(a,b-1))
	{
	SubMax = Matrix[a2offset + b-1] - CompSymbols(a,b-1);
	}
      /* set matrix value */
      Matrix[a2offset + b] = CompSymbols(a,b) + SubMax;
      if (Matrix[a2offset + b] > MS.Matrix.MatrixMax)
	{
	MS.Matrix.MatrixMax = Matrix[a2offset + b];
	/* MS.Matrix.MatrixMaxPos is "<" end, not "<=" */
	MS.Matrix.MatrixMaxPos[0]=a+1;
	MS.Matrix.MatrixMaxPos[1]=b+1;
	}
      }
    } /* expensive for loop */

#if DEBUG
  if (ShowStage2Flag)
  	{
	printf("Stage 2:\n  A: %s (%s: %d)\n  B: %s (%s: %d)\n",
	  MS.Label[0].Filename,MS.Label[0].Sectionname,MS.Symbols[0].SymbolMax,
	  MS.Label[1].Filename,MS.Label[1].Sectionname,MS.Symbols[1].SymbolMax);
	PrintMatrix(0,65536,0,65536);
	}
  if (Verbose > 1) VerboseStats(0);
#endif

  rc=GetSeqRange();
  /* Return offset symbols, so the first is "zero" */
  MS.Symbols[0].Symbol = MS.Symbols[0].SymbolBase;
  MS.Symbols[1].Symbol = MS.Symbols[1].SymbolBase;
  return(rc);
} /* ComputeMatrix() */

/**********************************************
 ExtremeTokens(): Set the extreme token range.
 This sets Symbols.SymbolStart[] and Symbols.SymbolEnd[].
 Computes the range over B based on tokens found in A.
 Only symbols within this range will be considered for
 comparisons.  This prevents scanning large, unmatched
 segments.
 **********************************************/
inline void	ExtremeTokens	(int Which)
{
  int A,B;
  int a,b;
  int EMin,EMax;
  A = Which; B = !A;

  if (MS.Label[A].SymbolORMax == 0) return;
  if (MS.Symbols[B].SymbolMax < 100) return; /* nothing will be optimized */

  /****
   Assume that MS.Symbols.SymbolStart and MS.Symbols.SymbolEnd are set to a max range.
   Find the outer most tokens!

   This must NEVER go outside of the predefined range.
   Furthermore, LoadNextData(1) must reset the range, but not
   beyond the specified values.
   ****/

  /* init bounds */
  EMin = MS.Symbols[B].SymbolStart;
  EMax = MS.Symbols[B].SymbolEnd;

  /* init range to extreme out-of-bounds range */
  MS.Symbols[B].SymbolStart = EMax;
  MS.Symbols[B].SymbolEnd = EMin;

  /* check remaining tokens */
  for(a=0; a < MS.Label[A].SymbolORMax; a++)
    {
    /* find first matching token -- this will be the start */
    if (MS.Symbols[B].SymbolStart > EMin)
      {
      b=EMin;
      while((b < MS.Symbols[B].SymbolStart) && (MS.Label[A].SymbolOR[a] != MS.Symbols[B].Symbol[b])) b++;
      if ((b < EMax) && (MS.Label[A].SymbolOR[a] == MS.Symbols[B].Symbol[b]))
	{
	/* save the minimum entry! */
	if (b < MS.Symbols[B].SymbolStart)
	  {
	  MS.Symbols[B].SymbolStart = b;
	  }
	}
      }

    /* find last matching token -- this will be the end */
    if (MS.Symbols[B].SymbolEnd < EMax)
      {
      b=EMax-1;
      while((b >= MS.Symbols[B].SymbolStart) && (MS.Label[A].SymbolOR[a] != MS.Symbols[B].Symbol[b])) b--;
      if ((b>=MS.Symbols[B].SymbolStart) && (MS.Label[A].SymbolOR[a] == MS.Symbols[B].Symbol[b]))
	{
	/* save the minimum entry! */
	if (b > MS.Symbols[B].SymbolEnd) MS.Symbols[B].SymbolEnd = b;
	}
      }
    }
#if DEBUG
  if (Verbose > 1)
    {
    printf("ExtremeTokens %d: [%d : %d] => [%d : %d] = %d\n",
      B,0,MS.Symbols[B].SymbolEnd,MS.Symbols[B].SymbolStart,
      MS.Symbols[B].SymbolEnd,MS.Symbols[B].SymbolEnd-MS.Symbols[B].SymbolStart);
    }
#endif

  /* Allow a few tokens (100) before and after the OR list. */
  MS.Symbols[B].SymbolStart = Max(MS.Symbols[B].SymbolStart-100,EMin);
  MS.Symbols[B].SymbolEnd = Min(MS.Symbols[B].SymbolEnd+100,EMax);

  /* Align end with offset */
#if 0
  if (MS.Symbols[B].SymbolStart >= MS.Symbols[B].SymbolEnd)
	{
	printf("  ExtremeTokens2: (%d,%d): %ld - %ld\n",
		A,B,MS.Symbols[B].SymbolStart,MS.Symbols[B].SymbolEnd);
	printf("EXTREMETOKEN RANGE ERROR!\n");
	}
#endif
} /* ExtremeTokens() */

/**********************************************
 CheckTokensOR(): Check if tokens exist.
 Return 1 if at least one token matches.
 Return 0 if none match.
 **********************************************/
inline int	CheckTokensOR	(int Which)
{
  int a,b;
  uint16_t *SymbolList,*ScanSymbols;
  int SymbolListMax;
  int ScanStart,ScanEnd;

  SymbolList = MS.Label[Which].SymbolOR;
  SymbolListMax = MS.Label[Which].SymbolORMax;
  ScanStart = MS.Symbols[!Which].SymbolStart;
  ScanEnd   = MS.Symbols[!Which].SymbolEnd;
  ScanSymbols = MS.Symbols[!Which].Symbol;

  for(b=ScanStart; b < ScanEnd; b++)
    {
    for(a=0; a < SymbolListMax; a++)
      {
      if (SymbolList[a] == ScanSymbols[b])
	  	{
		/* at least one matched! */
		return(1);
		}
      }
    }

#if DEBUG
  if (Verbose > 1)
    {
    printf("TokensOR missed: A=%s:%s  B=%s:%s\n",
	MS.Label[0].Filename,MS.Label[0].Sectionname,
	MS.Label[1].Filename,MS.Label[1].Sectionname);
    }
#endif
  return(0);
} /* CheckTokensOR() */

/**********************************************
 CheckTokensAND(): Check if tokens exist.
 Return 1 if all token matches.
 Return 0 if at least one does not match.
 **********************************************/
inline int	CheckTokensAND	(int Which)
{
  int a,b;
  uint16_t *SymbolList,*ScanSymbols;
  int SymbolListMax;
  int ScanStart,ScanEnd;

  SymbolList = MS.Label[Which].SymbolAND;
  SymbolListMax = MS.Label[Which].SymbolANDMax;
  ScanStart = MS.Symbols[!Which].SymbolStart;
  ScanEnd   = MS.Symbols[!Which].SymbolEnd;
  ScanSymbols = MS.Symbols[!Which].Symbol;

  for(a=0; a < SymbolListMax; a++)
    {
    b=ScanStart;
    while((b < ScanEnd) &&
	  (SymbolList[a] != ScanSymbols[b]))
	  	{
		/* if it is not the same, then increment */
		b++;
		}
    if (b==ScanEnd)
	{
#if DEBUG
	if (Verbose > 1)
	  {
	  printf("TokensAND missed: A=%s:%s  B=%s:%s\n",
	   MS.Label[0].Filename,MS.Label[0].Sectionname,
	   MS.Label[1].Filename,MS.Label[1].Sectionname);
	  }
#endif
	return(0);
	}
    }
  return(1);
} /* CheckTokensAND() */

/**********************************************
 LoadNextData(): Given a file containing data,
 load the data!
 This stops when it gets to a function token block (type 18)
 Which = index for sequence: 0=A, 1=B
 Tag 0140 is a match string when nothing else matches.
 Returns 0 if no data to load (EOF).
 Returns 1 if data!
 **********************************************/
int	LoadNextData	(const int Which, int Show0140)
{
  int Type;
  long Length;
  unsigned char *MapOffset;	/* quick index into memory map */
  unsigned char *MapMax;	/* end of memory map */
  int i;

  HeartbeatValue++;
  if (!RepFile[Which]) return(0);
  if ((Which == 1) && (MS.Symbols[0].SymbolEnd <= 0)) return(0);

  MapMax = RepFile[Which]->Mmap + RepFile[Which]->MmapSize;
  MapOffset = RepFile[Which]->Mmap + MS.Label[Which].MmapOffset;

GetNext:
  if (MapOffset >= MapMax)	return(0);
  do
    {
    if (MapOffset+4 >= MapMax)	return(0);

    /* read a label */
    if (MapOffset[0] == 255)
	{
	MapOffset++; /* boundary alignment */
	}
    Type = MapOffset[0] * 256 + MapOffset[1];
    MapOffset += 2;
    if (Type == 0x0000)
	{
	return(0);	/* EOF type */
	}
    Length = MapOffset[0] * 256 + MapOffset[1];
    MapOffset += 2;
    /* idiot check to make sure the file isn't short */
    if (MapOffset + Length > MapMax)
	{
	printf("FATAL: CORRUPT bSAM Cache file: %s (file too short)\n",
		MS.Label[Which].Filename);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}

#if DEBUG
    if (Verbose >= 3) printf("Loading[%d]: Type=%04x Length=%04lx\n",Which,Type,Length);
#endif
    switch(Type)
      {
      case 0x0000:	/* EOF */
	return(0);	/* EOF -- should never get here */
      case 0x0001:	/* File name */
    	MS.Label[Which].Filename = (char *)MapOffset;
	MS.Symbols[Which].SymbolMax = 0;
	break;
      case 0x0101:	/* Section name */
    	MS.Label[Which].Sectionname = (char *)MapOffset;
	/* Init all section items */
	MS.Label[Which].SectionStart = 0;
	MS.Label[Which].SectionEnd = -1;
	MS.Label[Which].SectionUnique = NULL;
	MS.Label[Which].SectionUniqueKey = -1;
	MS.Symbols[Which].SymbolMax = 0;
	MS.Symbols[Which].SymbolRealSizeLen = 0;
	MS.Symbols[Which].SymbolRealSize = NULL;
	MS.Label[Which].SymbolORMax = 0;
	MS.Label[Which].SymbolOR = NULL;
	MS.Label[Which].SymbolANDMax = 0;
	MS.Label[Which].SymbolAND = NULL;
	break;
#if 0
      /* not implemented yet */
      case 0x0002:	/* File checksum */
      case 0x0003:	/* File license */
      case 0x0103:	/* Section license */
    	break;
#endif
      case 0x0004:	/* File type */
      case 0x0104:	/* Section type (overrides File type) */
    	MS.Label[Which].Tokentype = (char *)MapOffset;
    	MS.Label[Which].TokentypeLen = Length;
	break;
      case 0x0108:	/* Section tokens */
	MS.Symbols[Which].SymbolMax = Length/2; /* 2 bytes per token */
	MS.Symbols[Which].SymbolBase = (uint16_t *)(MapOffset);
	MS.Symbols[Which].Symbol = MS.Symbols[Which].SymbolBase;
	MS.Symbols[Which].SymbolStart = 0;
	MS.Symbols[Which].SymbolEnd = MS.Symbols[Which].SymbolMax;
	break;
      case 0x0110:	/* Section unique */
	MS.Label[Which].SectionUnique = (char *)MapOffset;
	MS.Label[Which].SectionUniqueKey = -1;
	break;
      case 0x0118:	/* OR tokens */
	MS.Label[Which].SymbolORMax = Length/2; /* 2 bytes per token */
	MS.Label[Which].SymbolOR = (uint16_t *)(MapOffset);
	break;
      case 0x0128:	/* AND tokens */
	MS.Label[Which].SymbolANDMax = Length/2; /* 2 bytes per token */
	MS.Label[Which].SymbolAND = (uint16_t *)(MapOffset);
	break;
      case 0x0131:	/* start location */
	MS.Label[Which].SectionStart = 0;
	for(i=0; i<Length; i++)
	  {
	  MS.Label[Which].SectionStart = MS.Label[Which].SectionStart * 256 + MapOffset[i];
	  }
	break;
      case 0x0132:	/* end location */
	MS.Label[Which].SectionEnd = 0;
	for(i=0; i<Length; i++)
	  {
	  MS.Label[Which].SectionEnd = MS.Label[Which].SectionEnd * 256 + MapOffset[i];
	  }
	break;
      case 0x0138:	/* Offsets between tokens */
	MS.Symbols[Which].SymbolRealSize = (unsigned char *)MapOffset;
	MS.Symbols[Which].SymbolRealSizeLen = Length;
	break;
      case 0x0140:	/* precomputed match (phrase) */
	if (Show0140 && (Which == 0))
	  {
	  /* Phrases ALWAYS begin and end with one or more spaces */
	  /* Rather than counting spaces, count every non-space that
	     comes after a space */
	  int Len=0; /* Len = number of tokens in the string */
	  int HasSpace=1;
	  for(i=0; i < Length; i++)
	    {
	    if (isspace(MapOffset[i])) HasSpace=1;
	    else if (HasSpace) { Len++; HasSpace=0; }
	    }

	  MS.Matrix.MatrixMax = Len;
	  MS.Matrix.MatrixMinPos[0] = 1;
	  MS.Matrix.MatrixMaxPos[0] = Len;
	  MS.Matrix.MatrixMinPos[1] = 1;
	  MS.Matrix.MatrixMaxPos[1] = Len;
	  MS.Symbols[1].SymbolStart = 0;
	  MS.Symbols[1].SymbolEnd = Len;
	  MS.Label[1].Sectionname=(char *)MapOffset;
	  MS.Label[1].SectionStart=1;
	  MS.Label[1].SectionEnd=Length;
	  sprintf(MS.Path.PathString[0],"%ld-%ld",MS.Label[0].SectionStart,MS.Label[0].SectionEnd);
	  sprintf(MS.Path.PathString[1],"%ld-%ld",MS.Label[1].SectionStart,MS.Label[1].SectionEnd);
	  VerboseStats(1);
	  } /* case 0x0140 */
	break;
      case 0x1FF:	/* end of record */
	break;
      default:
    	break;	/* not implemented */
      }
#if 0
    if (Verbose > 0)
      {
      printf("%d Loaded: (%s: %ld: %lX)  (%s: %ld: %lX)\n",
	Which,
	MS.Label[0].Sectionname,MS.Symbols[0].SymbolMax,MS.Label[0].MmapOffset,
	MS.Label[1].Sectionname,MS.Symbols[1].SymbolMax,MS.Label[1].MmapOffset);
      }
#endif
    MapOffset += Length;
    } while(Type != 0x0108); /* while Type is not token data */

  /* Idiot checking */
  if (MS.Symbols[Which].SymbolRealSizeLen && (MS.Symbols[Which].SymbolRealSizeLen != MS.Symbols[Which].SymbolMax))
	{
	printf("FATAL: BAD bSAM offsets: %s :: %d should be %d\n",
		MS.Label[Which].Filename,MS.Symbols[Which].SymbolRealSizeLen,MS.Symbols[Which].SymbolMax);
	MS.Symbols[Which].SymbolRealSizeLen=0;
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}

  /* Optimization: Move all "can we compare these functions" to here.
     This reduces the number of function calls. */
  if (MS.Symbols[Which].SymbolMax < MatchLen[Which]) goto GetNext; /* need tokens */
  if (Which == 1)
	{
#if DEBUG
	if (Verbose > 2)
		{
		printf("Comparing:\n");
		printf("  Label.TokentypeLen: %d = %d\n",
			MS.Label[0].TokentypeLen,MS.Label[1].TokentypeLen);
		printf("  Label.Tokentype: %s = %s\n",
			MS.Label[0].Tokentype,MS.Label[1].Tokentype);
		printf("  Symbols.SymbolMax: %d = %d\n",
			MS.Symbols[0].SymbolMax,MS.Symbols[1].SymbolMax);
		printf("  MatchThreshold: %d = %d\n",
			MatchThreshold[0],MatchThreshold[1]);
		printf("  Scale0: %d = %d\n",
			MS.Symbols[0].SymbolMax * MatchThreshold[0],MS.Symbols[1].SymbolMax*100);
		printf("  Scale1: %d = %d\n",
			MS.Symbols[1].SymbolMax * MatchThreshold[1],MS.Symbols[0].SymbolMax*100);
		}
	else if (Verbose > 1)
		{
		printf("Comparing: A=%s:%s  B=%s:%s\n",
			MS.Label[0].Filename,MS.Label[0].Sectionname,
			MS.Label[1].Filename,MS.Label[1].Sectionname);
		}
#endif
	/* if wrong token type */
	if (MS.Label[0].TokentypeLen != MS.Label[1].TokentypeLen) goto GetNext;
	if (memcmp(MS.Label[0].Tokentype,MS.Label[1].Tokentype,MS.Label[0].TokentypeLen)) goto GetNext;
	/* if lengths will never match */
	if (MS.Symbols[0].SymbolMax * MatchThreshold[0] > MS.Symbols[1].SymbolMax*100) goto GetNext;
	if (MS.Symbols[1].SymbolMax * MatchThreshold[1] > MS.Symbols[0].SymbolMax*100) goto GetNext;

	/* set initial range */
	MS.Symbols[1].SymbolStart=0;
	MS.Symbols[1].SymbolEnd=MS.Symbols[1].SymbolMax;

	/* check if required tokens are present */
	if ((MS.Label[1].SymbolORMax > 0) && !CheckTokensOR(1)) goto GetNext;
	if ((MS.Label[0].SymbolORMax > 0) && !CheckTokensOR(0)) goto GetNext;
	if ((MS.Label[1].SymbolANDMax > 0) && !CheckTokensAND(1)) goto GetNext;
	if ((MS.Label[0].SymbolANDMax > 0) && !CheckTokensAND(0)) goto GetNext;
	/* optimize matrix scan range */
#if 0
	/** ExtremeTokens is supposed to be an optimization function.
	    However, gprof says it is slow.  Without it, ComputeMatrix
	    takes a fraction longer, but ExtremeTokens was taking the same
	    time as ComputeMatrix!.  It is faster to not use the
	    optimization. **/
	ExtremeTokens(0);
	ExtremeTokens(1);
#endif
	} /* if Which == 1 */
  else /* Which == 0 */
	{
	MS.Symbols[0].SymbolStart=0;
	MS.Symbols[0].SymbolEnd=MS.Symbols[0].SymbolMax;
	}

#if DEBUG
    if (Verbose > 1)
      {
      printf("%d Loaded: (%s: %d: %lX)  (%s : %s: %d: %lX)\n",
	Which,
	MS.Label[0].Sectionname,MS.Symbols[0].SymbolMax,MS.Label[0].MmapOffset,
	MS.Label[1].Filename,
	MS.Label[1].Sectionname,MS.Symbols[1].SymbolMax,MS.Label[1].MmapOffset);
      }
#endif

  MS.Label[Which].MmapOffset = MapOffset - RepFile[Which]->Mmap;
  return(1);
} /* LoadNextData() */

/**********************************************
 CloseFile(): Close a filename.
 **********************************************/
void	CloseFile	(int Which)
{
#if DEBUG
  if (Verbose > 1) fprintf(stderr,"Debug: closing[%d]\n",Which);
#endif
  RepMunmap(RepFile[Which]);
  RepFile[Which] = NULL;
  memset(RepFilename[Which],0,1024);
  MS.Label[Which].Filename = RepFilename[Which];
} /* CloseFile() */

/**********************************************
 OpenFile(): Open and mmap a file.
 Which = load as file 0 or file 1.
 Returns 0 on success, or -1 on failure.
 **********************************************/
int	OpenFile	(char *Filename, int Which)
{
  /* open the file (memory map) */
#if DEBUG
  if (Verbose > 1) fprintf(stderr,"Debug: opening[%d] %s\n",Which,RepFilename[Which]);
#endif

  CloseFile(Which);
  if (Filename)
    {
    memset(RepFilename[Which],0,1024);
    strcpy(RepFilename[Which],Filename);
    }

  if ((Pfile[Which] >= 0) && RepType)
    {
    /* Check if the file exists before trying to use it. */
    if (!RepExist(RepType,RepFilename[Which]))
	{
	fprintf(stderr,"WARNING: File not in the repository (%s %s)\n",
		RepType,RepFilename[Which]);
	RepFile[Which] = NULL;
	return(-1);
	}
    RepFile[Which] = RepMmap(RepType,RepFilename[Which]);
    if (RepFile[Which] == NULL)
	{
	/* Not able to open the repository file? */
	/* It is in the repository but cannot be accessed */
	fprintf(stderr,"ERROR: Unable to open repository (%s %s)\n",
		RepType,RepFilename[Which]);
	return(-1);
	}
    } /* if Type is set */
  else
    {
    /* no Type == Allocate it myself! */
    /** Use the same code as found in RepMmap **/
    RepFile[Which] = RepMmapFile(RepFilename[Which]);
    if (!RepFile[Which]) { return(-1); }
    }

  SetData(Which);
  return(0);
} /* OpenFile() */

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or
 NULL at \0.
 **********************************************/
char *	GetFieldValue	(char *Sin, char *Field, int FieldMax,
			 char *Value, int ValueMax)
{
  int s,f,v;
  int GotQuote;

  memset(Field,0,FieldMax);
  memset(Value,0,ValueMax);

  while(isspace(Sin[0])) Sin++; /* skip initial spaces */
  if (Sin[0]=='\0') return(NULL);
  f=0; v=0;

  for(s=0; (Sin[s] != '\0') && !isspace(Sin[s]) && (Sin[s] != '='); s++)
    {
    Field[f++] = Sin[s];
    }
  while(isspace(Sin[s])) s++; /* skip spaces after field name */
  if (Sin[s] != '=') /* if it is not a field, then just return it. */
    {
    return(Sin+s);
    }
  if (Sin[s]=='\0') return(NULL);
  s++; /* skip '=' */
  while(isspace(Sin[s])) s++; /* skip spaces after '=' */
  if (Sin[s]=='\0') return(NULL);

  GotQuote='\0';
  if ((Sin[s]=='\'') || (Sin[s]=='"'))
    {
    GotQuote = Sin[s];
    s++; /* skip quote */
    if (Sin[s]=='\0') return(NULL);
    }
  if (GotQuote)
    {
    for( ; (Sin[s] != '\0') && (Sin[s] != GotQuote); s++)
      {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
      }
    s++; /* move past the quote */
    }
  else
    {
    /* if it gets here, then there is no quote */
    for( ; (Sin[s] != '\0') && !isspace(Sin[s]); s++)
      {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
      }
    }
  while(isspace(Sin[s])) s++; /* skip spaces */
  return(Sin+s);
} /* GetFieldValue() */

/**********************************************
 ReadLine(): Read a single line from a file.
 Used to read from stdin.
 Process line elements.
 Returns: 1 of read data, 0=no data, -1=EOF.
 NOTE: It only returns 1 if a filename changes!
 **********************************************/
int	ReadLine	(FILE *Fin)
{
  int C='@';
  int i=0;	/* index */
  char FullLine[MAXLINE];
  char Field[MAXLINE];
  char Value[MAXLINE];
  char *FieldInset;
  int rc=0;	/* assume no data */

  /* If we inserted, then analyze the table */
  if (DBInsertCount > 0)
    {
#if 0
    /** Disabled: Database will take care of this **/
    DBaccess(DB,"ANALYZE agent_lic_meta;");
#endif
    DBInsertCount=0;
    }

  memset(FullLine,0,MAXLINE);
  /* inform scheduler that we're ready for data */
  printf("OK\n");
  alarm(60);
  HeartbeatValue = -1;
  fflush(stdout);

  if (feof(Fin))
    {
    return(-1);
    }
  while(!feof(Fin) && (i < MAXLINE-1) && (C != '\n') && (C>0))
    {
    C=fgetc(Fin);
    if ((C>0) && (C!='\n'))
      {
      FullLine[i]=C;
      i++;
      }
    else if ((C=='\n') && (i==0))
      {
      C='@';  /* ignore blank lines */
      }
    }
  if ((i==0) && feof(Fin)) return(-1);

  /* process the line. */
  /** line format: field='value' **/
  /** Known fields:
      A='Afilename in repository'
      B='Bfilename in repository'
      Akey='pfile key for A'
      Bkey='pfile key for B'
   **/
  FieldInset = FullLine;
  while((FieldInset = GetFieldValue(FieldInset,Field,MAXLINE,Value,MAXLINE)) != NULL)
    {
    /* process field/value */
    if (!strcasecmp(Field,"A"))
      {
      /* only change the data if the filename changes */
      if (strcmp(RepFilename[0],Value))
	{
	if (OpenFile(Value,0) != 0)
	  {
	  RepFilename[0][0]='\0';
	  return(0);
	  }
	rc=1;
	}
      }
    else if (!strcasecmp(Field,"B"))
      {
      /* only change the data if the filename changes */
      if (strcmp(RepFilename[1],Value))
	{
	if (OpenFile(Value,1) != 0)
	  {
	  RepFilename[1][0]='\0';
	  return(0);
	  }
	rc=1;
	}
      }
    else if (!strcasecmp(Field,"Akey")) { Pfile[0] = atol(Value); }
    else if (!strcasecmp(Field,"Bkey")) { Pfile[1] = atol(Value); }
    }
  return(rc);
} /* ReadLine() */

/************************************************************/
/************************************************************/

/**********************************************
 SAMfiles(): Given two files, compute SAM.
 This is a byte-by-byte comparison.
 It can get VERY slow if the files are large.
 **********************************************/
void	SAMfiles	()
{
  int HasMatch;
  int rc;
  matrixstate RMS;	/* original/real matrix configuration */

  /* idiot checking: both files must exist */
  if (!RepFile[0] || !RepFile[1]) return;

  switch(OutputFormat)
    {
    case 's': case 'N':
#if BEGIN_COMMIT
	printf("DB: BEGIN;\n");
	fflush(stdout);
#endif
	ReadOK();
	break;
    case 'n':
#if BEGIN_COMMIT
	rc = MyDBaccess(DB,"BEGIN;");
	if (rc < 0) ShowSQLERROR("BEGIN;",0);
#endif
	break;
    default:
    	break;
    }

  /* Now process the files */
  SetData(0);
  HasMatch=0;
  InitMatrixState(&RMS);
  RMS.Symbols[0].SymbolStart = MS.Symbols[0].SymbolStart;
  RMS.Symbols[0].SymbolEnd = MS.Symbols[0].SymbolEnd;
  while(LoadNextData(0,!HasMatch))
    {
    HasMatch=0;
    /* don't even load the ones that are too small */
    if (MS.Symbols[0].SymbolMax >= MatchLen[0])
      {
      SetData(1);
      while(LoadNextData(1,0))
	{
	/* ALL idiot checking moved to LoadNextData */
	MS.Symbols[0].SymbolStart=0;
	MS.Symbols[0].SymbolEnd=MS.Symbols[0].SymbolMax;
	/** Checking same function types moved to LoadNextData **/
#if DEBUG
	if (Verbose > 1)
		{
		printf("Loaded: (%s: %d)  (%s: %d)\n",
			MS.Label[0].Sectionname,MS.Symbols[0].SymbolMax,
			MS.Label[1].Sectionname,MS.Symbols[1].SymbolMax);
		}
#endif
	if (ComputeMatrix())
	  {
	  /* ok, it matched */
	  HasMatch=1;
	  GetPathString(0);
	  GetPathString(1);
	  VerboseStats(0);
	  } /* if ComputeMatrix */
	} /* while(LoadNextData(1)) */
      }
    /* BIG "WATCHOUT"
       LoadNextData called ExtremeTokens.  This modified the range for A.
       Need to reset A's range. */
    MS.Symbols[0].SymbolStart = RMS.Symbols[0].SymbolStart;
    MS.Symbols[0].SymbolEnd = RMS.Symbols[0].SymbolEnd;
    } /* while(LoadNextData(0)) */

  switch(OutputFormat)
    {
    case 's': case 'N':
	/* Later, when SAM is implemented in the DB, this will change */
	if (Pfile[0] > 0) printf("DB: UPDATE agent_lic_status SET processed = 'TRUE' where pfile_fk = '%ld';\n",Pfile[0]);
	if (Pfile[1] > 0) printf("DB: UPDATE agent_lic_status SET processed = 'TRUE' where pfile_fk = '%ld';\n",Pfile[1]);
#if BEGIN_COMMIT
	printf("DB: COMMIT;\n");
#endif
	fflush(stdout);
	ReadOK();
	break;
    case 'n':
	/* The "FOR UPDATE" clause locks the table */
	sprintf(SQL,"SELECT * FROM agent_lic_status WHERE pfile_fk = '%ld' FOR UPDATE;\n",Pfile[0]);
	rc = MyDBaccess(DB,SQL);
	if (rc < 0) ShowSQLERROR(SQL,0);
	sprintf(SQL,"UPDATE agent_lic_status SET processed = 'TRUE' WHERE pfile_fk = '%ld';\n",Pfile[0]);
	rc = MyDBaccess(DB,SQL);
	if (rc < 0) ShowSQLERROR(SQL,0);
#if BEGIN_COMMIT
	rc = MyDBaccess(DB,"COMMIT;");
	if (rc < 0) ShowSQLERROR("COMMIT;",0);
#endif
	break;
    default:
    	break;
    }
  FreeMatrixState(&RMS);
} /* SAMfiles() */

/************************************************************/
/************************************************************/

/**********************************************
 SAMfilesExhaustiveB(): Given a file (B), compute SAM.
 This is a byte-by-byte comparison.
 It can get VERY slow if the files are large.
 Making it slower, this is an EXHAUSTIVE search.
 It will return the best match for each segment.
 This assumes that "A" has already been loaded.
 THIS IS RECURSIVE!  It modifies global symbol pointers.
 **********************************************/
int	SAMfilesExhaustiveB	()
{
#if DEBUG_RECURSION
  static int Depth=0;
#endif

  /* store current values */
  matrixstate RMS;	/* original/real matrix configuration */

  /* store best matches */
  matrixstate BMS;	/* best matrix match */
  int HasMatch=0;
  int BestCmp;
  int GoodCount;	/* how many good matches have been seen? */

  /* don't even load the ones that are too small */
  if (MS.Symbols[0].SymbolEnd < MatchLen[0]) return(0);
  if (MS.Symbols[0].SymbolEnd < 1) return(0);

  /* save the existing symbol lists */
  SetData(1);
  InitMatrixState(&BMS);
  InitMatrixState(&RMS);
  CopyMatrixState(&MS,&RMS,1);
  MS.Matrix.MatrixMax=0;

  HasMatch=0;
  GoodCount=0;
  while(LoadNextData(1,0))
	{
	RMS.Label[1].MmapOffset = MS.Label[1].MmapOffset;
	/* ALL idiot checking moved to LoadNextData */
	/** Checking same function types moved to LoadNextData **/
#if DEBUG
	if (Verbose > 1)
		{
		printf("Loaded: (%s: %d)  (%s %d)\n",
			MS.Label[0].Sectionname,MS.Symbols[0].SymbolMax,
			MS.Label[1].Sectionname,MS.Symbols[1].SymbolMax);
		}
#endif
	if (MS.Symbols[0].SymbolEnd && MS.Symbols[1].SymbolEnd)
	if (ComputeMatrix())
	  {
	  /* Save best match value */
	  /* Determine:
	     IF it has a better percentage OR
	     it has the same percentage, but more tokens that match */
	  GoodCount++;
	  BestCmp = (MS.Matrix.MatrixMax > BMS.Matrix.MatrixMax);
	  if (!BestCmp && (MS.Matrix.MatrixMax == BMS.Matrix.MatrixMax))
	    {
	    /* If same match, choose the one with the fewest skipped */
	    BestCmp = (MS.Matrix.MatrixMaxPos[1]-MS.Matrix.MatrixMinPos[1]) <
		      (BMS.Matrix.MatrixMaxPos[1]-BMS.Matrix.MatrixMinPos[1]);
	    }
	  if (BestCmp)
	    {
	    if (MS.Matrix.MatrixMinPos[0] < MS.Matrix.MatrixMaxPos[0])
		{
		/* save the best match */
		GetPathString(0);
		GetPathString(1);
#if DEBUG
		if (Verbose)
		  {
		  PrintRanges("SET BEST",0,1);
		  PrintRanges("SET BEST",1,1);
		  }
#endif
		CopyMatrixState(&MS,&BMS,1);
		HasMatch=1;
#if DEBUG
		if (Verbose)
		  {
		  printf("DEBUG: GetSeqRange: Found part %d - %d in full %d - %d\n",
		  BMS.Symbols[0].SymbolStart+BMS.Matrix.MatrixMinPos[0],
		  BMS.Symbols[0].SymbolStart+BMS.Matrix.MatrixMaxPos[0],
		  0,RMS.Symbols[0].SymbolMax);
		  }
#endif
		}
	    } /* if best match candidate */
	  } /* if ComputeMatrix */

	/* BIG "WATCHOUT"
	   LoadNextData called ExtremeTokens.  This modified the range for A.
	   Need to reset A's range. */
	MS.Symbols[0].SymbolStart = RMS.Symbols[0].SymbolStart;
	MS.Symbols[0].SymbolEnd = RMS.Symbols[0].SymbolEnd;
	} /* while(LoadNextData(1)) */

  /* Ok, we have the best match! */
  if (HasMatch)
    {
    /* restore the best */
    CopyMatrixState(&BMS,&MS,1);
#if DEBUG_RECURSION
    printf("%*s BEST: %d - %d\n",Depth,"",MS.Symbols[0].SymbolStart+MS.Path.MatrixPath[0][MS.Matrix.MatrixBestMin],MS.Symbols[0].SymbolStart+MS.Path.MatrixPath[0][MS.Matrix.MatrixBestMax]);
#endif
#if DEBUG
    if (Verbose) { printf("DEBUG: Got a best match\n"); }
#endif
    VerboseStats(0);

    /* Now, recurse on the two segments: before and after */
#if DEBUG
    if (Verbose)
      {
      printf("DEBUG: Full range: %d - %d\n",0,MS.Symbols[0].SymbolMax);
      printf("DEBUG: Middle match: %d - %d :: %s:%s\n",
	MS.Symbols[0].SymbolStart,MS.Symbols[0].SymbolStart + MS.Symbols[0].SymbolEnd,
	MS.Label[1].Filename,MS.Label[1].Sectionname);
      }
#endif

    /* recurse on BEFORE segement */
    if ((GoodCount > 1) && (BMS.Matrix.MatrixBestMin > 0))
      { /* BEFORE */
      CopyMatrixState(&BMS,&MS,0);
      /** Don't change MS.Symbols.SymbolStart -- keep the start **/
      MS.Symbols[0].SymbolEnd = MS.Symbols[0].SymbolStart+MS.Path.MatrixPath[0][MS.Matrix.MatrixBestMin]-1;
      if (MS.Symbols[0].SymbolEnd - MS.Symbols[0].SymbolStart >= MatchLen[0])
	{
	MS.Matrix.MatrixMinPos[0] = 0;
	MS.Matrix.MatrixMaxPos[0] = 0;
	MS.Matrix.MatrixBestMin=0;
	MS.Matrix.MatrixBestMax=0;
#if DEBUG
	if (Verbose)
	  {
	  PrintRanges("BEFORE",0,0);
	  PrintRanges("BEFORE",1,0);
	  }
#endif
#if DEBUG_RECURSION
	printf("%*s BEFORE: %ld - %ld\n",Depth,"",MS.Symbols[0].SymbolStart,MS.Symbols[0].SymbolEnd);
	Depth++;
#endif
	HasMatch |= SAMfilesExhaustiveB();
#if DEBUG_RECURSION
	Depth--;
#endif
	}
      } /* BEFORE */

    /* recurse on AFTER segement */
    if ((GoodCount > 1) && (BMS.Matrix.MatrixBestMax > BMS.Matrix.MatrixBestMin))
      { /* AFTER */
      CopyMatrixState(&BMS,&MS,0);
      /** Don't change MS.Symbols.SymbolEnd -- keep the end **/
      MS.Symbols[0].SymbolStart = MS.Symbols[0].SymbolStart+MS.Path.MatrixPath[0][MS.Matrix.MatrixBestMax]+1;
      if (MS.Symbols[0].SymbolEnd - MS.Symbols[0].SymbolStart >= MatchLen[0])
	{
	MS.Matrix.MatrixMinPos[0] = 0;
	MS.Matrix.MatrixMaxPos[0] = 0;
	MS.Matrix.MatrixBestMin=0;
	MS.Matrix.MatrixBestMax=0;
#if DEBUG
	if (Verbose)
	  {
	  PrintRanges("AFTER",0,0);
	  PrintRanges("AFTER",1,0);
	  }
#endif
	/* Here is the RECURSION! */
#if DEBUG_RECURSION
	printf("%*s AFTER: %ld - %ld\n",Depth,"",MS.Symbols[0].SymbolStart,MS.Symbols[0].SymbolEnd);
	Depth++;
#endif
	HasMatch |= SAMfilesExhaustiveB();
#if DEBUG_RECURSION
	Depth--;
#endif
	}
      } /* AFTER */

    } /* if BestMatch */

  /* put everything back */
  CopyMatrixState(&RMS,&MS,1);
  FreeMatrixState(&RMS);
  FreeMatrixState(&BMS);
#if DEBUG_RECURSION
  printf("%*s OUT: %ld - %ld\n",Depth,"",MS.Symbols[0].SymbolStart,MS.Symbols[0].SymbolEnd);
#endif
  return(HasMatch);
} /* SAMfilesExhaustiveB() */

/**********************************************
 SAMfilesExhaustive(): Given two files, compute SAM.
 This is a byte-by-byte comparison.
 It can get VERY slow if the files are large.
 Making it slower, this is an EXHAUSTIVE search.
 It will return the best match for each segment.
 **********************************************/
void	SAMfilesExhaustive	()
{
  int HasMatch;
  int rc;

  /* idiot checking: both files must exist */
  if (!RepFile[0] || !RepFile[1]) return;

  /* Now process the files */
  SetData(0);
  HasMatch=0;

  switch(OutputFormat)
    {
    case 's': case 'N':
#if BEGIN_COMMIT
	printf("DB: BEGIN;\n");
	fflush(stdout);
#endif
	ReadOK();
	break;
    case 'n':
#if BEGIN_COMMIT
	rc = MyDBaccess(DB,"BEGIN;");
	if (rc < 0) ShowSQLERROR("BEGIN;",0);
#endif
	break;
    default:
    	break;
    }

  while(LoadNextData(0,!HasMatch))
    {
    HasMatch = SAMfilesExhaustiveB();
    } /* while(LoadNextData(0)) */

  switch(OutputFormat)
    {
    case 's': case 'N':
	/* Later, when SAM is implemented in the DB, this will change */
	if (Pfile[0] > 0) printf("DB: UPDATE agent_lic_status SET processed = 'TRUE' where pfile_fk = '%ld';\n",Pfile[0]);
	if (Pfile[1] > 0) printf("DB: UPDATE agent_lic_status SET processed = 'TRUE' where pfile_fk = '%ld';\n",Pfile[1]);
#if BEGIN_COMMIT
	printf("DB: COMMIT;\n");
#endif
	fflush(stdout);
	ReadOK();
	break;
    case 'n':
	/* The "FOR UPDATE" clause locks the table */
	sprintf(SQL,"SELECT * FROM agent_lic_status WHERE pfile_fk = '%ld' FOR UPDATE;\n",Pfile[0]);
	rc = MyDBaccess(DB,SQL);
	if (rc < 0) ShowSQLERROR(SQL,0);
	sprintf(SQL,"UPDATE agent_lic_status SET processed = 'TRUE' where pfile_fk = '%ld';\n",Pfile[0]);
	rc = MyDBaccess(DB,SQL);
	if (rc < 0) ShowSQLERROR(SQL,0);
#if BEGIN_COMMIT
	rc = MyDBaccess(DB,"COMMIT;");
	if (rc < 0) ShowSQLERROR("COMMIT;",0);
#endif
	break;
    default:
    	break;
    }
} /* SAMfilesExhaustive() */

/************************************************************/
/************************************************************/
/** Main **/
/************************************************************/
/************************************************************/

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 TBD: When this engine is used for other things, we will need
 a switch statement for the different types of agents.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='license' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'license' from the database table 'agent'\n");
	fflush(stdout);
        DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('license','unknown','Analyze files for licenses');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'license' to the database table 'agent'\n");
	fflush(stdout);
        DBclose(DB);
	exit(-1);
	}
#if 0
      /** Disabled: Database will take care of this **/
      DBaccess(DB,"ANALYZE agent;");
#endif
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='license' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'license' from the database table 'agent'\n");
	fflush(stdout);
        DBclose(DB);
	exit(-1);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/**********************************************
 Usage(): Display program usage.
 **********************************************/
void	Usage	(char *Name)
{
  printf("Usage: %s [options] fileA fileB\n",Name);
  printf("  Compares fileA against fileB.\n");
  printf("  If either fileA or fileB is -, then a list of files are read from stdin.\n");
  printf("  Stdin format: field=value pairs, separated by spaces.\n");
  printf("    A=file        :: set fileA to be a pfile ID or regular file.\n");
  printf("    B=file        :: set fileB to be a pfile ID or regular file.\n");
  printf("    Akey=file_key :: set fileA pfile ID and this is the pfile_pk.\n");
  printf("    Bkey=file_key :: set fileB pfile ID and this is the pfile_pk.\n");
  printf("    NOTE: stdin can override fileA/fileB set on the command-line!\n");
  printf("    NOTE: To use the repository, the corresponding key must be set.\n");
  printf("    To turn off the repository, set the key to -1 (default value).\n");
  printf("  Matching options:\n");
  printf("    -A percent = percent of data1 that must match (default: -A 90)\n");
  printf("    -B percent = percent of data2 that must match (default: -B 90)\n");
  printf("    -C percent = same as '-A percent -B percent'\n");
  printf("    -E = Exhaustive search for best match\n");
  printf("    -G g = set maximum gap to g (default: -G 5)\n");
  printf("    -L n = set minimum sequence length to check to n (default: -L 10)\n");
  printf("    -M m = set minimum sequential to m (default: -M 10)\n");
  printf("          -M and -G work together.  We want a sequence of m aligned\n");
  printf("          symbols with a gap no larger than g.\n");
  printf("    -T t = set a repository type (for -O s and -O t)\n");
  printf("    -O f = set output format:\n");
  printf("           -O n = Normal DB -- (default -T is 'license')\n");
  printf("           -O N = Normal DB -- like '-O n' except uses stdout instead of DB\n");
  printf("           -O s = SAM DB -- (default -T is 'sam')\n");
  printf("           -O t = Text\n");
  printf("  Debugging options:\n");
  printf("    -i = Initialize the database, then exit.\n");
  printf("    -t file = Test a bsam file (for proper parsing), then exit.\n");
#if DEBUG
  printf("    -v = Verbose (-vv = more verbose, etc.)\n");
  printf("    -1 = Show matrix stage 1 (same)\n");
  printf("    -2 = Show matrix stage 2 (align)\n");
#endif
} /* Usage() */

/**********************************************
 main():
 **********************************************/
int	main	(int argc, char *argv[])
{
  int c;

  while((c = getopt(argc,argv,"A:B:C:EG:iL:M:O:T:t:v12")) != -1)
    {
    switch(c)
      {
      case 'A':	MatchThreshold[0]=atoi(optarg);	break;
      case 'B':	MatchThreshold[1]=atoi(optarg);	break;
      case 'C':	
	MatchThreshold[0]=atoi(optarg);
	MatchThreshold[1]=MatchThreshold[0];
	break;
      case 'E': /* exhaustive search */
	ExhaustiveSearch=1;
	break;
      case 'G':	
	MatchGap[0]=atoi(optarg);
	MatchGap[1]=MatchGap[0];
	break;
      case 'i':
	DB = DBopen();
	if (!DB)
	  {
	  fprintf(stderr,"FATAL: Unable to open DB\n");
	  exit(-1);
	  }
	GetAgentKey();
	DBSetPhrase();
	DBclose(DB);
	return(0);
      case 'L':	
	MatchLen[0]=atoi(optarg);
	MatchLen[1]=MatchLen[0];
	break;
      case 'M':	
	MatchSeq[0]=atoi(optarg);
	MatchSeq[1]=MatchSeq[0];
	break;
      case 'O':
	switch(optarg[0])
	  {
	  case 'n':
		/* Normal for DB */
		OutputFormat='n';
		if (RepType == NULL) RepType = RepDEFAULT[1];
		DB = DBopen();
		if (!DB)
		  {
		  fprintf(stderr,"FATAL: Unable to open DB\n");
		  exit(-1);
		  }
		GetAgentKey();
		DBSetPhrase();
		break;
	  case 'N':
		/* Normal for DB */
		OutputFormat='N';
		DB = DBopen();
		if (!DB)
		  {
		  fprintf(stderr,"FATAL: Unable to open DB\n");
		  exit(-1);
		  }
		if (RepType == NULL) RepType = RepDEFAULT[1];
		GetAgentKey();
		DBSetPhrase();
		break;
	  case 's':
		/* SAM for DB -- TBD */
		OutputFormat='s';
		if (RepType == NULL) RepType = RepDEFAULT[2];
		break;
	  case 't':	OutputFormat='t'; break; /* Text (for debugging) */
	  default:
		Usage(argv[0]);
		fflush(stdout);
		DBclose(DB);
		exit(-1);
	  }
	break;
      case 'T':
	RepType=optarg;
	break;
      case 't':
	{
	/* Test a file, then exit */
	if (OpenFile(optarg,0) != 0)
	  {
	  printf("FATAL: File '%s' failed to open.\n",optarg);
	  fflush(stdout);
	  DBclose(DB);
	  exit(-1);
	  }
	/* It's open, so read it all */
	while(LoadNextData(0,0)) ;
	DBclose(DB);
	return(0);
	}
#if DEBUG
      case '1':	ShowStage1Flag=1;	break;
      case '2':	ShowStage2Flag=1;	break;
      case 'v':	Verbose++;	break;
#endif
      default:
	Usage(argv[0]);
	DBclose(DB);
	exit(-1);
      } /* switch */
    } /* while(getopt) */

  if (optind+2 != argc)
	{
	Usage(argv[0]);
	DBclose(DB);
	exit(-1);
	}

  if (MatchLen[0] < MatchSeq[0]) MatchLen[0] = MatchSeq[0];
  if (MatchLen[1] < MatchSeq[1]) MatchLen[1] = MatchSeq[1];
#if DEBUG
  if (Verbose)
    {
    printf("Debug options: -A %d -B %d -G %d -L %d -M %d\n",
	MatchThreshold[0],MatchThreshold[1],
	MatchGap[0],MatchLen[0],MatchSeq[0]);
    printf("  MatchThreshold: A=%d  B=%d\n",
	MatchThreshold[0],MatchThreshold[1]);
    printf("  MatchGap: A=%d  B=%d\n",
	MatchGap[0],MatchGap[1]);
    printf("  MatchLen: A=%d  B=%d\n",
	MatchLen[0],MatchLen[1]);
    printf("  MatchSeq: A=%d  B=%d\n",
	MatchSeq[0],MatchSeq[1]);
    }
#endif

  signal(SIGALRM,ShowHeartbeat);

  /* Allocate lots of memory (limits number of realloc calls) */
  InitMatrixState(&MS);
  MS.Symbols[0].SymbolMax=200;
  MS.Symbols[1].SymbolMax=200;
  SetMatrix();

  /** Four cases for running: either may come from command-line **/
  if (strcmp(argv[optind],"-") && strcmp(argv[optind+1],"-"))
    {
    /* simple case: both are regular filenames */
    /* do the file comparisons */
    if (OpenFile(argv[optind+0],0) != 0) { DBclose(DB); exit(-1); }
    if (OpenFile(argv[optind+1],1) != 0) { DBclose(DB); exit(-1); }
    if (ExhaustiveSearch)	SAMfilesExhaustive();
    else	SAMfiles();
    }
  else if (!strcmp(argv[optind],"-") && strcmp(argv[optind+1],"-"))
    {
    /* first file comes from stdin */
    if (OpenFile(argv[optind+1],1) != 0) { DBclose(DB); exit(-1); }
    while(!feof(stdin))
      {
      FreeMatrix();
      if (ReadLine(stdin) > 0)
	{
	if (ExhaustiveSearch)	SAMfilesExhaustive();
	else	SAMfiles();
	}
      }
    }
  else if (strcmp(argv[optind],"-") && !strcmp(argv[optind+1],"-"))
    {
    /* second file comes from stdin */
    strcpy(RepFilename[0],argv[optind]);
    if (OpenFile(argv[optind+0],0) == 0)
	{
	while(!feof(stdin))
	  {
	  FreeMatrix();
	  if (ReadLine(stdin) > 0)
	    {
	    if (ExhaustiveSearch)	SAMfilesExhaustive();
	    else	SAMfiles();
	    }
	  }
	}
    }
  else /* both are "-" */
    {
    /* both file comes from stdin -- same line, space deliminated */
    while(!feof(stdin))
      {
      FreeMatrix();
      if (ReadLine(stdin) > 0)
	{
	if (ExhaustiveSearch)	SAMfilesExhaustive();
	else	SAMfiles();
	} /* if readline */
      } /* while data on stdin */
    } /* if both are - */
  FreeMatrix();
  FreeMatrixState(&MS);
  CloseFile(0);
  CloseFile(1);
  if (DB) DBclose(DB);
  return(0);
} /* main() */

