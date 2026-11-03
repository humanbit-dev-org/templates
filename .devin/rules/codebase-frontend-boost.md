---
globs: frontend/**
description: "Next.js/React frontend. Apply when the request involves frontend/, Next, React, components, or UI under frontend/."
trigger: glob
---
<frontend-boost-guidelines>
=== foundation rules ===

## Foundational Context

Package versions are auto-generated. See @.cursor/deps-versions.md and @.devin/deps-versions.md

- node - 20.x
- pnpm - v9

=== frontend rules ===

# Frontend (Next.js + React)

- This app is a Next.js 15 App Router app with React 19. For Next/React conventions and APIs, prefer official docs (Next.js 15, React 19) and existing patterns in this codebase.

## Structure

- **App Router**: Routes live under `frontend/src/app/[lang]/` (dynamic `[lang]`). Use `layout.js` and `page.js` per segment; follow existing layout/page structure.
- **Components**: `frontend/src/components/` (alerts, blocks, dialogs, elements, footers, navbars). Use JSX; co-locate `.scss` with components. Check sibling components for structure and naming.
- **Hooks / utils**: `frontend/src/hooks/`, `frontend/src/utils/`. Use `pathInfo`, `fetchCsrf`, `deviceInfo` when relevant.
- **Config / providers**: `frontend/src/config/`, `frontend/src/providers/`. Use existing providers (API, Auth, Content, etc.) before adding new ones.
- **Styles**: Tailwind (config in PostCSS) and SCSS under `frontend/src/assets/scss/` (variables, mixins, partials). Prefer Tailwind utilities; use SCSS for shared layout/theme.

## Conventions

- Use functional components and hooks; avoid class components. Prefer existing components and patterns; check sibling files before adding new ones.
- For data fetching: use SWR or server-side in layout/page as in the rest of the app. Auth/API via existing providers and `frontend/src/lib/server.js` where needed.
- All code comments in **English**. Stick to existing naming (e.g. PascalCase for components, camelCase for hooks/utils).

## Build & run

- Dev: `pnpm dev` (from `frontend/`). If UI doesn’t update, suggest running `pnpm build` or `pnpm dev`.

</frontend-boost-guidelines>