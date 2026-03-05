/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file fosspkg.c
 * \brief Shared package-parsing library implementation.
 *
 * Location: src/lib/c/fosspkg.c
 * Compiled into: libfossology (fossology CMake target)
 * Header:  src/lib/c/fosspkg.h
 *
 * Parsers implemented here:
 *   FossPkg_ParseDpkgStatus   — /var/lib/dpkg/status
 *   FossPkg_ParseDebControl   — single .deb control file stanza
 *   FossPkg_ParseApkInstalled — /lib/apk/db/installed
 *   FossPkg_ParseRpmSqlite    — /var/lib/rpm/rpmdb.sqlite
 *
 * All parsers share the same line-oriented RFC-822 state machine for
 * the text-based formats.  The RPM parser uses libsqlite3.
 */

#include "fosspkg.h"
#include <libgen.h>
#include <errno.h>
#include <stdint.h>
#include <sqlite3.h>

/* =========================================================================
 * Internal helpers
 * ========================================================================= */

/** Trim leading and trailing whitespace in-place, return pointer. */
static char *Trim(char *s)
{
  if (!s) return s;
  while (isspace((unsigned char)*s)) s++;
  char *end = s + strlen(s);
  while (end > s && isspace((unsigned char)*(end-1))) end--;
  *end = '\0';
  return s;
}

/** Safe strncpy that always NUL-terminates. */
static void SafeCopy(char *dst, const char *src, size_t dstLen)
{
  if (!dst || !src || dstLen == 0) return;
  strncpy(dst, src, dstLen - 1);
  dst[dstLen - 1] = '\0';
}

/** Read entire file into a malloc'd buffer. Caller must free. */
static char *ReadFile(const char *path)
{
  FILE *fp = fopen(path, "rb");
  if (!fp) {
    FOSSPKG_ERR("cannot open '%s': %s", path, strerror(errno));
    return NULL;
  }
  fseek(fp, 0, SEEK_END);
  long sz = ftell(fp);
  rewind(fp);
  if (sz <= 0) { fclose(fp); return NULL; }

  char *buf = malloc(sz + 2);
  if (!buf) { fclose(fp); return NULL; }

  size_t got = fread(buf, 1, sz, fp);
  fclose(fp);
  buf[got] = '\n';   /* ensure file ends with newline for parser */
  buf[got+1] = '\0';
  return buf;
}

/** Append a dependency string to a FossPkgInfo's requires array. */
static void AppendDep(FossPkgInfo *pi, const char *dep)
{
  if (!dep || !*dep) return;
  if (pi->requireCount >= FOSSPKG_MAX_DEPS) return;
  char **tmp = realloc(pi->requires,
                       (pi->requireCount + 1) * sizeof(char *));
  if (!tmp) {
    /*
     * realloc() returns NULL on failure but does NOT free the original block.
     * The original pi->requires pointer is still valid; leave it intact so
     * FossPkgInfoFree() can correctly free all previously appended entries.
     * We simply drop this one new entry rather than corrupting the struct.
     */
    FOSSPKG_ERR("AppendDep: realloc failed at count=%d — entry dropped",
                pi->requireCount);
    return;
  }
  pi->requires = tmp;
  pi->requires[pi->requireCount++] = strdup(dep);
}

/**
 * \brief Split a comma-separated dependency string and append each token.
 *
 * Handles Debian-style "pkg (>= ver), pkg2, pkg3 | alt" by splitting on
 * commas only — the version constraint and alternates are kept intact so
 * the UI can display them as-is.
 */
static void AppendDepList(FossPkgInfo *pi, const char *depStr)
{
  if (!depStr || !*depStr) return;
  char *copy = strdup(depStr);
  char *tok  = strtok(copy, ",");
  while (tok) {
    AppendDep(pi, Trim(tok));
    tok = strtok(NULL, ",");
  }
  free(copy);
}

/* =========================================================================
 * Lifecycle
 * ========================================================================= */

