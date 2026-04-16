#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

generate_version_php()

install(DIRECTORY ./
    DESTINATION ${FO_MODDIR}/${PROJECT_NAME}/agent
    COMPONENT reuser
    FILES_MATCHING
    PATTERN *.php
    PATTERN version.php EXCLUDE
)

add_executable(reuser_agent reuser_agent.cpp)

target_link_libraries(reuser_agent PRIVATE pqxx pq)

# target_link_libraries(reuser_agent fossology_common) # Uncomment when needed

install(TARGETS reuser_agent DESTINATION bin)

add_symlink(${PROJECT_NAME} ${FO_LIBEXECDIR}/fo_wrapper ${FO_MODDIR}/${PROJECT_NAME}/agent)
