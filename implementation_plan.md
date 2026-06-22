# Implementation Plan

[Overview]
Build a simple “Backlink creator” web page using your existing layout that lets a user paste one URL, then clicks a renamed button “Create Backlinks” to generate/manage backlink pages or tasks in bulk (hundreds of backlink targets), using the same PHP backend style.

This repo currently has a “backlink checker”: `index.html` posts `target` + `urls` to `process.php`, which uses `lib/SimpleBacklinkCheck.class.php` to fetch each backlink page and check whether the target link appears in any `<a href="...">...</a>`. To repurpose this into a “backlink creator,” we need to decide what “creating backlinks” means in your context, because the existing code only verifies presence, it does not create or edit external sites (which is impossible from a static browser/PHP server unless you control those sites and have an API/admin integration).

The implementation will therefore add:
1) A renamed UI button (“Create Backlinks”) matching your current layout.
2) A backend mode that generates backlink *content* (e.g., HTML snippets, a list of backlink pages to publish, and/or a CSV/ZIP of pages) for the target URL across many sites you provide.
3) A bulk input workflow to support hundreds of backlink entries.
4) A clear separation between “generate” (possible) and “publish” (only possible if you integrate with APIs you have).

[Types]
Introduce simple PHP data structures to represent generated backlink pages/snippets and publishing jobs, including validation rules for URL inputs and output payload formats.

Proposed data structures (PHP arrays):
- `BacklinkJob`:
  - `source_url` (string, required, validated as URL): the backlink page or domain input line
  - `target_url` (string, required, validated as URL): the link you want placed
  - `anchor_text` (string, optional, default derived from target host)
  - `link_rel` (string, optional, default `"nofollow"`)
  - `strategy` (enum-like string, required, one of: `"html_snippet" | "page_template"`)
- `GeneratedBacklink`:
  - `source_url` (string)
  - `output_filename` (string)
  - `output_type` (string: `"snippet" | "html"`)
  - `content` (string)

[Files]
Modify existing UI and PHP backend files; add a new generator class and adjust routing to support “create” behavior.

- Modify: `index.html`
  - Rename button text from **“Check backlinks”** to **“Create Backlinks”**
  - Change the form fields from “Backlink URLs” (pages to check) to “Backlink Sites / Pages” (pages to generate backlink content for)
  - Keep the same POST mechanism to `process.php` for simplicity
  - Add a hidden input `mode=create` (or similar) so `process.php` can route logic
- Modify: `process.php`
  - Read `mode` and branch:
    - if `mode=create`: generate backlink snippets/pages based on submitted backlink entries
    - if `mode=check`: keep current checker behavior (optional but recommended for backward compatibility)
  - Output:
    - Either an HTML results page showing generated content per item
    - And/or a downloadable ZIP file of generated pages/snippets (recommended for “hundreds”)
- Modify (or replace): `lib/SimpleBacklinkCheck.class.php`
  - Do not break existing checker; instead add a new class to keep responsibilities clean.
- New: `lib/SimpleBacklinkCreate.class.php`
  - Implements backlink “generation” (HTML snippets/templates) from input:
    - target URL
    - backlink sites/pages list
    - anchor text strategy
  - Produces `GeneratedBacklink` content
- New (optional but useful): `lib/ZipUtil.php`
  - Small helper to create a ZIP in PHP for bulk outputs.

[Functions]
Add generator functions and minimal routing functions; keep existing checker functions intact.

New functions/classes:
- `class SimpleBacklinkCreate`
  - `public function setTarget(string $target): void`
  - `public function setUrls(array $urls): void` (same validation behavior as checker for each line)
  - `public function setAnchorText(?string $anchorText): void`
  - `public function setStrategy(string $strategy): void` (default `"page_template"`)
  - `public function process(): void`
    - For each backlink “site/page” input, generate:
      - `html snippet` containing an `<a href="TARGET_URL">ANCHOR_TEXT</a>` tag
      - optionally wrap in a minimal HTML page template
  - `public function getResults(): array`
    - returns mapping keyed by input URL to generated outputs

Modified functions:
- `process.php`
  - No exact function names here (script), but required logic changes:
    - parse `$_POST['mode']`
    - call either `SimpleBacklinkCheck` (existing) or `SimpleBacklinkCreate` (new)
    - render results accordingly

Removed functions:
- None.

[Classes]
Add one new generator class; no required removal.

- New: `SimpleBacklinkCreate` in `lib/SimpleBacklinkCreate.class.php`
  - Uses DOMDocument/curl only if needed (for generation we don’t need fetching external sites)
  - For “anchor text derived from target,” parse host and optionally strip `www.`
  - Generates safe HTML with the provided target URL embedded

[Dependencies]
No new external composer/npm dependencies required. Use built-in PHP features:
- existing `php-curl` remains required only for the checker mode
- generator mode does not require curl
- ZIP generation can be done with `ZipArchive` (built-in in most PHP installs)

[Testing]
Test by running locally (or on your PHP host) using:
1) Single target URL + 3 backlink entries → confirm results page renders correctly.
2) 200–500 backlink entries → confirm page does not time out (output should be downloadable ZIP or paginated display).
3) Invalid URLs in textarea → confirm they are filtered out by validation.
4) Anchor text presence/absence → confirm derived anchor text works.

Because there are no existing automated tests in the repo, validation will be done via manual test submissions and checking generated output structure.

[Implementation Order]
1) Update `index.html` UI and add hidden `mode=create` while preserving existing layout/styles.
2) Update `process.php` to route by `mode` and keep existing “check” behavior intact.
3) Create `lib/SimpleBacklinkCreate.class.php` to generate backlink snippet/page content.
4) Implement output rendering (HTML list + “download zip” if possible) for bulk results.
5) Verify end-to-end by submitting a small list first, then scale to hundreds.
