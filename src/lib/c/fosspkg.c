/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file fosspkg.c
 * \brief Shared package-parsing library implementation.
 *
 * All string fields in FossPkgInfo are heap char*.
 * FOSSPKG_SET() / FossPkgSet() are the only write paths — no strncpy,
 * no fixed buffers, no -Wstringop-truncation warnings.
 *
 * JSON parsing (npm, pip Pipfile, nuget, composer) uses json-c.
 * All other formats use bespoke line/XML parsers with no extra dependencies.
 */

#include "fosspkg.h"
#include <libgen.h>
#include <errno.h>
#include <stdint.h>
#include <sys/wait.h>
#include <unistd.h>

#ifdef FOSSPKG_HAVE_SQLITE3
#  include <sqlite3.h>
#endif

#include <json-c/json.h>

/* =========================================================================
 * Internal helpers
 * ========================================================================= */

/** Trim leading/trailing whitespace in-place; returns pointer into s. */
static char *Trim(char *s)
{
  if (!s) return s;
  while (isspace((unsigned char)*s)) s++;
  char *end = s + strlen(s);
  while (end > s && isspace((unsigned char)*(end - 1))) end--;
  *end = '\0';
  return s;
}

/** Read an entire file into a malloc'd, NUL-terminated buffer. Caller frees. */
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

  char *buf = malloc((size_t)sz + 2);
  if (!buf) { fclose(fp); return NULL; }

  size_t got = fread(buf, 1, (size_t)sz, fp);
  fclose(fp);
  buf[got]     = '\n'; /* sentinel so parsers never fall off the end */
  buf[got + 1] = '\0';
  return buf;
}

/** Append one dependency string to pi->requires[]; grows array by doubling. */
static void AppendDep(FossPkgInfo *pi, const char *dep)
{
  if (!dep || !dep[0]) return;
  if (pi->requireCount >= pi->requireCap) {
    int newCap = pi->requireCap ? pi->requireCap * 2 : FOSSPKG_INIT_DEPS;
    char **tmp = realloc(pi->requires, (size_t)newCap * sizeof(char *));
    if (!tmp) {
      FOSSPKG_ERR("AppendDep: realloc failed at count=%d — dep dropped",
                  pi->requireCount);
      return;
    }
    pi->requires   = tmp;
    pi->requireCap = newCap;
  }
  pi->requires[pi->requireCount++] = strdup(dep);
}

/** Split a comma-separated dep string and append each token. */
static void AppendDepList(FossPkgInfo *pi, const char *depStr)
{
  if (!depStr || !depStr[0]) return;
  char *copy = strdup(depStr);
  if (!copy) return;
  char *tok = strtok(copy, ",");
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
  free(pi->name);       free(pi->version);   free(pi->arch);
  free(pi->source);     free(pi->summary);   free(pi->description);
  free(pi->maintainer); free(pi->license);   free(pi->url);
  free(pi->section);    free(pi->priority);  free(pi->status);
  free(pi->dbFilePath);
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

  if (list->count >= FOSSPKG_HARD_MAX_PKGS) {
    static int warnedOnce = 0;
    if (!warnedOnce) {
      FOSSPKG_WARN("hard package cap (%d) reached — further entries dropped",
                   FOSSPKG_HARD_MAX_PKGS);
      warnedOnce = 1;
    }
    FossPkgInfoFree(pi);
    return;
  }

  if (list->count >= list->cap) {
    int newCap = list->cap ? list->cap * 2 : FOSSPKG_INIT_PKGS;
    if (newCap > FOSSPKG_HARD_MAX_PKGS) newCap = FOSSPKG_HARD_MAX_PKGS;
    FossPkgInfo **tmp = realloc(list->pkgs,
                                (size_t)newCap * sizeof(FossPkgInfo *));
    if (!tmp) {
      FOSSPKG_ERR("FossPkgListAppend: realloc failed (count=%d) — entry dropped",
                  list->count);
      FossPkgInfoFree(pi);
      return;
    }
    list->pkgs = tmp;
    list->cap  = newCap;
  }
  list->pkgs[list->count++] = pi;
}

void FossPkgListFree(FossPkgList *list)
{
  if (!list) return;
  for (int i = 0; i < list->count; i++) FossPkgInfoFree(list->pkgs[i]);
  free(list->pkgs);
  free(list);
}

/* =========================================================================
 * RFC-822 stanza parser (dpkg status + deb control + dist-info METADATA)
 *
 * FvalAppend / FlushStanza were previously macros with non-trivial side
 * effects.  They are now static inline functions for type safety, predictable
 * argument evaluation, and easier debugging.
 * ========================================================================= */

typedef void (*RFC822FieldCb)(FossPkgInfo *pi,
                               const char  *field,
                               const char  *value);

/* ---- Dynamic field-value buffer ---- */
typedef struct {
  char  *data;
  size_t len;
  size_t cap;
} FvalBuf;

static void FvalAppend(FvalBuf *fb, const char *s)
{
  if (!s) return;
  size_t slen = strlen(s);
  if (fb->len + slen + 2 > fb->cap) {
    size_t nc = fb->cap ? fb->cap * 2 : 256;
    while (nc < fb->len + slen + 2) nc *= 2;
    char *tmp = realloc(fb->data, nc);
    if (!tmp) return;
    fb->data = tmp;
    fb->cap  = nc;
  }
  memcpy(fb->data + fb->len, s, slen + 1);
  fb->len += slen;
}

static void FlushStanza(FossPkgInfo    **ppi,
                         FossPkgList     *list,
                         FossPkgManager   mgr,
                         RFC822FieldCb    fieldCb,
                         int              onlyInstalled,
                         char            *fieldName,
                         FvalBuf         *fb,
                         int             *inCont)
{
  FossPkgInfo *pi = *ppi;

  if (*inCont && fieldName[0] && fb->data)
    fieldCb(pi, fieldName, Trim(fb->data));

  if (pi->name) {
    int keep = 1;
    if (onlyInstalled && (!pi->status || !strstr(pi->status, "installed")))
      keep = 0;
    if (keep) FossPkgListAppend(list, pi);
    else      FossPkgInfoFree(pi);
  } else {
    FossPkgInfoFree(pi);
  }

  *ppi           = FossPkgInfoAlloc();
  (*ppi)->manager = mgr;
  fieldName[0]   = '\0';
  if (fb->data) { fb->data[0] = '\0'; fb->len = 0; }
  *inCont        = 0;
}

