#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only
set -e

git clone --branch release-1.1104 https://github.com/dagolden/IO-CaptureOutput.git
cd IO-CaptureOutput
perl Makefile.PL
make
sudo make install
cd ..
rm -rf IO-CaptureOutput

git clone https://github.com/dmgerman/ninka.git
cd ninka
git reset --hard 81f185261c8863c5b84344ee31192870be939faf
perl Makefile.PL
make
sudo make install
cd ..
rm -rf ninka
