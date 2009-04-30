#!/bin/sh
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
## with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
# Sam All: Do a full SAM comparison
# - Unpack files
# - Generate a cache of the submitted information.
# - Perform comparison
# - Display results to stdout
# - Clean up!
#
# Parameters:
#   $1=source directory
#   $2=target directory
# Options (before $1):
#   "-m #" specify number of processors to use (default: -m 1)

#set -x

export PATH=/bin:/usr/bin:/sbin:/usr/sbin:/usr/local/bin:/usr/local/sbin

UNUNPACKFLAG=1
MAXPROC=1
NO_RM=0
if [ "$1" == "-m" ] ; then
  shift
  MAXPROC=$1
  shift
fi
if [ "$1" == "-R" ] ; then
  shift
  NO_RM=1
fi

export DIR1="$1"
export DIR2="$2"
export PID="$$"
export LOG="Log.$PID"
export MERGE2="merge.$PID"

if [ ! -e "$DIR1" ] || [ ! -e "$DIR2" ] || [ "$DIR2" == "" ] ; then
  echo "Usage: $0 [-m cpus] source target"
  echo " -m cpus :: specify number of CPUs (default: -m 1)"
  echo " source :: source file or directory for comparison (what to compare)"
  echo " target :: target file or directory for comparison (what to compare against)"
  exit 1
fi

# Configurables
#export BINDIR="/home/osrb/bin"
export BINDIR="/home/nealk/work"

# Tools
export UNUNPACK="/usr/local/bin/ununpack"
export CHECKSUM="/usr/local/bin/checksum"
export LISTDIFF="$BINDIR/listdiff/listdiff"
export SPAWNER="/usr/local/bin/spawner"

# Filters
export SAMDIR="$BINDIR/bsam"
export SAMEXEC="$SAMDIR/bsam"
export SAMOPT="-M 40"
export SAM2BSAM="$SAMDIR/sam2bsam"
export SAMMERGE="$SAMDIR/bsammerge"
export SAM_FILTER_C="$SAMDIR/filter_C"
export SAM_FILTER_J="$SAMDIR/filter_Java"
export SAM_FILTER_O="$SAMDIR/filter_objdump_exec"
export SAM_FILTER_JC="$SAMDIR/filter_class"
export SEESAM="$SAMDIR/SeeSam"
export SEESAMFILES="$SAMDIR/SeeSam -t F -T 1000 -F 10"

# Sources
export HERE=`pwd`
export EXTRACT1="$HERE/Extract1.dir.$PID"
export EXTRACT2="$HERE/Extract2.dir.$PID"
export SAM1_DIR="$HERE/SAM1.dir.$PID"
export SAM2_DIR="$HERE/SAM2.dir.$PID"

###################################################################
### Extract files
if [ "$UNUNPACKFLAG" != "0" ] ; then
  echo '* Unpacking source'
  (
  $UNUNPACK -R -C -d "${EXTRACT1}" "${DIR1}"
  chmod -R 770 "${EXTRACT1}"
  ) > /dev/null 2>&1
  echo '* Unpacking comparison'
  (
  $UNUNPACK -R -C -d "${EXTRACT2}" "${DIR2}"
  chmod -R 770 "${EXTRACT2}"
  ) > /dev/null 2>&1
else
  EXTRACT1="$DIR1"
  EXTRACT2="$DIR2"
fi

###################################################################
# Make directories for storing cache files
(
echo '* Making storage directories for source cache files'
cd "$EXTRACT1"
find . -type d | sort | while read Directory ; do
  # echo "Mkdir $Directory" >&2
  mkdir -p "$SAM1_DIR/$Directory"
done
)

(
echo '* Making storage directories for comparison cache files'
cd "$EXTRACT2"
find . -type d | sort | while read Directory ; do
  # echo "Mkdir $Directory" >&2
  mkdir -p "$SAM2_DIR/$Directory"
done
)

###################################################################
# Create checksums
###################################################################
echo '* Generating checksum files'

if [ ! -f "${EXTRACT1}/Checksums.cache" ] ; then
  echo "* Extracting source checksums"
  $CHECKSUM ${EXTRACT1} | sed -e "s@ ${EXTRACT1}/@ @" | grep -v Checksums.cache | sort > "${EXTRACT1}/Checksums.cache"
fi

if [ ! -f "${EXTRACT2}/Checksums.cache" ] ; then
  echo "* Extracting comparison checksums"
  $CHECKSUM ${EXTRACT2} | sed -e "s@ ${EXTRACT2}/@ @" | grep -v Checksums.cache | sort > "${EXTRACT2}/Checksums.cache"
fi