static FossPkgList *ParseRfc822(char          *buf,
                                 FossPkgManager mgr,
                                 RFC822FieldCb  fieldCb,
                                 int            onlyInstalled)
{
  FossPkgList *list = FossPkgListAlloc();
  FossPkgInfo *pi   = FossPkgInfoAlloc();
  pi->manager       = mgr;

  char   fieldName[256] = "";
  FvalBuf fb            = { NULL, 0, 0 };
  int     inCont        = 0;

  char *line = buf, *next;
  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';

    if (*line == '\0') {
      FlushStanza(&pi, list, mgr, fieldCb, onlyInstalled,
                  fieldName, &fb, &inCont);
    } else if ((*line == ' ' || *line == '\t') && inCont) {
      FvalAppend(&fb, "\n");
      FvalAppend(&fb, Trim(line));
    } else {
      /* New field — flush previous accumulator */
      if (inCont && fieldName[0] && fb.data)
        fieldCb(pi, fieldName, Trim(fb.data));

      char *colon = strchr(line, ':');
      if (colon) {
        *colon = '\0';
        char *key = Trim(line);
        char *val = Trim(colon + 1);

        size_t klen = strlen(key);
        if (klen >= sizeof(fieldName)) klen = sizeof(fieldName) - 1;
        memcpy(fieldName, key, klen);
        fieldName[klen] = '\0';

        fb.len = 0;
        if (fb.data) fb.data[0] = '\0';
        FvalAppend(&fb, val);
        inCont = 1;
      }
    }
    line = next ? next + 1 : NULL;
  }
  FlushStanza(&pi, list, mgr, fieldCb, onlyInstalled,
              fieldName, &fb, &inCont);

  free(fb.data);
  return list;
}

/* =========================================================================
 * Rfc822_DpkgField — dispatch table replaces the if/else chain
 * ========================================================================= */

typedef struct {
  const char *field;
  size_t      fieldOff; /* offsetof char* field in FossPkgInfo, or SIZE_MAX */
  int         isDeps;   /* 1 = call AppendDepList instead of FOSSPKG_SET */
  int         isSrc;    /* 1 = also set name if not yet set */
} DpkgFieldEntry;

/* offsetof-style helper; works for the char* members we care about */
#define PKG_OFF(member) ((size_t)offsetof(FossPkgInfo, member))

static const DpkgFieldEntry DpkgFields[] = {
  { "Package",       PKG_OFF(name),        0, 0 },
  { "Version",       PKG_OFF(version),     0, 0 },
  { "Architecture",  PKG_OFF(arch),        0, 0 },
  { "Source",        PKG_OFF(source),      0, 1 }, /* also sets name */
  { "Maintainer",    PKG_OFF(maintainer),  0, 0 },
  { "Homepage",      PKG_OFF(url),         0, 0 },
  { "Status",        PKG_OFF(status),      0, 0 },
  { "Section",       PKG_OFF(section),     0, 0 },
  { "Priority",      PKG_OFF(priority),    0, 0 },
  { "Description",   PKG_OFF(summary),     0, 0 },
  { "Depends",       0,                    1, 0 },
  { "Pre-Depends",   0,                    1, 0 },
  { "Build-Depends", 0,                    1, 0 },
  { NULL, 0, 0, 0 }
};

static void Rfc822_DpkgField(FossPkgInfo *pi,
                              const char  *field,
                              const char  *value)
{
  for (int i = 0; DpkgFields[i].field; i++) {
    if (strcasecmp(field, DpkgFields[i].field) != 0) continue;

    if (DpkgFields[i].isDeps) {
      AppendDepList(pi, value);
      return;
    }

    char **dst = (char **)((char *)pi + DpkgFields[i].fieldOff);
    FossPkgSet(dst, value);

    if (DpkgFields[i].isSrc && !pi->name)
      FOSSPKG_SET(pi, name, value);

    return;
  }
}

/* =========================================================================
 * FossPkg_ParseDpkgStatus
 * ========================================================================= */
FossPkgList *FossPkg_ParseDpkgStatus(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;
  FossPkgList *list = ParseRfc822(buf, FOSSPKG_MGR_DPKG, Rfc822_DpkgField, 1);
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
  FossPkgList *list = ParseRfc822(buf, FOSSPKG_MGR_DEB, Rfc822_DpkgField, 0);
  free(buf);
  FossPkgInfo *pi = NULL;
  if (list->count > 0) { pi = list->pkgs[0]; list->pkgs[0] = NULL; }
  FossPkgListFree(list);
  return pi;
}

/* =========================================================================
 * FossPkg_ParseApkInstalled
 * ========================================================================= */
static void Apk_HandleBlock(FossPkgList *list, char **lines, int nLines)
{
  FossPkgInfo *pi = FossPkgInfoAlloc();
  pi->manager = FOSSPKG_MGR_APK;

  for (int i = 0; i < nLines; i++) {
    char *l = lines[i];
    if (strlen(l) < 2 || l[1] != ':') continue;
    char  key = l[0];
    char *val = Trim(l + 2);
    switch (key) {
      case 'P': FOSSPKG_SET(pi, name,       val); break;
      case 'V': FOSSPKG_SET(pi, version,    val); break;
      case 'A': FOSSPKG_SET(pi, arch,       val); break;
      case 'L': FOSSPKG_SET(pi, license,    val); break;
      case 'T': FOSSPKG_SET(pi, summary,    val); break;
      case 'U': FOSSPKG_SET(pi, url,        val); break;
      case 'm': FOSSPKG_SET(pi, maintainer, val); break;
      case 'o': FOSSPKG_SET(pi, source,     val); break;
      case 'D': {
        char *copy = strdup(val);
        if (copy) {
          char *tok = strtok(copy, " \t");
          while (tok) {
            if (*tok && strncmp(tok, "so:", 3) != 0) AppendDep(pi, tok);
            tok = strtok(NULL, " \t");
          }
          free(copy);
        }
        break;
      }
    }
  }
  if (pi->name) FossPkgListAppend(list, pi);
  else          FossPkgInfoFree(pi);
}

FossPkgList *FossPkg_ParseApkInstalled(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list  = FossPkgListAlloc();
  char *blockLines[1024];
  int   nLines       = 0;
  char *line = buf, *next;

  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';

    if (*line == '\0') {
      if (nLines > 0) { Apk_HandleBlock(list, blockLines, nLines); nLines = 0; }
    } else {
      if (nLines < 1024) blockLines[nLines++] = line;
    }
    line = next ? next + 1 : NULL;
  }
  if (nLines > 0) Apk_HandleBlock(list, blockLines, nLines);
  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParseRpmSqlite
 * ========================================================================= */
