/***************************************************************
 Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/
/**
 * file pkgagent.c
 * The package metadata agent puts data about each package (rpm and deb) into the database.
 * 
 * Pkgagent get RPM package info from rpm files using rpm library,
 * Build pkgagent.c need "rpm" and "librpm-dev", running binary just need "rpm".
 */
#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <libgen.h>
#include <time.h>

#include "libfossrepo.h"
#include "libfossdb.h"
#include "libfossagent.h"
#include "rpmlib.h"
#include "rpmts.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif /* SVN_REV */

#define MAXCMD  2048

#define NO      0
#define YES     1
#define TRUE YES
#define FALSE NO

struct rpmpkginfo {
  char pkgName[128];
  char pkgAlias[128];
  char pkgArch[32];
  char version[32];
  char rpmFilename[128];
  char license[255];
  char group[64];
  char packager[128];
  char release[32];
  char buildDate[64];
  char vendor[64];
  char url[128];
  char sourceRPM[128];
  char summary[MAXCMD];
  char description[MAXCMD];
  long pFileFk;
  char pFile[MAXCMD];
  char **requires;
  int req_size;
};
struct rpmpkginfo rpmpi;

int_32 tag[15] = {RPMTAG_NAME,
		RPMTAG_EPOCH,
		RPMTAG_ARCH,
		RPMTAG_VERSION,
		RPMTAG_LICENSE,
		RPMTAG_GROUP,
		RPMTAG_PACKAGER,
		RPMTAG_RELEASE,
		RPMTAG_BUILDTIME,
		RPMTAG_VENDOR,
		RPMTAG_URL,
		RPMTAG_SOURCERPM,
		RPMTAG_SUMMARY,
		RPMTAG_DESCRIPTION,
		RPMTAG_REQUIRENAME
        	};


void *DB=NULL;
int Agent_pk;
int PKG_RPM = 0; /**< Non-zero when it's RPM package */

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or
 NULL at \0.
**********************************************/
char *  GetFieldValue   (char *Sin, char *Field, int FieldMax,
                         char *Value, int ValueMax, char Separator)
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
  if (Sin[s] != Separator) /* if it is not a field, then just return it. */
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


/**
 * parseSchedInput (char *s)
 *
 * Expect 2 field from the scheduler, 'pfile_pk' and 'mimetype_name'
 */
void    parseSchedInput (char *s)
{
  char field[256];
  char value[1024];
  char *origS;
  long pfilefk = 0;
  char pfilename[MAXCMD];
  long uploadpk;
  char mimetype[128];

  if (!s) {
    return;
  }
  origS = s;

  while (s && (s[0] != '\0')) {
    s = GetFieldValue(s,field,256,value,1024,'=');
    if (value[0] != '\0') {
      if (!strcasecmp(field, "pfile_pk")) {
        pfilefk = atol(value);
      }
      else if (!strcasecmp(field, "pfilename")) {
        strncpy(pfilename, value, sizeof(pfilename));
      }
      else if (!strcasecmp(field, "upload_pk")) {
        uploadpk = atol(value);
      }
      else if (!strcasecmp(field, "mimetype")) {
        strncpy(mimetype, value, sizeof(mimetype));
      }
      else {
        printf("LOG: got other:%s\n", value); /* DEBUG */
      }
    }
  }
  printf ("mimetyp:%s\n",mimetype);
  if (!strcasecmp(mimetype, "application/x-rpm")) {
    rpmpi.pFileFk = pfilefk;
    strncpy(rpmpi.pFile, pfilename, sizeof(rpmpi.pFile));
    PKG_RPM = 1;
  }
}/*parseSchedInput (char *s)*/

/**
 * readHeaderInfo(Header header)
 * get RPM package info from rpm file header use rpm library
 */
