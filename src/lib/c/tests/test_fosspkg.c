/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file test_fosspkg.c
 * \brief Unit tests for libfosspkg using CUnit.
 *
 * Tests are self-contained: fixture data is written to temporary files,
 * parsed, and the results verified.  No database connection is needed.
 */

#include <CUnit/CUnit.h>
#include <CUnit/Basic.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <sys/stat.h>

#include "fosspkg.h"

/* =========================================================================
 * Helpers
 * ========================================================================= */

static char *WriteTempFile(const char *content)
{
  char *path = strdup("/tmp/fosspkg_test_XXXXXX");
  int fd = mkstemp(path);
  if (fd < 0) { free(path); return NULL; }
  if (write(fd, content, strlen(content)) < 0) {
    /* Write failed — close and return NULL so callers skip the test */
    close(fd);
    free(path);
    return NULL;
  }
  close(fd);
  return path;
}

/* =========================================================================
 * Lifecycle tests
 * ========================================================================= */

static void test_alloc_free(void)
{
  FossPkgInfo *pi = FossPkgInfoAlloc();
  CU_ASSERT_PTR_NOT_NULL(pi);
  CU_ASSERT_EQUAL(pi->layerIndex, -1);
  CU_ASSERT_EQUAL(pi->manager, FOSSPKG_MGR_UNKNOWN);
  CU_ASSERT_EQUAL(pi->name[0], '\0');
  FossPkgInfoFree(pi);   /* must not crash */
  FossPkgInfoFree(NULL); /* must not crash */

  FossPkgList *list = FossPkgListAlloc();
  CU_ASSERT_PTR_NOT_NULL(list);
  CU_ASSERT_EQUAL(list->count, 0);
  FossPkgListFree(list);
  FossPkgListFree(NULL);
}

/* =========================================================================
 * dpkg status tests
 * ========================================================================= */

static const char DPKG_STATUS_SIMPLE[] =
  "Package: bash\n"
  "Status: install ok installed\n"
  "Version: 5.1-6\n"
  "Architecture: amd64\n"
  "Maintainer: Matthias Klose <doko@debian.org>\n"
  "Homepage: http://tiswww.case.edu/php/chet/bash/bashtop.html\n"
  "Depends: base-files (>= 2.1.12), libreadline8 (>= 6.0)\n"
  "Description: GNU Bourne Again SHell\n"
  " Bash is an sh-compatible command language interpreter.\n"
  "\n"
  "Package: libc6\n"
  "Status: install ok installed\n"
  "Version: 2.33-7\n"
  "Architecture: amd64\n"
  "Source: glibc\n"
  "Description: GNU C Library: Shared libraries\n"
  "\n";

static const char DPKG_STATUS_SKIP_REMOVED[] =
  "Package: wget\n"
  "Status: deinstall ok config-files\n"  /* NOT installed — must be skipped */
  "Version: 1.21.3\n"
  "Architecture: amd64\n"
  "\n"
  "Package: curl\n"
  "Status: install ok installed\n"
  "Version: 7.88.1\n"
  "Architecture: amd64\n"
  "\n";

static void test_dpkg_simple(void)
{
  char *path = WriteTempFile(DPKG_STATUS_SIMPLE);
  CU_ASSERT_PTR_NOT_NULL_FATAL(path);

  FossPkgList *list = FossPkg_ParseDpkgStatus(path);
  unlink(path); free(path);

  CU_ASSERT_PTR_NOT_NULL_FATAL(list);
  CU_ASSERT_EQUAL(list->count, 2);

  FossPkgInfo *bash = list->pkgs[0];
  CU_ASSERT_STRING_EQUAL(bash->name,    "bash");
  CU_ASSERT_STRING_EQUAL(bash->version, "5.1-6");
  CU_ASSERT_STRING_EQUAL(bash->arch,    "amd64");
  CU_ASSERT_STRING_EQUAL(bash->maintainer,
    "Matthias Klose <doko@debian.org>");
  CU_ASSERT_STRING_EQUAL(bash->url,
    "http://tiswww.case.edu/php/chet/bash/bashtop.html");
  CU_ASSERT_EQUAL(bash->manager, FOSSPKG_MGR_DPKG);

  /* Dependencies */
  CU_ASSERT_EQUAL(bash->requireCount, 2);
  CU_ASSERT_STRING_EQUAL(bash->requires[0], "base-files (>= 2.1.12)");
  CU_ASSERT_STRING_EQUAL(bash->requires[1], "libreadline8 (>= 6.0)");

  FossPkgInfo *libc = list->pkgs[1];
  CU_ASSERT_STRING_EQUAL(libc->name,    "libc6");
  CU_ASSERT_STRING_EQUAL(libc->source,  "glibc");
  CU_ASSERT_EQUAL(libc->requireCount, 0);

  FossPkgListFree(list);
}