#define SQLITE3_MAGIC \
  "\x53\x51\x4c\x69\x74\x65\x20\x66\x6f\x72\x6d\x61\x74\x20\x33\x00"

static int IsSqlite3(const char *path)
{
  FILE *fp = fopen(path, "rb");
  if (!fp) return 0;
  char magic[16];
  int ok = (fread(magic, 1, 16, fp) == 16 &&
            memcmp(magic, SQLITE3_MAGIC, 16) == 0);
  fclose(fp);
  return ok;
}

FossPkgList *FossPkg_ParseRpmSqlite(const char *path)
{
  if (!IsSqlite3(path)) {
    FOSSPKG_WARN("'%s' is not sqlite3 — likely BerkeleyDB; use FossPkg_ParseRpmBdb()",
                 path);
    return FossPkgListAlloc();
  }

#ifndef FOSSPKG_HAVE_SQLITE3
  FOSSPKG_ERR("libsqlite3 not available — cannot parse '%s'", path);
  return NULL;
#else
  sqlite3 *db = NULL;
  if (sqlite3_open_v2(path, &db, SQLITE_OPEN_READONLY, NULL) != SQLITE_OK) {
    FOSSPKG_ERR("sqlite3_open '%s': %s", path, sqlite3_errmsg(db));
    sqlite3_close(db);
    return NULL;
  }

  FossPkgList  *list  = FossPkgListAlloc();
  sqlite3_stmt *stmt  = NULL;
  int           found = 0;

  /* Attempt 1: text-column nvra view (Fedora 37+ / RHEL 9+) */
  const char *textQueries[] = {
    "SELECT name, version || '-' || release, arch, sourcerpm, summary, requirename "
    "FROM nvra LEFT JOIN requires USING(hnum) GROUP BY nvra.hnum;",
    "SELECT name, version || '-' || release, arch, '', summary, '' FROM nvra;",
    NULL
  };
  for (int qi = 0; textQueries[qi] && !found; qi++) {
    if (sqlite3_prepare_v2(db, textQueries[qi], -1, &stmt, NULL) == SQLITE_OK) {
      found = 1;
      while (sqlite3_step(stmt) == SQLITE_ROW) {
        FossPkgInfo *pi = FossPkgInfoAlloc();
        pi->manager = FOSSPKG_MGR_RPM;
#define COLSTR(i) ((const char *)sqlite3_column_text(stmt, (i)))
        FOSSPKG_SET(pi, name,    COLSTR(0) ? COLSTR(0) : "");
        FOSSPKG_SET(pi, version, COLSTR(1) ? COLSTR(1) : "");
        FOSSPKG_SET(pi, arch,    COLSTR(2) ? COLSTR(2) : "");
        FOSSPKG_SET(pi, source,  COLSTR(3) ? COLSTR(3) : "");
        FOSSPKG_SET(pi, summary, COLSTR(4) ? COLSTR(4) : "");
        if (COLSTR(5) && COLSTR(5)[0]) AppendDep(pi, COLSTR(5));
#undef COLSTR
        if (pi->name) FossPkgListAppend(list, pi);
        else          FossPkgInfoFree(pi);
      }
      sqlite3_finalize(stmt); stmt = NULL;
    }
  }

  /* Attempt 2: raw Packages blob — XDR header decode */
  if (!found) {
    if (sqlite3_prepare_v2(db, "SELECT blob FROM Packages ORDER BY hnum;",
                           -1, &stmt, NULL) == SQLITE_OK) {
      while (sqlite3_step(stmt) == SQLITE_ROW) {
        const uint8_t *blob    = sqlite3_column_blob(stmt, 0);
        int            blobLen = sqlite3_column_bytes(stmt, 0);
        if (!blob || blobLen < 16) continue;
        if (blob[0] != 0x8e || blob[1] != 0xad ||
            blob[2] != 0xe8 || blob[3] != 0x01) continue;

        uint32_t nindex = ((uint32_t)blob[ 8] << 24) | ((uint32_t)blob[ 9] << 16) |
                          ((uint32_t)blob[10] <<  8) |  (uint32_t)blob[11];
        uint32_t hsize  = ((uint32_t)blob[12] << 24) | ((uint32_t)blob[13] << 16) |
                          ((uint32_t)blob[14] <<  8) |  (uint32_t)blob[15];
        const uint8_t *idx   = blob + 16;
        const uint8_t *store = idx + nindex * 16;
        if ((int)(16 + nindex * 16 + hsize) > blobLen) continue;

        FossPkgInfo *pi = FossPkgInfoAlloc();
        pi->manager = FOSSPKG_MGR_RPM;
        char rel[256] = "";

        for (uint32_t i = 0; i < nindex; i++) {
          const uint8_t *e  = idx + i * 16;
          uint32_t tag      = ((uint32_t)e[0] << 24) | ((uint32_t)e[1] << 16) |
                              ((uint32_t)e[2] <<  8) |  (uint32_t)e[3];
          uint32_t offset   = ((uint32_t)e[8] << 24) | ((uint32_t)e[9] << 16) |
                              ((uint32_t)e[10] << 8) |  (uint32_t)e[11];
          if (offset >= hsize) continue;
          const char *s = (const char *)(store + offset);
          switch (tag) {
            case 1000: FOSSPKG_SET(pi, name,    s); break;
            case 1001: FOSSPKG_SET(pi, version, s); break;
            case 1002: if (strlen(s) < sizeof(rel)) strcpy(rel, s); break;
            case 1022: FOSSPKG_SET(pi, arch,    s); break;
            case 1044: FOSSPKG_SET(pi, source,  s); break;
            case 1004: FOSSPKG_SET(pi, summary, s); break;
            case 1049: AppendDep(pi, s);             break;
          }
        }
        /* Append release to version: "1.2.3-1.el9" */
        if (rel[0] && pi->version) {
          size_t vlen = strlen(pi->version), rlen = strlen(rel);
          char *vr = malloc(vlen + rlen + 2);
          if (vr) {
            memcpy(vr, pi->version, vlen);
            vr[vlen] = '-';
            memcpy(vr + vlen + 1, rel, rlen + 1);
            free(pi->version);
            pi->version = vr;
          }
        }
        if (pi->name) FossPkgListAppend(list, pi);
        else          FossPkgInfoFree(pi);
      }
      sqlite3_finalize(stmt);
      found = 1;
    }
  }

  if (!found) FOSSPKG_ERR("'%s': no recognised RPM table schema", path);
  sqlite3_close(db);
  return list;
#endif
}

