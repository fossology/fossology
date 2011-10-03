/******************************************************************
 Copyright (C) 2007-2011 Hewlett-Packard Development Company, L.P.
 
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
 ******************************************************************/

/**
 * \file departition.c
 * \brief Extract a partition table from file systems.
 **/


#define _LARGEFILE64_SOURCE

#include <stdlib.h>
#include <errno.h>

/* specify support for files > 2G */
#ifndef __USE_LARGEFILE64
#define __USE_LARGEFILE64
#endif
#ifndef __USE_FILE_OFFSET64
#define __USE_FILE_OFFSET64
#endif

#include <stdio.h>
#include <stdint.h>
#include <unistd.h>
#include <limits.h>
#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
char Version[]=SVN_REV;
#endif

int	Test=0;	/* set to 0 to extract, 1 to just be verbose */
int	Verbose=0;

/**
 * \brief Extract a kernel file system and copy it to a new file 
 *        called "Kernel_%04d",Counter
 *
 * \param Fin Open file descriptor
 *
 *        The first linux kernels were files: vmlinux.
 *        The files got big, so they compressed them: vmlinux.gz
 *        Today, they are integrated into boot managers, so they
 *        actually are wrapped in a file system: vmlinux.gz + x86 sector = vmlinuz
 *        The location of the vmlinux.gz in vmlinuz varies based on
 *        boot options and versions.  (Assembly code is actually used to
 *        identify the offset.)
 *        So, we just scan until we find the start of the file.
 *        *.gz files begin with "1F 8B 08 00" or "1F 8B 08 08"
 *        Find the *.gz, then dump the entire file.
 *        (This is similar to the approach used by extract-ikconfig.)
 **/
void	ExtractKernel	(int Fin)
{
  long Hold;
  off_t ReadSize,WriteSize;
  unsigned char Buffer[655360], *Bp;
  /* file name */
  static int Counter=0;
  char Name[256];
  int Fout=-1;
  /* gz headers */
  unsigned char GZHead[2][4]={ {0x1f,0x8b,0x08,0x00},{0x1f,0x8b,0x08,0x08} };
  int GZindex=0;

  if (Test) return;

  /* save position */
  Hold = lseek(Fin,0,SEEK_CUR);

  /* seek header */
  GZindex=0;
  while(GZindex < 4)
  {
    ReadSize = read(Fin,Buffer+GZindex,1);
    if (ReadSize <= 0)
    {
      /* this will fall out */
      GZindex=0;
      break;
    }
    /* make sure I match the header! */
    if (!memcmp(Buffer,GZHead[0],GZindex+1) ||
        !memcmp(Buffer,GZHead[1],GZindex+1))
    {
      GZindex++;
    }
    else
    {
      GZindex=0;
    }
  }
  ReadSize=GZindex;

  if (ReadSize == 0) return; /* nothing to extract */
  if (ReadSize > 0)
  {
    /* prepare file for writing */
    memset(Name,0,sizeof(Name));
    snprintf(Name,250,"Kernel_%04d",Counter);
#ifdef O_LARGEFILE
    Fout = open(Name,O_CREAT | O_LARGEFILE | O_WRONLY | O_TRUNC, 0644);
#else
    /** BSD does not use nor need O_LARGEFILE **/
    Fout = open(Name,O_CREAT | O_WRONLY | O_TRUNC, 0644);
#endif
  }

  if (Fout == -1)
  {
    perror("ERROR: Unable to create output file for kernel");
    exit(-1);
  }

  /* Copy file */
  /** NOTE: ReadSize == bytes ready to save to file **/
  while(ReadSize > 0)
  {
    Bp=Buffer;
    while(ReadSize > 0)
    {
      WriteSize = write(Fout,Bp,ReadSize);
      Bp += WriteSize;
      ReadSize = ReadSize - WriteSize;
      if (WriteSize <= 0) { break; } /* abort! */
    }
    ReadSize = read(Fin,Buffer,sizeof(Buffer));
  }

  /* close file */
  close(Fout);
  Counter++;

  /* reset position */
  lseek(Fin,Hold,SEEK_SET);	/* rewind file */
} /* ExtractKernel() */

/**
 * \brief Dump a partition to a file
 *        This function extracts, then returns the pointer back
 *        to the original location.
 * \param Fin  source of data (disk image)
 * \param Start  begin of partition
 * \param Size   end of partition
 **/
