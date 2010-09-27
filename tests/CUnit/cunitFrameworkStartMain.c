/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * cunitFrameworkStartMain.c
 * \brief cunit test framework start
 *
 *  Created on: Aug 9, 2010
 *      Author: larry
 * @version "$Id: cunitFrameworkStartMain.c 3368 2010-08-10 03:08:09Z larry $"
 */

#include <stdio.h>

extern int CunitTestFramework(char RunMode, char *ProductList, int NeedCoverage, char *ResultDestination);
int main(int argc, char *argv[])
{
  char RunMode = 'a';
  char *ProductList = "";
  int NeedCoverage = 0;
  char *ResultDestination = "./Fosslogy_C_Test-Results.xml";
  CunitTestFramework(RunMode, ProductList, NeedCoverage, ResultDestination);
  return 0;
}
