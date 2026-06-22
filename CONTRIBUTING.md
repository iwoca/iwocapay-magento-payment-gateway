# Contributing

## Branching & Commits

All work happens on feature branches merged into `master` via GitLab MRs.

Commits must use [Conventional Commits](https://www.conventionalcommits.org/) format:

```
type(optional scope): description
```

Types that **trigger a release**:
- `feat:` — new feature (bumps minor version, e.g. 2.0.1 -> 2.1.0)
- `fix:` — bug fix (bumps patch version, e.g. 2.0.1 -> 2.0.2)
- `perf:` — performance improvement (bumps patch version)
- Breaking changes (add `BREAKING CHANGE:` in commit body) bump major version

Types that **do not trigger a release**:
- `ci:` — CI/CD changes
- `chore:` — maintenance tasks
- `docs:` — documentation
- `style:` — formatting
- `refactor:` — code restructuring
- `test:` — adding/updating tests

## Release Process

Releases are fully automated. When a release-triggering commit lands on `master`:

1. **Semantic Release** runs on GitLab CI, determines the next version from commit history, creates a git tag and GitLab release
2. **GitHub publish** clones the GitHub repo, copies the current source (stripping private files), replaces `$DEPLOY_VERSION` placeholders with the actual version number, commits on top of existing history, and pushes
3. **GitHub Release** is created with release notes generated from conventional commits since the last tag

### What gets stripped from GitHub

Files listed in `.publicignore` are removed before publishing to GitHub:
- `.gitlab-ci.yml`
- `.releaserc.json`
- `.publicignore`
- `.claude/`

### Version placeholder

`Model/Version.php` is the single source of truth for the version. Its `VERSION`
constant holds the only `$DEPLOY_VERSION` placeholder — CI replaces it with the
real version (e.g. `2.0.1`) when publishing to GitHub. The placeholder exists
only in our GitLab source.

Anything that needs the version at runtime injects `Version` and calls `get()`,
rather than embedding its own placeholder. This keeps the version in one place,
so it can never ship un-substituted from a file CI didn't envsubst, and a bump
is a one-line change.

Consumers:
- `Model/IwocaClientFactory.php` — sent as `iwocapay-integration-version` header
- `Model/IntegrationEventService.php` — sent with integration events
- `Model/CredentialValidator.php` — sent with the connection_check request
- `Block/System/Config/VersionComment.php` — backs the version shown in the
  Magento admin panel (wired in `etc/adminhtml/system.xml`)

### No version in composer.json

The `version` field is intentionally omitted from `composer.json`. Composer reads the version from git tags. Having it in the file risks a mismatch that breaks Packagist indexing.

## CI Variables Required

| Variable | Purpose |
|----------|---------|
| `GITLAB_RELEASE_TOKEN` | Project access token (Maintainer role, `api` + `write_repository` scopes) for semantic-release to push tags/commits |
| `GITHUB_APP_PRIVATE_KEY` | Base64-encoded GitHub App private key for authenticating pushes to GitHub |
| `GITHUB_APP_ID` | GitHub App ID for generating installation tokens |

## Testing a Release

To test without risk:
1. Push a `fix:` commit to master with a minor change
2. Watch the pipeline — it should create a new patch version, publish to GitHub, and create a GitHub release
3. If something goes wrong, the GitHub branch is protected against force pushes, so history can't be overwritten