static void test_dpkg_skip_removed(void)
{
  char *path = WriteTempFile(DPKG_STATUS_SKIP_REMOVED);
  CU_ASSERT_PTR_NOT_NULL_FATAL(path);

  FossPkgList *list = FossPkg_ParseDpkgStatus(path);
  unlink(path); free(path);

  CU_ASSERT_PTR_NOT_NULL_FATAL(list);
  /* wget is deinstalled — only curl should appear */
  CU_ASSERT_EQUAL(list->count, 1);
  CU_ASSERT_STRING_EQUAL(list->pkgs[0]->name, "curl");

  FossPkgListFree(list);
}

static void test_dpkg_empty_file(void)
{
  char *path = WriteTempFile("\n\n");
  FossPkgList *list = FossPkg_ParseDpkgStatus(path);
  unlink(path); free(path);
  CU_ASSERT_PTR_NOT_NULL(list);
  CU_ASSERT_EQUAL(list->count, 0);
  FossPkgListFree(list);
}

static void test_dpkg_no_trailing_newline(void)
{
  /* File with no trailing blank line — parser must flush last stanza */
  const char *content =
    "Package: zlib1g\n"
    "Status: install ok installed\n"
    "Version: 1:1.2.11.dfsg-2\n"
    "Architecture: amd64\n";
  char *path = WriteTempFile(content);
  FossPkgList *list = FossPkg_ParseDpkgStatus(path);
  unlink(path); free(path);
  CU_ASSERT_PTR_NOT_NULL_FATAL(list);
  CU_ASSERT_EQUAL(list->count, 1);
  CU_ASSERT_STRING_EQUAL(list->pkgs[0]->name, "zlib1g");
  FossPkgListFree(list);
}

/* =========================================================================
 * deb control file tests
 * ========================================================================= */

static const char DEB_CONTROL[] =
  "Package: libssl3\n"
  "Version: 3.0.7-1\n"
  "Architecture: amd64\n"
  "Maintainer: Debian OpenSSL Team <pkg-openssl-devel@alioth-lists.debian.net>\n"
  "Depends: libc6 (>= 2.17)\n"
  "Pre-Depends: libgcc-s1\n"
  "Description: Secure Sockets Layer toolkit - shared libraries\n"
  " This package is part of the OpenSSL project.\n";

static void test_deb_control(void)
{
  char *path = WriteTempFile(DEB_CONTROL);
  FossPkgInfo *pi = FossPkg_ParseDebControl(path);
  unlink(path); free(path);

  CU_ASSERT_PTR_NOT_NULL_FATAL(pi);
  CU_ASSERT_STRING_EQUAL(pi->name,    "libssl3");
  CU_ASSERT_STRING_EQUAL(pi->version, "3.0.7-1");
  CU_ASSERT_STRING_EQUAL(pi->arch,    "amd64");
  CU_ASSERT_EQUAL(pi->manager, FOSSPKG_MGR_DEB);

  /* Depends + Pre-Depends combined */
  CU_ASSERT_EQUAL(pi->requireCount, 2);

  FossPkgInfoFree(pi);
}

/* =========================================================================
 * Alpine apk tests
 * ========================================================================= */

