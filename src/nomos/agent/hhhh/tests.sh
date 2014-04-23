#!/bin/bash
for file in $(find . -type f)
  do
#echo "$file"
  ./nomossa "$file"
  done

