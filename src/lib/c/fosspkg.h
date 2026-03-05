/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file fosspkg.h
 * \brief Shared package-parsing library for FOSSology agents.
 *
 * Location: src/lib/c/fosspkg.h  (compiled into libfossology via fosspkg.c)
 *
 * Design — fully dynamic strings
 * --------------------------------
 * Every string field in FossPkgInfo is a heap-allocated char* (never a
 * fixed-size array).  This eliminates -Wstringop-truncation warnings that
 * arise from strncpy() on same-sized buffers and removes all hard caps on
 * field lengths.  Fields absent in a particular format are NULL.
 * Callers must test for NULL before using a field.
 *
 * Use the FOSSPKG_SET(pi, field, value) macro to assign a field safely —
 * it frees the old value and strdup's the new one.
 *
 * Parsers supported
 * -----------------
 * OS:       dpkg status, apk installed, rpm sqlite, rpm BerkeleyDB (via CLI),
 *           single .deb control file
 * Language: npm/yarn, pip/pyproject/Pipfile, Maven, Go, Cargo, Gem, NuGet,
 *           Composer
 *
 * Memory contract
 * ---------------
 * Every FossPkgInfo is heap-allocated via FossPkgInfoAlloc().
 * All string fields inside it are also heap-allocated.
 * Call FossPkgInfoFree() to release everything in one shot.
 *
 * FossPkgList grows dynamically from FOSSPKG_INIT_PKGS up to
 * FOSSPKG_HARD_MAX_PKGS (2 million entries).
 */

#ifndef _FOSSPKG_H
#define _FOSSPKG_H 1

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>

/* -------------------------------------------------------------------------
 * Capacity constants — initial/soft; structures grow dynamically as needed
 * ---------------------------------------------------------------------- */
#define FOSSPKG_INIT_DEPS        64       ///< Initial dep-array capacity per package
#define FOSSPKG_INIT_PKGS      1024       ///< Initial FossPkgList capacity
#define FOSSPKG_HARD_MAX_PKGS  2000000    ///< Hard ceiling: 2 M packages per database

/* -------------------------------------------------------------------------
 * Package manager identifiers
 * ---------------------------------------------------------------------- */
typedef enum {
  FOSSPKG_MGR_UNKNOWN  = 0,
  /* OS managers */
  FOSSPKG_MGR_DPKG,       ///< Debian / Ubuntu dpkg
  FOSSPKG_MGR_APK,        ///< Alpine apk
  FOSSPKG_MGR_RPM,        ///< RPM (sqlite rpmdb)
  FOSSPKG_MGR_RPM_BDB,    ///< RPM BerkeleyDB (RHEL 7/8) via rpm(1) CLI
  FOSSPKG_MGR_DEB,        ///< Single .deb control file (pkgagent)
  /* Language ecosystem managers */
  FOSSPKG_MGR_NPM,        ///< npm / yarn
  FOSSPKG_MGR_PIP,        ///< pip / PyPI (source manifests: requirements.txt etc.)
  FOSSPKG_MGR_PIP_DIST_INFO, ///< pip installed packages via PEP 566 dist-info/METADATA
  FOSSPKG_MGR_MAVEN,      ///< Maven
  FOSSPKG_MGR_GO,         ///< Go modules
  FOSSPKG_MGR_CARGO,      ///< Cargo (Rust)
  FOSSPKG_MGR_GEM,        ///< RubyGems
  FOSSPKG_MGR_NUGET,      ///< NuGet (.NET)
  FOSSPKG_MGR_COMPOSER,   ///< Composer (PHP)
} FossPkgManager;

/** Returns non-zero if mgr is a language-ecosystem manager. */
static inline int FossPkg_IsLangManager(FossPkgManager mgr)
{
  return (mgr >= FOSSPKG_MGR_NPM);
}

/* -------------------------------------------------------------------------
 * FossPkgInfo — single package record; all strings are heap char*
 *
 * Fields are NULL when absent in a particular format.
 * Use FOSSPKG_SET(pi, field, value) to assign.
 * Use FossPkgInfoFree() to release the whole struct.
 * ---------------------------------------------------------------------- */
typedef struct FossPkgInfo {
  /* Identity */
  char *name;         ///< Package name
  char *version;      ///< Installed / locked version string
  char *arch;         ///< Architecture ("amd64", "noarch") or NULL
  char *source;       ///< Source package / group:artifact / origin registry

  /* Descriptive */
  char *summary;      ///< One-line description
  char *description;  ///< Full multi-line description
  char *maintainer;   ///< Maintainer / Packager field
  char *license;      ///< SPDX license identifier
  char *url;          ///< Homepage / registry URL

  /* Debian binary package classification */
  char *section;      ///< e.g. "utils", "libs"
  char *priority;     ///< e.g. "optional", "extra"

  /* dpkg status */
  char *status;       ///< e.g. "install ok installed"

  /* Container context — set by container callers */
  char *dbFilePath;   ///< Absolute path to the source manifest in the repo

  /* Dependencies — dynamic array of heap strings */
  char **requires;    ///< Dependency tokens (each heap-allocated)
  int   requireCount; ///< Live count
  int   requireCap;   ///< Allocated capacity

  /* Metadata */
  FossPkgManager manager;    ///< Which parser produced this record
  int            layerIndex; ///< Container layer index (-1 = unknown)
} FossPkgInfo;

