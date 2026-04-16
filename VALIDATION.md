# Validation Steps for C++ Reuser Agent

This document shows how to verify the build and execution of the C++ reuser agent on your terminal. You can use these steps and screenshots as proof in your PR.

## 1. Build the Agent
```sh
cd build
cmake --build . --target reuser_agent
```
Expected output: Build completes without errors.

## 2. Run the Agent Binary
```sh
./build/src/reuser/agent/reuser_agent 1
```
Expected output:
```
Reuser agent (C++) starting...
```

## 3. (Optional) Run the Test Script
```sh
bash docker/reuser_agent_e2e_test.sh
```
Expected output (if DB credentials are missing):
```
[INFO] Reuser agent (C++) starting...
[ERROR] DB error: ...
```

## 4. Note for Reviewers
- Database integration and output validation require DB credentials or admin rights.
- Please see README for setup and further testing instructions.

---

Take screenshots of your terminal showing these steps and outputs for your PR.
