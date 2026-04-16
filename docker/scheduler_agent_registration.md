# Scheduler Integration Example

To register the new C++ reuser agent with the Fossology scheduler, add or update the agent entry in your scheduler configuration file (e.g., `agents.conf` or similar):

```
reuser_agent=/usr/local/bin/reuser_agent
```

- Make sure the path matches the installed location of your binary.
- Restart or reload the scheduler service after making this change.

**Verification:**
- Submit a test upload via the UI or CLI.
- Monitor the scheduler logs to ensure it launches your C++ agent.
- Check the database for expected changes.
