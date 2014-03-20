#!/bin/bash
#
# Report initial settings, make changes, report final settings !
#
echo Current Settings are:
sysctl kernel.shmmax
sysctl kernel.shmall
page_size=`getconf PAGE_SIZE`
phys_pages=`getconf _PHYS_PAGES`
shmall=`expr $phys_pages / 2`
shmmax=`expr $shmall \* $page_size`
# echo kernel.shmmax = $shmmax
# echo kernel.shmall = $shmall
echo New Settings are:
sysctl -w kernel.shmmax=$shmmax
sysctl -w kernel.shmall=$shmall
