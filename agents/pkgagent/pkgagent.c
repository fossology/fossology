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
 *
 * Pkgagent get Debian binary package info from .deb binary control file.
 *
 * Pkgagent get Debian source package info from .dsc file.
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
#include "rpmlog.h"

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
struct rpmpkginfo *glb_rpmpi;

struct debpkginfo {
  char pkgName[96];
  char source[96];
  char version[64];
  char section[32];
  char priority[16];
  char pkgArch[32];
  int installedSize;
  char maintainer[128];
  char homepage[128];
  char summary[128];
  char description[MAXCMD];
  long pFileFk;
  char pFile[MAXCMD];
  char **depends;
  int dep_size;
  char uploaders[128];
  char format[16];
  char standardsVersion[32];
};
struct debpkginfo *glb_debpi;

int tag[15] = {RPMTAG_NAME,
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
int PKG_DEB = 0; /**< Non-zero when it's DEBINE package */
int PKG_DEB_SRC = 0; /**< Non-zero when it's DEBINE source package */
int Verbose = 0;

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or
 NULL at \0.

	@param char *Sin
	@param char *Field
	@param int FieldMax
	@param char *Value
	@param int ValueMax
	@param char Separator
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
 * ParseSchedInput (char *s , struct rpmpkginfo *pi, struct debpkginfo *dpi)
 *
 * Expect 3 field from the scheduler, 'pfile_pk' 'pfilename' and 'mimetype'
 * Parameters:
 * 	@param char *s   string send from scheduler
 * 	@param struct rpmpkginfo *pi  rpmpkginfo global pointer
 * 	@param struct debpkginfo *dpi debpkginfo global pointer
 */
void    ParseSchedInput (char *s, struct rpmpkginfo *pi, struct debpkginfo *dpi)
{
  char field[256];
  char value[1024];
  char *origS;
  long pfilefk = 0;
  char pfilename[MAXCMD];
  long uploadpk;
  char mimetype[128];
  PKG_RPM = 0;
  PKG_DEB = 0;
  PKG_DEB_SRC = 0;

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
  if (Verbose) { printf ("mimetype:%s\n",mimetype);}
  
  /*  
   * if mimetype='application/x-rpm' set PKG_RPM=1
   * if mimetype='application/x-debian-package' set PKG_DEB=1
   * if mimetype='application/x-debian-source' set PKG_DEB_SRC=1
   * */  
  if (!strcasecmp(mimetype,"application/x-rpm")) {
    pi->pFileFk = pfilefk;
    strncpy(pi->pFile, pfilename, sizeof(pi->pFile));
    PKG_RPM = 1;
  }
  else if (!strcasecmp(mimetype, "application/x-debian-package")){
    dpi->pFileFk = pfilefk;
    strncpy(dpi->pFile, pfilename, sizeof(dpi->pFile));
    PKG_DEB = 1;
  }
  else if (!strcasecmp(mimetype, "application/x-debian-source")){
    dpi->pFileFk = pfilefk;
    strncpy(dpi->pFile, pfilename, sizeof(dpi->pFile));
    PKG_DEB_SRC = 1;	
  } else {
    printf("LOG: Not RPM and DEBIAN package!\n");
  } 
}/*ParseSchedInput (char *s, struct rpmpkginfo *pi, struct debpkginfo *dpi)*/

/**
 * ReadHeaderInfo(Header header, struct rpmpkginfo *pi)
 * get RPM package info from rpm file header use rpm library
 *
 * Parameters:
 * 	@param Header header rpm header
 * 	@param struct *pi    rpmpkginfo global pointer
 */
void ReadHeaderInfo(Header header, struct rpmpkginfo *pi) 
{
  char fmt[128];
  const char * msgstr;
  const char * errstr;
  int i,j;
  long *tp,t;
  int header_status;

#ifdef _RPM_4_4 
  void* pointer;
  int_32 type, data_size;
#endif /* RPM4.4 version*/

#ifdef _RPM_4_4_COMPAT
  struct rpmtd_s req;
  rpm_count_t data_size;
#endif /* After RPM4.4 version*/

  for (i = 0; i < 14; i++) {
    fmt[0] = '\0';
    strcat( fmt, "%{");
    strcat( fmt, tagName(tag[i]));
    strcat( fmt, "}\n");

    msgstr = headerSprintf(header, fmt, rpmTagTable, rpmHeaderFormats, &errstr);
    if (msgstr != NULL){
      if (Verbose) { printf("%s:%s",tagName(tag[i]),msgstr);}
      switch (tag[i]) {
      case RPMTAG_NAME:
        strncpy(pi->pkgName,msgstr,sizeof(pi->pkgName));
        break;
      case RPMTAG_EPOCH:
        strncpy(pi->pkgAlias,msgstr,sizeof(pi->pkgAlias));
        break;
      case RPMTAG_ARCH:
        strncpy(pi->pkgArch,msgstr,sizeof(pi->pkgArch));
        break;
      case RPMTAG_VERSION:
        strncpy(pi->version,msgstr,sizeof(pi->version));
        break;
      case RPMTAG_LICENSE:
        strncpy(pi->license,msgstr,sizeof(pi->license));
        break;
      case RPMTAG_GROUP:
        strncpy(pi->group,msgstr,sizeof(pi->group));
        break;
      case RPMTAG_PACKAGER:
        strncpy(pi->packager,msgstr,sizeof(pi->packager));
        break;
      case RPMTAG_RELEASE:
        strncpy(pi->release,msgstr,sizeof(pi->release));
        break;
      case RPMTAG_BUILDTIME:	
	tp = malloc(sizeof(long));
	t = atol(msgstr);
	tp = &t;
	strncpy(pi->buildDate,asctime(gmtime((time_t*)tp)),sizeof(pi->buildDate));
	break;
      case RPMTAG_VENDOR:
	strncpy(pi->vendor,msgstr,sizeof(pi->vendor));
	break;
      case RPMTAG_URL:
	strncpy(pi->url,msgstr,sizeof(pi->url));
	break;
      case RPMTAG_SOURCERPM:
	strncpy(pi->sourceRPM,msgstr,sizeof(pi->sourceRPM));
	break;
      case RPMTAG_SUMMARY:
	strncpy(pi->summary,msgstr,sizeof(pi->summary));
	break;
      case RPMTAG_DESCRIPTION:
	strncpy(pi->description,msgstr,sizeof(pi->description));
	break;
      default:
	break;
      }
    }
  }      
  if (Verbose) { printf("Name:%s\n",pi->buildDate);}
#ifdef _RPM_4_4
  header_status = headerGetEntry(header,tag[14],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_ARRAY_TYPE) {
      pi->requires = calloc(data_size, sizeof(char *));
      pi->requires = (char **) pointer;
      pi->req_size = data_size;
    } 
  }
#endif/* RPM4.4 version*/
#ifdef _RPM_4_4_COMPAT
  header_status = headerGet(header, tag[14], &req, HEADERGET_DEFAULT);
  if (header_status) {
    data_size = rpmtdCount(&req);
    pi->requires = calloc(data_size, sizeof(char *));
    for (j=0; j<data_size;j++){
      const char * temp = rpmtdNextString(&req);
      pi->requires[j] = malloc(MAXCMD);
      strcpy(pi->requires[j],temp);  
    }
    pi->req_size = data_size;
    rpmtdFreeData(&req);
  }
#endif/* After RPM4.4 version*/

  if (Verbose) { 
    printf("Size:%d\n",pi->req_size);
    for (j=0; j<pi->req_size;j++){
      printf("REQ:%s\n",pi->requires[j]);
    }
    printf("Name:%s\n",pi->sourceRPM);
  }
} /* ReadHeaderInfo(Header header, struct rpmpkginfo *pi) */
   