void	ExtractPartition	(int Fin, uint64_t Start, uint64_t Size)
{
  off64_t Hold;
  off_t ReadSize,WriteSize;
  unsigned char Buffer[655360], *Bp;
  /* file name */
  static int Counter=0;
  char Name[256];
  int Fout=-1;
  struct stat64 Stat;

  if (Test) return;

  /* Basic idiot test */
  if (Size <= 0)
  {
    /* invalid */
    if (Verbose) fprintf(stderr,"ERROR: Partition size is <= 0.\n");
    return;
  }

  /* save position */
  Hold = lseek64(Fin,0,SEEK_CUR);
  if (Start < Hold)
  {
    /* invalid */
    if (Verbose) fprintf(stderr,"ERROR: Start is before the starting area.\n");
    lseek64(Fin,Hold,SEEK_SET);	/* rewind file */
    return;
  }

  /* Don't go beyond the end of file */
  fstat64(Fin,&Stat);
  if (Start > Stat.st_size)
  {
    /* invalid */
    if (Verbose) fprintf(stderr,"ERROR: Partition start is after then end of file.\n");
    lseek64(Fin,Hold,SEEK_SET);	/* rewind file */
    return;
  }
  if (Start + Size > Stat.st_size)
  {
    /* permit partial files */
    if (Verbose) fprintf(stderr,"WARNING: Partition end is after then end of file; partition is truncated.\n");
    Size = Stat.st_size - Start;
  }

  /* prepare file for writing */
  memset(Name,0,sizeof(Name));
  snprintf(Name,250,"Partition_%04d",Counter);
#ifdef O_LARGEFILE
  Fout = open(Name,O_CREAT | O_LARGEFILE | O_WRONLY | O_TRUNC, 0644);
#else
  /** BSD does not use nor need O_LARGEFILE **/
  Fout = open(Name,O_CREAT | O_WRONLY | O_TRUNC, 0644);
#endif
  if (Fout == -1)
  {
    perror("ERROR: Unable to create output file for partition");
    exit(-1);
  }

  /* Copy file */
  /*** Support very large disk space ***/
  lseek64(Fin,(off64_t)Start,SEEK_SET);

  while(Size > 0)
  {
    if (Size > sizeof(Buffer))
    {
      ReadSize = read(Fin,Buffer,sizeof(Buffer));
    }
    else
    {
      ReadSize = read(Fin,Buffer,Size);
    }
    if (ReadSize <= 0) Size=0; /* abort! */
    Bp = Buffer;
    while(ReadSize > 0)
    {
      WriteSize = write(Fout,Bp,ReadSize);
      Size = Size - WriteSize;
      Bp += WriteSize;
      ReadSize = ReadSize - WriteSize;
      if (WriteSize <= 0) {ReadSize=0; Size=0;} /* abort! */
    }
  }

  /* close file */
  close(Fout);
  Counter++;

  /* reset position */
  lseek64(Fin,Hold,SEEK_SET);	/* rewind file */
} /* ExtractPartition() */

/**
 * \brief Read a master boot record (first 0x200 bytes).
 *        The MBR contains 446 bytes of assembly, and 4 partition tables.
 *        Extracts kernel and partitions based on MBR.
 * \param Fin Open file descriptor to read
 * \param MBRStart offset for record
 * \return 0=not MBR, 1=MBR
 **/
