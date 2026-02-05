# FOSSology Diagnosis Report

This report outlines critical issues and improvement suggestions identified during a deep analysis of the FOSSology codebase. The findings cover Security, Code Quality, and Infrastructure.

## 1. SQL Injection Vulnerabilities (Critical)

**Location:** `src/www/ui/ui-tags.php` (and potentially other legacy UI files)

**Description:**
The code constructs SQL queries using string concatenation with variables that are manually escaped using `str_replace("'", "''", ...)`. This method is error-prone and insufficient for preventing all forms of SQL injection. Furthermore, the global `$PG_CONN` and `pg_query` functions are used instead of the available `DbManager` class which supports prepared statements.

**Example (`src/www/ui/ui-tags.php`):**
```php
$tag_name = GetParm('tag_name', PARM_TEXT);
// ...
$sql = "SELECT * FROM tag WHERE tag = '$tag_name'";
$result = pg_query($PG_CONN, $sql);
```

**Recommendation:**
*   Replace all raw SQL construction with prepared statements.
*   Refactor legacy code to use `Fossology\Lib\Db\DbManager` (e.g., `getSingleRow`, `getRows`) which handles binding parameters safely.
*   Remove manual escaping functions like `str_replace` for SQL context.

## 2. Stored Cross-Site Scripting (XSS) (High)

**Location:** `src/www/ui/ui-tags.php`

**Description:**
User input (e.g., tag names) is stored in the database and subsequently output to the HTML page without proper sanitization. While some manual escaping is attempted for SQL, HTML entity encoding is missing during output, allowing malicious scripts to be executed in the browser of other users viewing the tags.

**Example (`src/www/ui/ui-tags.php`):**
```php
$tag = $row['tag']; // Retrieved from DB
// ...
$VEd .= "$text: <input type='text' ... value=\"$tag\"/> "; // Output directly
```

**Recommendation:**
*   Use `htmlspecialchars()` or a template engine that auto-escapes output when rendering variables to HTML.
*   Sanitize input using a dedicated library if rich text is required (though tag names should likely be plain text).

## 3. Buffer Overflow Risks in C Agents (High)

**Location:** `src/nomos/agent/nomos.c`, `src/nomos/agent/licenses.c`

**Description:**
The C code for the Nomos agent makes extensive use of unsafe string functions like `strcpy` and `sprintf` without bounds checking. This can lead to buffer overflows if input data (e.g., file paths, license strings) exceeds the allocated buffer size (`PATH_MAX` or fixed-size arrays).

**Example (`src/nomos/agent/nomos.c`):**
```c
char filename_buf[PATH_MAX] = {};
// ...
sprintf( filename_buf , "%s/%s",dir_name, dirent_handler->d_name);
```
If `dir_name` + `d_name` > `PATH_MAX`, this overflows.

**Recommendation:**
*   Replace `sprintf` with `snprintf`.
*   Replace `strcpy` with `strncpy` or `strlcpy` (if available).
*   Ensure destination buffers are sized correctly and check return values of string operations.

## 4. Docker Container Running as Root (Medium)

**Location:** `Dockerfile`, `docker-entrypoint.sh`

**Description:**
The Docker container runs processes (Apache, Scheduler) as the `root` user by default. This violates the principle of least privilege and increases the impact of a potential container breakout.

**Recommendation:**
*   Create a non-root user (e.g., `fossy`) in the Dockerfile.
*   Adjust permissions of necessary directories (`/var/log`, `/usr/local/etc/fossology`) so the non-root user can write to them.
*   Switch to this user using the `USER` directive in the Dockerfile or `su-exec`/`gosu` in the entrypoint script.

## 5. Recursive Forking Pattern (Low/Refactor)

**Location:** `src/nomos/agent/nomos.c` (`myFork` function)

**Description:**
The Nomos agent uses a recursive strategy to spawn worker processes. Process 1 forks Process 2, which forks Process 3, and so on.
```c
if (proc_num > 1) {
  myFork(proc_num - 1, pFile);
}
```
This creates a deep process tree. While likely functional for small counts, it can be inefficient and harder to manage than a master process spawning `N` worker children directly.

**Recommendation:**
*   Refactor `myFork` to use a loop where the parent process spawns all children.

## 6. Inconsistent Database Access Patterns (Code Quality)

**Location:** `src/www/ui/`

**Description:**
The codebase mixes modern `DbManager` usage with legacy `pg_query` and global `$PG_CONN` access. This inconsistency makes the code harder to read, maintain, and test.

**Recommendation:**
*   Standardize on using the Dependency Injection container to retrieve `db.manager`.
*   Systematically refactor older UI files to remove `global $PG_CONN`.