/* =========================================================================
 * FossPkg_ParseRpmBdb — BerkeleyDB fallback via rpm(1) CLI
 * ========================================================================= */
FossPkgList *FossPkg_ParseRpmBdb(const char *packagesPath)
{
  if (!packagesPath || !packagesPath[0]) return NULL;

  char *pathCopy = strdup(packagesPath);
  if (!pathCopy) return NULL;
  char *dbDir = dirname(pathCopy);

  const char *rpmBins[] = { "rpm", "/bin/rpm", "/usr/bin/rpm", NULL };
  const char *rpmBin = NULL;
  for (int i = 0; rpmBins[i]; i++)
    if (access(rpmBins[i], X_OK) == 0) { rpmBin = rpmBins[i]; break; }

  if (!rpmBin) {
    FOSSPKG_WARN("rpm binary not found — cannot parse BerkeleyDB at '%s'",
                 packagesPath);
    free(pathCopy);
    return FossPkgListAlloc();
  }

  char cmd[4096];
  snprintf(cmd, sizeof(cmd),
    "%s --dbpath '%s' -qa "
    "--queryformat '%%{NAME}\\t%%{VERSION}-%%{RELEASE}\\t%%{ARCH}\\t"
    "%%{SOURCERPM}\\t%%{SUMMARY}\\t%%{LICENSE}\\n' 2>/dev/null",
    rpmBin, dbDir);
  free(pathCopy);

  FILE *fp = popen(cmd, "r");
  if (!fp) {
    FOSSPKG_ERR("popen failed for rpm BDB query: %s", strerror(errno));
    return NULL;
  }

  FossPkgList *list = FossPkgListAlloc();
  char line[8192];

  while (fgets(line, sizeof(line), fp)) {
    char *nl = strchr(line, '\n');
    if (nl) *nl = '\0';

    char *fields[6] = { NULL };
    int   nf = 0;
    char *tok = strtok(line, "\t");
    while (tok && nf < 6) { fields[nf++] = tok; tok = strtok(NULL, "\t"); }
    if (nf < 2 || !fields[0] || !fields[0][0]) continue;

    FossPkgInfo *pi = FossPkgInfoAlloc();
    pi->manager = FOSSPKG_MGR_RPM_BDB;
    FOSSPKG_SET(pi, name,    fields[0]);
    FOSSPKG_SET(pi, version, fields[1]);
    if (nf > 2) FOSSPKG_SET(pi, arch,    fields[2]);
    if (nf > 3) FOSSPKG_SET(pi, source,  fields[3]);
    if (nf > 4) FOSSPKG_SET(pi, summary, fields[4]);
    if (nf > 5) FOSSPKG_SET(pi, license, fields[5]);
    FossPkgListAppend(list, pi);
  }
  pclose(fp);
  return list;
}

/* =========================================================================
 * JSON helper
 * ========================================================================= */

/** Safely get a string field from a json_object; returns "" on failure. */
static const char *JStr(struct json_object *obj, const char *key)
{
  struct json_object *val = NULL;
  if (!obj || !json_object_object_get_ex(obj, key, &val) || !val) return "";
  const char *s = json_object_get_string(val);
  return s ? s : "";
}

/* =========================================================================
 * FossPkg_ParseNpmLock
 * ========================================================================= */
static void NpmWalkDeps(FossPkgList *list, struct json_object *deps,
                        const char *parentName)
{
  if (!deps || json_object_get_type(deps) != json_type_object) return;
  json_object_object_foreach(deps, pkgName, pkgObj) {
    if (!pkgObj || !pkgName || !pkgName[0]) continue;
    const char *version = JStr(pkgObj, "version");
    if (!version[0]) continue;

    FossPkgInfo *pi = FossPkgInfoAlloc();
    pi->manager = FOSSPKG_MGR_NPM;
    FOSSPKG_SET(pi, name,    pkgName);
    FOSSPKG_SET(pi, version, version);
    if (parentName && parentName[0]) FOSSPKG_SET(pi, source, parentName);
    const char *lic = JStr(pkgObj, "license");
    if (lic[0]) FOSSPKG_SET(pi, license, lic);
    const char *res = JStr(pkgObj, "resolved");
    if (res[0]) FOSSPKG_SET(pi, url, res);
    FossPkgListAppend(list, pi);

    struct json_object *nested = NULL;
    if (json_object_object_get_ex(pkgObj, "dependencies", &nested))
      NpmWalkDeps(list, nested, pkgName);
  }
}

FossPkgList *FossPkg_ParseNpmLock(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;
  struct json_object *root = json_tokener_parse(buf);
  free(buf);
  if (!root) { FOSSPKG_ERR("npm: cannot parse JSON '%s'", path); return NULL; }

  FossPkgList *list = FossPkgListAlloc();

  /* v2/v3: "packages" object */
  struct json_object *packages = NULL;
  if (json_object_object_get_ex(root, "packages", &packages) && packages) {
    json_object_object_foreach(packages, rawKey, pkgObj) {
      if (!rawKey || rawKey[0] == '\0') continue;
      const char *name = rawKey;
      const char *last = strstr(rawKey, "node_modules/");
      if (last) name = last + strlen("node_modules/");
      if (!name[0]) continue;
      const char *version = JStr(pkgObj, "version");
      if (!version[0]) continue;

      FossPkgInfo *pi = FossPkgInfoAlloc();
      pi->manager = FOSSPKG_MGR_NPM;
      FOSSPKG_SET(pi, name,    name);
      FOSSPKG_SET(pi, version, version);
      const char *lic = JStr(pkgObj, "license");
      if (lic[0]) FOSSPKG_SET(pi, license, lic);
      const char *res = JStr(pkgObj, "resolved");
      if (res[0]) FOSSPKG_SET(pi, url, res);
      FossPkgListAppend(list, pi);
    }
  }

  /* v1 fallback: "dependencies" object */
  if (list->count == 0) {
    struct json_object *deps = NULL;
    if (json_object_object_get_ex(root, "dependencies", &deps))
      NpmWalkDeps(list, deps, NULL);
  }

  json_object_put(root);
  return list;
}

/* =========================================================================
 * FossPkg_ParseYarnLock
 * ========================================================================= */