int	ReadMBR	(int Fin, uint64_t MBRStart)
{
  unsigned char MBR[0x200]; /* master boot record sector */
  int i;
  /* extended partitions */
  off_t Offset;
  /* partition descriptions */
  int ActiveFlag,Type;
  int Head[2],Sec[2],Cyl[2];
  uint64_t Start,Size;
  /* disk descriptions */
  uint64_t SectorSize;
  uint64_t SectorPerCluster;
  uint64_t SectorPerCylinder;

  lseek(Fin,MBRStart,SEEK_SET);	/* rewind file */
  for(i=0; i<0x200; i++)
  {
    if(read(Fin,MBR+i,1) < 0)
    {
      fprintf(stderr, "ERROR %s.%d: unable to perform read", __FILE__, __LINE__);
      fprintf(stderr, "ERROR errno is: %s\n", strerror(errno));
    }
  }

  /* check if it really is a MBR */
  if ((MBR[0x1fe] != 0x55) || (MBR[0x1ff] != 0xaa))
  {
    fprintf(stderr,"ERROR: No master boot record\n");
    return(0);
  }

  /* 512 bytes per sector is pretty much standard.
     Apparently IBM's AS/400 systems use disks with 520 bytes/sector.
     MFM/RLL disks didn't have a native sector size.
     Some SCSI disks use 2048 bytes.
     But IDE uses 512.
   */
  SectorSize = 512;
  SectorPerCluster = 0;   /* does not matter for extraction */
  SectorPerCylinder = 0;  /* does not matter for extraction */

  /* process each partition table */
  for(i=446; i<510; i+=16)
  {
    /* 16 bytes describe each partition */
    ActiveFlag=MBR[i]; /* 0x1BE */
    Head[0]=MBR[i+1];
    Sec[0]=(MBR[i+2] >> 2) & 0xcf;
    Cyl[0]=MBR[i+3] + (MBR[i+2] & 0x3)*16;
    Type=MBR[i+4];
    Head[1]=MBR[i+5];
    Sec[1]=(MBR[i+6] >> 2) & 0xcf;
    Cyl[1]=MBR[i+7] + (MBR[i+6] & 0x3)*16;
    /* Starting sector number, size of the sector */
    Start=MBR[i+ 8] + MBR[i+ 9]*256 + MBR[i+10]*256*256 + MBR[i+11]*256*256*256;
    Size= MBR[i+12] + MBR[i+13]*256 + MBR[i+14]*256*256 + MBR[i+15]*256*256*256;
    if (Type != 0) /* Type 0 is unused */
    {
      printf("Partition: (Active=%d,Type=%x)\n",ActiveFlag & 0x80,Type);
      printf("           HSC Start=%d,%d,%d\n",Head[0],Sec[0],Cyl[0]);
      printf("           HSC End  =%d,%d,%d\n",Head[1],Sec[1],Cyl[1]);
      printf("           Sector: Start=%llu (%08llx)  End=%llu (%08llx)\n",
          (unsigned long long)Start,(unsigned long long)Start,(unsigned long long)Start+Size,(unsigned long long)Start+Size);
      printf("           Byte: Logical start= %llu (%08llx)\n",
          (unsigned long long)MBRStart+(Start)*SectorSize,
          (unsigned long long)MBRStart+(Start)*SectorSize);
      printf("           Byte: Logical end  = %llu (%08llx)\n",
          (unsigned long long)MBRStart+(Size+Start)*SectorSize,
          (unsigned long long)MBRStart+(Size+Start)*SectorSize);

      if (Start == 0) /* if it is a Linux kernel */
      {
        ExtractKernel(Fin);
        break;
      }
    }

    /* check for extended partitions */
    /** Types: http://www.win.tue.nl/~aeb/partitions/partition_types-1.html **/
    switch(Type)
    {
      case 0x00:	/* unused */
        break;
      case 0x05:	/* extended partition */
      case 0x0f:	/* Win95 extended partition */
        Offset = lseek(Fin,0,SEEK_CUR);
        ReadMBR(Fin,MBRStart+(Start)*SectorSize);
        Offset = lseek(Fin,Offset,SEEK_CUR);
        break;
      case 0x06:	/* FAT (DOS 3.3+) */
      case 0x07:	/* OS/2 HPFS, Windows NTFS, Advanced Unix */
      case 0x0b:	/* Win95 OSR2 FAT32 */
      case 0x0c:	/* Win95 OSR2 FAT32, LBA-mapped */
      case 0x82:	/* Linux swap */
      case 0x83:	/* Linux partition */
      default:
        /* extract partition */
      {
        long S,E;
        S=MBRStart+(Start)*SectorSize;
        E=MBRStart+(Size)*SectorSize;
        if (Verbose) fprintf(stderr,"Extracting type %02x: start=%04llx  size=%llu\n",Type,(unsigned long long)S,(unsigned long long)E);
        ExtractPartition(Fin,S,E);
      }
    }
  } /* for MBR */
  return(1);
} /* ReadMBR() */

/**
 * \brief Usage
 * \param Filename (executable argv[0] name)
 **/
void	Usage	(char *Filename)
{
  fprintf(stderr,"Usage: %s [-t] diskimage\n",Filename);
  fprintf(stderr,"  -t = test -- do not actually extract.\n");
  fprintf(stderr,"  -v = Verbose.\n");
} /* Usage() */

/**
 * \brief main
**/
int	main	(int argc, char *argv[])
{
  int Fin;
  int c;

  if ((argc < 2) || (argc > 3))
  {
    Usage(argv[0]);
    exit(-1);
  }

  while((c = getopt(argc,argv,"tv")) != -1)
  {
    switch(c)
    {
      case 't':	Test=1; break;
      case 'v':	Verbose++; break;
      default:
        Usage(argv[0]);
        exit(-1);
        break;
    }
  }
  if (optind != argc-1)
  {
    Usage(argv[0]);
    exit(-1);
  }

#ifdef O_LARGEFILE
  Fin = open(argv[optind],O_RDONLY | O_LARGEFILE);
#else
  /** BSD does not use nor need O_LARGEFILE **/
  Fin = open(argv[optind],O_RDONLY);
#endif
  if (Fin == -1)
  {
    perror("ERROR: Unable to open diskimage");
    exit(-1);
  }

  ReadMBR(Fin,0);
  close(Fin);
  return(0);
} /* main() */

