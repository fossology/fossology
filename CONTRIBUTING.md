<!-- SPDX-FileCopyrightText: © Fossology contributors

     SPDX-License-Identifier: GPL-2.0-only
-->
# Contributing

We welcome contributions in several forms, e.g.

- Improve end user documenting on the [Wiki](https://github.com/fossology/fossology/wiki)

- Testing

  - e.g. by using an instant version of FOSSology with [vagrant](https://github.com/fossology/fossology/wiki/vagrant)

  - Write unit tests and learn how the code works

- Verify available [patches (pull requests)](https://github.com/fossology/fossology/pulls)

- Working on [issues](https://github.com/fossology/fossology/issues)

  - Fix a bug
  - Add a new feature

- etc.

## Reporting Bugs

FOSSology uses GitHub's issue tracker. All bugs and enhancements should be
entered so that we don't lose track of them, can prioritize, assign, and so code
fixes can refer to the bug number in its check-in comments.

The issue usually contains much more detail (including test cases) than can be
reasonably put in check-in comments, so being able to correlate the two is
important.

Consider the usual best practice for writing issues, among them:

- More verbosity rather than one liners
- Screenshots are a great help
- Providing example files (in case for example scanning crashes)
- Please determine the version, better the commit id
- Details on the operating system you are using

## Code Guidelines

follow the [Coding Style](https://github.com/fossology/fossology/wiki/Coding-Style)

## Git Guidelines

Not familiar with git, see [Git basic commands](https://github.com/fossology/fossology/wiki/Git-basic-commands)

### Workflow

We are using the [Feature Branch Workflow (also known as GitHub Flow)](https://guides.github.com/introduction/flow/),
and prefer delivery as pull requests.

Our first line of defense is the [Travis CI](https://travis-ci.org/fossology/fossology/) build defined within [.travis.yml](https://github.com/fossology/fossology/blob/master/.travis.yml) and triggered for every pull request.

Create a feature branch:

```sh
git checkout -B feat/tune-vagrant-vm
```

### Git Commit

The cardinal rule for creating good commits is to ensure there is only one
"logical change" per commit. Why is this an important rule?

- The smaller the amount of code being changed, the quicker & easier it is to
  review & identify potential flaws.

- If a change is found to be flawed later, it may be necessary to revert the
  broken commit. This is much easier to do if there are no other unrelated
  code changes entangled with the original commit.

- When troubleshooting problems using Git's bisect capability, small well
  -defined changes will aid in isolating exactly where the code problem was
  introduced.

- When browsing history using Git annotate/blame, small well-defined changes
  also aid in isolating exactly where & why a piece of code came from.

Things to avoid when creating commits

- Mixing whitespace changes with functional code changes.
- Mixing two unrelated functional changes.
- Sending large new features in a single giant commit.

### Git Commit Conventions

We use git commit as per [Conventional Changelog](https://github.com/ajoslin/conventional-changelog):

```none
<type>(<scope>): <subject>
```

Example:

```none
feat(vagrant): increase upload size
```

Allowed types:

- **feat**: A new feature
- **fix**: A bug fix
- **docs**: Documentation only changes
- **style**: Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, newline, line endings, etc)
- **refactor**: A code change that neither fixes a bug or adds a feature
- **perf**: A code change that improves performance
- **test**: Adding missing tests
- **chore**: Changes to the build process or auxiliary tools and libraries such as documentation generation

You can add additional details after a new line to describe the change in detail or automatically close an issue on Github.

```none
feat(CONTRIBUTING.md): create initial CONTRIBUTING.md

makes the following wiki Page obsolete:
- https://github.com/fossology/fossology/wiki/Reporting-bugs

This closes #22
```

> **NOTE:** [CHANGELOG.md](CHANGELOG.md) is generated based on the commits.

### Developer Certificate of Origin (DCO)

All commits not submitted via GitHub pull request shall contain a
Signed-off-by line, also known as the **Developer Certificate of Origin (DCO)**
as we know it from the Linux Kernel [Documentation/SubmittingPatches](https://www.kernel.org/doc/Documentation/process/submitting-patches.rst)

```none
    Signed-off-by: Peace Fun Ingenium <peacefun.ingenium@example.com>
```

Additional tags in addition to Signed-off-by shall be used as long as it makes
sense for any commit, e.g.

```none
    Reviewed-by:
    Tested-by:
    Reviewed-by:
    Suggested-by:
    Acked-by:
    Sponsored-by:
```

## Pull requests

Pull requests with patches, improvements, new features are a great help.
Please keep them clean from unwanted commits.

Follow the steps to get your work included in the project.

1. [Fork](https://help.github.com/fork-a-repo/) the project, clone your fork,
   and add the fossology remote:

   ```bash
   # Clone your fork of the repo into the current directory
   git clone https://github.com/<your-username>/fossology.git
   # Navigate to the cloned directory
   cd fossology
   # Assign the original repo to a remote called "upstream"
   git remote add upstream https://github.com/fossology/fossology.git
   ```

2. Get the latest changes from upstream:

   ```bash
   git checkout master
   git pull upstream master
   ```

3. Create a new branch from the main master branch to contain your changes.
   Best way is to call is to follow the type described in **Git Commit Conventions**
   stated above: `<githubId>/#<issueNr>/<description/scope/topic>`

   ```bash
   git checkout -b <topic-branch-name>
   ```

   Example:

   ```bash
   git checkout -b john/138/buckets-undefined-index
   ```

   Or

   ```bash
   git checkout -b john/fix/138
   ```

4) It's coding time!
   Please respect the coding convention: [Coding guidelines](https://github.com/fossology/fossology/wiki/Coding-Style)

   Commit your changes in logical chunks. Please adhere to **Git Commit Conventions**
   and [Coding guidelines](https://github.com/fossology/fossology/wiki/Coding-Style)
   or your code is unlikely to be merged into the main project.
   Use Git's [interactive rebase](https://www.atlassian.com/git/tutorials/rewriting-history/git-rebase)
   feature to tidy up your commits before making them public.

5) Locally rebase the upstream master branch into your topic branch:

   ```bash
   git pull --rebase upstream master
   ```

6) Push your topic branch up to your fork:

   ```bash
   git push origin <topic-branch-name>
   ```

7) [Open a Pull Request](https://help.github.com/articles/using-pull-requests/)
   with a clear title and description against the `master` branch.

## IMPORTANT

The FOSSology project does not require you to assign the copyright of your
contributions, you retain the copyright. The FOSSology project **does** require
that you make your contributions available under the
[GNU General Public License as published by the Free Software Foundation, version 2](LICENSE),
in order to be accepted as a contribution in the main repo.

If appropriate, include the [GPLv2 license header](https://github.com/fossology/fossology/wiki/Coding-Style#default-license-and-file-headers)
at the top of each file along with the copyright info. If you are adding a new
file that you wrote, include your name in the copyright notice in the license
summary.
