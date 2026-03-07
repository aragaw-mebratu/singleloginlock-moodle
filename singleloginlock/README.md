# local_singleloginlock

Single active login control for Moodle with role-based enforcement, stale-session tolerance, and optional one-time takeover override.

This plugin is designed for clustered Moodle deployments (multiple web nodes/pods) using shared session storage (DB sessions or Redis-compatible handlers such as Dragonfly/Redis).

## What This Plugin Does

When a user logs in, the plugin checks whether another session for the same user is still considered active.

- If another active session exists, the new login is blocked.
- If the user has takeover override enabled through a custom profile checkbox, the new login is allowed and older sessions are terminated.
- If no active conflicting session exists, login is allowed.

The plugin uses two time concepts:

- Heartbeat interval: browser sends heartbeat every 2 minutes.
- Active session window: a session is considered active for up to 5 minutes since last heartbeat.

This avoids permanent lockout if a browser/device closes unexpectedly.

## Compatibility

## Moodle 5 package

- ZIP: `singleloginlock-moodle50.zip`
- Requires Moodle 5.0+ (`$plugin->requires = 2024042200`)

## Moodle 4 package

- ZIP: `singleloginlock-moodle4.zip`
- Requires Moodle 4.0+ (`$plugin->requires = 2022041900`)

Notes:

- Do not install both packages at the same time.
- Use only the package matching your Moodle major version.

## Core Behavior

## Enforcement scope

Enforcement applies only to users considered "enforced":

- Site admins are exempt.
- Users with capability `local/singleloginlock:enforce` at system context are enforced.
- Fallback: users assigned a role with archetype `student` or shortname `student` are enforced.

## Login decision flow

1. User submits credentials.
2. Moodle logs in user and fires `\core\event\user_loggedin`.
3. Plugin observer runs.
4. Plugin checks if another active session exists for that user.
5. If conflict:
- If profile checkbox override is enabled, plugin destroys older sessions and allows this login.
- Otherwise plugin terminates current (new) session and redirects to blocked page.
6. If no conflict, plugin marks current session as active.

## Heartbeat flow

For enforced logged-in users, plugin injects JS that sends heartbeat request every 120000 ms:

- Endpoint: `/local/singleloginlock/ping.php`
- Updates "last seen" timestamp for currently active SID.

Session considered stale when no heartbeat within 300 seconds.

## User-Facing Messages

Blocked login message:

`Login blocked: this account is currently active on another device.`

Blocked users are redirected to:

- `/local/singleloginlock/blocked.php`

This page shows the message and a "Login" button.

## File Map

- `version.php`: plugin metadata and version.
- `db/events.php`: observer registration for `user_loggedin`.
- `db/access.php`: capability definitions.
- `classes/observer.php`: login conflict logic.
- `classes/session_guard.php`: enforcement and session state logic.
- `lib.php`: heartbeat JS injection callback.
- `ping.php`: heartbeat endpoint.
- `blocked.php`: dedicated blocked login page.
- `classes/privacy/provider.php`: privacy API null provider.
- `lang/en/local_singleloginlock.php`: language strings.

## Stored State

The plugin stores lightweight per-user state in user preferences:

- `local_singleloginlock_activesid`: authoritative active session SID.
- `local_singleloginlock_lastseen`: UNIX timestamp of latest valid heartbeat.

For optional takeover checkbox:

- Custom profile field shortname expected: `singleloginlock_allowlogin`
- Datatype expected: `checkbox`
- Value `1` means takeover allowed for next login conflict.
- Value is auto-reset to `0` after successful login.

## Installation (Zip Upload)

1. Go to `Site administration > Plugins > Install plugins`.
2. Upload the correct ZIP for your Moodle major version.
3. Confirm installation and run upgrade.
4. Purge caches.

CLI alternative:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Configuration Steps

## Step 1: Role enforcement policy

Set capability `local/singleloginlock:enforce` according to your policy.

Common policy:

- Students: Allow
- Teachers/Managers/Admins: Not set or Prohibit

Site admins are exempt by plugin logic.

