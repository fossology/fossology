# Contributing

We welcome contributions in several forms, e.g.

*   Improve end user documenting on the [Wiki](https://github.com/fossology/fossology/wiki)

*   Testing

    *   e.g. by using an instant version of FOSSology with [vagrant](https://github.com/fossology/fossology/wiki/vagrant)

    *   Write unit tests and learn how the code works

*   Verify available [patches (pull requests)](https://github.com/fossology/fossology/pulls)

*   Working on [issues](https://github.com/fossology/fossology/issues)

    *   Fix a bug
    *   Add a new feature

*   etc.

## Reporting Bugs

FOSSology uses GitHub's issue tracker. All bugs and enhancements should be
entered so that we don't lose track of them, can prioritize, assign, and so code
fixes can refer to the bug number in its check-in comments.

The issue usually contains much more detail (including test cases) than can be
reasonably put in check-in comments, so being able to correlate the two is
important.

Consider the usual best practice for writing issues, among them:

*   More verbosity rather than one liners
*   Screenshots are a great help
*   Providing example files (in case for example scanning crashes)
*   Please determine the version, better the commit id
*   Details on operating system you are using

## Code Guidelines

follow the [Coding Style](https://github.com/fossology/fossology/wiki/Coding-Style)

## Git Guidelines

Not familiar with git, see [Git basic commands](https://github.com/fossology/fossology/wiki/Git-basic-commands)

### Workflow

We are using the [Feature Branch Workflow (also known as GitHub Flow)](https://guides.github.com/introduction/flow/),
and prefer delivery as pull requests.

Our first line of defense is the [Travis CI](https://travis-ci.org/fossology/fossology/) build defined within [.travis.yml](.travis.yml) and triggered for every pull request.

Create a feature branch:

```sh
git checkout -B feat/tune-vagrant-vm
```

### Git Commit

The cardinal rule for creating good commits is to ensure there is only one
"logical change" per commit. Why is this an important rule?

*   The smaller the amount of code being changed, the quicker & easier it is to
    review & identify potential flaws.

*   If a change is found to be flawed later, it may be necessary to revert the
    broken commit. This is much easier to do if there are not other unrelated
    code changes entangled with the original commit.

*   When troubleshooting problems using Git's bisect capability, small well
    defined changes will aid in isolating exactly where the code problem was
    introduced.

*   When browsing history using Git annotate/blame, small well defined changes
    also aid in isolating exactly where & why a piece of code came from.

Things to avoid when creating commits

*   Mixing whitespace changes with functional code changes.
*   Mixing two unrelated functional changes.
*   Sending large new features in a single giant commit.

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

*   **feat**: A new feature
*   **fix**: A bug fix
*   **docs**: Documentation only changes
*   **style**: Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, newline, line endings, etc)
*   **refactor**: A code change that neither fixes a bug or adds a feature
*   **perf**: A code change that improves performance
*   **test**: Adding missing tests
*   **chore**: Changes to the build process or auxiliary tools and libraries such as documentation generation

You can add additional details after a new line to describe the change in detail or automatically close a issue on Github.

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
as we know it from the Linux Kernel [Documenation/SubmittingPatches](https://www.kernel.org/doc/Documentation/SubmittingPatches)

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
