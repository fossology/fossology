/*****************************************************
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

 sam2bsam: convert a SAM cache file to a bSAM cache file.
 *****************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <stdint.h>
#include <string.h>

#define MAXCKSUM 100000

#define MAXSTRING	65535

/*****************************************************
 ComputeCksum(): Given a string, computer the checksum.
 *****************************************************/
uint16_t	ComputeCksum	(char *String)
{
  uint16_t Sum=0;
  int i;
  int StringLen;
  StringLen = strlen(String);
  for(i=0; i<StringLen; i+=2)
    {
    Sum = Sum + String[i]*256;
    if (i+1 < StringLen) Sum = Sum + String[i+1];
    }
  return(Sum);
} /* ComputeCksum() */

/*****************************************************
 ReadLine(): Read a single line from a file.
 Returns the line length.
 Returns -1 if eof.
 *****************************************************/
int	ReadLine	(FILE *Fin, char *String, int MaxString)
{
  int C,i;
  memset(String,'\0',MaxString);
  C=fgetc(Fin);
  if (C < 0) return(-1);
  i=0;
  while((C != -1) && (C != '\n'))
    {
    if (i < MAXSTRING-1)
      {
      String[i] = C;
      i++;
      }
    C=fgetc(Fin);
    }
  String[i]='\0';
  return(i);
} /* ReadLine() */

/*****************************************************
 WriteBinaryType(): Generate the type of data.
 *****************************************************/
void	WriteBinaryType	(FILE *Fout, char *Typename)
{
  int Len,i;

  fputc(0x00,Fout); fputc(0x04,Fout); /* Type 0x0004 = File type */
  Len = strlen(Typename)+1;
  fputc((Len/256) & 0xff,Fout);
  fputc((Len%256) & 0xff,Fout);
  for(i=0; i<Len; i++) fputc(Typename[i],Fout);
} /* WriteBinaryType() */

/*****************************************************
 WriteBinaryFunction(): Generate the function's data.
 *****************************************************/
void	WriteBinaryFunction	(FILE *Fout, char *FunctionName,
				 int CkSumMax, uint16_t *Cksums)
{
  int Len,i;

  /* write the function name */
  fputc(0x01,Fout); fputc(0x01,Fout); /* Type 0x0101 = Function name */
  Len = strlen(FunctionName)+1;
  fputc((Len/256) & 0xff,Fout);
  fputc((Len%256) & 0xff,Fout);
  for(i=0; i<Len; i++) fputc(FunctionName[i],Fout);
  /* byte alignment */
  if (Len % 2 == 1) fputc(0xff,Fout);

  /* write all the function data */
  fputc(0x01,Fout); fputc(0x08,Fout); /* Type 0x0108 = Function tokens */
  Len = CkSumMax*2; /* 2 bytes per data point */
  fputc((Len >> 8) & 0xff,Fout);
  fputc(Len & 0xff,Fout);
  for(i=0; i<CkSumMax; i++)
    {
    fputc((Cksums[i]/256) & 0xff,Fout);
    fputc((Cksums[i]%256) & 0xff,Fout);
    }
} /* WriteBinaryFunction() */

/*****************************************************
 ProcessFile(): Given a file, convert it to a binary file.
 *****************************************************/
int	ProcessFile	(FILE *Fin, FILE *Fout)
{
  char FunctionName[MAXSTRING];
  char Line[MAXSTRING];
  int Size;
  uint16_t Cksums[MAXCKSUM];	/* assume it is big enough */
  int CkSumMax=0;	/* number of Cksums loaded */

  if (ReadLine(Fin,FunctionName,MAXSTRING) < 0) return(-1);
  Size = ReadLine(Fin,Line,MAXSTRING);
  while(Size >= 0)
    {
    /* if there is data for a function, create the checksum */
    if (Size > 0)
      {
      if (CkSumMax < MAXCKSUM)
	{
	Cksums[CkSumMax] = ComputeCksum(Line);
	CkSumMax++;
	}
      }
    else /* if Size == 0 */
      {
      /* Display function */
      WriteBinaryFunction(Fout,FunctionName,CkSumMax,Cksums);
      CkSumMax = 0;
      if (ReadLine(Fin,FunctionName,MAXSTRING) < 0) return(-1);
      }

    Size = ReadLine(Fin,Line,MAXSTRING);
    }
  if (CkSumMax > 0) WriteBinaryFunction(Fout,FunctionName,CkSumMax,Cksums);
  return(0);
} /* ProcessFile() */

/*********************************************************************/
int	main	(int argc, char *argv[])
{
  if (argc != 2)
    {
    fprintf(stderr,"Usage: %s filetype\n",argv[0]);
    exit(-1);
    }
  WriteBinaryType(stdout,argv[1]);
  ProcessFile(stdin,stdout);
  return(0);
} /* main() */

