#pragma once
#include <string>
#include <fstream>
#include <iostream>
#include <json/json.h>

/**
 * Reads the default agentName and similarityMethod from atarashi.conf
 * @param configDir Base config directory (e.g. /usr/local/etc/fossology)
 * @param agentName Output param: will be set from config
 * @param similarityMethod Output param: will be set from config
 * @return true if read successfully, false otherwise
 */
bool readAtarashiConfig(
    const std::string &configDir,
    std::string &agentName,
    std::string &similarityMethod
);