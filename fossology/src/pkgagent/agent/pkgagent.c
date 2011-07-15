/***************************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
#include "pkgagent.h"

void *DB=NULL;
PGconn *pgConn = NULL; // the connection to Database
int Verbose = 0;

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif /* SVN_REV */

int tag[15] = { RPMTAG_NAME,
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

/* ***********************************************
 *  Trimming whitespace
 * ***********************************************/
char *trim(char *str)
{
  char *end;

  // Trim leading space
  while(isspace(*str)) str++;

  if(*str == 0)  // All spaces
    return str;

  // Trim trailing space
  end = str + strlen(str) - 1;
  while(end > str && isspace(*end)) end--;

  // Write new null terminator
  *(end+1) = 0;
  return str;
}

/**********************************************
 *  Escaping special characters(single quote)
 *  so that they cannot cause any harm
 *
 * ********************************************/
void	EscapeString	(const char *sourceString, char *escString, int esclen)
{
  int len;
  int error;

  /*  remove any backslashes from the string as they don't escape properly
   *  for example, "don\'t" in the input will cause an insert error
   */
  char *cp = (char *)sourceString;
  while(*cp) 
  {
    if (*cp == '\\') *cp = ' ';
    cp++;
  }

  /* Revert changes of revision 3721
   * If the size of input string larger than destination buffer,
   * will cut of the input string with destination buffer
   */ 
  len = strlen(sourceString);
  if ( len > esclen/2 )
    len = esclen/2 - 1;

  /* check the size of the destination buffer */
  /* comment this changes, will revisit later */
  /*
  if((len = strlen(sourceString)) > esclen/2) {
    printf("ERROR %s.%d: length of input string is too large\n", __FILE__, __LINE__);
    printf("ERROR length of string was %d, max length is %d\n", len, esclen/2);
    return;
  }
   */
  //printf("TEST:esclen---%d,sourcelen---%d\n",esclen,len);
  PQescapeStringConn(DB, escString, sourceString, len, &error);
  if (error)
    printf("WARNING: %s line %d: Error escaping string with multibype character set?\n",__FILE__, __LINE__ );
}

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
 * ProcessUpload (long upload_pk)
 *
 * Get all pfile need to processed use upload_pk
 * Parameters:
 * 	@param upload_pk   upload_pk send from scheduler
 */
int    ProcessUpload (long upload_pk)
{
  char mimetype[128];
  char sqlbuf[1024];
  PGresult *result;
  int mimetypepk = 0;
  int debmimetypepk = 0;
  int debsrcmimetypepk = 0; 
  int numrows;
  int i;

  struct rpmpkginfo *pi;
  struct debpkginfo *dpi;

  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  dpi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));

  pgConn = DBgetconn(DB);
  rpmReadConfigFiles(NULL, NULL);

  /*  "pkgagent" needs to know what? */

  /*  "pkgagent" needs to know the mimetype for 'application/x-rpm' and 'application/x-debian-package' and 'application/x-debian-source'*/
  snprintf(sqlbuf, sizeof(sqlbuf), "SELECT mimetype_pk FROM mimetype WHERE mimetype_name = 'application/x-rpm' LIMIT 1;");
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1); 
  mimetypepk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  if ( mimetypepk == 0 )
  {
    snprintf(sqlbuf, sizeof(sqlbuf), "INSERT INTO mimetype (mimetype_name) VALUES ('application/x-rpm');");
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
    snprintf(sqlbuf, sizeof(sqlbuf), "SELECT mimetype_pk FROM mimetype WHERE mimetype_name = 'application/x-rpm' LIMIT 1;");
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
    mimetypepk = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
    if ( mimetypepk == 0 )
    {
      printf("ERROR: pkgagent rpm mimetype not installed!\n");
      return FALSE;
    }
  }
  snprintf(sqlbuf, sizeof(sqlbuf), "SELECT mimetype_pk FROM mimetype WHERE mimetype_name = 'application/x-debian-package' LIMIT 1;");
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
  debmimetypepk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  if ( debmimetypepk == 0 )
  {
    snprintf(sqlbuf, sizeof(sqlbuf), "INSERT INTO mimetype (mimetype_name) VALUES ('application/x-debian-package');");
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
    snprintf(sqlbuf, sizeof(sqlbuf), "SELECT mimetype_pk FROM mimetype WHERE mimetype_name = 'application/x-debian-package' LIMIT 1;");
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
    debmimetypepk = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
    if ( debmimetypepk == 0 )
    {
      printf("ERROR: pkgagent deb mimetype not installed!\n");
      return FALSE;
    }
  }
  snprintf(sqlbuf, sizeof(sqlbuf), "SELECT mimetype_pk FROM mimetype WHERE mimetype_name = 'application/x-debian-source' LIMIT 1;");
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
  debsrcmimetypepk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  if ( debsrcmimetypepk == 0 )
  {
    snprintf(sqlbuf, sizeof(sqlbuf), "INSERT INTO mimetype (mimetype_name) VALUES ('application/x-debian-source');");
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
    snprintf(sqlbuf, sizeof(sqlbuf), "SELECT mimetype_pk FROM mimetype WHERE mimetype_name = 'application/x-debian-source' LIMIT 1;");
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);
    debsrcmimetypepk = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
    if ( debsrcmimetypepk == 0 )
    {
      printf("ERROR: pkgagent deb source mimetype not installed!\n");
      return FALSE;
    }
  }

  /*  retrieve the records to process */
  snprintf(sqlbuf, sizeof(sqlbuf),
      "SELECT pfile_pk as pfile_pk, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfilename, mimetype_name AS mimetype FROM uploadtree INNER JOIN pfile ON upload_fk = '%ld' INNER JOIN mimetype ON (mimetype_pk = '%d' OR mimetype_pk = '%d' OR mimetype_pk = '%d') AND uploadtree.pfile_fk = pfile_pk AND pfile.pfile_mimetypefk = mimetype.mimetype_pk AND pfile_pk NOT IN (SELECT pkg_rpm.pfile_fk FROM pkg_rpm) AND pfile_pk NOT IN (SELECT pkg_deb.pfile_fk FROM pkg_deb);", upload_pk, mimetypepk, debmimetypepk, debsrcmimetypepk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(pgConn, result, sqlbuf, __FILE__, __LINE__)) exit(-1);

  numrows = PQntuples(result);
  for (i=0; i<numrows; i++)
  {
    char *repFile = NULL;

    memset(pi,0,sizeof(struct rpmpkginfo));
    memset(dpi,0,sizeof(struct debpkginfo));

    strcpy(mimetype, PQgetvalue(result, i, 2));
    /*  
     * if mimetype='application/x-rpm' process RPM packages
     * if mimetype='application/x-debian-package' process DEBIAN packages
     * if mimetype='application/x-debian-source' process DEBIAN source packages
     * */  
    if (!strcasecmp(mimetype,"application/x-rpm")) {
      pi->pFileFk = atoi(PQgetvalue(result, i, 0));
      strncpy(pi->pFile, PQgetvalue(result, i, 1), sizeof(pi->pFile));
      repFile = RepMkPath("files", pi->pFile);
      if (!repFile) {
        printf("FATAL: pfile %ld PkgAgent unable to open file %s\n",
            pi->pFileFk, pi->pFile);
        fflush(stdout);
        return FALSE;
      }
      if (GetMetadata(repFile,pi)){
        RecordMetadataRPM(pi);
      }
    }
    else if (!strcasecmp(mimetype, "application/x-debian-package")){
      dpi->pFileFk = atoi(PQgetvalue(result, i, 0));
      strncpy(dpi->pFile, PQgetvalue(result, i, 1), sizeof(dpi->pFile));
      if (GetMetadataDebBinary(upload_pk, dpi)){
        RecordMetadataDEB(dpi);
      }
    }
    else if (!strcasecmp(mimetype, "application/x-debian-source")){
      dpi->pFileFk = atoi(PQgetvalue(result, i, 0));
      strncpy(dpi->pFile, PQgetvalue(result, i, 1), sizeof(dpi->pFile));
      repFile = RepMkPath("files", dpi->pFile);
      if (!repFile) {
        printf("FATAL: pfile %ld PkgAgent unable to open file %s\n",
            dpi->pFileFk, dpi->pFile);
        fflush(stdout);
        return FALSE;
      }
      if (GetMetadataDebSource(repFile,dpi)){
        RecordMetadataDEB(dpi);
      }
    } else {
      printf("LOG: Not RPM and DEBIAN package!\n");
    }
    Heartbeat(++HBItemsProcessed);
  }
  PQclear(result);
  rpmFreeMacros(NULL);
  return TRUE;
}/*ProcessUpload (long upload_pk)*/

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
    memset(fmt, 0, sizeof(fmt));
    strcat( fmt, "%{");
    strcat( fmt, tagName(tag[i]));
    strcat( fmt, "}\n");

    msgstr = headerSprintf(header, fmt, rpmTagTable, rpmHeaderFormats, &errstr);
    if (msgstr != NULL){
      if (Verbose) { printf("%s:%s",tagName(tag[i]),msgstr);}
      switch (tag[i]) {
        case RPMTAG_NAME:
          EscapeString(msgstr, pi->pkgName, sizeof(pi->pkgName));
          break;
        case RPMTAG_EPOCH:
          EscapeString(msgstr, pi->pkgAlias, sizeof(pi->pkgAlias));
          break;
        case RPMTAG_ARCH:
          EscapeString(msgstr, pi->pkgArch, sizeof(pi->pkgArch));
          break;
        case RPMTAG_VERSION:
          EscapeString(msgstr, pi->version, sizeof(pi->version));
          break;
        case RPMTAG_LICENSE:
          EscapeString(msgstr, pi->license, sizeof(pi->license));
          break;
        case RPMTAG_GROUP:
          EscapeString(msgstr, pi->group, sizeof(pi->group));
          break;
        case RPMTAG_PACKAGER:
          EscapeString(msgstr, pi->packager, sizeof(pi->packager));
          break;
        case RPMTAG_RELEASE:
          EscapeString(msgstr, pi->release, sizeof(pi->release));
          break;
        case RPMTAG_BUILDTIME:
          t = atol(msgstr);
          tp = &t;
          strncpy(pi->buildDate,asctime(gmtime((time_t*)tp)),sizeof(pi->buildDate));
          break;
        case RPMTAG_VENDOR:
          EscapeString(msgstr, pi->vendor, sizeof(pi->vendor));
          break;
        case RPMTAG_URL:
          EscapeString(msgstr, pi->url, sizeof(pi->url));
          break;
        case RPMTAG_SOURCERPM:
          EscapeString(msgstr, pi->sourceRPM,sizeof(pi->sourceRPM));
          break;
        case RPMTAG_SUMMARY:
          EscapeString(msgstr, pi->summary, sizeof(pi->summary));
          break;
        case RPMTAG_DESCRIPTION:
          EscapeString(msgstr, pi->description, sizeof(pi->description));
          break;
        default:
          break;
      }
    }
    free((void *)msgstr); 
  }      
  if (Verbose) { printf("Name:%s\n",pi->buildDate);}
