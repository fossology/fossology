/**************************************************************
 bSAM list: List contents of a bsam cache file.

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
 This program is used for debugging.
 Returns 0 if the bsam file is good, 1 if the file is bad.
 **************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <stdint.h>
#include <ctype.h>
#include <string.h>
#include <errno.h>
#include <sys/mman.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>


/************************************************************
 Globals: used for speed!
 ************************************************************/
int ProgramRC=0;	/* program return code */

uint16_t *Symbol;	/* the set of symbols */
u_int SymbolMax=0;	/* number of symbols */

int	Verbose=0;	/* debugging via '-v' */
int	Show1SL=1;	/* show 1sl */

/* for multiple datasets per file... */
int	FileHandle=-1;
u_int	MmapOffset=0;
int	MmapSize=0;
unsigned char	*Mmap=NULL;
char	*Filename;
char	*Functionname;
char	*Tokentype;
int	TokentypeLen = 0;
u_long	RawStart=0,RawEnd=0;


/************************************************************/
/************************************************************/
/** Data loading and processing **/
/************************************************************/
/************************************************************/

/**********************************************
 LoadNextData(): Given a file containing data,
 load the data!
 This stops when it gets to a function token block (type 18)
 Returns 0 if no data to load (EOF).
 Returns 1 if data!
 **********************************************/
int	LoadNextData	()
{
  int Type=0xff;
  u_int Offset;
  u_int Length;
  int i;

  Offset = MmapOffset;
  while(Type != 0x0108)
    {
    if (Offset >= MmapSize)	return(0); /* no more data */

    /* read a label */
    if (Mmap[Offset] == 0xff) Offset++;
    Type = Mmap[Offset] * 256 + Mmap[Offset+1];
    Offset += 2;
    if (Offset >= MmapSize)
	{
	printf("ERROR: Read type with no length.\n");
	ProgramRC=1;
	return(0);
	}
    if (Type == 0x0000) return(0);	/* EOF type */
    Length = Mmap[Offset] * 256 + Mmap[Offset+1];
    Offset += 2;
    if (Offset+Length > MmapSize)
	{
	printf("ERROR: Length goes beyond file size.\n");
	ProgramRC=1;
	return(0);
	}

    if (Verbose >= 3) printf("Loading: Type=%04x Length=%04x\n",Type,Length);
    switch(Type)
      {
      case 0x0000:	/* EOF */
	return(0);	/* EOF -- should never get here */
      case 0x0001:	/* File name */
    	Filename = (char *)(Mmap+Offset);
	printf("\nFile: '%s'\n",Filename);
	RawStart=0;
	RawEnd=0;
	break;
      case 0x0101:	/* Function name */
    	Functionname = (char *)(Mmap+Offset);
	break;
      case 0x0010:	/* File unique */
      case 0x0110:	/* Function unique */
	printf("  Unique: '%s'\n",Mmap+Offset);
	break;
      case 0x0002:	/* File checksum */
      case 0x0003:	/* File license */
      case 0x0103:	/* Function license */
    	break;	/* not implemented yet */
      case 0x0004:	/* File type */
      case 0x0104:	/* Function type (overrides File type) */
    	Tokentype = (char *)(Mmap+Offset);
    	TokentypeLen = Length;
	break;
      case 0x0108:	/* Function tokens */
	if (Length % 2) { printf("ERROR: number of tokens is not divisible by two.\n"); }
	SymbolMax = Length/2; /* 2 bytes per token */
	printf("  Symbols: %d  (%d bytes)\n",SymbolMax,Length);
	Symbol = (uint16_t *)(Mmap+Offset);
	break;
      case 0x0118:	/* OR tokens */
	printf("  OR tokens: %d found\n",Length/2);
	break;
      case 0x0128:	/* AND tokens */
	printf("  AND tokens: %d found\n",Length/2);
	break;
      case 0x0131:	/* Function offset start in raw data */
        RawStart=0;
	for(i=0; i<Length; i++)
		RawStart = RawStart * 256 + Mmap[Offset+i];
	if (Verbose > 1) printf("  Start = %ld / 0x%lX\n",RawStart,RawStart);
	break;
      case 0x0132:	/* Function offset end in raw data */
        RawEnd=0;
	for(i=0; i<Length; i++)
		RawEnd = RawEnd * 256 + Mmap[Offset+i];
	if (Verbose > 1) printf("  End = %ld / 0x%lX\n",RawEnd,RawEnd);
	break;
      case 0x0138:	/* Offsets between tokens */
	printf("  Offset record found: %d entries\n",Length);
	break;
      case 0x0140:	/* Function's one-sentence license */
	if (Show1SL)
	  {
	  printf("  1SL: @ 0x%08lx - 0x%08lx '",RawStart,RawEnd);
	  for(i=Offset; Mmap[i] != '\0'; i++)
		{
		if (!Verbose && isspace(Mmap[i])) fputc(' ',stdout);
		else fputc(Mmap[i],stdout);
		}
	  printf("'\n");
	  }
	break;
      case 0x01ff:	/* End of function */
	if (Verbose) printf("EOF\n");
	break;
      default:
	printf("Type %04x (length %d) not implemented\n",Type,Length);
    	break;	/* not implemented */
      }
#if 0
    printf("Loaded: (%s: %u: %X: %u)\n",
	Functionname,SymbolMax,MmapOffset,Length);
#endif
    Offset += Length;
    } /* while Type is not token data */

  MmapOffset = Offset;
  return(1);
} /* LoadNextData() */

