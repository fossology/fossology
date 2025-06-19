# SPDX-FileCopyrightText: © 2022 Siemens AG
# SPDX-License-Identifier: GPL-2.0-only AND LGPL-2.1-only

cmake_minimum_required(VERSION 3.5)

set(AGENT_NAME "textphrase")
set(AGENT_VERSION "1.0.0")

# Agent source files
set(AGENT_SOURCES
  agent/textphrase.php
  agent/textphrase_agent.php
  agent/TextPhraseDecider.php
)

# UI source files
set(UI_SOURCES
  ui/TextPhrasePlugin.php
  ui/TextPhraseAgentPlugin.php
  ui/plugin.php
  ui/templates/list.html.twig
  ui/templates/add.html.twig
  ui/templates/bulk_import.html.twig
  ui/templates/upload_options.html.twig
  ui/templates/edit.html.twig
)

# Install agent files
install(FILES ${AGENT_SOURCES}
  DESTINATION ${INSTALL_PREFIX}/lib/fossology/agents/${AGENT_NAME}
)

# Install UI files
install(FILES ${UI_SOURCES}
  DESTINATION ${INSTALL_PREFIX}/lib/fossology/ui/${AGENT_NAME}
)

# Install configuration
install(FILES textphrase.conf
  DESTINATION ${INSTALL_PREFIX}/etc/fossology/agents
)

# Install schema
install(FILES agent/schema.sql
  DESTINATION ${INSTALL_PREFIX}/lib/fossology/agents/${AGENT_NAME}
) 