FossPkgInfo *FossPkgInfoAlloc(void)
{
  FossPkgInfo *pi = calloc(1, sizeof(FossPkgInfo));
  if (!pi) { FOSSPKG_ERR("OOM allocating FossPkgInfo"); abort(); }
  pi->layerIndex = -1;
  pi->manager    = FOSSPKG_MGR_UNKNOWN;
  return pi;
}

void FossPkgInfoFree(FossPkgInfo *pi)
{
  if (!pi) return;
  for (int i = 0; i < pi->requireCount; i++) free(pi->requires[i]);
  free(pi->requires);
  free(pi);
}

FossPkgList *FossPkgListAlloc(void)
{
  FossPkgList *list = calloc(1, sizeof(FossPkgList));
  if (!list) { FOSSPKG_ERR("OOM allocating FossPkgList"); abort(); }
  return list;
}

void FossPkgListAppend(FossPkgList *list, FossPkgInfo *pi)
{
  if (!list || !pi) return;
  if (list->count >= list->cap) {
    int newCap = list->cap ? list->cap * 2 : 64;
    if (newCap > FOSSPKG_MAX_PKGS) newCap = FOSSPKG_MAX_PKGS;
    FossPkgInfo **tmp = realloc(list->pkgs, newCap * sizeof(FossPkgInfo *));
    if (!tmp) {
      /*
       * realloc() does NOT free the original block on failure — the original
       * list->pkgs pointer remains valid.  Do NOT null it out here; doing so
       * would leak every FossPkgInfo pointer already stored in the array and
       * corrupt subsequent FossPkgListFree() calls.
       *
       * We simply cannot grow the array right now, so free only the new
       * entry that could not be stored and return.
       */
      FOSSPKG_ERR("FossPkgListAppend: realloc failed (count=%d) — entry dropped",
                  list->count);
      FossPkgInfoFree(pi);
      return;
    }
    list->pkgs = tmp;
    list->cap  = newCap;
  }
  if (list->count < FOSSPKG_MAX_PKGS)
    list->pkgs[list->count++] = pi;
  else
    FossPkgInfoFree(pi);
}

void FossPkgListFree(FossPkgList *list)
{
  if (!list) return;
  for (int i = 0; i < list->count; i++) FossPkgInfoFree(list->pkgs[i]);
  free(list->pkgs);
  free(list);
}

/* =========================================================================
 * RFC-822 stanza parser (shared by dpkg status and deb control)
 *
 * The format is:
 *   Field: value
 *   Field: multi
 *    line continuation (leading space)
 *
 *   (blank line separates stanzas)
 * ========================================================================= */

/**
 * \brief Callback invoked for each field in a stanza.
 * \param pi     Package being built.
 * \param field  Field name (NUL-terminated, trimmed).
 * \param value  Field value (NUL-terminated, trimmed, continuations joined).
 */
typedef void (*RFC822FieldCb)(FossPkgInfo *pi,
                               const char  *field,
                               const char  *value);

/**
 * \brief Common RFC-822 field handler for dpkg/deb fields.
 */
