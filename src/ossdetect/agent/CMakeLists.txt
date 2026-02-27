#[========================================================================[
# SPDX-License-Identifier: GPL-2.0-only
# SPDX-FileCopyrightText: © Fossology contributors
#]========================================================================]

cmake_minimum_required(VERSION 3.18 FATAL_ERROR)
project(ossdetect-agent LANGUAGES CXX)

set(FO_CWD ${CMAKE_CURRENT_SOURCE_DIR})

# Source files
set(SOURCES
    ossdetect.cc
    ossdetect_dbhandler.cc
)

# Find required packages
find_package(PkgConfig REQUIRED)
pkg_check_modules(JSONCPP REQUIRED jsoncpp)

# Include directories
include_directories(
    ${FO_CWD}
    ${JSONCPP_INCLUDE_DIRS}
    ${FO_CLIB_SRC}
    ${FO_CXXLIB_SRC}
    ${PostgreSQL_INCLUDE_DIRS}
)

# Build executable
add_executable(ossdetect ${SOURCES})

# Link libraries
target_link_libraries(ossdetect
    PRIVATE fossologyCPP
    ${JSONCPP_LIBRARIES}
)

# Installation
install(TARGETS ossdetect
    RUNTIME DESTINATION ${FO_MODDIR}/ossdetect/agent
    PERMISSIONS OWNER_EXECUTE OWNER_WRITE OWNER_READ
                GROUP_EXECUTE GROUP_READ
                WORLD_EXECUTE WORLD_READ
)

# Install Python scripts
install(FILES
    metadata_parser.py
    similarity_matcher.py
    DESTINATION ${FO_MODDIR}/ossdetect/agent
    PERMISSIONS OWNER_EXECUTE OWNER_WRITE OWNER_READ
                GROUP_EXECUTE GROUP_READ
                WORLD_EXECUTE WORLD_READ
)