static const char APK_INSTALLED[] =
  "C:Q1abc123==\n"
  "P:musl\n"
  "V:1.2.3-r4\n"
  "A:x86_64\n"
  "L:MIT\n"
  "T:the musl c library (libc) implementation\n"
  "U:https://musl.libc.org/\n"
  "m:Alpine Developers <alpine-devel@lists.alpinelinux.org>\n"
  "o:musl\n"
  "\n"
  "C:Q1def456==\n"
  "P:busybox\n"
  "V:1.36.0-r0\n"
  "A:x86_64\n"
  "L:GPL-2.0-only\n"
  "T:Size optimized toolbox of many common UNIX utilities\n"
  "U:https://busybox.net/\n"
  "D:musl so:libc.musl-x86_64.so.1\n"
  "m:Sören Tempel <soeren+alpine@soeren-tempel.net>\n"
  "o:busybox\n"
  "\n";

static void test_apk_simple(void)
{
  char *path = WriteTempFile(APK_INSTALLED);
  FossPkgList *list = FossPkg_ParseApkInstalled(path);
  unlink(path); free(path);

  CU_ASSERT_PTR_NOT_NULL_FATAL(list);
  CU_ASSERT_EQUAL(list->count, 2);

  FossPkgInfo *musl = list->pkgs[0];
  CU_ASSERT_STRING_EQUAL(musl->name,    "musl");
  CU_ASSERT_STRING_EQUAL(musl->version, "1.2.3-r4");
  CU_ASSERT_STRING_EQUAL(musl->arch,    "x86_64");
  CU_ASSERT_STRING_EQUAL(musl->license, "MIT");
  CU_ASSERT_STRING_EQUAL(musl->url,     "https://musl.libc.org/");
  CU_ASSERT_EQUAL(musl->manager, FOSSPKG_MGR_APK);
  CU_ASSERT_EQUAL(musl->requireCount, 0);

  FossPkgInfo *bb = list->pkgs[1];
  CU_ASSERT_STRING_EQUAL(bb->name, "busybox");
  /* "so:" prefixed tokens are filtered out, leaving "musl" */
  CU_ASSERT_EQUAL(bb->requireCount, 1);
  CU_ASSERT_STRING_EQUAL(bb->requires[0], "musl");

  FossPkgListFree(list);
}

/* =========================================================================
 * Utility tests
 * ========================================================================= */

static void test_detect_manager(void)
{
  CU_ASSERT_EQUAL(FossPkg_DetectManager("/var/lib/dpkg/status"),
                  FOSSPKG_MGR_DPKG);
  CU_ASSERT_EQUAL(FossPkg_DetectManager("/lib/apk/db/installed"),
                  FOSSPKG_MGR_APK);
  CU_ASSERT_EQUAL(FossPkg_DetectManager("/var/lib/rpm/rpmdb.sqlite"),
                  FOSSPKG_MGR_RPM);
  CU_ASSERT_EQUAL(FossPkg_DetectManager("/var/lib/rpm/Packages"),
                  FOSSPKG_MGR_RPM);
  CU_ASSERT_EQUAL(FossPkg_DetectManager("/some/unknown/file"),
                  FOSSPKG_MGR_UNKNOWN);
}

static void test_manager_name(void)
{
  CU_ASSERT_STRING_EQUAL(FossPkg_ManagerName(FOSSPKG_MGR_DPKG), "dpkg");
  CU_ASSERT_STRING_EQUAL(FossPkg_ManagerName(FOSSPKG_MGR_APK),  "apk");
  CU_ASSERT_STRING_EQUAL(FossPkg_ManagerName(FOSSPKG_MGR_RPM),  "rpm");
  CU_ASSERT_STRING_EQUAL(FossPkg_ManagerName(FOSSPKG_MGR_UNKNOWN), "unknown");
}

