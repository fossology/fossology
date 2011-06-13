#!/bin/bash

# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
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
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#

# "$Id$"

# run and process the unit test results
#

cd fossology/tests
echo "we are at:";pwd

./checkTestData.php
if [[ $? -ne 0 ]]
then
  echo "FATAL! $0 could not check or downlown load test data, stopping tests"
  exit 1
fi

//echo "workspace is:$WORKSPACE"
cd "$WORKSPACE/fossology/agents"
echo -n  "Where are at:"
pwd

echo "starting runUnit"
xml2html=$WORKSPACE"/fossology/tests/Reports/hudson/xml2Junit.php"
unitList='copyright ununpack'

for unit in $unitList
do
    echo "unit is:$unit"
    cd $unit
    echo -n  "Where are at:"
    pwd

    make test 
    if [[ $? -ne 0 ]]
    then
      echo "The unit tests did not make"
    fi
    cd tests
    echo -n  "we are at:"
    pwd
    
    #remove previous results ?? really? doesn't this remove current results?
    rm $unit"_Tests-Results.xml"
    rm $unit"_Tests-Listing.xml"
    
    sed '1,$s/href\="/href\="http:\/\/fonightly.usa.hp.com\/~fosstester\/dtds\//' *-Results.xml > uRTmp.xml
    sed '1,$s/href\="/href\="http:\/\/fonightly.usa.hp.com\/~fosstester\/dtds\//' *-Listing.xml > uLTmp.xml

    sed '1,$s/CUnit-Run.dtd/http:\/\/fonightly.usa.hp.com\/~fosstester\/dtds\/CUnit-Run.dtd/' uRTmp.xml > uRBoth.xml
    sed '1,$s/CUnit-List.dtd/http:\/\/fonightly.usa.hp.com\/~fosstester\/dtds\/CUnit-List.dtd/' uLTmp.xml > uLBoth.xml
    
    
    ## experiments that sorta or didn't work.
    #sed '1,$s/href\="/href\="\//' *-Results.xml > uRTmp.xml
    #sed '1,$s/href\="/href\="\//' *-Listing.xml > uLTmp.xml

    #sed '1,$s/CUnit-Run.dtd/http:\/\/fonightly.usa.hp.com\/home\/fosstester\/public_html\/dtds\/CUnit-Run.dtd/' uRTmp.xml > uRBoth.xml
    #sed '1,$s/CUnit-List.dtd/http:\/\/fonightly.usa.hp.com\/home\/fosstester\/public_html\/dtds\/CUnit-List.dtd/' uLTmp.xml > uLBoth.xml
    
    echo -n "we are at:"
    pwd

    mv uRBoth.xml $unit"_Tests-Results.xml"
    mv uLBoth.xml $unit"_Tests-Listing.xml"
    rm uRTmp.xml uLTmp.xml
    
    $failCount=0
# check the xml files for test failures    
 
# process Run results into html 
    inFile=$unit"_Tests-Results.xml"
    outFile=$WORKSPACE"/fossology/tests/Reports/"$unit"_Tests-Results.html"
    echo "outfile is:$outFile"
    xslFile=$WORKSPACE"/fossology/tests/Reports/CUnit-Run.xsl"
    $xml2html -f $inFile -o $outFile -x $xslFile > xmlhtml.out 2>&1
    if [[ $? -ne 0 ]]
    then
      echo "Error when processing Results file $inFile\n"
      echo "Error was:"
      cat xmlhtml.out
      rm xmlhtml.out
    fi
    
# process listings into html
    inFile=$unit"_Tests-Listing.xml"
    outFile=$WORKSPACE"/fossology/tests/Reports/"$unit"_Tests-Listing.html"
    echo "outfile is:$outFile"
    xslFile=$WORKSPACE"/fossology/tests/Reports/CUnit-List.xsl"
    $xml2html -f $inFile -o $outFile -x $xslFile > xmlhtml.out 2>&1
    if [[ $? -ne 0 ]]
    then
      echo "Error when processing Results file $inFile\n"
      echo "Error was:\n"
      cat xmlhtml.out
      rm xmlhtml.out
    fi
    
    today=`date +'%b.%d.%Y'`
    mkdir /home/jenkins/public_html/UnitTests/Results-$today
    cp $unit"_Tests-Results.xml" /home/jenkins/public_html/UnitTests/Results-$today
    cp $unit"_Tests-Listing.xml" /home/jenkins/public_html/UnitTests/Results-$today
    
    cd ../..
    echo -n "we are at:"
    pwd
done