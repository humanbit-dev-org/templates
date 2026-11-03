#!/usr/bin/env bash
# Builds backend/resources/audit/report.json for the Dependency Audit page
# (Backpack Tools): vulnerabilities and major updates of the PHP and frontend
# dependencies, the Node / Next.js / React versions the frontend ships, and the
# end-of-life tables the page matches live versions (PHP, Laravel, MySQL) against.
#
# Usage: .github/scripts/audit-report.sh [codebase-root] [ref] [commit]
#
# Every tool runs in a throwaway container fed through stdin, never through bind
# mounts (the CI runner may itself run in a container), so the host needs Docker
# only. It never fails its caller: whatever breaks is written into the report,
# and the build goes on.
set -uo pipefail

SCRIPTS="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "${1:-$SCRIPTS/../..}" && pwd)"
REF="${2:-$(git -C "$ROOT" describe --tags --always 2>/dev/null || echo local)}"
COMMIT="${3:-$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)}"
# CI passes the tag and its commit; without them the report is a local one,
# whose commit may exist only on this machine (the page shows it unlinked).
LOCAL=$([ -z "${3:-}" ] && echo 1 || echo 0)
OUT="$ROOT/backend/resources/audit/report.json"

# Same base image as the frontend Dockerfile, so the Node version reported is
# the one shipped. composer:2 brings PHP and Composer for the backend part.
NODE_IMAGE="${AUDIT_NODE_IMAGE:-node:lts-bookworm-slim}"
COMPOSER_IMAGE="${AUDIT_COMPOSER_IMAGE:-composer:2}"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$(dirname "$OUT")"

# 1. Frontend. pnpm audit and pnpm outdated both work from the lockfile alone:
#    nothing is installed. Their exit code is nonzero when they find something,
#    so only the output files matter.
tar -C "$ROOT/frontend" -cf - package.json pnpm-lock.yaml pnpm-workspace.yaml \
  | docker run -i --rm "$NODE_IMAGE" sh -c '
      mkdir -p /work /out && cd /work && tar -xf -
      npm install -g pnpm@latest > /out/pnpm-install.err 2>&1
      node -v | sed "s/^v//" > /out/node-version.txt
      pnpm audit --json > /out/pnpm-audit.json 2> /out/pnpm-audit.json.err
      pnpm outdated --format json > /out/pnpm-outdated.json 2> /out/pnpm-outdated.json.err
      tar -C /out -cf - .' \
  | tar -C "$WORK" -xf - 2>> "$WORK/frontend.err" \
  || echo "frontend tools did not run" >> "$WORK/frontend.err"

# 2. Backend, then assembly. composer audit/outdated read the lockfile (no
#    vendor/), and audit-report.php turns every output into the report.
tar -cf - -C "$WORK" . -C "$ROOT/backend" composer.json composer.lock \
    -C "$ROOT/frontend" pnpm-lock.yaml package.json -C "$SCRIPTS" audit-report.php \
  | docker run -i --rm -e COMPOSER_HOME=/tmp/composer \
      -e AUDIT_REF="$REF" -e AUDIT_COMMIT="$COMMIT" -e AUDIT_LOCAL="$LOCAL" "$COMPOSER_IMAGE" sh -c '
      mkdir -p /work && cd /work && tar -xf -
      composer audit --locked --format=json --no-interaction > composer-audit.json 2> composer-audit.json.err
      composer outdated --locked --direct --major-only --format=json --no-interaction --ignore-platform-reqs \
        > composer-outdated.json 2> composer-outdated.json.err
      php audit-report.php' \
  > "$WORK/report.json" 2> "$WORK/assemble.err"

# 3. Keep the report only if the assembly produced one; otherwise write a
#    minimal one saying so, so the page shows the failure instead of nothing.
if head -c 1 "$WORK/report.json" 2>/dev/null | grep -q '{'; then
  cp "$WORK/report.json" "$OUT"
  cat "$WORK/assemble.err" >&2
else
  REASON="$(tr -d '"\\' < "$WORK/assemble.err" 2>/dev/null | tr '\n' ' ' | cut -c1-500)"
  printf '{"schema":1,"generated_at":"%s","ref":"%s","commit":"%s","local":%s,"error":"report not assembled: %s"}\n' \
    "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$REF" "$COMMIT" "$([ "$LOCAL" = 1 ] && echo true || echo false)" "$REASON" > "$OUT"
  echo "WARNING: dependency audit report not assembled: $REASON" >&2
fi

echo "Dependency audit report written to $OUT" >&2
exit 0
