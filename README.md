# External Content Sync Importer (WordPress + ACF)

A reusable WordPress plugin that **imports and keeps WordPress posts in sync with an external data source** (REST API), including **Advanced Custom Fields (ACF)** values.

This repo is intentionally written with **industry-agnostic naming** (“entity”, “external data”, etc.) so you can adapt it to any vertical (directories, locations, partners, providers, products, etc.).

## What it does

- Fetches records from a remote REST endpoint (authenticated via OpenID Connect if needed)
- Creates/updates WordPress posts for each record on a schedule (daily by default)
- Synchronizes ACF fields from the external payload into your post’s ACF field group
- Supports updating core post attributes (title, content, etc.) when external data changes
- Optional: logo/media URL composition via a configurable base URL

## Quick start

1. Copy the plugin folder into `wp-content/plugins/`:
   - `ExternalContentSyncImporter/`
2. Activate **External Content Sync Importer** in WP Admin → Plugins
3. Go to WP Admin → Settings → **External Content Sync Importer**
4. Configure:
   - **External Data Source Settings** (endpoint URL + method)
   - **OpenID Connect Settings** (if your endpoint needs OAuth/OpenID token flow)
   - **Advanced Custom Fields mapping**
     - Choose a Field Group
     - Choose the field used as a **unique entity identifier**
     - Set **Target Post Type Slug** (e.g. `entity`, `listing`, `provider`)
     - Set **Base URL for Logo Images** (optional)

## Adapting it to your use case

This plugin uses “entity” as a placeholder concept. You’ll typically customize:

- **Target post type slug**
  - Set in Settings → “Target Post Type Slug”
- **Field mappings**
  - Adjust the ACF field keys / expected payload keys inside `includes/AdvancedCustomFields/`
- **Import logic**
  - The task runner lives in `includes/Services/TaskService.php`
  - Post sync logic lives in `includes/AdvancedCustomFields/AcfPostSync.php`

If you want to fully “rebrand” the code for your domain, a quick sweep rename of *Entity* → *YourThing* is safe:
- `AcfEntityModel` → `AcfLocationModel` (example)
- Methods like `syncEntityPost()` → `syncLocationPost()`

## Security notes

- Do **not** commit real secrets (client secrets, tokens, private keys, etc.) to Git.
- Store runtime secrets in WordPress options via the Settings UI, environment variables, or a secrets manager.
- If anything sensitive was ever committed in git history, rotate it and rewrite history before publishing.

## License

MIT
