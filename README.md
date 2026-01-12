# External Content Sync Importer (WordPress) — Quick Setup Tutorial

This plugin pulls records from a remote source and **creates/updates WordPress posts on a schedule**, including **ACF fields**, so your site stays current without manual edits. It’s built to be reusable for any “directory-style” content (profiles, locations, partners, listings, etc.).

---

## What you need
- WordPress site (admin access)
- **ACF** installed and active
- A **Custom Post Type** you want to populate (example: `entity`)
- A remote endpoint that returns a list of records (your API / feed)

---

## Install
1. Upload the plugin folder to:  
   `wp-content/plugins/external-content-sync-importer/`
2. Activate it in **WP Admin → Plugins**.

---

## Create your content type (CPT)
Create (or confirm you already have) a post type that will hold the imported records.

**Example slug:** `entity`  
You can use any slug you want, but you’ll need to set it in the plugin settings.

---

## Create ACF fields
Create an ACF Field Group assigned to your target post type and add, at minimum:

- **external_id** (Text)  
  This is the unique ID from the remote source. It prevents duplicates and allows updates to target the right post.

Optional but common:
- **logo_url** (URL or Text)
- any other fields you want the importer to maintain

---

## Configure the plugin
Go to: **WP Admin → Settings → External Content Sync Importer**

Set:
1. **Remote Endpoint URL**  
   The URL your plugin will fetch records from.
2. **Target Post Type Slug**  
   The CPT slug you created (example: `entity`).
3. **Logo Base URL** (optional)  
   Use this if your source provides logo “paths” instead of full URLs.
4. **Debug Logging** (optional)  
   Turn on while testing, then turn off.

Save.

---

## Run it and verify
After activation + configuration:

1. The plugin will sync on its scheduled run (daily by default).
2. Go to **WP Admin → your CPT list** (ex: Entities) and confirm posts were created.
3. Open one post and confirm:
   - Title/content updated
   - ACF fields (like `external_id`, `logo_url`) populated

If you don’t see posts after the first run, enable debug logging and check your WP debug log.

---

## Make it reliable (production best practice)
WordPress “cron” can be traffic-based. For consistent scheduling:
- Use a real server cron to trigger WP-Cron, or
- Use your host’s scheduled task runner if available

This prevents missed runs on low-traffic sites.

---

## Adapting it for a different industry
To reuse this for a different use case, you typically only change:
- the **endpoint URL**
- the **target post type**
- the **ACF field names/mapping** in the plugin’s mapper/model

No “home care” assumptions are required—treat the remote records as generic “entities.”

---

## Safety note (GitHub / public repos)
Never commit real secrets (tokens, private URLs, credentials).  
Use environment variables or wp-config constants, and keep `.env` ignored.
