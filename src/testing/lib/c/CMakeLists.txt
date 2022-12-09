#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} -fPIC")
add_library(focunit EXCLUDE_FROM_ALL ${CMAKE_CURRENT_SOURCE_DIR}/libfocunit.c)
target_compile_definitions(focunit PRIVATE _FILE_OFFSET_BITS=64)