FossPkgList *FossPkg_ParseYarnLock(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list = FossPkgListAlloc();
  FossPkgInfo *pi   = NULL;
  char *line = buf, *next;

  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';

    if (line[0] == '#' || strncmp(line, "__metadata", 10) == 0) {
      line = next ? next + 1 : NULL; continue;
    }

    int indented = (line[0] == ' ' || line[0] == '\t');

    if (!indented && line[0] != '\0') {
      if (pi && pi->name) FossPkgListAppend(list, pi);
      else if (pi)        FossPkgInfoFree(pi);

      pi = FossPkgInfoAlloc();
      pi->manager = FOSSPKG_MGR_NPM;

      char *spec = strdup(line);
      if (spec) {
        char *col = strrchr(spec, ':');
        if (col) *col = '\0';
        char *s = spec;
        if (*s == '"') s++;
        char *at = strrchr(s, '@');
        if (at && at > s) *at = '\0';
        size_t slen = strlen(s);
        while (slen > 0 && (s[slen - 1] == '"' || s[slen - 1] == ','))
          s[--slen] = '\0';
        FOSSPKG_SET(pi, name, s);
        free(spec);
      }
    } else if (indented && pi) {
      char *trimmed = Trim(line);
      if (strncmp(trimmed, "version ", 8) == 0) {
        char *v = Trim(trimmed + 8);
        if (*v == '"') { v++; char *q = strrchr(v, '"'); if (q) *q = '\0'; }
        FOSSPKG_SET(pi, version, v);
      } else if (strncmp(trimmed, "resolved ", 9) == 0) {
        char *v = Trim(trimmed + 9);
        if (*v == '"') { v++; char *q = strrchr(v, '"'); if (q) *q = '\0'; }
        FOSSPKG_SET(pi, url, v);
      }
    }
    line = next ? next + 1 : NULL;
  }
  if (pi && pi->name) FossPkgListAppend(list, pi);
  else if (pi)        FossPkgInfoFree(pi);

  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParsePipRequirements
 * ========================================================================= */
FossPkgList *FossPkg_ParsePipRequirements(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list = FossPkgListAlloc();
  char *line = buf, *next;

  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';
    char *trimmed = Trim(line);

    if (!trimmed[0] || trimmed[0] == '#' || trimmed[0] == '-') {
      line = next ? next + 1 : NULL; continue;
    }
    char *hash = strchr(trimmed, '#');
    if (hash) *hash = '\0';
    Trim(trimmed);

    const char *ops[] = { "==", ">=", "<=", "~=", "!=", ">", "<", NULL };
    char *opPos = NULL;
    for (int i = 0; ops[i]; i++) {
      char *p = strstr(trimmed, ops[i]);
      if (p && (!opPos || p < opPos)) opPos = p;
    }

    char *pkgName = NULL, *pkgVer = NULL;
    if (opPos) {
      pkgName = strndup(trimmed, (size_t)(opPos - trimmed));
      if (pkgName) Trim(pkgName);
      pkgVer  = strdup(opPos);
      if (pkgVer) Trim(pkgVer);
    } else {
      pkgName = strdup(trimmed);
    }

    if (!pkgName || !pkgName[0]) {
      free(pkgName); free(pkgVer);
      line = next ? next + 1 : NULL; continue;
    }

    FossPkgInfo *pi = FossPkgInfoAlloc();
    pi->manager = FOSSPKG_MGR_PIP;
    pi->name    = pkgName;
    pi->version = pkgVer;
    FossPkgListAppend(list, pi);
    line = next ? next + 1 : NULL;
  }
  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParsePipfileLock
 * ========================================================================= */
static void PipfileLock_WalkSection(FossPkgList *list, struct json_object *sect)
{
  if (!sect || json_object_get_type(sect) != json_type_object) return;
  json_object_object_foreach(sect, pkgName, pkgObj) {
    if (!pkgObj) continue;
    const char *ver = JStr(pkgObj, "version");
    FossPkgInfo *pi = FossPkgInfoAlloc();
    pi->manager = FOSSPKG_MGR_PIP;
    FOSSPKG_SET(pi, name, pkgName);
    const char *v = ver;
    if (v[0] == '=' && v[1] == '=') v += 2;
    FOSSPKG_SET(pi, version, v);
    FossPkgListAppend(list, pi);
  }
}

FossPkgList *FossPkg_ParsePipfileLock(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;
  struct json_object *root = json_tokener_parse(buf);
  free(buf);
  if (!root) { FOSSPKG_ERR("pipfile: cannot parse JSON '%s'", path); return NULL; }

  FossPkgList *list = FossPkgListAlloc();
  struct json_object *sec = NULL;
  if (json_object_object_get_ex(root, "default", &sec)) PipfileLock_WalkSection(list, sec);
  if (json_object_object_get_ex(root, "develop", &sec)) PipfileLock_WalkSection(list, sec);
  json_object_put(root);
  return list;
}

/* =========================================================================
 * FossPkg_ParsePyprojectToml
 * ========================================================================= */
FossPkgList *FossPkg_ParsePyprojectToml(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list      = FossPkgListAlloc();
  int          inSection = 0;
  char *line = buf, *next;

  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';
    char *trimmed = Trim(line);

    if (trimmed[0] == '[') {
      inSection = (strncmp(trimmed, "[tool.poetry.dependencies]", 26) == 0);
      line = next ? next + 1 : NULL; continue;
    }
    if (!inSection || !trimmed[0] || trimmed[0] == '#') {
      line = next ? next + 1 : NULL; continue;
    }

    char *eq = strchr(trimmed, '=');
    if (!eq) { line = next ? next + 1 : NULL; continue; }
    *eq = '\0';
    char *key = Trim(trimmed);
    char *val = Trim(eq + 1);
    if (!key[0] || !strcmp(key, "python")) { line = next ? next + 1 : NULL; continue; }

    char *ver = NULL;
    if (val[0] == '{') {
      char *vk = strstr(val, "version");
      if (vk) {
        char *eq2 = strchr(vk, '=');
        if (eq2) {
          char *v2 = Trim(eq2 + 1);
          if (*v2 == '"') { v2++; char *q = strchr(v2, '"'); if (q) *q = '\0'; }
          ver = strdup(v2);
        }
      }
    } else {
      if (val[0] == '"') { val++; char *q = strrchr(val, '"'); if (q) *q = '\0'; }
      ver = strdup(val);
    }

    FossPkgInfo *pi = FossPkgInfoAlloc();
    pi->manager = FOSSPKG_MGR_PIP;
    FOSSPKG_SET(pi, name, key);
    pi->version = ver;
    FossPkgListAppend(list, pi);
    line = next ? next + 1 : NULL;
  }
  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParsePipDistInfo — PEP 566/658 dist-info METADATA
 * ========================================================================= */
