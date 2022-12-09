#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

add_library(fodbreposysconf 
    EXCLUDE_FROM_ALL ${CMAKE_CURRENT_SOURCE_DIR}/libfodbreposysconf.c)
target_compile_definitions(fodbreposysconf 
    PRIVATE TESTDBDIR="${FO_TESTDIR}/db" _FILE_OFFSET_BITS=64)
target_include_directories(fodbreposysconf 
    PRIVATE ${FO_CLIB_SRC} ${glib_INCLUDE_DIRS} ${PostgreSQL_INCLUDE_DIRS})