/**
 * GetMetadata(char *pkg, struct rpmpkginfo *pi)
 * 
 * Get RPM package info.
 * Parameters:
 * 	@param char *pkg                path of repo pfile
 * 	@param struct rpmpkginfo *pi    rpmpkginfo global pointer
 * Returns:
 * 		True for success
 */
int	GetMetadata	(char *pkg, struct rpmpkginfo *pi)
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
      rpmlog(RPMLOG_ERR, "open of %s failed: %s\n", pkg, Fstrerror(fd));
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

    rpmReadConfigFiles(NULL, NULL);
    rpmrc = rpmReadPackageFile(ts, fd, pkg,&header);
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
        rpmlog(RPMLOG_ERR, "%s cannot be read\n", pkg);
        return FALSE;
    }
    ReadHeaderInfo(header, pi);
    header = headerFree(header);
  }
  return TRUE;
} /* GetMetadata(char *pkg, struct rpmpkginfo *pi) */

/**
 * RecordMetadata(struct rpmpkginfo *pi)
 * Store rpm package info into database
 *	
 * 	@param struct rpmpkginfo *pi
 *
 */
void	RecordMetadataRPM	(struct rpmpkginfo *pi)
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

    if (Verbose) { printf("pkg_pk:%d\n",pkg_pk);}
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
} /* RecordMetadata(struct rpmpkginfo *pi) */


