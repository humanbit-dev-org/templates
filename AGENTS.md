# Agent Guidelines

## Working rules

- Verify real state with tools before answering — never guess at files,
  config, or app state.
- Do NOT implement before the user confirms. Plan/propose first.
- Keep changes tight: smallest diff that solves the problem, no
  drive-by refactors unless directly requested. Bigger changes are never the first choice.
- If the work turns into a major overhaul of the codebase — wide
  refactors, architecture changes, many files touched — stop and ask
  first, even when running with full access. Only proceed without
  asking when the user explicitly says to go ahead with no holds.
- When offering options, put the recommended one first and say why in
  one line.
- Backend work ships with tests. When building a feature or changing
  behavior, write as many unit/feature tests as needed to prove it
  works end to end. Prefer adding cases to an existing test file for
  the same class/feature; create a new file only when none fits.
- Comment what you implement in English — both docblocks/file headers
  AND inline comments inside the logic. Every non-trivial piece of new
  or changed code explains what it does and why, written for a stranger
  reading the codebase: plain, doc-like language, simple structure, no
  personal/chat phrasing. Informative, not technical showing-off.
- Do not leave stray files, backups, or dead code behind — and say so
  before creating anything extra.
- No commits, pushes, or destructive git ops unless explicitly asked.

## Environment assumptions

- Never assume a tool, CLI, or library is installed. Before writing or
  running anything that calls one (scripts, skills, one-off commands),
  check it exists (`command -v`, a version flag, or the package
  manifest). If it is missing, use a fallback that is installed or ask
  before installing.

## Obsidian vault (docbase MCP server)

The `docbase` MCP server exposes an Obsidian vault containing project
notes (conventions, planning, infrastructure, setup guides, etc.). It is
pre-approved in `~/.config/devin/config.json` via
`mcp__docbase__resource:obsidian://docbase/*`.

Use the vault according to the permissions the user's docbase access
allows: read where readable, write where writable. If a write is
denied, skip it — never work around permissions.

### When to consult it

Consult the vault **before answering or implementing** when the request concerns:

- Team conventions (branch naming, commit style, naming/formatting rules)
- Infrastructure and operations not fully described in the repo (Docker workflows,
  VPN/shared drive setup, database dump/restore procedures)
- Planning and architectural decisions (e.g. multi-tenant strategy, forks,
  feature branches) — these may be decided in notes but not yet in code
- Anything the user prefaces with "check the vault", "docbase", or "Obsidian"

### When NOT to consult it

Skip the vault when the answer lives in the code itself:

- Code implementation, bugs, refactors — the codebase is authoritative
- Questions about current behavior — read the actual code/config files
- Simple mechanical tasks (run a command, rename, format)

When in doubt, check the vault — conventions and best practices may
live there even when it is not obvious where. Start from `MOC.md` or a
targeted `list`/`read` rather than scanning many notes.
