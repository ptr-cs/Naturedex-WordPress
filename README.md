# Naturedex — WordPress edition

A Virginia wildlife field guide with an original Game Boy Advance–inspired look: 24 animated pixel-art animals, a linked region map, a personal discovery collection, and a field journal.

This is the WordPress implementation of [Naturedex](../Naturedex), built for comparison with the Astro/React/Drupal original. It reuses that project's species data, journal posts, sprites, CSS, and test scenarios. Its frontend is deliberately different: **PHP templates and plain TypeScript, with no UI framework.**

Nothing here has been deployed. The AWS plan is in [docs/deployment-aws.md](docs/deployment-aws.md).

## Run it locally

Requires Node 22.12 or later. No PHP, MySQL, or Docker is needed, because WordPress runs on PHP-WASM through [WordPress Playground](https://wordpress.github.io/wordpress-playground/).

```sh
npm ci
npm run dev
```

Open **http://127.0.0.1:4321**. The first start downloads WordPress and seeds the site, which takes under a minute. Sign in at `/editor` with username `admin` and password `password` (local development only).

- The site lives in `.wp-local/` and survives restarts. Delete that folder to start over.
- PHP changes under `wordpress/` apply on the next request.
- After changing `frontend/`, run `npm run build:frontend`.

## How it is built

| Part | Location | Role |
| --- | --- | --- |
| Plugin | `wordpress/plugins/naturedex-core` | Content model, REST API, wp-admin forms, seed data, hardening, sprites |
| Theme | `wordpress/themes/naturedex` | PHP templates that render every page |
| Frontend | `frontend/` | TypeScript behavior, bundled into the theme by esbuild |
| Production image | `docker/` | Apache and PHP image, configured by environment variables |
| Infrastructure | `infra/` | AWS CDK stack (synthesizes offline; no deploy script) |

**Rendering.** PHP produces all the HTML, so pages work without JavaScript. TypeScript then adds filtering, the seen collection, dialogs, sprite motion, and the Writing desk. It moves and updates server-rendered elements rather than rebuilding them. A species entry is rendered once by PHP, into both its own page and the `<template>` that a dialog clones.

**Content model.**

| Content | WordPress type |
| --- | --- |
| Species | `species` custom post type with registered metadata |
| Animal groups, regions | Custom taxonomies (regions hold map position and color as term metadata) |
| Field notes | Core posts and categories, stored as native blocks |
| Related species | Post metadata holding species IDs, so renaming a species keeps its links |
| Field guide, map, journal, Writing desk, About | Core pages (About is edited as normal WordPress content) |

Species were a source-controlled JSON file in the first version. Here they are edited in wp-admin, and the JSON is only seed data.

**Writing.** The Writing desk and the block editor edit the same posts. The desk's format is paragraphs and `## ` headings. If a post uses anything richer, the desk leaves its body alone and links to the block editor, and the API refuses to overwrite it. Raw HTML typed in the desk is shown as text.

**Access.** Reading is public. The Writing desk uses WordPress sign-in, roles, and REST nonces: contributors can draft, authors and above can publish, and each person can only change what their role allows. Deleting moves a post to the WordPress trash.

**Your browser.** The discovery collection and motion preference stay in local storage. Neither reaches WordPress.

## API

Under `/wp-json/naturedex/v1`:

| Route | Access |
| --- | --- |
| `GET /species`, `GET /regions` | Public |
| `GET /posts` | Public; `?includeDrafts=1` needs an editorial account |
| `GET /posts/{id or slug}` | Published posts are public; drafts need edit access |
| `POST /posts` | Signed in, with a nonce |
| `PATCH /posts/{id}`, `DELETE /posts/{id}` | Signed in, with a nonce and rights to that post |

Invalid input returns 422, and block-editor content that the desk can't represent returns 409.

## Commands

```sh
npm run dev            # local site
npm run build          # sprites, type-check, frontend bundle
npm test               # unit tests
npm run test:e2e       # browser, API, and accessibility tests
npm run test:a11y      # accessibility subset
npm run infra:test     # assertions on the AWS stack
npm run infra:synth    # generate CloudFormation offline
npm run verify         # all of the above except synth
```

Browser tests need Chromium: `npx playwright install chromium`.

To change a sprite animation, edit `data/sprite-motion-*.json` and run `npm run sprites:build`.

Seed data can also be imported with `wp naturedex seed`. It only adds what is missing, never overwrites edits, and does not bring back seeds you deleted.

## Verification

Results from the last full run are in [docs/comparison.md](docs/comparison.md#measured-results).

**Tests run on SQLite, not MySQL.** Playground uses WordPress's SQLite integration, so the suite proves the application against WordPress 7.1.4 and PHP 8.4 but not against the production database. Other parts that have not run are listed under [what has not been tested](docs/deployment-aws.md#what-has-not-been-tested).

Passing automated scans is not a WCAG or Section 508 conformance claim. Manual checks are recorded as not evaluated in [docs/accessibility.md](docs/accessibility.md).

## Content scope

This is a representative collection across mammals, birds, reptiles, amphibians, fish, and invertebrates, not a complete checklist of Virginia wildlife. Map pins mark broad regional habitat associations, **not sightings, ranges, or precise boundaries**. The interface and sprites are original and contain no Pokémon artwork. The Silkscreen font is under the SIL Open Font License (`wordpress/themes/naturedex/assets/fonts/OFL.txt`).