void readHeaderInfo(Header header) 
{
  int_32 type;
  void* pointer;
  int_32 data_size;
  int header_status = headerGetEntry(header,tag[0],&type,&pointer,&data_size);
  
  if (header_status) {
    if (type == RPM_STRING_TYPE) {
      strncpy(rpmpi.pkgName,pointer,sizeof(rpmpi.pkgName));
    } 
  }

  header_status = headerGetEntry(header,tag[1],&type,&pointer,&data_size);
  
  if (header_status) {
    if (type == RPM_STRING_TYPE) {
      strncpy(rpmpi.pkgAlias,pointer,sizeof(rpmpi.pkgAlias));
    } 
  }

  header_status = headerGetEntry(header,tag[2],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.pkgArch,pointer,sizeof(rpmpi.pkgArch));
  }

  header_status = headerGetEntry(header,tag[3],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.version,pointer,sizeof(rpmpi.version));
  }

  header_status = headerGetEntry(header,tag[4],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.license,pointer,sizeof(rpmpi.license));
  }

  header_status = headerGetEntry(header,tag[5],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.group,pointer,sizeof(rpmpi.group));
  }

  header_status = headerGetEntry(header,tag[6],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.packager,pointer,sizeof(rpmpi.packager));
  }

  header_status = headerGetEntry(header,tag[7],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.release,pointer,sizeof(rpmpi.release));
  }

  header_status = headerGetEntry(header,tag[8],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_INT32_TYPE){
      strncpy(rpmpi.buildDate,asctime(gmtime((time_t*)pointer)),sizeof(rpmpi.buildDate));
    }
  }

  header_status = headerGetEntry(header,tag[9],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.vendor,pointer,sizeof(rpmpi.vendor));
  }

  header_status = headerGetEntry(header,tag[10],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.url,pointer,sizeof(rpmpi.url));
  }

  header_status = headerGetEntry(header,tag[11],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.sourceRPM,pointer,sizeof(rpmpi.sourceRPM));
  }

  header_status = headerGetEntry(header,tag[12],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.summary,pointer,sizeof(rpmpi.summary));
  }

  header_status = headerGetEntry(header,tag[13],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_TYPE) 
      strncpy(rpmpi.description,pointer,sizeof(rpmpi.description));
  }

  header_status = headerGetEntry(header,tag[14],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_ARRAY_TYPE) {
      rpmpi.requires = calloc(data_size, sizeof(char *));
      rpmpi.requires = (char **) pointer;
      rpmpi.req_size = data_size;
    } 
  }
  printf("Name:%s\n",rpmpi.sourceRPM);
} /* readHeadinfo() */
   
/**
 * getMetadata(char *pkg)
 *
 */
int	getMetadata	(char *pkg)
{
  //rpmpi.pFileFk = 4234634;
  if (PKG_RPM)
  {
    FD_t fd;
    rpmRC rpmrc;
    Header header;
    rpmts ts;
    rpmVSFlags vsflags;

    vsflags = RPMVSF_DEFAULT;
    ts = (rpmts) rpmtsCreate();

    fd = Fopen(pkg,"r");
    if ( fd == NULL ||Ferror(fd)){
      rpmError(RPMERR_OPEN, "open of %s failed: %s\n", pkg, Fstrerror(fd));
      if (fd){
        Fclose(fd);
      }
      return FALSE;
    }

    vsflags |= _RPMVSF_NOSIGNATURES;
    vsflags |= _RPMVSF_NODIGESTS;
    vsflags |= RPMVSF_NOHDRCHK;
    vsflags |= RPMVSF_NEEDPAYLOAD;
    
    rpmtsSetVSFlags(ts, vsflags);

    rpmrc = rpmReadPackageFile(ts, fd, pkg, &header);
    Fclose(fd);
    ts = (rpmts) rpmtsFree(ts);

    switch (rpmrc) {
    case RPMRC_OK:
    case RPMRC_NOKEY:
    case RPMRC_NOTTRUSTED:
        break;
    case RPMRC_NOTFOUND:
    case RPMRC_FAIL:
    default:
        rpmError(RPMERR_OPEN, "%s cannot be read\n", pkg);
        return FALSE;
    }
    readHeaderInfo(header);
    header = headerFree(header);
  }
  return TRUE;
} /* getMetadata */

/**
 *recordMetadata()
 *
 */
