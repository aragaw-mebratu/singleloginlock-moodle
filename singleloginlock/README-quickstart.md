# Single Login Lock Quickstart

Minimal setup guide for admins.

## 1) Choose the right package

- Moodle 5.x: `singleloginlock-moodle50.zip`
- Moodle 4.x: `singleloginlock-moodle4.zip`

Install only one package.

## 2) Install plugin

1. Go to `Site administration > Plugins > Install plugins`.
2. Upload the ZIP.
3. Complete upgrade.
4. Purge caches.

CLI:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## 3) Configure who is enforced

Capability: `local/singleloginlock:enforce`

Typical policy:

- Student: `Allow`
- Teacher/Manager/Admin: `Not set` or `Prohibit`

Site admins are exempt by plugin logic.

## Plugin on/off switch

You can disable behavior without uninstall:

- Go to plugin settings and set `Enable plugin` to disabled.
- Re-enable anytime from the same setting.
- On re-enable, users with multiple active sessions are logged out and must login again.

## 4) Optional takeover checkbox

From `1.13.0+`, this custom profile checkbox is auto-created on install/upgrade if missing:

- Type: `Checkbox`
- Shortname: `singleloginlock_allowlogin`

Check it for a user when you want to allow takeover; after successful login, plugin auto-unchecks it.

## 5) Current defaults

- Heartbeat interval: 2 minutes
- Active session window: 5 minutes
- Block message page: `/local/singleloginlock/blocked.php`

## 6) Quick test

1. Login as enforced user on Device A.
2. Login same user on Device B.
3. Expect Device B blocked with message page.
4. Check takeover checkbox and retry on Device B.
5. Expect Device B login allowed, Device A logged out.
6. Verify checkbox auto-reset to unchecked.

## 7) Common fixes

- Behavior not updating: run upgrade + purge caches.
- Students not enforced: verify role/capability assignment.
- Message missing: verify redirect reaches `/local/singleloginlock/blocked.php`.
