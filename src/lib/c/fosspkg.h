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
 * All agents that link against the fossology target automatically have
 * access to this header via the FO_CLIB_SRC include path.  No extra
 * CMakeLists changes are needed in agent build files.
 *
 * Provides parsers for:
 *   - Debian dpkg status database  (/var/lib/dpkg/status)
 *   - Alpine apk installed database (/lib/apk/db/installed)
 *   - RPM sqlite database           (/var/lib/rpm/rpmdb.sqlite)
 *   - Debian control file           (single .deb control block)
 *
 * Both pkgagent and containeragent link against this library so parsing
 * logic is never duplicated.  The library is pure C, has no FOSSology
 * scheduler dependencies, and can be unit-tested independently.
 *
 * Memory contract
 * ---------------
 * Every FossPkgInfo returned by a Parse* function is heap-allocated.
 * The caller must free it with FossPkgInfoFree().  Arrays inside the
 * struct (e.g. requires[]) are also heap-allocated and freed by the
 * same call.
 *
 * Error handling
 * --------------
 * All functions return NULL / -1 on error and write a message to
 * stderr via the FOSSPKG_ERR macro (overridable for testing).
 */

#ifndef _FOSSPKG_H
#define _FOSSPKG_H 1

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <ctype.h>

/* -------------------------------------------------------------------------
 * Tunables
 * ---------------------------------------------------------------------- */
#define FOSSPKG_MAXFIELD   4096   ///< Max length of any single field value
#define FOSSPKG_MAXNAME     512   ///< Max length of package name / version
#define FOSSPKG_MAX_DEPS    256   ///< Max dependency entries per package
#define FOSSPKG_MAX_PKGS  16384  ///< Max packages returned from a database

/* -------------------------------------------------------------------------
 * Package manager identifiers
 * ---------------------------------------------------------------------- */
typedef enum {
  FOSSPKG_MGR_UNKNOWN = 0,
  FOSSPKG_MGR_DPKG,    ///< Debian / Ubuntu dpkg
  FOSSPKG_MGR_APK,     ///< Alpine apk
  FOSSPKG_MGR_RPM,     ///< RPM (sqlite rpmdb)
  FOSSPKG_MGR_DEB,     ///< Single .deb control file (pkgagent use)
} FossPkgManager;

/* -------------------------------------------------------------------------
 * Single package record
 * ---------------------------------------------------------------------- */
/**
 * \struct FossPkgInfo
 * \brief Holds metadata for one installed package.
 *
 * Fields that are not present in a particular format are left as empty
 * strings ("") or zero.  Callers should check for empty strings before
 * using a field.
 */
typedef struct FossPkgInfo {
  /* Identity */
  char name[FOSSPKG_MAXNAME];       ///< Package name
  char version[FOSSPKG_MAXNAME];    ///< Installed version string
  char arch[FOSSPKG_MAXNAME];       ///< Architecture  (e.g. "amd64", "noarch")
  char source[FOSSPKG_MAXNAME];     ///< Source package name (dpkg / rpm)

  /* Descriptive */
  char summary[FOSSPKG_MAXFIELD];   ///< One-line description
  char description[FOSSPKG_MAXFIELD]; ///< Full description (may be multi-line)
  char maintainer[FOSSPKG_MAXFIELD];  ///< Maintainer / Packager field
  char license[FOSSPKG_MAXNAME];    ///< License (rpm) or empty for dpkg/apk
  char url[FOSSPKG_MAXFIELD];       ///< Homepage / URL

  /* Debian binary package classification */
  char section[FOSSPKG_MAXNAME];    ///< e.g. "utils", "libs"
  char priority[FOSSPKG_MAXNAME];   ///< e.g. "optional", "extra"

  /* Status (dpkg) */
  char status[FOSSPKG_MAXNAME];     ///< e.g. "install ok installed"

  /* Dependencies — flat string array, each entry one dep token */
  char **requires;                  ///< Dependency strings (heap array)
  int   requireCount;               ///< Number of entries in requires[]

  /* Package manager that produced this record */
  FossPkgManager manager;

  /* Container context — set by container callers, ignored by pkgagent */
  int   layerIndex;                 ///< Layer the package was found in (-1 = unknown)
  char  dbFilePath[FOSSPKG_MAXFIELD]; ///< Path to the database file in the repo
} FossPkgInfo;

/* -------------------------------------------------------------------------
 * Package list — result of parsing a whole database file
 * ---------------------------------------------------------------------- */
/**
 * \struct FossPkgList
 * \brief Dynamic array of FossPkgInfo pointers.
 */