static void Rfc822_DpkgField(FossPkgInfo *pi,
                              const char  *field,
                              const char  *value)
{
  if      (strcasecmp(field, "Package")      == 0) SafeCopy(pi->name,        value, sizeof(pi->name));
  else if (strcasecmp(field, "Version")      == 0) SafeCopy(pi->version,     value, sizeof(pi->version));
  else if (strcasecmp(field, "Architecture") == 0) SafeCopy(pi->arch,        value, sizeof(pi->arch));
  else if (strcasecmp(field, "Source")       == 0) {
    /* For .dsc files the name field is "Source:", not "Package:".
     * Copy to pi->source always; also seed pi->name if not yet set
     * so FossPkg_ParseDebControl() returns a non-NULL result. */
    SafeCopy(pi->source, value, sizeof(pi->source));
    if (pi->name[0] == '\0')
      SafeCopy(pi->name, value, sizeof(pi->name));
  }
  else if (strcasecmp(field, "Maintainer")   == 0) SafeCopy(pi->maintainer,  value, sizeof(pi->maintainer));
  else if (strcasecmp(field, "Homepage")     == 0) SafeCopy(pi->url,         value, sizeof(pi->url));
  else if (strcasecmp(field, "Status")       == 0) SafeCopy(pi->status,      value, sizeof(pi->status));
  else if (strcasecmp(field, "Section")      == 0) SafeCopy(pi->section,     value, sizeof(pi->section));
  else if (strcasecmp(field, "Priority")     == 0) SafeCopy(pi->priority,    value, sizeof(pi->priority));
  else if (strcasecmp(field, "Description")  == 0) SafeCopy(pi->summary,     value, sizeof(pi->summary));
  else if (strcasecmp(field, "Depends")      == 0) AppendDepList(pi, value);
  else if (strcasecmp(field, "Pre-Depends")  == 0) AppendDepList(pi, value);
  else if (strcasecmp(field, "Build-Depends")== 0) AppendDepList(pi, value);
}

/**
 * \brief Parse a buffer containing one or more RFC-822 stanzas.
 *
 * \param buf      NUL-terminated text buffer (will be modified in-place).
 * \param mgr      Package manager tag to set on each record.
 * \param fieldCb  Called for each field:value pair.
 * \param onlyInstalled  If true, skip stanzas where Status != "*installed*".
 * \return         FossPkgList (caller owns).
 */
static FossPkgList *ParseRfc822(char          *buf,
                                 FossPkgManager mgr,
                                 RFC822FieldCb  fieldCb,
                                 int            onlyInstalled)
{
  FossPkgList *list = FossPkgListAlloc();
  FossPkgInfo *pi   = FossPkgInfoAlloc();
  pi->manager = mgr;

  char  fieldName[256]           = "";
  char  fieldVal[FOSSPKG_MAXFIELD] = "";
  int   inContinuation           = 0;

  char *line = buf;
  char *next;

  while (line && *line) {
    /* Find end of current line */
    next = strchr(line, '\n');
    if (next) *next = '\0';

    /* Strip trailing CR */
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';

    if (*line == '\0') {
      /* Blank line — end of stanza */
      if (inContinuation && fieldName[0]) {
        /* Flush last field */
        fieldCb(pi, fieldName, Trim(fieldVal));
      }
      /* Save stanza if valid and (if required) installed */
      if (pi->name[0]) {
        int keep = 1;
        if (onlyInstalled && strstr(pi->status, "installed") == NULL)
          keep = 0;
        if (keep)
          FossPkgListAppend(list, pi);
        else
          FossPkgInfoFree(pi);
      } else {
        FossPkgInfoFree(pi);
      }
      pi = FossPkgInfoAlloc();
      pi->manager = mgr;
      fieldName[0]     = '\0';
      fieldVal[0]      = '\0';
      inContinuation   = 0;
    } else if ((*line == ' ' || *line == '\t') && inContinuation) {
      /* Continuation line — append to current field value */
      size_t curLen = strlen(fieldVal);
      if (curLen + 2 < sizeof(fieldVal)) {
        fieldVal[curLen]   = '\n';
        fieldVal[curLen+1] = '\0';
        strncat(fieldVal, Trim(line), sizeof(fieldVal) - curLen - 2);
        fieldVal[sizeof(fieldVal) - 1] = '\0'; /* always NUL-terminate */
      }
    } else {
      /* New field — flush previous */
      if (inContinuation && fieldName[0])
        fieldCb(pi, fieldName, Trim(fieldVal));

      char *colon = strchr(line, ':');
      if (colon) {
        *colon = '\0';
        SafeCopy(fieldName, Trim(line),    sizeof(fieldName));
        SafeCopy(fieldVal,  Trim(colon+1), sizeof(fieldVal));
        inContinuation = 1;
      }
    }

    line = next ? next + 1 : NULL;
  }

  /* Flush trailing stanza (file may not end with blank line) */
  if (inContinuation && fieldName[0])
    fieldCb(pi, fieldName, Trim(fieldVal));
  if (pi->name[0]) {
    int keep = 1;
    if (onlyInstalled && strstr(pi->status, "installed") == NULL)
      keep = 0;
    if (keep)
      FossPkgListAppend(list, pi);
    else
      FossPkgInfoFree(pi);
  } else {
    FossPkgInfoFree(pi);
  }

  return list;
}

