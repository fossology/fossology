cmake_minimum_required(VERSION 3.10)
project(mlscan)

# SPDX-FileCopyrightText: © Fossology contributors
# SPDX-License-Identifier: GPL-2.0-only

# Find required packages
find_package(PkgConfig REQUIRED)
pkg_check_modules(JSONCPP REQUIRED jsoncpp)

# Include directories
include_directories(
    ${CMAKE_CURRENT_SOURCE_DIR}
    ${JSONCPP_INCLUDE_DIRS}
)

# Source files
set(MLSCAN_SOURCES
    agent/mlscan.cc
    agent/mlscan_wrapper.cc
    agent/mlscan_dbhandler.cc
    agent/mlscan_state.cc
)

# Create mlscan executable
add_executable(mlscan ${MLSCAN_SOURCES})

# Link libraries
target_link_libraries(mlscan
    fossology
    ${JSONCPP_LIBRARIES}
)

# Install targets
install(TARGETS mlscan
    RUNTIME DESTINATION ${FO_MODDIR}/mlscan/agent
)

# Install Python ML code
install(DIRECTORY agent/ml/
    DESTINATION ${FO_MODDIR}/mlscan/agent/ml
    FILES_MATCHING PATTERN "*.py"
)

# Install data files
install(DIRECTORY data/
    DESTINATION ${FO_MODDIR}/mlscan/data
    FILES_MATCHING 
    PATTERN "*.json"
    PATTERN "*.csv"
    PATTERN "*.pkl"
)

# Install configuration
install(FILES mlscan.conf
    DESTINATION ${FO_SYSCONFDIR}/mods-enabled
)