#ifdef _RPM_4_4
  header_status = headerGetEntry(header,tag[14],&type,&pointer,&data_size);
  if (header_status) {
    if (type == RPM_STRING_ARRAY_TYPE) {
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
  //if (PKG_RPM)
  //{
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

  //rpmReadConfigFiles(NULL, NULL);
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
      rpmlog(RPMLOG_ERR, "%s cannot be read or is not an RPM.\n", pkg);
      return FALSE;
  }
  ReadHeaderInfo(header, pi);
  //rpmFreeMacros(NULL);
  header = headerFree(header);
  //}
  return TRUE;
} /* GetMetadata(char *pkg, struct rpmpkginfo *pi) */

/**
 * RecordMetadata(struct rpmpkginfo *pi)
 * Store rpm package info into database
 *	
 * 	@param struct rpmpkginfo *pi
 *
 */
int	RecordMetadataRPM	(struct rpmpkginfo *pi)
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
    return FALSE;
  }
  if (DBdatasize(DB) <=0)
  {
    memset(SQL,0,sizeof(SQL));
    DBaccess(DB,"BEGIN;");
    snprintf(SQL,sizeof(SQL),"INSERT INTO pkg_rpm (pkg_name,pkg_alias,pkg_arch,version,rpm_filename,license,pkg_group,packager,release,build_date,vendor,url,source_rpm,summary,description,pfile_fk) values (E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',%ld);",trim(pi->pkgName),trim(pi->pkgAlias),trim(pi->pkgArch),trim(pi->version),trim(pi->rpmFilename),trim(pi->license),trim(pi->group),trim(pi->packager),trim(pi->release),pi->buildDate,trim(pi->vendor),trim(pi->url),trim(pi->sourceRPM),trim(pi->summary),trim(pi->description),pi->pFileFk);
    rc = DBaccess(DB,SQL);
    if (rc < 0)
    {
      DBaccess(DB,"ROLLBACK;");
      printf("ERROR pfile %s Unable to access database.\n",pi->pFile);
      printf("LOG pfile %s ERROR: %s\n",pi->pFile,SQL);
      fflush(stdout);
      return FALSE;
    }
    if (rc != 2)  
    {
      DBaccess(DB,"SELECT currval('pkg_rpm_pkg_pk_seq'::regclass);");
      pkg_pk = atoi(DBgetvalue(DB,0,0));

      if (Verbose) { printf("pkg_pk:%d\n",pkg_pk);}
      int i;
      for (i=0;i<pi->req_size;i++)
      {
        memset(SQL,0,sizeof(SQL));
        snprintf(SQL,sizeof(SQL),"INSERT INTO pkg_rpm_req (pkg_fk,req_value) values (%d,E'%s');",pkg_pk,trim(pi->requires[i]));
        rc = DBaccess(DB,SQL);
        if (rc < 0)
        {
          DBaccess(DB,"ROLLBACK;");
          printf("LOG pkg %d ERROR: %s\n",pkg_pk,SQL);
          fflush(stdout);
          return FALSE;
        }
      }
      DBaccess(DB,"COMMIT;");
    } else {
      //ignore duplicate constraint failure, rollback transaction
      DBaccess(DB,"ROLLBACK;"); 
    }
  }
  return TRUE;	
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

  memset(Field,0,MAXCMD);
  memset(Value,0,MAXCMD);

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
 * GetMetadataDebBinary(long upload_pk, struct debpkginfo *pi)
 *
 * get debian binary package info
 */