static void test_parse_auto_dispatch(void)
{
  /* ParseAuto should dispatch to dpkg parser for a file named "status" */
  char *path = WriteTempFile(DPKG_STATUS_SIMPLE);
  CU_ASSERT_PTR_NOT_NULL_FATAL(path);

  /* Rename into a temp dir with the exact basename "status" so that
   * FossPkg_DetectManager() recognises it (it matches on basename == "status"). */
  char statusDir[256];
  char statusPath[280];
  snprintf(statusDir, sizeof(statusDir), "/tmp/fosspkg_autotest_%d", getpid());
  int mkdirRet = mkdir(statusDir, 0700);
  if (mkdirRet != 0 && errno != EEXIST) {
    unlink(path); free(path);
    CU_FAIL_FATAL("mkdir failed for ParseAuto test");
  }
  snprintf(statusPath, sizeof(statusPath), "%s/status", statusDir);
  if (rename(path, statusPath) != 0) {
    unlink(path); free(path);
    rmdir(statusDir);
    CU_FAIL_FATAL("rename failed for ParseAuto test");
  }
  free(path);

  FossPkgList *list = FossPkg_ParseAuto(statusPath);
  unlink(statusPath);
  rmdir(statusDir);

  CU_ASSERT_PTR_NOT_NULL_FATAL(list);
  CU_ASSERT_EQUAL(list->count, 2);
  FossPkgListFree(list);
}

/* =========================================================================
 * Large input stress test
 * ========================================================================= */

static void test_dpkg_many_packages(void)
{
  /* Generate a status file with 500 packages */
  FILE *fp = tmpfile();
  CU_ASSERT_PTR_NOT_NULL_FATAL(fp);

  for (int i = 0; i < 500; i++) {
    fprintf(fp,
      "Package: pkg%04d\n"
      "Status: install ok installed\n"
      "Version: 1.0.%d\n"
      "Architecture: amd64\n"
      "Depends: pkg%04d\n"
      "\n",
      i, i, (i + 1) % 500);
  }
  rewind(fp);

  /* Write to a named temp file (ParseDpkgStatus needs a path) */
  char tmpPath[64];
  snprintf(tmpPath, sizeof(tmpPath), "/tmp/fosspkg_stress_%d", getpid());
  FILE *out = fopen(tmpPath, "w");
  char buf[4096];
  size_t n;
  while ((n = fread(buf, 1, sizeof(buf), fp)) > 0)
    fwrite(buf, 1, n, out);
  fclose(fp);
  fclose(out);

  FossPkgList *list = FossPkg_ParseDpkgStatus(tmpPath);
  unlink(tmpPath);

  CU_ASSERT_PTR_NOT_NULL_FATAL(list);
  CU_ASSERT_EQUAL(list->count, 500);

  for (int i = 0; i < list->count; i++) {
    CU_ASSERT_EQUAL(list->pkgs[i]->requireCount, 1);
  }

  FossPkgListFree(list);
}

/* =========================================================================
 * main
 * ========================================================================= */

int main(void)
{
  CU_initialize_registry();

  CU_pSuite lifecycle = CU_add_suite("Lifecycle", NULL, NULL);
  CU_add_test(lifecycle, "alloc/free", test_alloc_free);

  CU_pSuite dpkg = CU_add_suite("dpkg status parser", NULL, NULL);
  CU_add_test(dpkg, "simple two-package file",   test_dpkg_simple);
  CU_add_test(dpkg, "skip deinstalled packages", test_dpkg_skip_removed);
  CU_add_test(dpkg, "empty file",                test_dpkg_empty_file);
  CU_add_test(dpkg, "no trailing newline",       test_dpkg_no_trailing_newline);
  CU_add_test(dpkg, "500 packages stress",       test_dpkg_many_packages);

  CU_pSuite deb = CU_add_suite("deb control parser", NULL, NULL);
  CU_add_test(deb, "single control file", test_deb_control);

  CU_pSuite apk = CU_add_suite("apk installed parser", NULL, NULL);
  CU_add_test(apk, "simple two-package file", test_apk_simple);

  CU_pSuite utils = CU_add_suite("Utilities", NULL, NULL);
  CU_add_test(utils, "detect manager from path", test_detect_manager);
  CU_add_test(utils, "manager name strings",     test_manager_name);
  CU_add_test(utils, "ParseAuto dispatch",        test_parse_auto_dispatch);

  CU_basic_set_mode(CU_BRM_VERBOSE);
  CU_basic_run_tests();
  int failures = CU_get_number_of_failures();
  CU_cleanup_registry();
  return failures ? 1 : 0;
}