void	recordMetadataRPM	(struct rpmpkginfo *pi)
{
  char SQL[MAXCMD];
  int rc;
  int pkg_pk;

  memset(SQL,0,sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT pfile_fk FROM pkg_rpm WHERE pfile_fk = %ld;",pi->pFileFk);
  rc = DBaccess(DB,SQL);
  if (rc < 0)
  {
    printf("ERROR pfile %s Unable to access database.\n",pi->pFile);
    printf("LOG pfile %s ERROR: %s\n",pi->pFile,SQL);
    fflush(stdout);
    DBclose(DB);
    exit(-1);
  }
  if (DBdatasize(DB) <=0)
  {
    memset(SQL,0,sizeof(SQL));
    DBaccess(DB,"BEGIN;");  
    snprintf(SQL,sizeof(SQL),"INSERT INTO pkg_rpm (pkg_name,pkg_alias,pkg_arch,version,rpm_filename,license,pkg_group,packager,release,build_date,vendor,url,source_rpm,summary,description,pfile_fk) values ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s',%ld);",pi->pkgName,pi->pkgAlias,pi->pkgArch,pi->version,pi->rpmFilename,pi->license,pi->group,pi->packager,pi->release,pi->buildDate,pi->vendor,pi->url,pi->sourceRPM,pi->summary,pi->description,pi->pFileFk);
    rc = DBaccess(DB,SQL);
    if (rc < 0)
    {
      DBaccess(DB,"ROLLBACK;");
      printf("ERROR pfile %s Unable to access database.\n",pi->pFile);
      printf("LOG pfile %s ERROR: %s\n",pi->pFile,SQL);
      fflush(stdout);
      DBclose(DB);
      exit(-1);
    }
    
    DBaccess(DB,"SELECT currval('pkg_rpm_pkg_pk_seq'::regclass);");
    pkg_pk = atoi(DBgetvalue(DB,0,0));

    printf("pkg_pk:%d\n",pkg_pk);
    int i;
    for (i=0;i<pi->req_size;i++)
    {
      memset(SQL,0,sizeof(SQL));
      snprintf(SQL,sizeof(SQL),"INSERT INTO pkg_rpm_req (pkg_fk,req_value) values (%d,'%s');",pkg_pk,pi->requires[i]);
      rc = DBaccess(DB,SQL);
      if (rc < 0)
      {
        DBaccess(DB,"ROLLBACK;");
        printf("LOG pkg %d ERROR: %s\n",pkg_pk,SQL);
        fflush(stdout);
        DBclose(DB);
        exit(-1);
      }
    }
    DBaccess(DB,"COMMIT;");
  }
}

/***********************************************
 Usage():
 Command line options allow you to write the agent so it works 
 stand alone, in addition to working with the scheduler.
 This simplifies code development and testing.
 So if you have options, have a Usage().
 Here are some suggested options (in addition to the program
 specific options you may already have).
 ***********************************************/
void	Usage	(char *Name)
{ 
  printf("Usage: %s [options] [file [file [...]]\n",Name);
  printf("  -i   :: initialize the database, then exit.\n");
  printf("  -v   :: verbose (-vv = more verbose)\n");
  printf("  file :: if files are rpm or debian package listed, display their meta data.\n");
  printf("  no file :: process data from the scheduler.\n");
} /* Usage() */

/***********************************************/
int	main	(int argc, char *argv[])
{
  char Parm[MAXCMD];
  int c;
  char *agent_desc = "Pulls metadata out of RPM or DEBIAN packages";
  int i;

  DB = DBopen();
  if (!DB)
  {
    printf("FATAL: Unable to connect to database\n");
    fflush(stdout);
    exit(-1);
  }

  Agent_pk = GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agent_desc);
  /* Process command-line */
  while((c = getopt(argc,argv,"iv")) != -1)
  {
    switch(c)
	{
	case 'i':
                DBclose(DB);  /* DB was opened above, now close it and exit */
                exit(0);
        case 'v':
                break;
	default:
		Usage(argv[0]);
		DBclose(DB);
		exit(-1);
	}
  }

  /* If no args, run from scheduler! */
  if (argc == 1)
  {
    char *repFile;
    signal(SIGALRM,ShowHeartbeat);
    alarm(60);

    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);

    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
    {
      printf("PKG: pkgagent read %s\n", Parm);
      fflush(stdout);

      if (Parm[0] != '\0') 
      {
	parseSchedInput(Parm);
        if (PKG_RPM) {
          repFile = RepMkPath("files", rpmpi.pFile);
	  if (!repFile) {
	    printf("FATAL: pfile %ld PkgAgent unable to open file %s\n",
                            rpmpi.pFileFk, rpmpi.pFile);
            fflush(stdout);
            DBclose(DB);
            exit(-1);
	  }
          getMetadata(repFile);
          recordMetadataRPM(&rpmpi);
        } else {
	  /* Deal with the debian package*/
	}
        printf("OK\n");
        fflush(stdout);
      }
    }
  }
  else
  {
    /* printf("Main: running in cli mode, processing file(s)\n"); */
    for (i = 1; i < argc; i++)
    {
       PKG_RPM=1;
       getMetadata(argv[i]);
       recordMetadataRPM(&rpmpi);
    }
  }

  DBclose(DB);
  return(0);
} /* main() */