/* =========================================================================
 * FossPkg_ParseDpkgStatus
 * ========================================================================= */
FossPkgList *FossPkg_ParseDpkgStatus(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list = ParseRfc822(buf, FOSSPKG_MGR_DPKG,
                                   Rfc822_DpkgField,
                                   1 /* onlyInstalled */);
  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParseDebControl
 * ========================================================================= */
FossPkgInfo *FossPkg_ParseDebControl(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  /* Parse as a single-stanza dpkg file (no status filter needed) */
  FossPkgList *list = ParseRfc822(buf, FOSSPKG_MGR_DEB,
                                   Rfc822_DpkgField,
                                   0 /* all stanzas */);
  free(buf);

  FossPkgInfo *pi = NULL;
  if (list->count > 0) {
    pi = list->pkgs[0];
    list->pkgs[0] = NULL;  /* take ownership */
  }
  FossPkgListFree(list);
  return pi;
}

/* =========================================================================
 * FossPkg_ParseApkInstalled
 *
 * Alpine apk format (/lib/apk/db/installed):
 *   Each package block is blank-line separated.
 *   Each line: <single-letter-key>:<value>
 *
 *   P:package-name
 *   V:1.2.3-r0
 *   A:x86_64
 *   L:MIT
 *   T:Short description
 *   U:https://homepage
 *   D:dep1 dep2 dep3        (space-separated, no commas)
 *   o:origin-package
 *   m:maintainer@example.com
 * ========================================================================= */
static void Apk_HandleBlock(FossPkgList *list, char **lines, int nLines)
{
  FossPkgInfo *pi = FossPkgInfoAlloc();
  pi->manager = FOSSPKG_MGR_APK;

  for (int i = 0; i < nLines; i++) {
    char *l = lines[i];
    if (strlen(l) < 2 || l[1] != ':') continue;
    char key = l[0];
    char *val = Trim(l + 2);

    switch (key) {
      case 'P': SafeCopy(pi->name,        val, sizeof(pi->name));        break;
      case 'V': SafeCopy(pi->version,     val, sizeof(pi->version));     break;
      case 'A': SafeCopy(pi->arch,        val, sizeof(pi->arch));        break;
      case 'L': SafeCopy(pi->license,     val, sizeof(pi->license));     break;
      case 'T': SafeCopy(pi->summary,     val, sizeof(pi->summary));     break;
      case 'U': SafeCopy(pi->url,         val, sizeof(pi->url));         break;
      case 'm': SafeCopy(pi->maintainer,  val, sizeof(pi->maintainer));  break;
      case 'o': SafeCopy(pi->source,      val, sizeof(pi->source));      break;
      case 'D': {
        /* space-separated dep list */
        char *copy = strdup(val);
        char *tok  = strtok(copy, " \t");
        while (tok) {
          if (*tok && strncmp(tok, "so:", 3) != 0)
            AppendDep(pi, tok);
          tok = strtok(NULL, " \t");
        }
        free(copy);
        break;
      }
    }
  }

  if (pi->name[0])
    FossPkgListAppend(list, pi);
  else
    FossPkgInfoFree(pi);
}

FossPkgList *FossPkg_ParseApkInstalled(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list   = FossPkgListAlloc();
  char        *blockLines[1024];
  int          nLines = 0;
  char        *line   = buf;
  char        *next;

  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';

    /* Strip CR */
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';

    if (*line == '\0') {
      /* End of block */
      if (nLines > 0) {
        Apk_HandleBlock(list, blockLines, nLines);
        nLines = 0;
      }
    } else {
      if (nLines < 1024)
        blockLines[nLines++] = line;
    }
    line = next ? next + 1 : NULL;
  }
  /* Flush last block */
  if (nLines > 0)
    Apk_HandleBlock(list, blockLines, nLines);

  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParseRpmSqlite
 *
 * Modern RPM (4.16+) stores its database in an sqlite3 file.
 * The main table is "Packages" with a "blob" column containing the
 * RPM header in XDR/network-byte-order binary.  However, rpm 4.16+
 * also exposes a key-value table "sqlite_Packages" (or similar) on some
 * distros, and rpm --exportdb writes human-readable XML.
 *
 * The most portable approach that doesn't require librpm is to look for
 * the "rpmdb.sqlite" schema used by Fedora/RHEL 9+ which stores a
 * denormalised "Packages" table with TEXT columns for name, version, etc.
 * via the "rpm-sequoia" backend.
 *
 * For distros still using the BerkeleyDB format (RHEL 7/8), we return
 * an empty list with a warning — full BDB parsing would require librpm
 * which is an optional dependency.  We detect BDB by the magic bytes.
 *
 * Schema (Fedora 37+ / RHEL 9+):
 *   CREATE TABLE Packages (
 *     hnum     INTEGER PRIMARY KEY,
 *     blob     BLOB NOT NULL         -- header in network byte order
 *   );
 *   Plus a header_image table on some versions.
 *
 * Because the blob format requires librpm to decode properly, we also
 * check for a simpler denormalised view that some rpm versions create:
 *   CREATE TABLE nvra (name TEXT, version TEXT, release TEXT, arch TEXT);
 *
 * If neither is available we fall back to running `rpm --dbpath --query`
 * but that requires rpm to be installed on the scanning host, which is
 * not guaranteed.  In that case we return empty with a warning.
 * ========================================================================= */

/** SQLite3 magic bytes */
#define SQLITE3_MAGIC "\x53\x51\x4c\x69\x74\x65\x20\x66\x6f\x72\x6d\x61\x74\x20\x33\x00"

static int IsSqlite3(const char *path)
{
  FILE *fp = fopen(path, "rb");
  if (!fp) return 0;
  char magic[16];
  int  ok = (fread(magic, 1, 16, fp) == 16 &&
             memcmp(magic, SQLITE3_MAGIC, 16) == 0);
  fclose(fp);
  return ok;
}

FossPkgList *FossPkg_ParseRpmSqlite(const char *path)
{
  if (!IsSqlite3(path)) {
    FOSSPKG_ERR("'%s' is not an sqlite3 file (legacy BerkeleyDB format "
                "requires librpm — skipping)", path);
    return FossPkgListAlloc();   /* empty, not NULL */
  }

  sqlite3 *db = NULL;
  if (sqlite3_open_v2(path, &db, SQLITE_OPEN_READONLY, NULL) != SQLITE_OK) {
    FOSSPKG_ERR("sqlite3_open '%s': %s", path, sqlite3_errmsg(db));
    sqlite3_close(db);
    return NULL;
  }

  FossPkgList *list = FossPkgListAlloc();

  /*
   * Try the denormalised "nvra" view first (rpm-sequoia / Fedora 37+).
   * Fall back to a direct query on the Packages table if that fails.
   *
   * The Packages table blob can be decoded without librpm by reading
   * the RPM header index structure, but that is complex.  We handle
   * both paths:
   *
   *   1. nvra view (or similar) → TEXT columns → easy
   *   2. Raw blob in Packages   → parse index entries for known tags
   */

  /* ---- Attempt 1: look for a text-column view or table ---- */
  const char *textQueries[] = {
    /* rpm-sequoia schema (Fedora 37+) */
    "SELECT name, version || '-' || release, arch, sourcerpm, summary, "
    "       requirename "
    "FROM nvra LEFT JOIN requires USING(hnum) "
    "GROUP BY nvra.hnum;",

    /* Simpler fallback — just name/version/arch if full view absent */
    "SELECT name, version || '-' || release, arch, '', summary, '' "
    "FROM nvra;",

    NULL
  };

  int        found = 0;
  sqlite3_stmt *stmt = NULL;

  for (int qi = 0; textQueries[qi] && !found; qi++) {
    if (sqlite3_prepare_v2(db, textQueries[qi], -1, &stmt, NULL)
        == SQLITE_OK) {
      found = 1;
      while (sqlite3_step(stmt) == SQLITE_ROW) {
        FossPkgInfo *pi = FossPkgInfoAlloc();
        pi->manager = FOSSPKG_MGR_RPM;

        const char *col;
#define COLSTR(i) ((col = (const char*)sqlite3_column_text(stmt, i)) ? col : "")
        SafeCopy(pi->name,        COLSTR(0), sizeof(pi->name));
        SafeCopy(pi->version,     COLSTR(1), sizeof(pi->version));
        SafeCopy(pi->arch,        COLSTR(2), sizeof(pi->arch));
        SafeCopy(pi->source,      COLSTR(3), sizeof(pi->source));
        SafeCopy(pi->summary,     COLSTR(4), sizeof(pi->summary));
        if (COLSTR(5)[0]) AppendDep(pi, COLSTR(5));
#undef COLSTR

        if (pi->name[0])
          FossPkgListAppend(list, pi);
        else
          FossPkgInfoFree(pi);
      }
      sqlite3_finalize(stmt);
      stmt = NULL;
    }
  }

  /* ---- Attempt 2: raw Packages blob (decode RPM header index) ---- */
  if (!found) {
    /*
     * RPM header wire format (big-endian):
     *   8 bytes magic + reserved
     *   4 bytes nindex (number of index entries)
     *   4 bytes hsize  (size of data store)
     *   nindex * 16 bytes index entries:
     *     4 bytes tag, 4 bytes type, 4 bytes offset, 4 bytes count
     *   hsize bytes data store
     *
     * Tag IDs we care about:
     *   1000 RPMTAG_NAME
     *   1001 RPMTAG_VERSION
     *   1002 RPMTAG_RELEASE
     *   1022 RPMTAG_ARCH
     *   1044 RPMTAG_SOURCERPM
     *   1004 RPMTAG_SUMMARY
     *   1049 RPMTAG_REQUIRENAME
     */
    const char *blobQuery =
      "SELECT blob FROM Packages ORDER BY hnum;";

    if (sqlite3_prepare_v2(db, blobQuery, -1, &stmt, NULL) == SQLITE_OK) {
      while (sqlite3_step(stmt) == SQLITE_ROW) {
        const uint8_t *blob = sqlite3_column_blob(stmt, 0);
        int blobLen         = sqlite3_column_bytes(stmt, 0);
        if (!blob || blobLen < 16) continue;

        /* Validate magic: 0x8e 0xad 0xe8 0x01 ... */
        if (blob[0] != 0x8e || blob[1] != 0xad ||
            blob[2] != 0xe8 || blob[3] != 0x01) continue;

        uint32_t nindex = ((uint32_t)blob[8]  << 24) |
                          ((uint32_t)blob[9]  << 16) |
                          ((uint32_t)blob[10] <<  8) |
                           (uint32_t)blob[11];
        uint32_t hsize  = ((uint32_t)blob[12] << 24) |
                          ((uint32_t)blob[13] << 16) |
                          ((uint32_t)blob[14] <<  8) |
                           (uint32_t)blob[15];

        const uint8_t *idx   = blob + 16;
        const uint8_t *store = idx + nindex * 16;

        if ((int)(16 + nindex * 16 + hsize) > blobLen) continue;

        FossPkgInfo *pi = FossPkgInfoAlloc();
        pi->manager = FOSSPKG_MGR_RPM;

        char rel[FOSSPKG_MAXNAME] = "";

        for (uint32_t i = 0; i < nindex; i++) {
          const uint8_t *e = idx + i * 16;
          uint32_t tag    = ((uint32_t)e[0]<<24)|((uint32_t)e[1]<<16)|
                            ((uint32_t)e[2]<< 8)| (uint32_t)e[3];
          /* uint32_t type = ...; */
          uint32_t offset = ((uint32_t)e[8]<<24)|((uint32_t)e[9]<<16)|
                            ((uint32_t)e[10]<<8)| (uint32_t)e[11];

          if (offset >= hsize) continue;
          const char *s = (const char *)(store + offset);

          switch (tag) {
            case 1000: SafeCopy(pi->name,    s, sizeof(pi->name));    break;
            case 1001: SafeCopy(pi->version, s, sizeof(pi->version)); break;
            case 1002: SafeCopy(rel,         s, sizeof(rel));          break;
            case 1022: SafeCopy(pi->arch,    s, sizeof(pi->arch));    break;
            case 1044: SafeCopy(pi->source,  s, sizeof(pi->source));  break;
            case 1004: SafeCopy(pi->summary, s, sizeof(pi->summary)); break;
            case 1049: AppendDep(pi, s);                               break;
          }
        }

        /* Combine version-release */
        if (rel[0] && strlen(pi->version) + strlen(rel) + 2
            < sizeof(pi->version)) {
          strncat(pi->version, "-", sizeof(pi->version) - strlen(pi->version) - 1);
          strncat(pi->version, rel,  sizeof(pi->version) - strlen(pi->version) - 1);
        }

        if (pi->name[0])
          FossPkgListAppend(list, pi);
        else
          FossPkgInfoFree(pi);
      }
      sqlite3_finalize(stmt);
      found = 1;
    }
  }

  if (!found)
    FOSSPKG_ERR("'%s': no recognised RPM table schema found", path);

  sqlite3_close(db);
  return list;
}

/* =========================================================================
 * Utilities
 * ========================================================================= */

FossPkgManager FossPkg_DetectManager(const char *path)
{
  if (!path) return FOSSPKG_MGR_UNKNOWN;

  /* Work on a copy since basename() may modify its argument */
  char *copy = strdup(path);
  char *base = basename(copy);

  FossPkgManager mgr = FOSSPKG_MGR_UNKNOWN;

  if      (strcmp(base, "status")       == 0) mgr = FOSSPKG_MGR_DPKG;
  else if (strcmp(base, "installed")    == 0) mgr = FOSSPKG_MGR_APK;
  else if (strcmp(base, "rpmdb.sqlite") == 0) mgr = FOSSPKG_MGR_RPM;
  else if (strcmp(base, "Packages.db")  == 0) mgr = FOSSPKG_MGR_RPM;
  else if (strcmp(base, "Packages")     == 0) mgr = FOSSPKG_MGR_RPM;

  free(copy);
  return mgr;
}

const char *FossPkg_ManagerName(FossPkgManager mgr)
{
  switch (mgr) {
    case FOSSPKG_MGR_DPKG: return "dpkg";
    case FOSSPKG_MGR_APK:  return "apk";
    case FOSSPKG_MGR_RPM:  return "rpm";
    case FOSSPKG_MGR_DEB:  return "deb";
    default:               return "unknown";
  }
}

FossPkgList *FossPkg_ParseAuto(const char *path)
{
  FossPkgManager mgr = FossPkg_DetectManager(path);
  switch (mgr) {
    case FOSSPKG_MGR_DPKG: return FossPkg_ParseDpkgStatus(path);
    case FOSSPKG_MGR_APK:  return FossPkg_ParseApkInstalled(path);
    case FOSSPKG_MGR_RPM:  return FossPkg_ParseRpmSqlite(path);
    default:
      FOSSPKG_ERR("cannot detect package manager for '%s'", path);
      return NULL;
  }
}
