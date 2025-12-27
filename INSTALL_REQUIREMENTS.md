<!-- SPDX-FileCopyrightText: © Fossology contributors

     SPDX-License-Identifier: GPL-2.0-only
-->

# FOSSology Installation Requirements

This document lists the **required** system configuration for FOSSology to function properly. These are not optional tuning recommendations - they must be completed as part of the installation process.

## ⚠️ Critical: PHP Configuration (Required)

**Without proper PHP configuration, FOSSology will not be able to upload or process packages.**

### Why This Is Required

By default, PHP sets very conservative limits for file uploads and memory usage:
- `upload_max_filesize`: 2M (default)
- `post_max_size`: 8M (default)  
- `memory_limit`: 128M (default)

These values are **insufficient** for FOSSology, which needs to handle software packages that can be hundreds of megabytes in size. Users who skip this step will encounter:
- Upload failures for packages larger than 2MB
- Memory exhaustion errors during analysis
- Incomplete scan results

### Required PHP Settings

FOSSology requires the following minimum PHP configuration:

```ini
memory_limit = 702M
post_max_size = 701M
upload_max_filesize = 700M
max_execution_time = 300
```

### How to Configure PHP

Choose **one** of the following methods:

#### Option A: Automated Configuration (Recommended)

Run the provided configuration script:

```bash
sudo /path/to/fossology/install/scripts/php-conf-fix.sh --overwrite
sudo systemctl restart apache2  # or: sudo service apache2 restart
```

#### Option B: Manual Configuration

1. Locate your `php.ini` file:
   - **Debian/Ubuntu**: `/etc/php/[version]/apache2/php.ini`
   - **Red Hat/CentOS/Fedora**: `/etc/php.ini`
   - To find it: `php --ini | grep "Loaded Configuration File"`

2. Edit the file and update these settings:

```ini
memory_limit = 702M
post_max_size = 701M
upload_max_filesize = 700M
max_execution_time = 300
```

3. Restart your web server:

```bash
# For Apache on Debian/Ubuntu
sudo systemctl restart apache2

# For Apache on Red Hat/CentOS/Fedora
sudo systemctl restart httpd

# For Nginx
sudo systemctl restart nginx
sudo systemctl restart php-fpm
```

### Verification

After configuration, verify the settings are applied:

```bash
# Run the verification script (no sudo required)
/path/to/fossology/install/scripts/check-fossology-config.sh

# Or check manually
php -i | grep -E 'memory_limit|post_max_size|upload_max_filesize'
```

You should see output similar to:

```
memory_limit => 702M => 702M
post_max_size => 701M
upload_max_filesize => 700M
```

## Database Requirements

FOSSology requires **PostgreSQL** as its database backend.

- **Minimum version**: PostgreSQL 9.6+
- **Recommended version**: PostgreSQL 11+ or later
- Other databases (MySQL, MariaDB) are **not supported**

## Web Server Requirements

FOSSology requires a web server with PHP support:

- **Apache HTTP Server**: 2.4+ (recommended)
- **Nginx**: Supported but requires additional PHP-FPM configuration

## System Dependencies

Install required system packages using the FOSSology dependency installer:

```bash
sudo utils/fo-installdeps
```

This script will install all necessary system dependencies for your Linux distribution.

## Python Dependencies

Some FOSSology agents require Python packages:

```bash
sudo install/fo-install-pythondeps
```

## Troubleshooting

### Upload Failures

**Symptom**: Uploads fail or are rejected, with errors like "File too large" or "Post data exceeds limit"

**Solution**: Verify PHP settings using the verification script:
```bash
sudo install/scripts/check-fossology-config.sh
```

If settings are incorrect, run:
```bash
sudo install/scripts/php-conf-fix.sh --overwrite
sudo systemctl restart apache2
```

### Memory Errors During Scanning

**Symptom**: Scans fail with "Allowed memory size exhausted" errors

**Solution**: Increase `memory_limit` in php.ini (already covered by php-conf-fix.sh)

### Configuration Not Applied

**Symptom**: Settings appear correct in CLI but uploads still fail

**Cause**: You may have edited the CLI php.ini instead of the Apache/web server php.ini

**Solution**: 
1. Check which php.ini Apache is using: `php --ini`
2. Edit the correct file (usually in `/etc/php/[version]/apache2/php.ini`)
3. Restart Apache

## Additional Information

For optional performance tuning and advanced configuration options, see:
- [Configuration and Tuning Guide](https://github.com/fossology/fossology/wiki/Configuration-and-Tuning)

For the complete installation guide, see:
- [Install from Source](https://github.com/fossology/fossology/wiki/Install-from-Source)
