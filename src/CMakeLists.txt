#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

#[[ Controls which agents are configured
    Usage: Add -DAGENTS="agent1;agent2;agent3" while configuring CMake
    Default is all agents
#]]
if(DEFINED AGENTS)
    foreach(AGENT ${AGENTS})
        string(TOLOWER "${AGENT}" AGENT)
        add_subdirectory(${AGENT})
    endforeach()
else()
    # To disable any agent comment the respective line
    add_subdirectory(lib)
    add_subdirectory(scheduler)
    add_subdirectory(adj2nest)
    add_subdirectory(buckets)
    add_subdirectory(copyright)
    add_subdirectory(cli)
    add_subdirectory(clixml)
    add_subdirectory(cyclonedx)
    add_subdirectory(decider)
    add_subdirectory(deciderjob)
    add_subdirectory(decisionexporter)
    add_subdirectory(decisionimporter)
    add_subdirectory(delagent)
    add_subdirectory(debug)
    add_subdirectory(maintagent)
    add_subdirectory(mimetype)
    add_subdirectory(pkgagent)
    add_subdirectory(ojo)
    add_subdirectory(readmeoss)
    add_subdirectory(reportImport)
    add_subdirectory(reuser)
    add_subdirectory(reso)
    add_subdirectory(scancode)
    add_subdirectory(scanoss)
    add_subdirectory(spasht)
    add_subdirectory(spdx2)
    add_subdirectory(softwareHeritage)
    add_subdirectory(nomos)
    add_subdirectory(monk)
    add_subdirectory(ununpack)
    add_subdirectory(unifiedreport)
    add_subdirectory(wget_agent)
    add_subdirectory(www)

    if(NOT OFFLINE)
        add_subdirectory(genvendor)
    endif()

# deprecated or not used agents
    # add_subdirectory(demomod)
    # add_subdirectory(example_wc_agent)
    # add_subdirectory(ninka)
    # add_subdirectory(regexscan)
endif()