int	GetMetadataDebBinary	(long upload_pk, struct debpkginfo *pi)
{
  char *repfile;
  char *filename;
  char SQL[MAXCMD];
  int  rc;
  unsigned long lft, rgt;
  
  FILE *fp;
  char field[MAXCMD];
  char value[MAXCMD];
  char line[MAXCMD];
  char *s = NULL;
  char temp[MAXCMD];

  /* Get the debian control file's repository path */
  /* First get the uploadtree bounds (lft,rgt) for the package */
  snprintf(SQL,sizeof(SQL),"SELECT lft,rgt FROM uploadtree WHERE upload_fk = %ld AND pfile_fk = %ld limit 1",
      upload_pk, pi->pFileFk);
  rc = DBaccess(DB,SQL);
  if (rc < 0)
  {
    printf("ERROR %s(%d): %s\n", __FILE__, __LINE__, SQL);
    fflush(stdout);
    return FALSE;
  }
  if (DBdatasize(DB) == 0)
  {
    printf("ERROR Missing debian package (internal data inconsistancy).  %s(%d): %s\n", __FILE__, __LINE__, SQL);
    fflush(stdout);
    return FALSE;
  } 
  lft = strtoul(DBgetvalue(DB,0,0), NULL, 10);	
  rgt = strtoul(DBgetvalue(DB,0,1), NULL, 10);	

  snprintf(SQL,sizeof(SQL),"SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size FROM pfile, uploadtree where (pfile_pk=pfile_fk) and (upload_fk = %ld) AND (lft > %ld) AND (rgt < %ld) AND (ufile_name = 'control')",
      upload_pk, lft, rgt);
  rc = DBaccess(DB,SQL);
  if (rc < 0)
  {
    printf("LOG ERROR: %s\n",SQL);
    fflush(stdout);
    return FALSE;
  }
  if (DBdatasize(DB) > 0)
  {
    filename = DBgetvalue(DB,0,0);	
    repfile = RepMkPath("files", filename);
    if (!repfile) {
      printf("FATAL: PkgAgent unable to open file %s\n",filename);
      fflush(stdout);
      return FALSE;
    }
  } 
  else 
  {
    printf("LOG: Unable to find debian/control file! This file had wrong mimetype, ignore it!\n");
    memset(SQL,0,sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"UPDATE pfile SET pfile_mimetypefk = NULL WHERE pfile_pk = %ld;", pi->pFileFk);
    DBaccess(DB,SQL);
    return FALSE;
  }

  /* Parse the debian/control file to get every Field and Value */
  if ((fp = fopen(repfile, "r")) == NULL){
    printf("FATAL: Unable to open debian/control file %s\n",repfile);
    fflush(stdout);
    return FALSE;
  }

  while (fgets(line,MAXCMD,fp)!=NULL)
  {
    s = ParseDebFile(line,field,value);
    if (!strcasecmp(field, "Description")) {
      EscapeString(value, pi->summary, sizeof(pi->summary));
      strcpy(temp, "");
    }
    if ((s[0] != '\0') && (temp!=NULL))
      strcat(temp,s);
    if (!strcasecmp(field, "Package")) {
      EscapeString(value, pi->pkgName, sizeof(pi->pkgName));
    }
    if (!strcasecmp(field, "Version")) {
      EscapeString(value, pi->version, sizeof(pi->version));
    }
    if (!strcasecmp(field, "Architecture")) {
      EscapeString(value, pi->pkgArch, sizeof(pi->pkgArch));
    }
    if (!strcasecmp(field, "Maintainer")) {
      EscapeString(value, pi->maintainer, sizeof(pi->maintainer));
    }
    if (!strcasecmp(field, "Installed-Size")) {
      pi->installedSize=atol(value);
    }
    if (!strcasecmp(field, "Section")) {
      EscapeString(value, pi->section, sizeof(pi->section));
    }
    if (!strcasecmp(field, "Priority")) {
      EscapeString(value, pi->priority, sizeof(pi->priority));
    }
    if (!strcasecmp(field, "Homepage")) {
      EscapeString(value, pi->homepage, sizeof(pi->homepage));
    }
    if (!strcasecmp(field, "Source")) {
      EscapeString(value, pi->source, sizeof(pi->source));
    }
    if (!strcasecmp(field, "Depends")) {
      char *depends = NULL;
      char tempvalue[MAXCMD];
      int size,i,length;
      length = MAXLENGTH;
      size = 0;
      if (value[0] != '\0'){
        strncpy(tempvalue, value, sizeof(tempvalue));
        depends = strtok(value, ",");
        while (depends && (depends[0] != '\0')) {
          if (strlen(depends) >= length)
            length = strlen(depends) + 1;
          depends = strtok(NULL, ",");
          size++;
        }
        if (Verbose) { printf("SIZE:%d\n", size);}
        
        pi->depends = calloc(size, sizeof(char *));
        pi->depends[0] = calloc(length, sizeof(char));
        strcpy(pi->depends[0],strtok(tempvalue,","));
        for (i=1;i<size;i++){
          pi->depends[i] = calloc(length, sizeof(char));
          strcpy(pi->depends[i],strtok(NULL, ","));
        }
        pi->dep_size = size;
      }
    }
  }
  if (temp!=NULL)
    EscapeString(temp, pi->description, sizeof(pi->description));

  fclose(fp);
  return TRUE;
}/* GetMetadataDebBinary(struct debpkginfo *pi) */

