# Email Notification Setup (Modern Debian/Ubuntu)

## Overview
FOSSology supports sending email notifications (e.g., job completion alerts) using a system mail client.

Note: The previously documented `heirloom-mailx` package is deprecated and no longer available on modern Debian/Ubuntu systems (e.g., Ubuntu 22.04+, WSL environments).

---

## Supported Alternatives

### Option 1: Install bsd-mailx
sudo apt update
sudo apt install bsd-mailx

### Option 2: Install mailutils
sudo apt update
sudo apt install mailutils

---

## Configuration Notes

- Both `bsd-mailx` and `mailutils` can be used as replacements for sending emails.
- You may need to configure an SMTP server depending on your setup.

### Example (mailutils SMTP configuration)

Edit or create:
~/.mailrc

Add:
set smtp=smtp://your-smtp-server:port
set smtp-auth=login
set smtp-auth-user=your-email@example.com
set smtp-auth-password=your-password
set from="your-email@example.com"

---

## Important Differences

- `mailutils` may require more configuration compared to `heirloom-mailx`.
- Behavior depends on your system's mail setup.

---

## Recommendation

- Use `bsd-mailx` for simple setups
- Use `mailutils` for advanced SMTP configuration

---

## Summary

 Package          Status       Recommended 
|--------------|--------------|-------------|
 heirloom-mailx   Deprecated    No          
 bsd-mailx        Supported     Yes         
 mailutils        Supported     Yes         
