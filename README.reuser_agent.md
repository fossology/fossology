# C++ Reuser Agent Setup Guide

## 1. Database User and Permissions
- Ensure a PostgreSQL user exists with access to the `fossology` database.
- Example (as postgres):
  ```sh
  sudo -u postgres createuser --superuser <youruser>
  sudo -u postgres createdb -O <youruser> fossology
  ```
- Set a password if needed:
  ```sh
  sudo -u postgres psql
  ALTER USER <youruser> WITH PASSWORD 'yourpassword';
  \q
  ```

## 2. Database Schema
- Run the migration script to create required tables/columns:
  ```sh
  psql -U <youruser> -d fossology -f db/migrations/2026-04-reuser-agent.sql
  ```

## 3. Build and Install the Agent
- Build:
  ```sh
  cd build
  cmake ..
  cmake --build . --target reuser_agent
  ```
- Install:
  ```sh
  sudo cp ./src/reuser/agent/reuser_agent /usr/local/bin/reuser_agent
  sudo chmod +x /usr/local/bin/reuser_agent
  ```

## 4. Running the Agent
- Run manually:
  ```sh
  /usr/local/bin/reuser_agent <upload_id>
  ```
- Or via the scheduler (see scheduler integration docs).

## 5. Testing
- Use the provided test script:
  ```sh
  bash docker/reuser_agent_e2e_test.sh
  ```
- You can override DB parameters:
  ```sh
  DBUSER=<youruser> DB=<yourdb> bash docker/reuser_agent_e2e_test.sh
  ```

## 6. Troubleshooting
- Check logs for errors.
- Ensure all environment variables and permissions are correct.
