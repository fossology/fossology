/*
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file
 * \brief Utilities to scan directories
 */

#include "directoryScan.hpp"

using namespace std;
namespace fs = boost::filesystem;

/**
 * Scan a given directory with OJO and print as JSON or to STDOUT based on
 * json parameter.
 * @param json Set true to print results as JSON, false to print as plain text
 * @param directoryPath Directory to be scanned.
 */
void scanDirectory(const bool json, const string &directoryPath)
{
  fs::recursive_directory_iterator dirIterator(directoryPath);
  fs::recursive_directory_iterator end;

  OjoAgent agentObj;

  vector<string> filePaths;

  for (fs::path const &p : boost::make_iterator_range(dirIterator, {}))
  {
    if (fs::is_directory(p))
    {
      // Can not do anything with a directory
      continue;
    }
    // Store the paths in a vector as of now since we can not `#pragma omp for`
    // on recursive_directory_iterator
    filePaths.push_back(p.string());
  }
  const unsigned long filePathsSize = filePaths.size();
  bool printComma = false;

  if (json)
  {
    cout << "[" << endl;
  }
#pragma omp parallel shared(printComma)
  {
#pragma omp for
    for (unsigned int i = 0; i < filePathsSize; i++)
    {
      const string fileName = filePaths[i];

      vector<ojomatch> l;
      try
      {
        l = agentObj.processFile(fileName);
      }
      catch (std::runtime_error &e)
      {
        cerr << "Unable to read " << e.what();
        continue;
      }
      pair<string, vector<ojomatch>> scanResult(fileName, l);
      if (json)
      {
        appendToJson(fileName, scanResult, printComma);
      }
      else
      {
        printResultToStdout(fileName, scanResult);
      }
    }
  }
  if (json)
  {
    cout << endl << "]" << endl;
  }
}
