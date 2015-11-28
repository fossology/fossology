#!/bin/bash

git clone https://github.com/dmgerman/ninka.git
cd ninka
perl Makefile.PL
make
sudo make install
cd ..
rm -rf ninka
