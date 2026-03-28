/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit tests for containeragent metadata parsing functions.
 *
 * Tests cover:
 *   - ParseDockerManifest()  : extracts image name, tag, layer count
 *   - ParseImageConfig()     : extracts OS, arch, env, ports, labels, layers
 *   - GetMetadataDocker()    : integration with uploadtree DB query
 */

#include "containeragent.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "CUnit/CUnit.h"

#define MAXSQL 4096
extern char *DBConfFile;

/* =========================================================================
 * Helpers — write temporary JSON test files
 * ========================================================================= */

/** Write a minimal Docker manifest.json to a temp file. */
static char *WriteTestManifest(const char *imageName, const char *tag,
                                const char *configFile, int layerCount)
{
  char *path = strdup("/tmp/test_manifest_XXXXXX");
  int  fd    = mkstemp(path);
  if (fd < 0) { free(path); return NULL; }

  FILE *fp = fdopen(fd, "w");
  fprintf(fp,
    "[{\"Config\":\"%s\","
    "\"RepoTags\":[\"%s:%s\"],"
    "\"Layers\":[",
    configFile, imageName, tag);
  for (int i = 0; i < layerCount; i++) {
    if (i > 0) fprintf(fp, ",");
    fprintf(fp, "\"layer%d/layer.tar\"", i);
  }
  fprintf(fp, "]}]\n");
  fclose(fp);
  return path;
}

/** Write a minimal image config JSON to a temp file. */
static char *WriteTestConfig()
{
  char *path = strdup("/tmp/test_config_XXXXXX");
  int  fd    = mkstemp(path);
  if (fd < 0) { free(path); return NULL; }

  FILE *fp = fdopen(fd, "w");
  fprintf(fp,
    "{"
    "\"os\":\"linux\","
    "\"architecture\":\"amd64\","
    "\"variant\":\"v8\","
    "\"created\":\"2024-01-15T10:00:00Z\","
    "\"author\":\"Test Author\","
    "\"config\":{"
    "  \"Entrypoint\":[\"/bin/sh\",\"-c\"],"
    "  \"Cmd\":[\"/usr/bin/nginx\"],"
    "  \"WorkingDir\":\"/app\","
    "  \"User\":\"nobody\","
    "  \"Env\":[\"PATH=/usr/local/sbin:/usr/local/bin\",\"NGINX_VERSION=1.24\"],"
    "  \"ExposedPorts\":{\"80/tcp\":{},\"443/tcp\":{}},"
    "  \"Labels\":{"
    "    \"maintainer\":\"test@example.com\","
    "    \"org.opencontainers.image.description\":\"Test nginx image\""
    "  }"
    "},"
    "\"history\":["
    "  {\"created_by\":\"/bin/sh -c apt-get install nginx\",\"empty_layer\":false},"
    "  {\"created_by\":\"/bin/sh -c echo done\",\"empty_layer\":true}"
    "]"
    "}\n");
  fclose(fp);
  return path;
}

/* =========================================================================
 * Test: ParseDockerManifest
 * ========================================================================= */

/**
 * \brief Test that ParseDockerManifest correctly extracts image name, tag,
 *        layer count, and config filename.
 * \test
 * -# Write a synthetic manifest.json to a temp file
 * -# Call ParseDockerManifest()
 * -# Assert image name, tag, layer count, and config filename are correct
 */
void test_ParseDockerManifest_basic()
{
  struct containerpkginfo pi;
  memset(&pi, 0, sizeof(pi));

  char configFilename[MAXLENGTH] = "";
  char *manifestPath = WriteTestManifest("nginx", "1.24",
                                         "abc123.json", 3);
  CU_ASSERT_PTR_NOT_NULL_FATAL(manifestPath);

  int rc = ParseDockerManifest(manifestPath, &pi,
                               configFilename, sizeof(configFilename));

  CU_ASSERT_EQUAL(rc, 0);
  CU_ASSERT_STRING_EQUAL(pi.imageName, "nginx");
  CU_ASSERT_STRING_EQUAL(pi.imageTag,  "1.24");
  CU_ASSERT_EQUAL(pi.layerCount, 3);
  CU_ASSERT_STRING_EQUAL(configFilename, "abc123.json");
  CU_ASSERT_STRING_EQUAL(pi.format, "docker");

  remove(manifestPath);
  free(manifestPath);
}

/**
 * \brief Test that ParseDockerManifest handles a tag-less image name
 *        by defaulting to "latest".
 * \test
 * -# Write manifest with a tag-less repo name
 * -# Assert imageTag defaults to "latest"
 */