###################################################################
# cache data
(
echo '* Generating source cache files'
cd "$EXTRACT1"

( # begin spawner
# only cache files that are different
$LISTDIFF -1 -C "${EXTRACT1}/Checksums.cache" -C "${EXTRACT2}/Checksums.cache" \
| while read Same i ; do
  case "$i" in
  # C
  (*.c)           echo "$SAM_FILTER_C '$i' > '${SAM1_DIR}/$i'" ;;
  (*.cc)          echo "$SAM_FILTER_C '$i' > '${SAM1_DIR}/$i'" ;;
  (*.c[px+][px+]) echo "$SAM_FILTER_C '$i' > '${SAM1_DIR}/$i'" ;;
  # Java
  (*.java)  echo "$SAM_FILTER_J '$i' > '${SAM1_DIR}/$i'" ;;
  # Java class
  (*.class) echo "$SAM_FILTER_JC '$i' | '$SAM2BSAM' Class > '${SAM1_DIR}/$i'" ;;
  # Objects
  (*.o)   echo "$SAM_FILTER_O '$i' > '${SAM1_DIR}/$i'" ;;
  (*.ko)  echo "$SAM_FILTER_O '$i' > '${SAM1_DIR}/$i'" ;;
  (*.exe) echo "$SAM_FILTER_O '$i' > '${SAM1_DIR}/$i'" ;;
  (*.dll) echo "$SAM_FILTER_O '$i' > '${SAM1_DIR}/$i'" ;;
  (*.sys) echo "$SAM_FILTER_O '$i' > '${SAM1_DIR}/$i'" ;;
  # Everything else
  (*)   if [ -x "$i" ] ; then
	  echo "$SAM_FILTER_O' '$i' > '${SAM1_DIR}/$i'"
	fi
	;;
  esac
done
) | $SPAWNER -m "$MAXPROC" # spawn the work
) 2>/dev/null # end cache directory

(
echo '* Generating comparison cache files'
cd "$EXTRACT2"

# Generate a cache for every file
( # begin spawner
find . -type f | while read i ; do
  case "$i" in
  # C
  (*.c)           echo "$SAM_FILTER_C '$i' > '${SAM2_DIR}/$i'" ;;
  (*.cc)          echo "$SAM_FILTER_C '$i' > '${SAM2_DIR}/$i'" ;;
  (*.c[px+][px+]) echo "$SAM_FILTER_C '$i' > '${SAM2_DIR}/$i'" ;;
  # Java
  (*.java)  echo "$SAM_FILTER_J '$i' > '${SAM2_DIR}/$i'" ;;
  # Java class
  (*.class) echo "$SAM_FILTER_JC '$i' | '$SAM2BSAM' Class > '${SAM2_DIR}/$i'" ;;
  # Objects
  (*.o)   echo "$SAM_FILTER_O '$i' > '${SAM2_DIR}/$i'" ;;
  (*.ko)  echo "$SAM_FILTER_O '$i' > '${SAM2_DIR}/$i'" ;;
  (*.exe) echo "$SAM_FILTER_O '$i' > '${SAM2_DIR}/$i'" ;;
  (*.dll) echo "$SAM_FILTER_O '$i' > '${SAM2_DIR}/$i'" ;;
  (*.sys) echo "$SAM_FILTER_O '$i' > '${SAM2_DIR}/$i'" ;;
  # Everything else
  (*)   if [ -x "$i" ] ; then
	  echo "$SAM_FILTER_O' '$i' > '${SAM2_DIR}/$i'"
	fi
	;;
  esac
done
) | $SPAWNER -m "$MAXPROC" # spawn the work
) 2>/dev/null # end cache directory

##################################################################
# Some files can be invalid.  Remove all zero-length cache files.
# Basically, if the file size is <= 14 then it is too small.
##################################################################
find "$SAM1_DIR" "$SAM2_DIR" -type f -exec ls -l --full-time "{}" \; \
  | while read A A A A Size A A A Filename ; do
  if [ "$Size" -le 14 ] ; then
    rm "$Filename"
  fi
done

##################################################################
# Create single cache file
##################################################################
echo '* Merging cache files'
(
cd "$SAM2_DIR"
find . -type f | sed -e 's@^./@@' | "$SAMMERGE" -
) > "$MERGE2"

##################################################################
# Do Comparisons
##################################################################
echo '* Comparing files'
(
# Process largest first -- this optimizes spawning
find "$SAM1_DIR" -type f -printf "%s %p\n" | sort -rn | while read s i ; do
  echo "$SAMEXEC $SAMOPT '$i' '$MERGE2' | sed -e 's@$SAM1_DIR/@Source:@'"
done | $SPAWNER -m "$MAXPROC" > "$LOG"
)

##################################################################
# Check results
##################################################################

# List all same files
echo ""
echo "=== Files that are the same ==="
export Counter=1
$LISTDIFF -s -C "${EXTRACT1}/Checksums.cache" -C "${EXTRACT2}/Checksums.cache" | while read Same File1 File2 ; do
  echo "$Counter:"
  echo "  Source:  $File1"
  echo "  Compare: $File2"
  ((Counter=$Counter+1))
done

# List extreme matches by file
echo ""
echo "=== Extreme file-match results ==="
$SEESAMFILES "$LOG"

# List specific function matches
echo ""
echo "=== Detailed function-match results ==="
$SEESAM "$LOG"

##################################################################
# Cleanup stage
##################################################################

### Mega cleanup
# Ensure I can delete
if [ $NO_RM == "0" ] ; then
  rm -rf "$SAM1_DIR" "$SAM2_DIR" "$DIR2.$PID" "$LOG" "$MERGE2"
  if [ "$UNUNPACKFLAG" != "0" ] ; then
    rm -rf "$EXTRACT1" "$EXTRACT2"
  fi
fi
