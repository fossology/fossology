#include "confighelper.hpp"
#include <sstream>
#include <fstream>
#include <string>

bool readAtarashiConfig(
  const std::string &configDir,
  std::string &agentName,
  std::string &similarityMethod
) 
{
  try {
    std::string confPath = configDir + "/mods-enabled/atarashi/agent/atarashi.conf";

    std::ifstream file(confPath);
    if (!file.is_open()) {
      std::cerr << "Warning: Could not open config file: " << confPath << "\n";
      return false;
    }

    std::ostringstream jsonContent;
    std::string line;
    while (std::getline(file, line)) {
      // Trim whitespace
      line.erase(0, line.find_first_not_of(" \t\r\n"));
      line.erase(line.find_last_not_of(" \t\r\n") + 1);

      // Skip empty lines, comments, and section headers
      if (line.empty() || line[0] == '#' || line[0] == '[') {
        continue;
      }
      jsonContent << line << "\n";
    }

    Json::Value root;
    std::istringstream jsonStream(jsonContent.str());
    jsonStream >> root;

    if (root.isMember("agentName") && root["agentName"].isString()) {
      agentName = root["agentName"].asString();
    }
    if (root.isMember("similarityMethod") && root["similarityMethod"].isString()) {
      similarityMethod = root["similarityMethod"].asString();
    }

    return true;
  }
  catch (const std::exception &e) {
      std::cerr << "Error reading atarashi.conf: " << e.what() << "\n";
      return false;
  }
}