/* 
 * ParseDebFile(char *Sin, char *Field, char *Value)
 *
 * parse debian bianry control file with Field/Value pairs
 *
 * */
char * ParseDebFile(char *Sin, char *Field, char *Value)
{
  int s,f,v;

  memset(Field,0,256);
  memset(Value,0,1024);

  f=0; v=0;
  if(!isspace(Sin[0]))
  {
    for(s=0; (Sin[s] != '\0') && !isspace(Sin[s]) && (Sin[s] != ':'); s++)
    {
      Field[f++] = Sin[s];
    }
    while(isspace(Sin[s])) s++;
    if (Sin[s] != ':')
    {
      return(Sin+s);
    }
    s++;
    while(isspace(Sin[s])) s++;

    for( ; Sin[s] != '\0'; s++)
    {
      Value[v++]=Sin[s];
    }
      if (Verbose) { printf("Field is %s and Value is %s", Field, Value);}
      return(Sin+s);
  } else
  {
    if (Verbose) { printf("ExValue is %s", Sin);}
    return(Sin);
  }
} /* ParseDebFile(char *Sin, char *Field, char *Value) */

/**
 * GetMetadataDebBinary(struct debpkginfo *pi)
 *
 * get debian binary package info
 */
void	GetMetadataDebBinary	(struct debpkginfo *pi)
{
  char *repfile =NULL;
  char *filename = NULL;
  char SQL[MAXCMD];
  int rc;
  
  FILE *fp;
  char field[256];
  char value[1024];
  char line[MAXCMD];
  char *s = NULL;
  char temp[MAXCMD];

  /* Get the debian/control file's rep path */
  memset(SQL,0,sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfilename FROM (SELECT pfile_fk,ufile_name FROM uploadtree WHERE parent IN (SELECT uploadtree_pk FROM uploadtree WHERE parent IN (SELECT uploadtree_pk FROM uploadtree WHERE parent IN (SELECT uploadtree_pk FROM uploadtree WHERE parent IN (SELECT uploadtree_pk FROM uploadtree WHERE parent IN (SELECT uploadtree_pk FROM uploadtree WHERE pfile_fk = %ld))))) AND ufile_name = 'control') TEMP INNER JOIN pfile ON TEMP.pfile_fk = pfile_pk;", pi->pFileFk);
  rc = DBaccess(DB,SQL);
  if (rc < 0)
  {
    printf("LOG ERROR: %s\n",SQL);
    fflush(stdout);
    DBclose(DB);
    exit(-1);
  }
  filename = DBgetvalue(DB,0,0);
  if (filename)
  {
    repfile = RepMkPath("files", filename);
    if (!repfile) {
      printf("FATAL: PkgAgent unable to open file %s\n",filename);
      fflush(stdout);
      DBclose(DB);
      exit(-1);
    }
  } else {
    printf("FATAL: Unable to find debian/control file!\n");
    fflush(stdout);
    DBclose(DB);
    exit(-1);
  }

  /* Parse the debian/control file to get every Field and Value */
  if ((fp = fopen(repfile, "r")) == NULL){
    printf("FATAL: Unable to open debian/control file %s\n",repfile);
    fflush(stdout);
    exit(-1);
  }

  while (fgets(line,2048,fp)!=NULL)
  {
    s = ParseDebFile(line,field,value);
    if (!strcasecmp(field, "Description")) {
       strncpy(pi->summary, value, sizeof(pi->summary));
       strcpy(temp, "");
    }
    if ((s[0] != '\0') && (temp!=NULL))
      strcat(temp,s);
    if (!strcasecmp(field, "Package")) {
       strncpy(pi->pkgName, value, sizeof(pi->pkgName));
    }
    if (!strcasecmp(field, "Version")) {
       strncpy(pi->version, value, sizeof(pi->version));
    }
    if (!strcasecmp(field, "Architecture")) {
       strncpy(pi->pkgArch,  value, sizeof(pi->pkgArch));
    }
    if (!strcasecmp(field, "Maintainer")) {
       strncpy(pi->maintainer, value, sizeof(pi->maintainer));
    }
    if (!strcasecmp(field, "Installed-Size")) {
       pi->installedSize=atol(value);
    }
    if (!strcasecmp(field, "Section")) {
       strncpy(pi->section, value, sizeof(pi->section));
    }
    if (!strcasecmp(field, "Priority")) {
       strncpy(pi->priority, value, sizeof(pi->priority));
    }
    if (!strcasecmp(field, "Homepage")) {
       strncpy(pi->homepage, value, sizeof(pi->homepage));
    }
    if (!strcasecmp(field, "Source")) {
       strncpy(pi->source, value, sizeof(pi->source));
    }
    if (!strcasecmp(field, "Depends")) {
       char *depends = NULL;
       char tempvalue[1024];
       int size,i;
       size = 0;

       strncpy(tempvalue, value, sizeof(tempvalue));
       depends = strtok(value, ",");
       while (depends && (depends[0] != '\0')) {
         depends = strtok(NULL, ",");
         size++;
       }
       if (Verbose) { printf("SIZE:%d\n", size);}
       
       pi->depends = calloc(size, sizeof(char *));
       pi->depends[0] = calloc(256, sizeof(char));
       strcpy(pi->depends[0],strtok(tempvalue,","));
       for (i=1;i<size;i++){
         pi->depends[i] = calloc(256, sizeof(char));
         strcpy(pi->depends[i],strtok(NULL, ","));
       }
       pi->dep_size = size;
    }
  }
  if (temp!=NULL)
    strncpy(pi->description, temp, sizeof(pi->description));

  fclose(fp);
}/* GetMetadataDebBinary(struct debpkginfo *pi) */

/**
 * RecordMetadataDEB(struct debpkginfo *pi)
 * 
 * Store debian package info into database
 */
void    RecordMetadataDEB       (struct debpkginfo *pi)
{
  char SQL[MAXCMD];
  int rc;
  int pkg_pk;

  memset(SQL,0,sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT pfile_fk FROM pkg_deb WHERE pfile_fk = %ld;",pi->pFileFk);
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
    snprintf(SQL,sizeof(SQL),"INSERT INTO pkg_deb (pkg_name,pkg_arch,version,maintainer,installed_size,section,priority,homepage,source,summary,description,format,uploaders,standards_version,pfile_fk) values ('%s','%s','%s','%s',%d,'%s','%s','%s','%s','%s','%s','%s','%s','%s',%ld);",pi->pkgName,pi->pkgArch,pi->version,pi->maintainer,pi->installedSize,pi->section,pi->priority,pi->homepage,pi->source,pi->summary,pi->description,pi->format,pi->uploaders,pi->standardsVersion,pi->pFileFk);
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

    DBaccess(DB,"SELECT currval('pkg_deb_pkg_pk_seq'::regclass);");
    pkg_pk = atoi(DBgetvalue(DB,0,0));

    if (Verbose) { printf("pkg_pk:%d\n",pkg_pk);}
    int i;
    for (i=0;i<pi->dep_size;i++)
    {
      memset(SQL,0,sizeof(SQL));
      snprintf(SQL,sizeof(SQL),"INSERT INTO pkg_deb_req (pkg_fk,req_value) values (%d,'%s');",pkg_pk,pi->depends[i]);
      if (Verbose) { printf("DEPENDS:%s\n",pi->depends[i]);}
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
}/* RecordMetadataDEB(struct debpkginfo *pi) */

/**
 * GetMetadataDebSource(char *repFile, struct debpkginfo *pi)
 *
 * get debian source package info from .dsc file
 **/
void	GetMetadataDebSource	(char *repFile, struct debpkginfo *pi)
{ 
  FILE *fp;
  char field[256];
  char value[1024];
  char line[MAXCMD];
  char *s = NULL;

  /*  Parse the debian .dsc file to get every Field and Value */
  if ((fp = fopen(repFile, "r")) == NULL){
    printf("FATAL: Unable to open .dsc file %s\n",repFile);
    fflush(stdout);
    exit(-1);
  }

  while (fgets(line,2048,fp)!=NULL)
  {
    s = ParseDebFile(line,field,value);

    if (!strcasecmp(field, "Format")) {
       strncpy(pi->format, value, sizeof(pi->format));
    }
    if (!strcasecmp(field, "Source")) {
       strncpy(pi->source, value, sizeof(pi->source));
    }
    if (!strcasecmp(field, "Binary")) {
       strncpy(pi->pkgName, value, sizeof(pi->pkgName));
    }
    if (!strcasecmp(field, "Architecture")) {
       strncpy(pi->pkgArch,  value, sizeof(pi->pkgArch));
    }
    if (!strcasecmp(field, "Version")) {
       if (strlen(pi->version) == 0)
         strncpy(pi->version, value, sizeof(pi->version));
    }
    if (!strcasecmp(field, "Maintainer")) {
       strncpy(pi->maintainer, value, sizeof(pi->maintainer));
    }
    if (!strcasecmp(field, "Uploaders")) {
       strncpy(pi->uploaders, value, sizeof(pi->uploaders));
    }
    if (!strcasecmp(field, "Standards-Version")) {
       strncpy(pi->standardsVersion, value, sizeof(pi->standardsVersion));
    }
    if (!strcasecmp(field, "Build-Depends")) {
       char *depends = NULL;
       char tempvalue[1024];
       int size,i;
       size = 0;

       strncpy(tempvalue, value, sizeof(tempvalue));
       depends = strtok(value, ",");
       while (depends && (depends[0] != '\0')) {
         depends = strtok(NULL, ",");
         size++;
       }
       if (Verbose) { printf("SIZE:%d\n", size);}
       
       pi->depends = calloc(size, sizeof(char *));
       pi->depends[0] = calloc(256, sizeof(char));
       strcpy(pi->depends[0],strtok(tempvalue,","));
       for (i=1;i<size;i++){
         pi->depends[i] = calloc(256, sizeof(char));
         strcpy(pi->depends[i],strtok(NULL, ","));
       }
       pi->dep_size = size;
    }
  }

  fclose(fp);
}/*  GetMetadataDebSource(char *repFile, struct debpkginfo *pi) */

/* **************************************************
 *  IsExe(): Check if the executable exists.
 *  (Like the command-line "which" but without returning
 *  the path.)
 *  This should only be used on relative path executables.
 *  Returns: 1 if exists, 0 if does not exist.
 * ***************************************************/
int     IsExe   (char *Exe, int Quiet)
{
  char *Path;
  int i,j;
  char TestCmd[FILENAME_MAX];

  Path = getenv("PATH");
  if (!Path) return(0); /*  nope! */

  memset(TestCmd,'\0',sizeof(TestCmd));
  j=0;
  for(i=0; (j<FILENAME_MAX-1) && (Path[i] != '\0'); i++)
    {
    if (Path[i]==':')
        {
        if ((j>0) && (TestCmd[j-1] != '/')) strcat(TestCmd,"/");
        strcat(TestCmd,Exe);
        if (IsFile(TestCmd,1))  return(1); /*  found it! */
        /*  missed */
        memset(TestCmd,'\0',sizeof(TestCmd));
        j=0;
        }
    else
        {
        TestCmd[j]=Path[i];
        j++;
        }
    }

  /*  check last path element */
  if (j>0)
    {
    if (TestCmd[j-1] != '/') strcat(TestCmd,"/");
    strcat(TestCmd,Exe);
    if (IsFile(TestCmd,1))      return(1); /*  found it! */
    }
  if (!Quiet) fprintf(stderr,"  %s :: not found in $PATH\n",Exe);
  return(0); /*  not in path */
} /*  IsExe() */

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
  printf("  file :: if files are rpm package listed, display their meta data.\n");
  printf("  no file :: process data from the scheduler.\n");
} /* Usage() */

/***********************************************/
int	main	(int argc, char *argv[])
{
  char Parm[MAXCMD];
  int c;
  char *agent_desc = "Pulls metadata out of RPM or DEBIAN packages";
  glb_rpmpi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  glb_debpi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
  int DISABLED = 0;

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
                /*  Check if dpkg-source tools is installed, if not update agent table
                 *  Set agent_enabled = false */
                if (!IsExe("dpkg-source",1)){
        	  char sql[256];
        	  int rc = 0;
        	  printf("WARNING: Package agent disabled, because <dpkg-source> could not be found on the system.\n");
        	  fflush(stdout);

        	  sprintf(sql, "UPDATE agent set agent_enabled=false WHERE agent_name ='%s';", basename(argv[0]));
     	   	  rc = DBaccess(DB, sql);
         	  if (rc < 0){
                    printf("ERROR: Agent_enabled unable to write to the database. %s\n", sql);
                    DBclose(DB);
                    exit(-1);
        	  }
   		}
                DBclose(DB);  /* DB was opened above, now close it and exit */
                exit(0);
        case 'v':
                Verbose++;
                break;
	default:
		Usage(argv[0]);
		DBclose(DB);
		exit(-1);
	}
  }

  /* Check if dpkg-source tools is not installed, pkgagent will not run */
  if (!IsExe("dpkg-source",1)){
	DISABLED = 1;
	printf("WARNING: Package agent disabled, no information could be determined about the package, because <dpkg-source> could not be found on the system. To generate this information ensure that it is available to on the standard search path and reschedule this agent.\n");
        fflush(stdout);

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
      if (Verbose) { printf("PKG: pkgagent read %s\n", Parm);}
      fflush(stdout);

      if (Parm[0] != '\0') 
      {
	ParseSchedInput(Parm,glb_rpmpi,glb_debpi);
        if (PKG_RPM) {
          repFile = RepMkPath("files", glb_rpmpi->pFile);
	  if (!repFile) {
	    printf("FATAL: pfile %ld PkgAgent unable to open file %s\n",
                            glb_rpmpi->pFileFk, glb_rpmpi->pFile);
            fflush(stdout);
            DBclose(DB);
            exit(-1);
	  }
          GetMetadata(repFile,glb_rpmpi);
          RecordMetadataRPM(glb_rpmpi);
        } else if (PKG_DEB) {
	  if (!DISABLED){
            GetMetadataDebBinary(glb_debpi);
            RecordMetadataDEB(glb_debpi);
	  }
	} else if (PKG_DEB_SRC) {
	  if (!DISABLED){
	    repFile = RepMkPath("files", glb_debpi->pFile);
	    if (!repFile) {
	      printf("FATAL: pfile %ld PkgAgent unable to open file %s\n",
                            glb_debpi->pFileFk, glb_debpi->pFile);
              fflush(stdout);
              DBclose(DB);
              exit(-1);
            }
	    GetMetadataDebSource(repFile,glb_debpi);
	    RecordMetadataDEB(glb_debpi);
	  }
        } else {
	  /* Deal with the other package*/
	}
        printf("OK\n");
        fflush(stdout);
      }
    }
  }
  else
  {
    /* printf("DEBUG: running in cli mode, processing file(s)\n"); */
    for (; optind < argc; optind++)
    {
       PKG_RPM=1;
       GetMetadata(argv[optind],glb_rpmpi);
       //RecordMetadataRPM(glb_rpmpi);
    }
  }

  DBclose(DB);
  return(0);
} /* main() */