void test_ParseDockerManifest_no_tag()
{
  struct containerpkginfo pi;
  memset(&pi, 0, sizeof(pi));

  char  configFilename[MAXLENGTH] = "";
  char *path = strdup("/tmp/test_manifest2_XXXXXX");
  int   fd   = mkstemp(path);
  FILE *fp   = fdopen(fd, "w");
  fprintf(fp,
    "[{\"Config\":\"cfg.json\","
    "\"RepoTags\":[\"ubuntu\"],"
    "\"Layers\":[\"l0/layer.tar\"]}]\n");
  fclose(fp);

  int rc = ParseDockerManifest(path, &pi, configFilename,
                               sizeof(configFilename));
  CU_ASSERT_EQUAL(rc, 0);
  CU_ASSERT_STRING_EQUAL(pi.imageName, "ubuntu");
  CU_ASSERT_STRING_EQUAL(pi.imageTag,  "latest");

  remove(path);
  free(path);
}

/* =========================================================================
 * Test: ParseImageConfig
 * ========================================================================= */

/**
 * \brief Test that ParseImageConfig correctly extracts all metadata fields.
 * \test
 * -# Write a synthetic config JSON to a temp file
 * -# Call ParseImageConfig()
 * -# Assert OS, arch, entrypoint, cmd, workingDir, user,
 *    envCount, portCount, labelCount, and layerCount are correct
 */
void test_ParseImageConfig_full()
{
  struct containerpkginfo pi;
  memset(&pi, 0, sizeof(pi));

  char *cfgPath = WriteTestConfig();
  CU_ASSERT_PTR_NOT_NULL_FATAL(cfgPath);

  int rc = ParseImageConfig(cfgPath, &pi);

  CU_ASSERT_EQUAL(rc, 0);

  /* Platform */
  CU_ASSERT_STRING_EQUAL(pi.os,           "linux");
  CU_ASSERT_STRING_EQUAL(pi.architecture, "amd64");
  CU_ASSERT_STRING_EQUAL(pi.variant,      "v8");
  CU_ASSERT_STRING_EQUAL(pi.created,      "2024-01-15T10:00:00Z");
  CU_ASSERT_STRING_EQUAL(pi.author,       "Test Author");

  /* Runtime */
  CU_ASSERT_STRING_EQUAL(pi.workingDir,   "/app");
  CU_ASSERT_STRING_EQUAL(pi.user,         "nobody");

  /* Entrypoint and Cmd are flattened arrays */
  CU_ASSERT_STRING_EQUAL(pi.entrypoint,   "/bin/sh -c");
  CU_ASSERT_STRING_EQUAL(pi.cmd,          "/usr/bin/nginx");

  /* Dynamic arrays */
  CU_ASSERT_EQUAL(pi.envCount,   2);
  CU_ASSERT_EQUAL(pi.portCount,  2);
  CU_ASSERT_EQUAL(pi.labelCount, 2);

  /* Layer history (2 entries in the test config) */
  CU_ASSERT_EQUAL(pi.layerCount, 2);
  CU_ASSERT_PTR_NOT_NULL(pi.layers);
  CU_ASSERT_EQUAL(pi.layers[1].emptyLayer, 1);

  /* description pulled from OCI label */
  CU_ASSERT_STRING_EQUAL(pi.description, "Test nginx image");

  remove(cfgPath);
  free(cfgPath);
  FreeContainerInfo(&pi);
}

/**
 * \brief Test that ParseImageConfig returns -1 for an invalid JSON file.
 * \test
 * -# Write a non-JSON file
 * -# Assert ParseImageConfig() returns -1
 */
void test_ParseImageConfig_invalid_json()
{
  char *path = strdup("/tmp/test_bad_config_XXXXXX");
  int   fd   = mkstemp(path);
  FILE *fp   = fdopen(fd, "w");
  fprintf(fp, "this is not json at all!!!\n");
  fclose(fp);

  struct containerpkginfo pi;
  memset(&pi, 0, sizeof(pi));

  int rc = ParseImageConfig(path, &pi);
  CU_ASSERT_EQUAL(rc, -1);

  remove(path);
  free(path);
}

/* =========================================================================
 * Test: GetMetadataDocker (DB integration)
 * ========================================================================= */

/**
 * \brief Test GetMetadataDocker returns -1 when upload_pk is 0.
 * \test
 * -# Call GetMetadataDocker with upload_pk = 0
 * -# Assert return value is -1
 */
void test_GetMetadataDocker_no_uploadpk()
{
  struct containerpkginfo pi;
  memset(&pi, 0, sizeof(pi));

  char *ErrorBuf;
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);

  int rc = GetMetadataDocker(0, &pi);
  CU_ASSERT_EQUAL(rc, -1);

  PQfinish(db_conn);
}

/* =========================================================================
 * Test suite registration
 * ========================================================================= */

CU_TestInfo testcases_ContainerAgent[] = {
  { "ParseDockerManifest: basic extraction",
    test_ParseDockerManifest_basic },
  { "ParseDockerManifest: tag defaults to latest",
    test_ParseDockerManifest_no_tag },
  { "ParseImageConfig: all fields",
    test_ParseImageConfig_full },
  { "ParseImageConfig: invalid JSON returns -1",
    test_ParseImageConfig_invalid_json },
  { "GetMetadataDocker: returns -1 with no upload_pk",
    test_GetMetadataDocker_no_uploadpk },
  CU_TEST_INFO_NULL
};