/**
 * RecordMetadataDEB(struct debpkginfo *pi)
 * 
 * Store debian package info into database
 */
int    RecordMetadataDEB       (struct debpkginfo *pi)
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
    return FALSE;
  }
  if (DBdatasize(DB) <=0)
  {
    memset(SQL,0,sizeof(SQL));
    DBaccess(DB,"BEGIN;");
    snprintf(SQL,sizeof(SQL),"INSERT INTO pkg_deb (pkg_name,pkg_arch,version,maintainer,installed_size,section,priority,homepage,source,summary,description,format,uploaders,standards_version,pfile_fk) values (E'%s',E'%s',E'%s',E'%s',%d,E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',E'%s',%ld);",trim(pi->pkgName),trim(pi->pkgArch),trim(pi->version),trim(pi->maintainer),pi->installedSize,trim(pi->section),trim(pi->priority),trim(pi->homepage),trim(pi->source),trim(pi->summary),trim(pi->description),trim(pi->format),trim(pi->uploaders),trim(pi->standardsVersion),pi->pFileFk);
    rc = DBaccess(DB,SQL);
    if (rc < 0)
    {
      DBaccess(DB,"ROLLBACK;");
      printf("ERROR pfile %s Unable to access database.\n",pi->pFile);
      printf("LOG pfile %s ERROR: %s\n",pi->pFile,SQL);
      fflush(stdout);
      return FALSE;
    }
    if (rc != 2)
    {
      DBaccess(DB,"SELECT currval('pkg_deb_pkg_pk_seq'::regclass);");
      pkg_pk = atoi(DBgetvalue(DB,0,0));

      if (Verbose) { printf("pkg_pk:%d\n",pkg_pk);}
      int i;
      for (i=0;i<pi->dep_size;i++)
      {
        memset(SQL,0,sizeof(SQL));
        snprintf(SQL,sizeof(SQL),"INSERT INTO pkg_deb_req (pkg_fk,req_value) values (%d,E'%s');",pkg_pk,trim(pi->depends[i]));
        if (Verbose) { printf("DEPENDS:%s\n",pi->depends[i]);}
        rc = DBaccess(DB,SQL);
        if (rc < 0)
        {
          DBaccess(DB,"ROLLBACK;");
          printf("LOG pkg %d ERROR: %s\n",pkg_pk,SQL);
          fflush(stdout);
          return FALSE;
        }
      }
      DBaccess(DB,"COMMIT;");
    } else {
      //ignore duplicate constraint failure, rollback transaction
      DBaccess(DB,"ROLLBACK;");
    }
  }
  return TRUE;
}/* RecordMetadataDEB(struct debpkginfo *pi) */

