/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/

#include "CommentExtractor.hpp"

#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <sys/wait.h>
#include <sstream>
#include <unistd.h>
#include <json/json.h>

/**
 * @brief Shell-escape a string for use in a popen() command.
 */
static std::string shellEscape(const std::string& s)
{
  std::string r = "'";
  for (char c : s)
    r += (c == '\'') ? std::string("'\\''") : std::string(1, c);
  r += "'";
  return r;
}

/**
 * @brief Parse a single comment array from the JSON output.
 */
static std::vector<CommentBlock> parseCommentArray(const Json::Value& arr)
{
  std::vector<CommentBlock> result;
  if (!arr.isArray()) return result;
  for (const auto& item : arr)
  {
    CommentBlock cb;
    if (item.isMember("start_line"))
    {
      cb.startLine = item.get("start_line", 0).asInt();
      cb.endLine   = item.get("end_line", 0).asInt();
    }
    else
    {
      cb.startLine = item.get("line_number", 0).asInt();
      cb.endLine   = cb.startLine;
    }
    cb.text      = item.get("comment", "").asString();
    result.push_back(cb);
  }
  return result;
}

/**
 * @brief Locate the nirjas binary in common installation paths.
 *        Falls back to bare "nirjas" for PATH resolution.
 */
static std::string findNirjasBinary()
{
  const char* home = getenv("HOME");
  std::vector<std::string> candidates;

  if (home)
    candidates.push_back(std::string(home) + "/.local/bin/nirjas");
  candidates.push_back("/usr/local/bin/nirjas");
  candidates.push_back("/usr/bin/nirjas");

  for (const auto& path : candidates)
    if (access(path.c_str(), X_OK) == 0)
      return path;

  return "nirjas"; // fallback to PATH
}

NirjasOutput extractComments(const std::string& filePath,
                             const std::string& originalFilename)
{
  NirjasOutput output;
  output.lang = "error";

  if (filePath.empty()) return output;

  // The repository stores files with hash-based names, but nirjas determines
  // the language from the file extension.  Create a temporary symlink with the
  // original filename so nirjas can detect the language correctly.
  struct TempCleanup {
    std::string symlinkPath;
    std::string tmpDirPath;
    ~TempCleanup() {
      if (!symlinkPath.empty()) unlink(symlinkPath.c_str());
      if (!tmpDirPath.empty()) rmdir(tmpDirPath.c_str());
    }
  } tempCleanup;

  std::string targetPath = filePath;

  if (!originalFilename.empty())
  {
    std::string tmpTemplate = "/tmp/nirjas_XXXXXX";
    char* tmpDirBuf = strdup(tmpTemplate.c_str());
    if (mkdtemp(tmpDirBuf))
    {
      tempCleanup.tmpDirPath = tmpDirBuf;
      // Strip any directory components from originalFilename so the
      // symlink target is a plain filename (no /, .., or .).
      std::string safeName = originalFilename;
      size_t slash = safeName.rfind('/');
      if (slash != std::string::npos)
        safeName = safeName.substr(slash + 1);
      tempCleanup.symlinkPath = tempCleanup.tmpDirPath + "/" + safeName;
      if (symlink(filePath.c_str(), tempCleanup.symlinkPath.c_str()) != 0)
        tempCleanup.symlinkPath.clear();
      else
        targetPath = tempCleanup.symlinkPath;
    }
    free(tmpDirBuf);
  }

  std::string nirjas = findNirjasBinary();
  std::string cmd = nirjas + " " + shellEscape(targetPath) + " 2>/dev/null";

  FILE* pipe = popen(cmd.c_str(), "r");
  if (!pipe) return output;

  std::string jsonStr;
  char buf[4096];
  while (fgets(buf, sizeof(buf), pipe))
    jsonStr += buf;
  int status = pclose(pipe);

  if (!WIFEXITED(status) || WEXITSTATUS(status) != 0)
    return output;

  Json::CharReaderBuilder readerBuilder;
  Json::Value root;
  std::string errs;
  std::istringstream iss(jsonStr);

  if (!Json::parseFromStream(readerBuilder, iss, &root, &errs))
    return output;

  output.filename           = root.get("metadata", Json::Value()).get("filename", "").asString();
  output.lang               = root.get("metadata", Json::Value()).get("lang", "").asString();
  output.totalLines         = root.get("metadata", Json::Value()).get("total_lines", 0).asInt();
  output.totalLinesOfComments = root.get("metadata", Json::Value()).get("total_lines_of_comments", 0).asInt();
  output.blankLines         = root.get("metadata", Json::Value()).get("blank_lines", 0).asInt();
  output.sloc               = root.get("metadata", Json::Value()).get("sloc", 0).asInt();

  output.singleLineComments     = parseCommentArray(root["single_line_comment"]);
  output.contSingleLineComments = parseCommentArray(root["cont_single_line_comment"]);
  output.multiLineComments      = parseCommentArray(root["multi_line_comment"]);

  return output;
}
