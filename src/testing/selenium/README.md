## E2E Selenium Test-Suite

#### Container Setup
1. `npm install`
2. `npm start`

#### Local Setup
1. `npm install`
2. `node test/helper/generate_dummy_files.js`
3. `SELENIUM_ENV=http://ip:port/wd/hub FOSSOLOGY_ENV=http://ip:port/repo/ npm test`