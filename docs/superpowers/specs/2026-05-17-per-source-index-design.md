# Per-source index pattern for ClaudeTarget

**Status:** Approved
**Date:** 2026-05-17
**Scope:** `src/Targets/ClaudeTarget.php`, README

## Problem

`ClaudeTarget::syncIndex()` currently writes a full per-source section directly into the project's root `CLAUDE.md`, including the bullet list of every generated page with its H1 title. With three sources (Laravel, Flux UI, Livewire) and dozens of pages each, the root `CLAUDE.md` ends up with 100+ lines of generated content, drowning out the user's own project instructions and making the file unwieldy.

## Goal

Keep the root `CLAUDE.md` slim: it should contain pointers to per-source indexes, not the indexes themselves. The detailed file list moves to a per-source `index.md`, and the root only carries a small managed block listing the indexes.

## Non-goals

- Changing the Cursor or Copilot targets (they don't use an index file; their `syncIndex` is already a no-op).
- Changing the `Target` or `DocsDriver` interfaces (would break custom drivers/targets).
- Adding a test suite (no test infrastructure exists yet; that's a separate concern).
- Automatic migration of pre-existing root `CLAUDE.md` sections written by older versions.

## Design

### Output structure (Claude target)

```
.claude/
├── flux/
│   ├── index.md          ← NEW per-source index
│   ├── button.md
│   └── ...
├── laravel/
│   ├── index.md
│   └── ...
└── livewire/
    ├── index.md
    └── ...
CLAUDE.md                 ← single managed block; rest untouched
```

### Per-source `index.md` content

```markdown
# Flux UI documentation

> Read on demand. Load only the file relevant to the current task — do NOT load all files at once.

Available files in `.claude/flux/`:
- `button.md` — Button component
- `card.md` — Card component
- ...
```

The H1 derives from the source's display label (parsed from `DocsDriver::indexSection()`, e.g. `## Flux UI` → `Flux UI`). The bullet list is the same shape as today's root-CLAUDE.md output: filename + H1 of the generated file (or a title-cased slug fallback).

### Root `CLAUDE.md` managed block

```markdown
<!-- docs-generator:start -->
## Generated documentation

Indexes maintained by docs-generator. Load on demand:

- **Flux UI** → `.claude/flux/index.md`
- **Laravel** → `.claude/laravel/index.md`
- **Livewire** → `.claude/livewire/index.md`
<!-- docs-generator:end -->
```

Rules:
- The block is delimited by HTML-comment markers so detection is unambiguous (no regex on Markdown headings).
- `syncIndex(source)` upserts only the line for `source` inside the block. Other sources' lines are preserved.
- If the block is absent, append it to the end of root `CLAUDE.md` (with a leading blank line).
- The lines inside the block are sorted alphabetically by display label for stable diffs.
- Anything outside the markers is never read or written.

### Behaviour of `ClaudeTarget::syncIndex(string $source, string $sectionHeading)`

Two steps, in order:

1. **Write per-source index.** Generate the bullet list (same logic as today) and write it to `<outputDir(source)>/index.md`. The H1 comes from `sectionHeading` with the leading `## ` stripped.
2. **Upsert root pointer.** Read root `CLAUDE.md` (or treat as empty if absent). Locate the managed block via `<!-- docs-generator:start -->` … `<!-- docs-generator:end -->`. If missing, append a fresh block. Within the block, upsert the line `- **<label>** → \`<relative-path-to-index.md>\``, keeping the list sorted. Write the file back.

The current `index.md` file produced for the source must be excluded from the bullet list (otherwise it would list itself).

### Code touchpoints

- `src/Targets/ClaudeTarget.php`: rewrite `syncIndex()`; add a private helper for the managed-block upsert. The existing `relativeFromBase()` helper stays.
- No changes to: `Orchestrator`, `Target` interface, `DocsDriver` interface, `CursorTarget`, `CopilotTarget`, config, command, drivers.

### README update

The "Output → Claude target" section in `README.md` is updated to show the new layout (per-source `index.md` files and the managed block in root). Other README sections are untouched.

## Backwards compatibility

Users who ran the previous version will have `## Laravel`, `## Flux UI`, `## Livewire` sections sitting in their root `CLAUDE.md` from before. The new code does not detect, warn about, or remove them — it only manages content inside its own marker block. On first run of the new version, the new block is appended to the end; the user removes the old sections manually when convenient. This avoids any risk of touching user-edited content.

## Risks & mitigations

- **Risk:** A user has a literal `<!-- docs-generator:start -->` comment somewhere unrelated. **Mitigation:** Unlikely in practice; markers are package-specific. Document the markers in the README.
- **Risk:** Two sources happen to produce the same display label. **Mitigation:** Display labels are derived from `indexSection()` which is unique per driver; collision implies driver misconfiguration, not a bug here.
- **Risk:** The `.claude/<source>/index.md` file is itself an `.md` in the source folder, so naive `File::files()` would include it in the next regeneration's bullet list. **Mitigation:** Filter `index.md` out when building the list.

## Acceptance

- After `php artisan docs:generate laravel`:
  - `.claude/laravel/index.md` exists with H1, intro, and bullet list of all generated `.md` files (excluding itself).
  - Root `CLAUDE.md` contains exactly one `<!-- docs-generator:start -->` … `<!-- docs-generator:end -->` block, and that block contains exactly one bullet pointing at `.claude/laravel/index.md`.
- After also running `docs:generate flux` and `docs:generate livewire`:
  - The managed block contains all three pointer lines, sorted alphabetically by label.
- Re-running `docs:generate laravel` is idempotent: no duplicate lines, no churn outside the managed block, no churn inside `.claude/flux/` or `.claude/livewire/`.
- Content outside the markers in root `CLAUDE.md` is byte-identical before and after a run.
