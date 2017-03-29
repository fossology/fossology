#!/bin/bash
set -e
curl https://raw.githubusercontent.com/creationix/nvm/v0.33.1/install.sh | bash
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm
nvm install v6
cd src/testing/selenium/
npm install
FOSSOLOGY_ENV=http://172.18.0.22/repo/ SELENIUM_ENV=http://172.18.0.23:4444/wd/hub/ FOSSOLOGY_TEST_FOLDER=/home/TestData/ npm start
