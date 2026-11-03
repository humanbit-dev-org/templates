---
name: git-commit
description: 'Execute git commit with commit message analysis, intelligent staging, and message generation. Use when user asks to commit changes, create a git commit, or mentions "/commit". Supports: (1) Auto-detecting verb and scope from changes, (2) Generating commit messages from diff, (3) Interactive commit with optional verb/scope/explanation overrides, (4) Intelligent file staging for logical grouping'
license: MIT
allowed-tools: Bash
---

# Git Commit

## Overview

Create standardized git commits. Analyze the actual diff to determine the right verb, scope, and message.

## Commit Format

```
<verb>[optional scope]: <generic explanation>

[optional paragraph explanation]

[optional body bullets]

[optional footer(s)]
```

- `verb` is one of: `feat`, `fix`, `hotfix`, `update`, `remove`, `refactor`, `perf`, `bump`, `revert`, `merge`, `chore`, `ci`, `test`
- Lowercase only
- `scope` is optional and in parentheses
- `generic explanation` describes what changed in plain language

## Commit Verbs

| Verb | Use when... |
| --- | --- |
| `feat` | introducing new features or capabilities |
| `fix` | correcting bugs or non-urgent issues |
| `hotfix` | urgent fixes for immediate deployment |
| `update` | modifying existing features, docs, config, or dependencies |
| `remove` | deleting code, features, files, or dependencies |
| `refactor` | restructuring code without changing behavior |
| `perf` | performance improvements |
| `bump` | updating dependency versions |
| `revert` | undoing previous changes |
| `merge` | combining branches or integrating changes |
| `chore` | maintenance tasks that don't modify source or test files |
| `ci` | continuous integration and deployment pipeline changes |
| `test` | adding or updating tests |

## Breaking Changes

Use the `BREAKING CHANGE` footer or append `!` to the verb/scope:

```
update!: change auth token format

BREAKING CHANGE: tokens now require `Bearer ` prefix
```

## Commit Body Style

- Use `-` for bullet points
- Do not add topic headings before bullets
- Put the verb in the title; the body lists the specific actions
- Keep each bullet short and focused

Example:

```
feat(api): attachment metadata endpoint

- add `AttachmentMetadataResource`
- validate file type and size in `AttachmentRequest`
- expose `metadata` relation on `Attachment` model
```

Example with paragraph before bullets:

```
fix(frontend): prevent navbar dropdown from closing on mobile

The dropdown was closing whenever the user scrolled, which made mobile
navigation unusable on long pages.

- close menu only on outside click
- restore focus to toggle button
```

## Optional Footers

Footers are rarely used. When needed, place them at the very end of the commit, after any body bullets, for metadata only:

- `BREAKING CHANGE: ...`
- `Closes #123`
- `Refs #456`
- `Co-authored-by: ...`

For normal context or reasoning, prefer a short paragraph before the bullet list instead of a footer.

## Workflow

### 1. Analyze Diff

```bash
# If files are staged, use staged diff
git diff --staged

# If nothing staged, use working tree diff
git diff

# Also check status
git status --porcelain
```

### 2. Stage Files (if needed)

If nothing is staged or you want to group changes differently:

```bash
# Stage specific files
git add path/to/file1 path/to/file2

# Stage by pattern
git add *.test.*
git add src/components/*

# Interactive staging
git add -p
```

**Never commit secrets** (.env, credentials.json, private keys).

### 3. Generate Commit Message

Analyze the diff to draft the commit message. Work through this checklist:

- **Verb**: pick one from the commit verb list
- **Scope**: what area/module is affected, if any
- **Generic explanation**: one-line summary of what changed (plain language, <72 chars)
- **Intent**: infer the intent behind the changes by reading them in the context of the broader codebase, and reflect that intent in the commit message rather than describing the changes literally
- **Paragraph**: optional context or reason, placed before bullets
- **Body bullets**: specific actions, one per line, using `-`
- **Footer (rare)**: optional metadata, placed after bullets

Only the title, optional paragraph, body bullets, and optional footer are printed in the commit.

### 4. Execute Commit

```bash
# Single line
git commit -m "<verb>[scope]: <generic explanation>"

# Multi-line with paragraph and bullets
git commit -m "$(cat <<'EOF'
<verb>[scope]: <generic explanation>

Optional paragraph explanation.

- bullet detail
- bullet detail
EOF
)"
```

### 5. Changelog entry (docbase)

After a successful commit, decide whether it deserves an entry in the
docbase Changelog (`Roadmap/Changelog/`). The bar is high — most
commits do NOT get one.

- **Access gate**: only attempt the write when the docbase `create`
  tool is available and permitted in this environment. Only setups
  configured with docbase write access can produce entries — if the
  tool is absent or the write is denied, skip silently. Never work
  around a denial.
- **Importance gate**: only notable changes — production bugs,
  infra/deploy changes, meaningful features. Match the caliber of the
  existing entries. Skip typos, dep bumps, tweaks, WIP, internal-only
  polish. When unsure, ask before writing.
- **Fixer**: the commit author's GitHub handle — `git config user.name`
  (e.g. `g-silveri`); if unset, `gh api user --jq .login`.
- **File**: `Roadmap/Changelog/<slug>.md` — slug is a short hyphenated
  topic (`redis-jobs`, `prod-init`). Create via the docbase `create` tool.
- **Frontmatter** (fields feed the auto-generated index — never edit
  `Changelog.md` itself):

  ```
  ---
  date: <YYYY-MM-DD>
  environment: <Integration, Staging, Production — list what applies>
  type: <bug|feature|...>
  version: "[<tag>](https://github.com/humanbit-dev-org/rays/tree/refs/tags/<tag>) > [Commit](https://github.com/humanbit-dev-org/rays/commit/<sha>)"
  fixer: <commit author's GitHub handle>
  ---
  ```

  Tag: `git describe --tags --abbrev=0`. Sha: `git rev-parse HEAD`.
- **Body** (Italian, three labeled paragraphs):
  `**Segnalazione:**` what was reported · `**Difetto:**` what was wrong
  · `**Fix:**` what the change does.

## Best Practices

- One logical change per commit
- Use a verb from the commit verb list for every title
- Keep the title under 72 characters
- Use bullet points for the body; avoid paragraphs when bullets are enough
- Place an optional explanation paragraph before the bullet list
- Reserve footers for metadata; most commits do not need one

## Git Safety Protocol

- NEVER update git config
- NEVER run destructive commands (--force, hard reset) without explicit request
- NEVER skip hooks (--no-verify) unless user asks
- NEVER force push to main/master
- NEVER add itself as a co-author or include any AI-generated attribution footers; commits must be made exclusively under the handle of the user issuing the prompt, unless explicitly instructed otherwise
- If commit fails due to hooks, fix and create NEW commit (don't amend)
