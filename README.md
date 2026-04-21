# Custom Emails

WordPress plugin that captures and lets you edit every system-generated email from **WordPress core**, **BuddyPress**, and **bbPress** in one admin UI.

## Features

- **Core overrides**: password reset, new user (to user + admin), password change notice, email change notice, comment notification, comment moderation
- **bbPress overrides**: topic-subscription replies and forum-subscription new topics
- **BuddyPress bridge**: lists every `bp-email` post and deep-links to BP's native editor
- **Discovery logger**: 7-day rolling `wp_mail` capture with fingerprint grouping to auto-surface unknown emails (incl. third-party plugins)
- **Promote to registry**: one-click convert a logged email into an editable override matched by subject pattern
- **Global HTML wrapper**: optional header/footer applied to every outgoing message with auto Content-Type switch
- **Send test email**: per-email test with placeholder token substitution
- **Export / Import**: JSON round-trip for staging -> production migrations
- **WP-CLI**: `wp custom-emails list|test|export|import|reset`

## Installation

1. Upload the `custom-emails` folder to `wp-content/plugins/`
2. Activate **Custom Emails** in the Plugins screen
3. Go to **Custom Emails** in the admin menu

## Structure

```
custom-emails/
├── custom-emails.php          Plugin bootstrap
└── includes/
    ├── class-ce-registry.php  Catalog of overridable emails
    ├── class-ce-store.php     Options-table storage of overrides
    ├── class-ce-renderer.php  Token substitution
    ├── class-ce-wrapper.php   Global header/footer on wp_mail
    ├── class-ce-logger.php    Discovery: capture outgoing mail
    ├── class-ce-admin.php     List/edit UI + Send test
    ├── class-ce-settings.php  Settings + Discovery + Promote
    ├── class-ce-exporter.php  Export / Import JSON
    ├── class-ce-cli.php       WP-CLI commands
    └── interceptors/
        ├── core-password-reset.php
        ├── core-new-user.php
        ├── core-profile.php   password_change_email + email_change_email
        ├── core-comments.php  notification + moderation
        ├── bbp-subscriptions.php
        ├── bp-bridge.php
        └── promoted-catchall.php  wp_mail fallback for promoted emails
```

## WP-CLI examples

```bash
wp custom-emails list
wp custom-emails test core_password_reset --to=you@example.com
wp custom-emails export > backup.json
wp custom-emails import backup.json
wp custom-emails reset core_password_reset
```

## How it works

Two-tier interception:

1. **Specific filters** (clean, preferred): `retrieve_password_message`, `wp_new_user_notification_email`, `comment_notification_text`, `bbp_subscription_mail_message`, etc.
2. **`wp_mail` catch-all** (safety net): matches by subject fingerprint, used for promoted/discovered emails coming from third-party plugins.

BuddyPress already has a `bp-email` post-type editor, so the plugin surfaces those in its own list and routes the Edit button to BP's native screen.

## License

GPL-2.0-or-later (matching WordPress).