/**
 * GetMetadataDebSource(char *repFile, struct debpkginfo *pi)
 *
 * get debian source package info from .dsc file
 **/
int	GetMetadataDebSource	(char *repFile, struct debpkginfo *pi)
{ 
  FILE *fp;
  char field[MAXCMD];
  char value[MAXCMD];
  char line[MAXCMD];
  char *s = NULL;

  /*  Parse the debian .dsc file to get every Field and Value */
  if ((fp = fopen(repFile, "r")) == NULL){
    printf("FATAL: Unable to open .dsc file %s\n",repFile);
    fflush(stdout);
    return FALSE;
  }

  while (fgets(line,MAXCMD,fp)!=NULL)
  {
    s = ParseDebFile(line,field,value);

    if (!strcasecmp(field, "Format")) {
      EscapeString(value, pi->format, sizeof(pi->format));
    }
    if (!strcasecmp(field, "Source")) {
      EscapeString(value, pi->source, sizeof(pi->source));
    }
    if (!strcasecmp(field, "Source")) {
      EscapeString(value, pi->pkgName, sizeof(pi->pkgName));
    }
    if (!strcasecmp(field, "Architecture")) {
      EscapeString(value, pi->pkgArch, sizeof(pi->pkgArch));
    }
    if (!strcasecmp(field, "Version")) {
      if (strlen(pi->version) == 0)
        EscapeString(value, pi->version, sizeof(pi->version));
    }
    if (!strcasecmp(field, "Maintainer")) {
      EscapeString(value, pi->maintainer, sizeof(pi->maintainer));
    }
    if (!strcasecmp(field, "Uploaders")) {
      EscapeString(value, pi->uploaders, sizeof(pi->uploaders));
    }
    if (!strcasecmp(field, "Standards-Version")) {
      EscapeString(value, pi->standardsVersion, sizeof(pi->standardsVersion));
    }
    if (!strcasecmp(field, "Build-Depends")) {
      char *depends = NULL;
      char tempvalue[MAXCMD];
      int size,i,length;
      size = 0;
      length = MAXLENGTH;
      if (value[0] != '\0'){
        strncpy(tempvalue, value, sizeof(tempvalue));
        depends = strtok(value, ",");
        while (depends && (depends[0] != '\0')) {
          if (strlen(depends) >= length)
            length = strlen(depends) + 1;
          depends = strtok(NULL, ",");
          size++;
        }
        if (Verbose) { printf("SIZE:%d\n", size);}

        pi->depends = calloc(size, sizeof(char *));
        pi->depends[0] = calloc(length, sizeof(char));
        strcpy(pi->depends[0],strtok(tempvalue,","));
        for (i=1;i<size;i++){
          pi->depends[i] = calloc(length, sizeof(char));
          strcpy(pi->depends[i],strtok(NULL, ","));
        }
        pi->dep_size = size;
      }
    }
  }

  fclose(fp);
  return TRUE;
}/*  GetMetadataDebSource(char *repFile, struct debpkginfo *pi) */

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