/* -------------------------------------------------------------------------
 * FOSSPKG_SET — safe heap-string assignment for FossPkgInfo fields.
 *
 * Usage: FOSSPKG_SET(pi, name, "openssl");
 *
 * - Frees the previous heap value if non-NULL.
 * - If val is NULL or empty (""), the field is set to NULL (absent).
 * - On strdup OOM the field is set to NULL and a warning is emitted.
 *
 * Implemented as an inline function (FossPkgSet) plus a macro wrapper so
 * call sites remain readable without knowing the field address.
 * ---------------------------------------------------------------------- */

#ifndef FOSSPKG_WARN
#define FOSSPKG_WARN(fmt, ...) \
  fprintf(stderr, "fosspkg: WARNING: " fmt "\n", ##__VA_ARGS__)
#endif

static inline void FossPkgSet(char **field, const char *val)
{
  free(*field);
  if (!val || val[0] == '\0') {
    *field = NULL;
    return;
  }
  *field = strdup(val);
  if (!*field)
    FOSSPKG_WARN("FossPkgSet: strdup OOM — field set to NULL");
}

/** FOSSPKG_SET(pi, field, val)  expands to  FossPkgSet(&pi->field, val) */
#define FOSSPKG_SET(pi, field, val)  FossPkgSet(&((pi)->field), (val))

/* -------------------------------------------------------------------------
 * FossPkgList — dynamically-growing array of FossPkgInfo pointers
 * ---------------------------------------------------------------------- */
typedef struct FossPkgList {
  FossPkgInfo **pkgs;   ///< Array of pointers (each heap-allocated)
  int           count;  ///< Number of valid entries
  int           cap;    ///< Allocated capacity
} FossPkgList;

/* -------------------------------------------------------------------------
 * Lifecycle
 * ---------------------------------------------------------------------- */

/** Allocate and zero a new FossPkgInfo.  Aborts on OOM (never returns NULL). */
FossPkgInfo *FossPkgInfoAlloc(void);

/** Free a FossPkgInfo and all its heap members.  Safe to call with NULL. */
void FossPkgInfoFree(FossPkgInfo *pi);

/** Allocate an empty FossPkgList. */
FossPkgList *FossPkgListAlloc(void);

/**
 * Append a FossPkgInfo to a list (takes ownership).
 * Grows the backing array as needed.  If FOSSPKG_HARD_MAX_PKGS is reached
 * the entry is freed and dropped with a warning rather than crashing.
 */
void FossPkgListAppend(FossPkgList *list, FossPkgInfo *pi);

/** Free a FossPkgList and all contained FossPkgInfo structs. */
void FossPkgListFree(FossPkgList *list);

/* -------------------------------------------------------------------------
 * OS parsers
 * ---------------------------------------------------------------------- */

FossPkgList *FossPkg_ParseDpkgStatus(const char *path);
FossPkgInfo *FossPkg_ParseDebControl(const char *path);
FossPkgList *FossPkg_ParseApkInstalled(const char *path);
FossPkgList *FossPkg_ParseRpmSqlite(const char *path);
FossPkgList *FossPkg_ParseRpmBdb(const char *packagesPath);

/* -------------------------------------------------------------------------
 * Language ecosystem parsers
 * ---------------------------------------------------------------------- */

FossPkgList *FossPkg_ParseNpmLock(const char *path);
FossPkgList *FossPkg_ParseYarnLock(const char *path);
FossPkgList *FossPkg_ParsePipRequirements(const char *path);
FossPkgList *FossPkg_ParsePipfileLock(const char *path);
FossPkgList *FossPkg_ParsePyprojectToml(const char *path);
/**
 * Parse a PEP 566/658 dist-info METADATA file produced by `pip install`.
 *
 * Each installed pip package leaves a METADATA file inside its
 * <name>-<ver>.dist-info/ directory under site-packages.  This is the only
 * reliable record of pip-installed packages when no requirements.txt was
 * copied into the image (e.g. Dockerfiles that do `RUN pip install <pkg>`).
 *
 * The format is RFC 822 / email-header style, identical to PEP 241/314/566.
 * Relevant fields extracted: Name, Version, Summary, Home-page, License,
 * Author, Author-email.
 *
 * Returns a FossPkgList with exactly one entry on success, NULL on error.
 */
FossPkgList *FossPkg_ParsePipDistInfo(const char *path);
FossPkgList *FossPkg_ParseMavenPom(const char *path);
FossPkgList *FossPkg_ParseGoMod(const char *path);
FossPkgList *FossPkg_ParseCargoLock(const char *path);
FossPkgList *FossPkg_ParseGemfileLock(const char *path);
FossPkgList *FossPkg_ParseNugetLock(const char *path);
FossPkgList *FossPkg_ParseComposerLock(const char *path);

/* -------------------------------------------------------------------------
 * Utilities
 * ---------------------------------------------------------------------- */

FossPkgManager  FossPkg_DetectManager(const char *path);
const char     *FossPkg_ManagerName(FossPkgManager mgr);
FossPkgList    *FossPkg_ParseAuto(const char *path);

/* -------------------------------------------------------------------------
 * Error macro (override in unit tests)
 * ---------------------------------------------------------------------- */
#ifndef FOSSPKG_ERR
#define FOSSPKG_ERR(fmt, ...) \
  fprintf(stderr, "fosspkg: " fmt "\n", ##__VA_ARGS__)
#endif

#endif /* _FOSSPKG_H */
