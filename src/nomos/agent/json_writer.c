/***************************************************************
 Copyright (C) 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

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

#include "json_writer.h"
#include "nomos.h"
#include "nomos_utils.h"
#include <json-c/json.h>

void writeToTemp()
{
  char realPathOfTarget[PATH_MAX];
  if (optionIsSet(OPTS_LONG_CMD_OUTPUT))
  {
    if (!realpath(cur.targetFile, realPathOfTarget))
    {
      strcpy(realPathOfTarget, basename(cur.targetFile));
    }
  }
  else
  {
    strcpy(realPathOfTarget, basename(cur.targetFile));
  }
  sem_wait(&cur.mutexTempJson);
  fprintf(cur.tempJsonPath, "%s;%s\n", realPathOfTarget, cur.compLic);
  fflush(cur.tempJsonPath);
  sem_post(&cur.mutexTempJson);
}

void writeToStdOut()
{
  json_object *root = json_object_new_object();
  json_object *results = json_object_new_array();
  json_object *result = json_object_new_object();
  json_object *licenses = json_object_new_array();
  json_object *fileLocation = NULL;
  json_object *aLicense = NULL;
  char realPathOfTarget[PATH_MAX];
  parseLicenseList();
  size_t i = 0;
  while (cur.licenseList[i] != NULL)
  {
    aLicense = json_object_new_string(cur.licenseList[i]);
    json_object_array_add(licenses, aLicense);
    ++i;
  }
  if (optionIsSet(OPTS_LONG_CMD_OUTPUT)
      && realpath(cur.targetFile, realPathOfTarget))
  {
    fileLocation = json_object_new_string(realPathOfTarget);
  }
  else
  {
    fileLocation = json_object_new_string(basename(cur.targetFile));
  }
  json_object_object_add(result, "file", fileLocation);
  json_object_object_add(result, "licenses", licenses);
  json_object_array_add(results, result);
  json_object_object_add(root, "results", results);
  const char *prettyJson = unescapePathSeparator(
      (char*) json_object_to_json_string_ext(root,
      JSON_C_TO_STRING_PRETTY));
  printf("%s\n", prettyJson);
  json_object_put(root);
}

void parseTempJson()
{
  char *line = NULL;
  size_t len = 0;
  ssize_t read;
  json_object *root = json_object_new_object();
  json_object *results = json_object_new_array();
  json_object *result = NULL;
  json_object *licenses = NULL;
  json_object *fileLocation = NULL;
  json_object *aLicense = NULL;

  fseek(cur.tempJsonPath, 0, SEEK_SET);
  while ((read = getline(&line, &len, cur.tempJsonPath)) != -1)
  {
    if (line[read - 1] == '\n')
    {
      line[read - 1] = '\0';
    }
    fileLocation = json_object_new_string(strtok(line, ";"));
    strcpy(cur.compLic, strtok(NULL, ";"));
    parseLicenseList();

    licenses = json_object_new_array();
    size_t i = 0;
    while (cur.licenseList[i] != NULL)
    {
      aLicense = json_object_new_string(cur.licenseList[i]);
      json_object_array_add(licenses, aLicense);
      ++i;
    }
    result = json_object_new_object();
    json_object_object_add(result, "file", fileLocation);
    json_object_object_add(result, "licenses", licenses);
    json_object_array_add(results, result);
  }
  json_object_object_add(root, "results", results);
  const char *prettyJson = unescapePathSeparator(
      (char*) json_object_to_json_string_ext(root,
      JSON_C_TO_STRING_PRETTY));
  printf("%s\n", prettyJson);
  json_object_put(root);
  if (line)
  {
    free(line);
  }
}

char *unescapePathSeparator(char* json)
{
  const char *escapedSeparator = "\\/";
  const char *pathSeparator = "/";
  const int escPathLen = 2;
  char *result;
  char *tmp;
  int count;
  if (!json)
  {
    return NULL;
  }

  tmp = json;
  for (count = 0; (tmp = strstr(tmp, escapedSeparator)); count++)
  {
    tmp += escPathLen;
  }

  result = malloc(sizeof(char) * ((strlen(json) - (escPathLen * count)) + 1));

  strcpy(result, strtok(json, escapedSeparator));
  while (count--)
  {
    strcat(result, pathSeparator);
    strcat(result, strtok(NULL, escapedSeparator));
  }
  return result;
}