static void Rfc822_DistInfoField(FossPkgInfo *pi,
                                  const char  *field,
                                  const char  *value)
{
  if      (!strcasecmp(field, "Name"))         FOSSPKG_SET(pi, name,       value);
  else if (!strcasecmp(field, "Version"))      FOSSPKG_SET(pi, version,    value);
  else if (!strcasecmp(field, "Summary"))      FOSSPKG_SET(pi, summary,    value);
  else if (!strcasecmp(field, "Home-page"))    FOSSPKG_SET(pi, url,        value);
  else if (!strcasecmp(field, "License"))      FOSSPKG_SET(pi, license,    value);
  else if (!strcasecmp(field, "Author"))       FOSSPKG_SET(pi, maintainer, value);
  else if (!strcasecmp(field, "Author-email")) {
    if (pi->maintainer && pi->maintainer[0]) {
      size_t mlen = strlen(pi->maintainer);
      size_t elen = strlen(value);
      char *combined = malloc(mlen + elen + 4);
      if (combined) {
        memcpy(combined, pi->maintainer, mlen);
        combined[mlen]             = ' ';
        combined[mlen + 1]         = '<';
        memcpy(combined + mlen + 2, value, elen);
        combined[mlen + 2 + elen]  = '>';
        combined[mlen + 3 + elen]  = '\0';
        free(pi->maintainer);
        pi->maintainer = combined;
      }
    } else {
      FOSSPKG_SET(pi, maintainer, value);
    }
  }
  else if (!strcasecmp(field, "Requires-Dist")) AppendDep(pi, value);
}

FossPkgList *FossPkg_ParsePipDistInfo(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list = ParseRfc822(buf, FOSSPKG_MGR_PIP_DIST_INFO,
                                   Rfc822_DistInfoField, 0);
  free(buf);

  for (int i = 0; i < list->count; i++)
    if (list->pkgs[i]) list->pkgs[i]->manager = FOSSPKG_MGR_PIP_DIST_INFO;

  return list;
}

/* =========================================================================
 * FossPkg_ParseMavenPom — minimal XML pull-parser (no libxml2)
 * ========================================================================= */
static void SkipTag(const char **p)
{
  while (**p && **p != '>') (*p)++;
  if (**p) (*p)++;
}

static char *ExtractTextHeap(const char **p)
{
  const char *start = *p;
  while (**p && **p != '<') (*p)++;
  size_t len = (size_t)(*p - start);
  char *out = malloc(len + 1);
  if (!out) return NULL;
  memcpy(out, start, len);
  out[len] = '\0';
  return Trim(out);
}