/**********************************************
 OpenFile(): Open and mmap a file.
 Returns file handle, or -1 on failure.
 **********************************************/
int	OpenFile	(char *Fname)
{
  int F;
  struct stat Stat;

  /* open the file (memory map) */
  if (Verbose > 1) fprintf(stderr,"Debug: opening %s\n",Fname);
  Filename = Fname;
  F = open(Filename,O_RDONLY);
  if (F == -1)
	{
	fprintf(stderr,"ERROR: Unable to open file (%s)\n",Filename);
	exit(-1);
	}
  if (fstat(F,&Stat) == -1)
	{
	fprintf(stderr,"ERROR: Unable to stat file (%s)\n",Filename);
	exit(-1);
	}
  MmapSize = Stat.st_size;
  MmapOffset = 0;
  Mmap = mmap(0,MmapSize,PROT_READ,MAP_PRIVATE,F,0);
  if (Mmap == MAP_FAILED)
	{
	fprintf(stderr,"ERROR: Unable to mmap file (%s)\n",Filename);
	exit(-1);
	}
  FileHandle=F;
  return(F);
} /* OpenFile() */

/**********************************************
 CloseFile(): Close a filename.
 **********************************************/
void	CloseFile	()
{
  if (Verbose > 1) fprintf(stderr,"Debug: closing\n");
  munmap(Mmap,MmapSize);
  close(FileHandle);
  FileHandle=-1;
} /* CloseFile() */


/**********************************************
 ShowFiles(): Given two files, compute SAM.
 This is a byte-by-byte comparison.
 It can get VERY slow if the files are large.
 **********************************************/
void	ShowFiles	()
{
  /* Now process the files */
  MmapOffset = 0;
  while(LoadNextData())
	{
	/* ensure that we're comparing apples to apples */
	printf("%s %s (%s,%d) @ 0x%08lx - 0x%08lx\n",
		Filename,Functionname,Tokentype,SymbolMax,
		RawStart,RawEnd);
	} /* while(LoadNextData()) */
} /* ShowFiles() */

/************************************************************/
/************************************************************/
/** Main **/
/************************************************************/
/************************************************************/

/**********************************************
 Usage(): Display program usage.
 **********************************************/
void	Usage	(char *Name)
{
  printf("Usage: %s [options] file1\n",Name);
  printf("  List contents of file1.\n");
  printf("  Debugging options:\n");
  printf("    -v = Verbose (-vv = more verbose, etc.)\n");
  printf("    -1 = disable display of phrases (one-sentence licenses)\n");
} /* Usage() */

/**********************************************
 main():
 **********************************************/
int	main	(int argc, char *argv[])
{
  int c;

  while((c = getopt(argc,argv,"v1")) != -1)
    {
    switch(c)
      {
      case 'v':	Verbose++;	break;
      case '1': Show1SL=0;	break;
      default:
	Usage(argv[0]);
	exit(-1);
      } /* switch */
    } /* while(getopt) */

  if ((optind+2 != argc) && (optind+1 != argc))
	{
	Usage(argv[0]);
	exit(-1);
	}

  while(optind < argc)
    {
    /* do the file comparisons */
    OpenFile(argv[optind]);
    ShowFiles();
    CloseFile();
    optind++;
    }
  return(ProgramRC);
} /* main() */

