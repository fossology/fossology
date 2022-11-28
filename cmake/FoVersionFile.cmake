#[=======================================================================[
SPDX-License-Identifier: GPL-2.0-only
SPDX-FileCopyrightText: © 2021 Avinal Kumar <avinal.xlvii@gmail.com>
#]=======================================================================]

#[[ template file for generating various files at build and install times
    @param input file's directory
    @param input file name
    @param output file's directory
    @param output file name
]]
configure_file(
    "${INPUT_FILE_DIR}/${IN_FILE_NAME}"
    "${OUTPUT_FILE_DIR}/${OUT_FILE_NAME}"
    NEWLINE_STYLE LF
    @ONLY)