## Plugin enable/disable (no uninstall required)

You can toggle plugin runtime behavior from admin settings:

- Setting: `Enable plugin`
- Location: `Site administration > Plugins > Single login lock > Single login lock`

When disabled:

- New login conflicts are not blocked.
- Heartbeat logic does not run.
- Blocked page redirects to standard login page.

When re-enabled:

- Normal enforcement resumes immediately.
- Enforced users with more than one active session are logged out (all their sessions are terminated), so they must login again with a single active session.
- If a stale page remains open, the next request/heartbeat also forces logout for that multi-session user.

## Manage page

Plugin management page:

- Location: `Site administration > Plugins > Single login lock > Manage`
- Shows columns: `Name`, `Version`, `Enable`, `Settings`, `Uninstall`
- Enable/Disable uses Moodle eye icons (`show/hide`) for a standard toggle experience.

## Step 2: Optional takeover override checkbox

Plugin install/upgrade auto-creates this custom profile field if missing:

- Type: Checkbox
- Shortname: `singleloginlock_allowlogin`

If checked for a user, conflict login can take over (new session wins, older sessions terminated), then the checkbox auto-unchecks.

## Security Design

Implemented controls:

- `defined('MOODLE_INTERNAL') || die()` in internal files.
- Session conflict decisions executed server-side in observer.
- Heartbeat endpoint requires authenticated session.
- Heartbeat endpoint enforces `require_sesskey()` to mitigate cross-site keepalive abuse.
- Blocked page has no-store/no-cache headers.
- Uses Moodle session APIs (`session_exists`, `destroy_user_sessions`, `terminate_current`) rather than direct session-table writes.

Operational assumptions:

- TLS/HTTPS enabled.
- Standard Moodle session security settings are enabled.
- Shared session backend across all Moodle nodes.

## Performance Notes

Heartbeat default:

- 1 request per enforced active user every 2 minutes.

Rough estimate:

- 6,000 enforced active users => ~50 heartbeat requests/second average.

Endpoint is lightweight JSON and typically inexpensive; main costs are request handling and user preference updates.

## Cluster and Cache Compatibility

Works with:

- DB-backed Moodle sessions
- Redis/Dragonfly-backed Moodle sessions
- Multi-node Kubernetes/pod deployments

Requirement:

- All nodes must share the same session store and DB.

## Testing Checklist

## Functional

1. Login as enforced user on Device A.
2. Without logging out on A, login on Device B.
3. Verify B is blocked and redirected to blocked page.
4. Wait >5 minutes without heartbeat from A and retry on B.
5. Verify B can now login.

## Override

1. Enable profile checkbox `singleloginlock_allowlogin` for user.
2. Repeat conflict login.
3. Verify B is allowed and A is logged out.
4. Verify checkbox auto-resets to unchecked.

## Exemption

1. Login as site admin on A.
2. Login same admin on B.
3. Verify no block (admin exempt).

## Troubleshooting

## "Second login is still allowed for students"

- Confirm role assignment exists for student user.
- Confirm role archetype/shortname is standard (`student`) or assign capability `local/singleloginlock:enforce`.
- Purge caches after role/capability changes.

## "Message not shown"

- Confirm redirection reaches `/local/singleloginlock/blocked.php`.
- Confirm plugin version upgraded and caches purged.

## "Users remain blocked too long"

- Check browser/device activity on old session.
- Verify heartbeat requests are reaching `ping.php`.
- Confirm server clocks are synchronized (NTP).

## Upgrade Notes

Always:

1. Replace plugin code with new package.
2. Run Moodle upgrade.
3. Purge caches.

## License

This plugin is distributed under GNU GPL v3 or later. See `LICENSE`.

## Uninstall

Uninstall from Moodle plugins UI.

Data impact:

- Plugin stores small user preference keys.
- Standard Moodle uninstall cleanup applies.

## Limitations

- This is not real-time push logout (no WebSocket/SSE).
- Enforcement timing depends on heartbeat interval and active window.
- If custom SSO/auth plugins alter normal login/session flow, additional integration testing is required.
