# Comparing this implementation

This edition is one side of the exercise in the original project's [docs/comparison.md](../../Naturedex/docs/comparison.md). This file records the facts for WordPress. It doesn't score them.

## What differs from the first Naturedex

| | First Naturedex | WordPress edition |
| --- | --- | --- |
| Frontend | Astro and React | PHP templates and plain TypeScript |
| Rendering | React, server-rendered and hydrated | PHP; TypeScript enhances existing HTML |
| CMS | Drupal 11, SQLite | WordPress 7.1, MySQL in production |
| Species | JSON file in the repository | Custom post type, editable in wp-admin |
| Journal storage | Custom fields holding plain text | Core posts holding native blocks |
| Editor access | Local token on loopback | WordPress accounts, roles, and nonces |
| Delete | Permanent | Trash, recoverable |
| Species address | Dialog only | Dialog and its own page |
| Without JavaScript | Not a goal | Guide, entries, and journal readable |
| Related animals in the desk | One | Several |
| Local runtime | FrankenPHP binary | PHP-WASM through Playground |

## Change exercises

| Exercise | What happened here |
| --- | --- |
| Create, preview, draft, publish | Writing desk, and also the block editor. Covered by browser tests |
| Correct a reused record | One wp-admin edit updates the card, entry page, and dialog. Covered by a browser test |
| Add a field and filter on it | Register the meta key, add it to the admin form, the record, and the template, then the filter in TypeScript. Not exercised yet |
| Unpublish referenced content | An unpublished species drops off public pages, and the link is kept. Not covered by a test |
| Restart after an edit | Data persists in `.wp-local`. Checked by hand |
| Replace the renderer | The REST API exposes all content, but templates read it directly from PHP, so this isn't isolated |

## Measured results

Measured November 6, 2026, on WordPress 7.1.4, PHP 8.4, Chromium, and the Playground runtime.

| Measure | First Naturedex | WordPress edition |
| --- | ---: | ---: |
| Browser JavaScript (minified) | 254 KB | 11.6 KB |
| Browser JavaScript (gzip) | 79 KB | 4.7 KB |

| Custom code | Lines |
| --- | ---: |
| TypeScript frontend | 818 |
| PHP plugin | 1,534 |
| PHP theme | 1,172 |
| CDK stack | about 600 |

| Automated tests | Passed |
| --- | ---: |
| Unit (Vitest) | 17 of 17 |
| Browser, API, accessibility (Playwright) | 40 of 40 |
| AWS stack (CDK assertions) | 13 of 13 |

The browser suite includes the first version's scenarios, adapted to real sign-in. Three changed in substance: the Writing desk journey now waits for its save confirmation, the cross-origin test sends a valid nonce, and the local-mutation unit tests were replaced by API tests of roles and nonces. All 30 PHP files also parse on PHP 8.4.

The size difference is mostly React itself, and the first version's JavaScript also did the work PHP does here. Page speed was not measured. Playground is far slower than production, about a second per page, so its timings say nothing about either CMS. Hands-on time was not measured.

Don't read fewer lines or tests as a more elegant result; the original comparison warns against this.

## Things WordPress made easier or harder

Observed while building this. These are notes, not scores.

**Easier**

- Revisions, trash, roles, sign-in, RSS, sitemaps, and media came with the platform.
- Pretty addresses for pages, posts, and species took little code.
- Playground runs real WordPress in Node with no system dependencies.

**Harder**

- Public hardening took deliberate work. `xmlrpc_enabled` leaves pingbacks running, and `?author=1` reveals usernames through a canonical redirect unless intercepted early. The tests caught both.
- WordPress quietly changes data: it appends `-2` to taken slugs and stores entities in titles. The API has to reject or undo this.
- Two authoring surfaces need one format, which is why the desk refuses to edit rich content.
- WordPress's caching story depends on a fixed cookie name set in configuration.
- Playground differs from Apache in `PHP_SELF`, which changed 404 behavior and needed a local-only shim.
