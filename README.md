# wp-change-login-url

A WordPress plugin (no dependencies) that renames `wp-login.php` to a
custom slug — e.g. `https://example.com/panel-login/`. Direct hits to
`wp-login.php` return 404, which kills the vast majority of brute-force
login bots that only knock on the default URL.

## How it works

1. **Settings → Change Login URL**: set a custom slug (default
   `panel-login`). A **recovery key** is auto-generated for you.
2. A rewrite rule maps `/<slug>/` to `wp-login.php` internally, so the
   login form, lost-password and registration flows all work at the new
   URL.
3. `login_init` blocks **direct** `wp-login.php` requests with a 404 —
   unless `?wcl_key=<recovery-key>` matches (your emergency backdoor if
   you forget the slug).
4. The `login_url`, `lostpassword_url`, `register_url` and `logout_url`
   filters are rewritten so WordPress itself links to the new slug
   (including the logout link, which would otherwise 404 too).

## Quick start

1. Upload `change-login-url.php` (and `uninstall.php`) to
   `wp-content/plugins/wp-change-login-url/`.
2. Activate the plugin — rewrite rules flush automatically.
3. Go to **Settings → Change Login URL**, pick your slug, save.
4. Bookmark the new URL. Store the recovery key somewhere safe.

Slug rules: lowercase letters, numbers and hyphens only; reserved slugs
(`wp-admin`, `wp-login`, `admin`, `login`, …) are rejected.

## Files

- `change-login-url.php` — the whole plugin
- `uninstall.php` — drops the `wcl_settings` option on uninstall

## Notes

- Pretty permalinks must be enabled (the slug works via a rewrite rule).
- Deactivating the plugin flushes rewrite rules and restores the default
  `wp-login.php` behavior.
- This is **obscurity, not a replacement** for strong passwords, 2FA and
  login-attempt limiting.

## Requirements

WordPress 5.0+, PHP 7.4+.

## License

MIT — see `LICENSE`.
