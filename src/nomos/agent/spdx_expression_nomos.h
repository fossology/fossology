/*
 SPDX-FileCopyrightText: (C) 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef SPDX_EXPRESSION_NOMOS_H_
#define SPDX_EXPRESSION_NOMOS_H_

/**
 * Extract valid complex SPDX expressions from SPDX-License-Identifier style
 * declarations and mask accepted expression ranges in the supplied working
 * buffer. The original file buffer must be passed separately so candidate
 * ranges are based on unmodified text.
 *
 * \param originalFileText Original file content
 * \param workingFileText  Mutable copy used by normal Nomos scanning
 * \param size             Buffer size
 * \return Number of accepted complex SPDX expressions
 */
int extractSpdxExpressionFindings(char* originalFileText, char* workingFileText,
    int size);

#endif /* SPDX_EXPRESSION_NOMOS_H_ */