FossPkgList *FossPkg_ParseMavenPom(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;
  FossPkgList *list = FossPkgListAlloc();
  const char  *p    = buf;

  while (*p) {
    if (*p != '<') { p++; continue; }
    p++;
    if (strncmp(p, "dependency>", 11) != 0) { SkipTag(&p); continue; }
    p += 11;

    char *groupId = NULL, *artifactId = NULL, *version = NULL;

    while (*p) {
      if (*p != '<') { p++; continue; }
      p++;
      if (strncmp(p, "/dependency>", 12) == 0) { p += 12; break; }
      if      (strncmp(p, "groupId>",    8)  == 0) { p +=  8; groupId    = ExtractTextHeap(&p); }
      else if (strncmp(p, "artifactId>", 11) == 0) { p += 11; artifactId = ExtractTextHeap(&p); }
      else if (strncmp(p, "version>",    8)  == 0) { p +=  8; version    = ExtractTextHeap(&p); }
      else SkipTag(&p);
    }

    if (groupId && groupId[0] && artifactId && artifactId[0]) {
      FossPkgInfo *pi = FossPkgInfoAlloc();
      pi->manager = FOSSPKG_MGR_MAVEN;
      size_t nlen = strlen(groupId) + strlen(artifactId) + 2;
      pi->name = malloc(nlen);
      if (pi->name) snprintf(pi->name, nlen, "%s:%s", groupId, artifactId);
      pi->version = version; version  = NULL;
      pi->source  = groupId; groupId  = NULL;
      FossPkgListAppend(list, pi);
    }
    free(groupId); free(artifactId); free(version);
  }
  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParseGoMod
 * ========================================================================= */
FossPkgList *FossPkg_ParseGoMod(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list    = FossPkgListAlloc();
  int          inBlock = 0;
  char *line = buf, *next;

  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';
    char *trimmed = Trim(line);

    char *sl = strstr(trimmed, "//");
    if (sl) { *sl = '\0'; trimmed = Trim(trimmed); }
    if (!trimmed[0]) { line = next ? next + 1 : NULL; continue; }

    if (!inBlock && strncmp(trimmed, "require (", 9) == 0) {
      inBlock = 1; line = next ? next + 1 : NULL; continue;
    }
    if (inBlock && trimmed[0] == ')') {
      inBlock = 0; line = next ? next + 1 : NULL; continue;
    }

    const char *depLine = NULL;
    if (!inBlock && strncmp(trimmed, "require ", 8) == 0)
      depLine = Trim((char *)(trimmed + 8));
    else if (inBlock)
      depLine = trimmed;

    if (depLine && depLine[0] && depLine[0] != '(') {
      const char *sp  = strchr(depLine, ' ');
      char *mod = sp ? strndup(depLine, (size_t)(sp - depLine)) : strdup(depLine);
      char *ver = (sp && sp[1]) ? strdup(Trim((char *)(sp + 1))) : NULL;
      if (mod && mod[0]) {
        FossPkgInfo *pi = FossPkgInfoAlloc();
        pi->manager = FOSSPKG_MGR_GO;
        pi->name    = mod; mod = NULL;
        pi->version = ver; ver = NULL;
        FossPkgListAppend(list, pi);
      }
      free(mod); free(ver);
    }
    line = next ? next + 1 : NULL;
  }
  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParseCargoLock
 * ========================================================================= */
FossPkgList *FossPkg_ParseCargoLock(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list  = FossPkgListAlloc();
  FossPkgInfo *pi    = NULL;
  int          inPkg = 0;
  char *line = buf, *next;

  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';
    char *trimmed = Trim(line);

    if (trimmed[0] == '#' || !trimmed[0]) { line = next ? next + 1 : NULL; continue; }

    if (strcmp(trimmed, "[[package]]") == 0) {
      if (pi && pi->name) FossPkgListAppend(list, pi);
      else if (pi)        FossPkgInfoFree(pi);
      pi = FossPkgInfoAlloc();
      pi->manager = FOSSPKG_MGR_CARGO;
      inPkg = 1;
      line = next ? next + 1 : NULL; continue;
    }

    if (!inPkg || !pi) { line = next ? next + 1 : NULL; continue; }

    char *eq = strchr(trimmed, '=');
    if (!eq) { line = next ? next + 1 : NULL; continue; }
    *eq = '\0';
    char *key = Trim(trimmed);
    char *val = Trim(eq + 1);

    if (val[0] == '"') {
      val++;
      char *q = strrchr(val, '"');
      if (q) *q = '\0';
      if      (!strcmp(key, "name"))    FOSSPKG_SET(pi, name,    val);
      else if (!strcmp(key, "version")) FOSSPKG_SET(pi, version, val);
      else if (!strcmp(key, "source"))  FOSSPKG_SET(pi, url,     val);
    } else if (val[0] == '[' && !strcmp(key, "dependencies")) {
      char *arr = val + 1;
      char *end = strchr(arr, ']');
      if (end) *end = '\0';
      char *tok = strtok(arr, ",");
      while (tok) {
        char *t = Trim(tok);
        if (*t == '"') { t++; char *q = strrchr(t, '"'); if (q) *q = '\0'; }
        if (t[0]) AppendDep(pi, t);
        tok = strtok(NULL, ",");
      }
    }
    line = next ? next + 1 : NULL;
  }
  if (pi && pi->name) FossPkgListAppend(list, pi);
  else if (pi)        FossPkgInfoFree(pi);

  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParseGemfileLock
 * ========================================================================= */
FossPkgList *FossPkg_ParseGemfileLock(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;

  FossPkgList *list    = FossPkgListAlloc();
  int          inSpecs = 0;
  char *line = buf, *next;

  while (line && *line) {
    next = strchr(line, '\n');
    if (next) *next = '\0';
    char *cr = strchr(line, '\r');
    if (cr) *cr = '\0';

    if (line[0] != ' ' && line[0] != '\t') {
      inSpecs = 0; line = next ? next + 1 : NULL; continue;
    }

    char *trimmed = Trim(line);
    if (!strcmp(trimmed, "specs:")) {
      inSpecs = 1; line = next ? next + 1 : NULL; continue;
    }
    if (!inSpecs) { line = next ? next + 1 : NULL; continue; }

    int spaces = 0;
    char *pp = line;
    while (*pp == ' ') { spaces++; pp++; }
    if (spaces != 4) { line = next ? next + 1 : NULL; continue; }

    char *op = strchr(trimmed, '(');
    char *cp = strchr(trimmed, ')');
    if (!op || !cp || cp < op) { line = next ? next + 1 : NULL; continue; }

    size_t nlen = (size_t)(op - trimmed);
    while (nlen > 0 && isspace((unsigned char)trimmed[nlen - 1])) nlen--;
    char *gname = strndup(trimmed, nlen);
    char *gver  = strndup(op + 1, (size_t)(cp - op - 1));

    if (gname && gname[0]) {
      FossPkgInfo *pi = FossPkgInfoAlloc();
      pi->manager = FOSSPKG_MGR_GEM;
      pi->name    = gname; gname = NULL;
      pi->version = gver;  gver  = NULL;
      FossPkgListAppend(list, pi);
    }
    free(gname); free(gver);
    line = next ? next + 1 : NULL;
  }
  free(buf);
  return list;
}

/* =========================================================================
 * FossPkg_ParseNugetLock
 * ========================================================================= */
FossPkgList *FossPkg_ParseNugetLock(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;
  struct json_object *root = json_tokener_parse(buf);
  free(buf);
  if (!root) { FOSSPKG_ERR("nuget: cannot parse JSON '%s'", path); return NULL; }

  FossPkgList *list = FossPkgListAlloc();
  struct json_object *deps = NULL;
  if (!json_object_object_get_ex(root, "dependencies", &deps) || !deps) {
    json_object_put(root); return list;
  }

  json_object_object_foreach(deps, _fwKey, fwObj) {
    (void)_fwKey;
    if (!fwObj || json_object_get_type(fwObj) != json_type_object) continue;
    json_object_object_foreach(fwObj, pkgName, pkgObj) {
      if (!pkgObj) continue;
      const char *resolved  = JStr(pkgObj, "resolved");
      const char *requested = JStr(pkgObj, "requested");
      const char *ver = resolved[0] ? resolved : requested;
      FossPkgInfo *pi = FossPkgInfoAlloc();
      pi->manager = FOSSPKG_MGR_NUGET;
      FOSSPKG_SET(pi, name,    pkgName);
      FOSSPKG_SET(pi, version, ver);
      FossPkgListAppend(list, pi);
    }
  }
  json_object_put(root);
  return list;
}

/* =========================================================================
 * FossPkg_ParseComposerLock
 * ========================================================================= */
static void Composer_WalkArray(FossPkgList *list, struct json_object *arr)
{
  if (!arr || json_object_get_type(arr) != json_type_array) return;
  int n = (int)json_object_array_length(arr);
  for (int i = 0; i < n; i++) {
    struct json_object *pkg = json_object_array_get_idx(arr, i);
    if (!pkg) continue;
    const char *name = JStr(pkg, "name");
    if (!name[0]) continue;

    FossPkgInfo *pi = FossPkgInfoAlloc();
    pi->manager = FOSSPKG_MGR_COMPOSER;
    FOSSPKG_SET(pi, name,    name);
    FOSSPKG_SET(pi, version, JStr(pkg, "version"));
    FOSSPKG_SET(pi, summary, JStr(pkg, "description"));

    struct json_object *licArr = NULL;
    if (json_object_object_get_ex(pkg, "license", &licArr) && licArr &&
        json_object_get_type(licArr) == json_type_array &&
        json_object_array_length(licArr) > 0) {
      struct json_object *l0 = json_object_array_get_idx(licArr, 0);
      if (l0) FOSSPKG_SET(pi, license, json_object_get_string(l0));
    }
    struct json_object *src = NULL;
    if (json_object_object_get_ex(pkg, "source", &src) && src)
      FOSSPKG_SET(pi, url, JStr(src, "url"));

    FossPkgListAppend(list, pi);
  }
}

FossPkgList *FossPkg_ParseComposerLock(const char *path)
{
  char *buf = ReadFile(path);
  if (!buf) return NULL;
  struct json_object *root = json_tokener_parse(buf);
  free(buf);
  if (!root) { FOSSPKG_ERR("composer: cannot parse JSON '%s'", path); return NULL; }

  FossPkgList *list = FossPkgListAlloc();
  struct json_object *arr = NULL;
  if (json_object_object_get_ex(root, "packages",     &arr)) Composer_WalkArray(list, arr);
  if (json_object_object_get_ex(root, "packages-dev", &arr)) Composer_WalkArray(list, arr);
  json_object_put(root);
  return list;
}

/* =========================================================================
 * Utilities
 * ========================================================================= */

FossPkgManager FossPkg_DetectManager(const char *path)
{
  if (!path) return FOSSPKG_MGR_UNKNOWN;

  char *c1 = strdup(path);
  char *c2 = strdup(path);
  if (!c1 || !c2) { free(c1); free(c2); return FOSSPKG_MGR_UNKNOWN; }
  const char *base  = basename(c1);
  const char *dbase = basename(dirname(c2));

  FossPkgManager mgr = FOSSPKG_MGR_UNKNOWN;

  if      (!strcmp(base, "status")            && !strcmp(dbase, "dpkg")) mgr = FOSSPKG_MGR_DPKG;
  else if (!strcmp(base, "status"))                                        mgr = FOSSPKG_MGR_DPKG;
  else if (!strcmp(base, "installed")         && !strcmp(dbase, "db"))   mgr = FOSSPKG_MGR_APK;
  else if (!strcmp(base, "installed"))                                     mgr = FOSSPKG_MGR_APK;
  else if (!strcmp(base, "rpmdb.sqlite"))                                  mgr = FOSSPKG_MGR_RPM;
  else if (!strcmp(base, "Packages.db"))                                   mgr = FOSSPKG_MGR_RPM;
  else if (!strcmp(base, "Packages")          && !strcmp(dbase, "rpm"))  mgr = FOSSPKG_MGR_RPM_BDB;
  else if (!strcmp(base, "package-lock.json"))                             mgr = FOSSPKG_MGR_NPM;
  else if (!strcmp(base, "yarn.lock"))                                     mgr = FOSSPKG_MGR_NPM;
  else if (!strcmp(base, "requirements.txt"))                              mgr = FOSSPKG_MGR_PIP;
  else if (!strcmp(base, "Pipfile.lock"))                                  mgr = FOSSPKG_MGR_PIP;
  else if (!strcmp(base, "pyproject.toml"))                                mgr = FOSSPKG_MGR_PIP;
  else if (!strcmp(base, "pom.xml"))                                       mgr = FOSSPKG_MGR_MAVEN;
  else if (!strcmp(base, "go.mod"))                                        mgr = FOSSPKG_MGR_GO;
  else if (!strcmp(base, "Cargo.lock"))                                    mgr = FOSSPKG_MGR_CARGO;
  else if (!strcmp(base, "Gemfile.lock"))                                  mgr = FOSSPKG_MGR_GEM;
  else if (!strcmp(base, "composer.lock"))                                 mgr = FOSSPKG_MGR_COMPOSER;
  else if (!strcmp(base, "packages.lock.json"))                            mgr = FOSSPKG_MGR_NUGET;
  else {
    size_t blen = strlen(base);
    const char *sfx    = ".packages.lock.json";
    size_t      sfxLen = strlen(sfx);
    if (blen > sfxLen && !strcmp(base + blen - sfxLen, sfx))
      mgr = FOSSPKG_MGR_NUGET;
  }

  free(c1); free(c2);
  return mgr;
}

const char *FossPkg_ManagerName(FossPkgManager mgr)
{
  switch (mgr) {
    case FOSSPKG_MGR_DPKG:          return "dpkg";
    case FOSSPKG_MGR_APK:           return "apk";
    case FOSSPKG_MGR_RPM:           return "rpm";
    case FOSSPKG_MGR_RPM_BDB:       return "rpm-bdb";
    case FOSSPKG_MGR_DEB:           return "deb";
    case FOSSPKG_MGR_NPM:           return "npm";
    case FOSSPKG_MGR_PIP:           return "pip";
    case FOSSPKG_MGR_PIP_DIST_INFO: return "pip";   /* stored as 'pip' — DB CHECK constraint */
    case FOSSPKG_MGR_MAVEN:         return "maven";
    case FOSSPKG_MGR_GO:            return "go";
    case FOSSPKG_MGR_CARGO:         return "cargo";
    case FOSSPKG_MGR_GEM:           return "gem";
    case FOSSPKG_MGR_NUGET:         return "nuget";
    case FOSSPKG_MGR_COMPOSER:      return "composer";
    default:                        return "unknown";
  }
}

FossPkgList *FossPkg_ParseAuto(const char *path)
{
  if (!path) return NULL;
  char *c = strdup(path);
  if (!c) return NULL;
  const char *base = basename(c);

  FossPkgManager mgr         = FossPkg_DetectManager(path);
  int            isYarn       = !strcmp(base, "yarn.lock");
  int            isPipfileLock = !strcmp(base, "Pipfile.lock");
  int            isPyproject  = !strcmp(base, "pyproject.toml");
  free(c);

  switch (mgr) {
    case FOSSPKG_MGR_DPKG:          return FossPkg_ParseDpkgStatus(path);
    case FOSSPKG_MGR_APK:           return FossPkg_ParseApkInstalled(path);
    case FOSSPKG_MGR_RPM:           return FossPkg_ParseRpmSqlite(path);
    case FOSSPKG_MGR_RPM_BDB:       return FossPkg_ParseRpmBdb(path);
    case FOSSPKG_MGR_NPM:
      return isYarn ? FossPkg_ParseYarnLock(path) : FossPkg_ParseNpmLock(path);
    case FOSSPKG_MGR_PIP:
      if (isPipfileLock) return FossPkg_ParsePipfileLock(path);
      if (isPyproject)   return FossPkg_ParsePyprojectToml(path);
      return FossPkg_ParsePipRequirements(path);
    case FOSSPKG_MGR_PIP_DIST_INFO: return FossPkg_ParsePipDistInfo(path);
    case FOSSPKG_MGR_MAVEN:         return FossPkg_ParseMavenPom(path);
    case FOSSPKG_MGR_GO:            return FossPkg_ParseGoMod(path);
    case FOSSPKG_MGR_CARGO:         return FossPkg_ParseCargoLock(path);
    case FOSSPKG_MGR_GEM:           return FossPkg_ParseGemfileLock(path);
    case FOSSPKG_MGR_NUGET:         return FossPkg_ParseNugetLock(path);
    case FOSSPKG_MGR_COMPOSER:      return FossPkg_ParseComposerLock(path);
    default:
      FOSSPKG_ERR("cannot detect manager for '%s'", path);
      return NULL;
  }
}
