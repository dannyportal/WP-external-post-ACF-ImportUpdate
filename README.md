# HomeCareAgencyImporter (Sanitized)

This repository is a sanitized export intended for publishing on GitHub.

## What was scrubbed
- A hardcoded internal-looking URL host was replaced in:
  `includes/AdvancedCustomFields/AcfAgencyModel.php` (`LOGO_URL_PREFIX`).

## Configuration (keep secrets out of git)
If this plugin ever needs credentials (API keys, DB URLs, OAuth client secrets, webhooks, etc.), provide them at runtime and **do not** commit them to the repo.

Recommended patterns:
- Use environment variables (e.g., `.env` in local dev) and read them in your bootstrap code.
- Or store non-secret configuration in WordPress options, and keep secrets in server-side env vars.

This repo includes a `.gitignore` that ignores `.env` files, private keys, and other common secret artifacts.

### Logo host
Set the real logo host via configuration at deploy time instead of hardcoding it:
- Replace `https://example.com` with your public host, or
- Refactor `LOGO_URL_PREFIX` into a WP option / setting.
