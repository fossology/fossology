#!/bin/bash

set -e 

sudo apt-get install software-properties-common
sudo add-apt-repository ppa:deadsnakes/ppa
sudo apt-get update
sudo apt-get install python>=3.6

curl https://bootstrap.pypa.io/get-pip.py -o get-pip.py
python3 get-pip.py

pip install -i https://test.pypi.org/simple/ atarashi