typedef struct FossPkgList {
  FossPkgInfo **pkgs;   ///< Array of pointers (each heap-allocated)
  int           count;  ///< Number of valid entries
  int           cap;    ///< Allocated capacity
} FossPkgList;

/* -------------------------------------------------------------------------
 * Lifecycle
 * ---------------------------------------------------------------------- */

/**
 * \brief Allocate and zero a new FossPkgInfo.
 * \return Heap-allocated struct; never NULL (aborts on OOM).
 */
FossPkgInfo *FossPkgInfoAlloc(void);

/**
 * \brief Free a FossPkgInfo and all its heap members.
 * \param pi  May be NULL (no-op).
 */
void FossPkgInfoFree(FossPkgInfo *pi);

/**
 * \brief Allocate an empty FossPkgList.
 */
FossPkgList *FossPkgListAlloc(void);

/**
 * \brief Append a FossPkgInfo to a list (takes ownership).
 */
void FossPkgListAppend(FossPkgList *list, FossPkgInfo *pi);

/**
 * \brief Free a FossPkgList and all contained FossPkgInfo structs.
 */
void FossPkgListFree(FossPkgList *list);

/* -------------------------------------------------------------------------
 * Parsers
 * ---------------------------------------------------------------------- */

/**
 * \brief Parse a Debian dpkg status database file.
 *
 * Reads /var/lib/dpkg/status (or a file with the same format) and returns
 * one FossPkgInfo per stanza.  Only stanzas with Status containing
 * "installed" are included — partially-installed or removed packages are
 * skipped.
 *
 * \param path  Absolute path to the status file.
 * \return      FossPkgList on success (may be empty), NULL on parse error.
 *              Caller must call FossPkgListFree().
 */
FossPkgList *FossPkg_ParseDpkgStatus(const char *path);

/**
 * \brief Parse a single Debian control file block (pkgagent compatibility).
 *
 * Reads one RFC-822 stanza (as found in a .deb's control file) and
 * returns a single FossPkgInfo.  Replaces / wraps the existing pkgagent
 * Deb_Control() logic so pkgagent can call this instead.
 *
 * \param path  Path to the extracted control file.
 * \return      Heap-allocated FossPkgInfo, or NULL on error.
 */
FossPkgInfo *FossPkg_ParseDebControl(const char *path);

/**
 * \brief Parse an Alpine apk installed database file.
 *
 * Reads /lib/apk/db/installed.  Each package block is separated by a
 * blank line; fields are single-letter prefixes followed by a colon and
 * value.
 *
 * \param path  Absolute path to the installed file.
 * \return      FossPkgList on success, NULL on error.
 */
FossPkgList *FossPkg_ParseApkInstalled(const char *path);

/**
 * \brief Parse an RPM sqlite database (rpmdb.sqlite / Packages.db).
 *
 * Reads the modern RPM sqlite database introduced in rpm 4.16+.
 * Requires libsqlite3.  Falls back gracefully if the file is a legacy
 * BerkeleyDB format (returns an empty list with a warning).
 *
 * \param path  Absolute path to rpmdb.sqlite or Packages.db.
 * \return      FossPkgList on success, NULL on error.
 */
FossPkgList *FossPkg_ParseRpmSqlite(const char *path);

/* -------------------------------------------------------------------------
 * Utilities
 * ---------------------------------------------------------------------- */

/**
 * \brief Detect the package manager from a filename / path.
 *
 * Uses the basename of path to guess the format:
 *   "status"           → FOSSPKG_MGR_DPKG
 *   "installed"        → FOSSPKG_MGR_APK
 *   "rpmdb.sqlite"     → FOSSPKG_MGR_RPM
 *   "Packages.db"      → FOSSPKG_MGR_RPM
 *
 * \param path  Any path; basename is extracted internally.
 * \return      FossPkgManager enum value.
 */
FossPkgManager FossPkg_DetectManager(const char *path);

/**
 * \brief Return a human-readable string for a FossPkgManager.
 */
const char *FossPkg_ManagerName(FossPkgManager mgr);

/**
 * \brief Parse whichever database format is detected from the filename.
 *
 * Convenience wrapper: calls FossPkg_DetectManager then dispatches to
 * the appropriate parser.
 *
 * \param path  Path to the database file.
 * \return      FossPkgList or NULL.
 */
FossPkgList *FossPkg_ParseAuto(const char *path);

/* -------------------------------------------------------------------------
 * Error macro (override in tests)
 * ---------------------------------------------------------------------- */
#ifndef FOSSPKG_ERR
#define FOSSPKG_ERR(fmt, ...) \
  fprintf(stderr, "fosspkg: " fmt "\n", ##__VA_ARGS__)
#endif

#endif /* _FOSSPKG_H */
