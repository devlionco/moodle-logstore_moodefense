# Changelog

## Unreleased

- Incident type 10, Moodle Security Announcement Match, is part of the free version again: it raises
  an issue when a moodle.org security announcement lists the installed Moodle version as affected.
  It needs the Moodefense API, configured by the new *API URL* and *Token for API* settings (with a
  *Test API* button), and runs daily in its own scheduled task, *MooDefense Security Announcement
  Check*. *Security announcement severities to notify* picks which severities notify.

## 2.1.0 - 2026-10-08

Built for large sites. Detection, the issues page and the Excel export no longer slow down as the log
and the issues grow: the number of queries is fixed per batch instead of growing with every issue,
and every query reads an index. Measured on a test site with 3.1 million log entries and 500,000
issues:

| | 2.0.0 | 2.1.0 |
|---|---|---|
| Shared-device detection (algorithm 2, 3,000 devices) | hours | under 1 s |
| Multiple IP addresses detection (algorithm 4) | 6.6 s of SQL + 3 queries per issue | 0.2-0.6 s |
| Password guessing detection (algorithm 7) | 8.1 s of SQL + 4 queries per issue | 0.1-0.4 s |
| Issues page, page 500 | 0.8-2.3 s | 0.02 s |
| Dashboard, latest issues | 1.1 s | under 0.01 s |
| Excel export of all issues | ran out of memory (est. 8 min, 4 GB) | 30 s, 178 MB |

### Performance

- Algorithms fetch what they need in batches: first logins, whether an attacked account then logged
  in, the issues already stored, user roles and notification recipients are each one query per run
  (or per 1,000 issues) instead of one or more per issue.
- An issue is matched to its incident by a new indexed `issuekey` column instead of `LIKE` on its
  JSON data.
- The log indexes match the detection queries, which read covering indexes. Five indexes no query
  used (`origin`, `useragent`, `useragentint`, `sessionidentifier`, `ipint`) are dropped, which makes
  writing the log cheaper on every request.
- The issues page picks a page's issues from an index before loading them, and counts without joining
  users unless filtering by name. The dashboard finds the latest issues without sorting the table.
- The Excel export is streamed (Moodle's dataformat API) instead of built in memory.

### Security and privacy

- Changing an issue and clearing all issues are logged as events (`issue_updated`, `issues_cleared`),
  so deleting the evidence leaves a trace.
- Requests to delete a user's data delete their log entries but keep the issues: they are the
  evidence of security incidents, kept for the site's legitimate interest in its security, so a
  suspect cannot have them erased. Issues are still exported. Documented in the privacy metadata and
  the README.
- Moodle notifications about issues only go to users who may view issues. An account that merely
  shares a configured email address is emailed, but gets no notification.
- The issue details dialogue reads its data without decoding HTML entities twice.

### Fixed

- Password guessing went undetected when "Log guest access" was off: failed logins are now always
  logged.
- Shared-device detection reported only one pair of users per device; it reports every user of a
  shared device, with the evidence of their logins on that device (not their first login anywhere).
- On MySQL and MariaDB, long lists were cut at 1,024 bytes: the IP addresses of a multiple-IP issue
  and the users of a shared device.
- Two devices could get the same identifier (a 32-bit checksum without a separator between IP
  address and user agent); it is now a 60-bit hash of both.
- The Excel export failed with an error on large sites.
- A failing algorithm went unnoticed: it is now reported, with its stack trace, in the task log; the
  other algorithms still run, and the task does not fail, so Moodle does not postpone them all.
- Upgrading from before 2.0.0 deleted the issues of the free incident types too, with their comments.
- The plain-text notifications showed a literal `<b>` tag around the algorithm name.

### Changed

- The issues table can be sorted by number (№), newest or oldest first. The order is kept by paging,
  the filters and the Excel export, which exports exactly the issues the filters select.
- The issue details dialogue shows every field for every incident type.
- A refreshed issue that became more severe always notifies again (previously only for password
  guessing).
- The permitted countries for admin logins (algorithm 5) default to the site's default country
  instead of IL.
- The test email reports its errors in the site language.
- The free version banner uses Bootstrap utilities: the plugin adds no styles to other pages.

### Upgrade notes

- The upgrade rebuilds the indexes of the log and issues tables and fills in `issuekey` for existing
  issues. On a large site this takes a few minutes (about 6 minutes for 3 million log entries and
  500,000 issues).
- Device identifiers are computed differently: for the 4 hours after upgrading, a device used both
  before and after it counts as two devices for shared-device detection.
- Shared-device detection may now raise more issues than before, as it reports every user of a
  device.
- Language string identifiers `alotofip`, `brutforcewithoutrole`, `brutforcewithsameip` and
  `paidalgorithm_contact` are now `manyips`, `bruteforceunknownuser`, `bruteforce` and
  `premiumalgorithm_contact`.

### For developers

- Severities, statuses and algorithms are enums (`local\severity`, `local\status`,
  `local\algorithm_type`). The base `algorithm` class builds, stores and notifies about issues;
  each algorithm keeps its own queries.
- Issue evidence is stored as JSON with invalid UTF-8 (e.g. in a user agent) substituted.
- The issues table is a template (`issues`), with its styles in `css/issues.css`.
- Removed: the per-algorithm modal field manifest, `\logstore_moodefense\process` (the task runs
  the algorithms) and the cross-database `GROUP_CONCAT(DISTINCT)` helper.

## 2.0.0 - 2026-09-30

The plugin now ships only the free incident types. The paid ones are part of MooDefense Premium.

### Removed

- Incident types 1, 3, 6, 8, 9 and 10, which ran SQL downloaded from the Moodefense API or needed it
  (file integrity, security announcements). With them went the security announcement task, the file
  review page (and Tabulator), the "rebuild" web service and button, and the API response cache.
- **Upgrading deletes all recorded issues** and deselects the removed incident types.

### Changed

- "Active incident types" and "Algorithms with notifications" are one table: each free incident type
  with its description and a Detect and a Notify checkbox (Notify only while it is detected), then
  the premium incident types, which show how to get MooDefense Premium.
- No external service is used any more: countries of IP addresses are looked up in the site's own
  GeoIP2 database (Location settings) when there is one, otherwise in the GeoLite2 City database
  shipped again in `source/`. The API URL and token settings and "Test API" are gone; the test email
  web service is now `logstore_moodefense_test_email`.
- Clearer messages for the shared-device and multiple-IP-addresses incident types.
- Phones: the issues and latest-issues tables show each issue as a card, the details dialogue fits
  the screen, and the issues page filters and actions rearrange to the width. On laptops the
  issues table fits the page instead of scrolling sideways.
- The issues page filters are one compact row that can be collapsed, with the result count and
  the export, clear and filter buttons above it; issues have a number (№) column.
- A free version banner on the dashboard and the settings page.
- Stable maturity; supports Moodle 4.5 to 5.2. CSS passes Moodle's stylelint (no !important) and
  PHP the moodle-extra coding standard; GitHub Actions run moodle-plugin-ci on PostgreSQL and
  MariaDB.

### Fixed

- Stored XSS: a URL in an issue message was printed unescaped as its own link text on the issues
  page and in the details dialogue (`algorithm::linkify()`).
- Privacy API: requests now go through tool_log like any log store (`logstore_provider`), per event
  context; log entries where the user is the related or real ("login as") user and shared-device
  issues naming the user as the suspect are now found, exported and deleted; exported logs keep the
  user agent; all stored fields and the admin notifications are declared in the metadata.
- Missing name of the IP-to-country cache on the cache administration page.
- Detection only ran between 01:00 and 01:59, so most incidents were never found: the processing
  task now runs every 5 minutes.
- User details in the HTML notifications were not escaped.
- Two queries failed on SQL Server (distinct lists of values, roles ordered by sort order).
- `perpage=0` on the issues page loaded every issue and failed on a division by zero.
- "Clear all history" is only offered to users who may clear it.

## 1.4.0 - 2026-09-26

Plugin directory preparation: code clean-up, bug fixes, and PHPUnit/Behat coverage of the free
incident types.

### Fixed

- A resolved or "not relevant" issue was reopened on every processing run for as long as the events
  behind it stayed inside the algorithm's look-back window. Existing issues are now only refreshed
  (and reopened) when newer evidence arrives (`algorithm::save_issue()`).
- Algorithm 2 matched an existing issue for suspect user 12 when looking for suspect user 1, and
  algorithm 7 matched IP 10.0.0.12 when looking for 10.0.0.1, merging unrelated incidents.
- Algorithm 4 flagged not-logged-in and guest traffic (all sharing one userid) as one user hopping
  between IP addresses, and silently dropped rows for users seen with several user agents.
- Algorithms 4 and 5 showed wrong addresses for IPv6 clients (`long2ip()` of a hash).
- Algorithm 5 queried the Moodefense API for every login on every run, even with no API configured;
  country lookups now go through the cached `geoip` helper, shared with the details dialogue.
- User agents longer than 255 characters made writing the log entry fail.
- The site admin list kept growing with every algorithm instance (static array appended to).
- The processing task could skip a run because the log manager's cached store list was out of date.
- `save_modal_data` declared top-level optional parameters (debugging notice on every call) and
  accepted any integer as a status.
- Filter labels on the issues page were not associated with their dropdowns.
- Severity badges failed WCAG AA colour contrast; dashboard icons never rendered (Bootstrap Icons
  was not loaded there).
- Double HTML-escaping of the user agent in the details dialogue.

### Changed

- The issues page filters, counts and pages in SQL instead of loading every issue into memory; the
  Excel export applies exactly the same filters (`local\issues_filter`). Unused sort parameters removed.
- Shared insert/refresh/notify logic of all algorithms moved into `algorithm::save_issue()`; the
  free algorithms use plain inline SQL and expose their thresholds as class constants.
- Removed the unused 63 MB `source/GeoLite2-City.mmdb` (MaxMind's licence does not allow
  redistributing it), Bootstrap Icons (replaced by Moodle's Font Awesome), unused language strings
  and dead code. Tabulator moved to `javascript/` and its version corrected in `thirdpartylibs.xml`.
- The incident type descriptions on the settings page are generated from the `algorithmN_case`
  strings instead of a duplicated HTML table; descriptions corrected to match the actual thresholds.
- Hard-coded English ("System", "N/A", "more"/"less", severities, web service messages) moved to
  language strings; language file sorted.
- Code style: moodle-extra phpcs clean.

### Added

- PHPUnit tests for the free algorithms (2, 4, 5, 7), log store, GeoIP lookup, notifier, web
  services, privacy provider, tasks and issues filter; Behat scenarios for the issues page, the
  dashboard (including accessibility checks), and an end-to-end brute-force detection.
- Test data generators (`tests/generator`) for log entries and issues.

## 1.3.0 - 2026-08-20

### Added: moodle.org security announcement matching

Adds an automated pipeline that watches moodle.org's official security announcements and raises
an issue in MooDefense whenever a published announcement's affected version range includes this
site's installed Moodle version.

**AWS infrastructure (`cloudformation.yaml`, new file, deployed as stack `moodefense-security-pipeline`)**

- New DynamoDB table `moodefense` (`id` as partition key, on-demand billing) storing parsed
  security announcement posts.
- New Lambda `moodefensefetchposts` (Python 3.11): fetches the moodle.org security RSS feed,
  parses each post's title/author/date/description and its "Severity/Risk", "Versions affected",
  "Versions fixed", "Reported by", "CVE identifier", "Changes (main)" and "Tracker issue" fields,
  and upserts them into the `moodefense` table. Also pre-parses "Versions affected" free text
  (e.g. "5.1 to 5.1.4", "4.5 and earlier unsupported versions") into simple
  `versions_affected_ranges: [{min, max}]` pairs so the Moodle-side comparison is a single
  `version_compare()` call, with no text parsing required there.
- Triggered daily at 00:00 UTC via a new `AWS::Events::Rule` (EventBridge/CloudWatch Events),
  ahead of the Moodle-side task's 09:00 run.
- New Lambda `apimoodefensesecurityissues` (Python 3.11): scans the `moodefense` table and
  returns its contents as JSON. Wired into the existing `moodefense` API Gateway (referenced by
  ID, not managed by this stack) as a new `/securityissues` resource, behind the same API-key
  requirement as the existing `/getalgorithms` and `/geoip` endpoints.
- Dedicated IAM roles for both Lambdas (write-only and read-only DynamoDB access respectively).
- Fixed an RSS/HTML parsing bug in `moodefensefetchposts` where posts using `<table style="...">`
  or `<tr style="...">` (with inline style attributes, as opposed to bare `<table>`/`<tr>`) lost
  all of their parsed fields (severity, versions, CVE, etc.). Regressed two real announcements
  (`MSA-26-0022`, `MSA-26-0024`) before the fix.
- One-time historical backfill (local script, not part of the deployed stack): imported all 89
  historical announcements whose affected-version range touches the 4.5 branch, sourced from
  `moodle.org/security/index.php?o=3&s=10&p=N` (paginated, full post content, unlike the RSS
  feed which only exposes the last ~20 posts and unlike individual forum discussion pages which
  return HTTP 403 to non-browser requests). Upserted by the same `id`, so no duplicates were
  created against the posts already fetched via RSS.

**Moodle plugin**

- `classes/api.php`: new `api::getsecurityissues()`, calling the `/securityissues` endpoint the
  same way `api::getdata()` calls `/getalgorithms` (cached, same `x-api-key` auth).
- `classes/algorithms/algorithm.php`:
  - New `SECURITY_ANNOUNCEMENT_TASK_ISSUE` (`100`) issue-type identifier. Not a detection
    algorithm ID - deliberately kept well outside the 1-9 range used by the numbered algorithms
    so it can never collide with a future `algorithm_10`, `algorithm_11`, etc.
  - New `version_in_range()`: checks an installed Moodle release against the
    `versions_affected_ranges` computed by the Lambda, via `version_compare()`.
  - New `linkify()`: escapes text for safe HTML output while turning any `http(s)://` URLs within
    it into clickable links - used to make the source-post URL in an issue's message clickable in
    both the issues table and its details modal.
- `classes/notifier.php` (new): admin-notification sending (Moodle notification + optional email)
  extracted out of `algorithm` into its own class, so message formatting/delivery isn't tangled
  up with detection logic. `notifier::notify()` replaces the old `algorithm::adminsnotify()` and
  is `static`, so it can be called by non-algorithm callers (the new scheduled task) as well as by
  every `algorithm_1`..`algorithm_9` subclass (all 18 existing call sites were updated to
  `notifier::notify(..., algorithmid: $this->algorithm)`, preserving their existing
  `algorithms_notifications` gating). Also adds a third message template variant
  (`*_withlink`) that shows an extra source-post link above the "View table of issues" link,
  selected automatically when an `$extralink` is passed in.
- `classes/task/security_announcement_task.php` (new): scheduled task, runs daily at 09:00
  (`db/tasks.php`). Fetches announcements via `api::getsecurityissues()`, filters by the
  `securityannouncementseverities` setting, checks `algorithm::version_in_range()` against
  `$CFG->release`, and inserts one `logstore_moodefense_issues` row per newly-matched ticket
  (`algorithm = SECURITY_ANNOUNCEMENT_TASK_ISSUE`, `userid = 0`, dedup by `ticket_id` stored in
  `data`), notifying admins once per ticket via `notifier::notify()` with the post's own link.
- `settings.php`: new `securityannouncementseverities` multiselect setting. Its options are keyed
  by the exact severity labels moodle.org itself uses (`Minor`, `Serious`, `Critical`), matching
  the `severity` value returned by the API, so filtering needs no translation table. When an issue
  is created, the severity is mapped to the plugin's existing internal scale for storage
  (`Minor` → `Low`, `Serious` → `High`, `Critical` → `Critical`) so it shows up correctly in the
  existing Issues table/dashboard filters without any changes to their vocabulary.
- `issues.php` / `dashboard.php`: added `Low` to the severity filter dropdown and dashboard
  chart/ordering (previously only `Critical`/`High`/`Medium`); extended the existing
  "show 'System' instead of a username" rule (previously only for `ALGORITHM_FILE_INTEGRITY`) to
  also cover `SECURITY_ANNOUNCEMENT_TASK_ISSUE`, since neither issue type is tied to a specific
  user; and special-cased the incident-type label/description lookup for
  `SECURITY_ANNOUNCEMENT_TASK_ISSUE` (`securityannouncementtype`/`_case` strings) instead of the
  generic `algorithm{N}` pattern, since `100` isn't a real algorithm ID. The issue message is now
  run through `algorithm::linkify()` so its source-post link is clickable.
- `lang/en/logstore_moodefense.php`, `lang/he/logstore_moodefense.php`: strings for the new issue
  type (`securityannouncementtype`, `securityannouncementtype_case`), task name
  (`tasksecurityannouncement`), setting (`securityannouncementseverities`,
  `securityannouncementseverities_desc`), severities (`severitylow`, `severityserious`,
  `severityminor`), the issue message template (`securityannouncementmessage`), and the
  `*_withlink` notification templates.
- `version.php`: bumped to `2026082001` / release `1.3.0`.

### Verified

- Parsing validated against real moodle.org RSS/index data (20 recent + 89 historical posts).
- Version-range matching validated against known-affected/known-fixed version pairs, including
  open-ended ("X and earlier unsupported versions") and bare-branch ("5.2") forms.
- Dedup verified: re-running the scheduled task creates no duplicate issues or notifications.
- Severity filter verified: with only `Serious` selected, `Minor`-severity announcements create no
  issue and send no notification; restoring all three severities restores full coverage.
- Notification email verified: shows a clickable "View the source post" link above "View table of
  issues" when the task passes one.

### Fixed: stop logging CLI events

- `classes/log/store.php`: `is_event_ignored()` now always ignores events raised from CLI scripts
  (cron, CLI tools, etc). CLI events have no real IP or useragent to analyse (useragent is always
  `CLI/NoUserAgent`), so they were only adding noise to `logstore_moodefense_log` with no value for
  security incident detection. Previously CLI events were deliberately always logged, on the
  reasoning that there's no login to check there; guest/logged-out filtering (`logguests` setting)
  is unaffected and still applies the same as before for non-CLI requests.

### Fixed/improved: dashboard roles and severity charts

- `dashboard.php`: roles are now sorted by user count (site admins always first), and only grouped
  into an "Others" slice once there are more than `DASHBOARD_ROLES_LIST_LIMIT` (6) roles in total -
  with few roles, every one is shown as-is, including empty ones. Past that limit, empty roles and
  the lowest-count roles are the ones pushed into "Others" first. Each role gets a stable colour
  derived from a hash of its shortname, so the same role always has the same colour regardless of
  how many roles exist or their sort order.
- Fixed the severity bar chart ignoring the colours passed from PHP entirely (hardcoded to 3 colours
  for 4 severities); Critical/High/Medium/Low now consistently render red/orange/yellow/green,
  matching the severity badges in the issues table.
- Fixed "Moodle Security Announcement Match" issues always showing "Unknown" instead of "System" as
  the user name, caused by a strict type comparison against a value read as a string from the DB.
- The issues table is now rendered by the mustache template from structured data, instead of being
  built as an HTML string in PHP.
- `amd/src/dashboard.js`: both charts' hover/tap tooltips now share one implementation and one
  floating element, so they look and behave identically; a global dismiss-on-tap-outside handler
  was added so a tooltip tapped open on a touch device doesn't stay stuck on screen.
- `css/dashboard.css`: fixed the layout breaking once a site has many roles (missing vertical gap
  between the two top cards once they stack below Bootstrap's `lg` breakpoint - this theme's
  Bootstrap 4 doesn't support the `.g-4` gap utility used in the markup; the roles legend is now
  height-capped with internal scrolling instead of growing the card indefinitely) and made the
  "Others" tooltip render in two columns.
- Fixed the "Others" legend control (a `<button>`, so its tooltip is reachable by keyboard/tap, not
  just by mouse hover) picking up some host themes' default button hover/focus/active background,
  and fixed the Bootstrap tooltip's default 90% opacity letting page content bleed through behind
  the roles breakdown.
- Fixed the roles-chart tooltip showing the role's user count twice (e.g. "admins (5): 5 users").

### Fixed: security-announcement false positives from open-ended version ranges

`moodefensefetchposts` (AWS Lambda) could not derive an upper bound from "Versions affected" text
when it named the newest branch with no explicit upper bound (e.g. plain "5.2" rather than
"5.1 to 5.1.4"), and fell back to an open-ended `X.999999` sentinel for that branch - without
consulting the announcement's own "Versions fixed" text, which already gives the precise fix
version for that same branch. This made `algorithm_10` treat every patch release of the branch as
permanently vulnerable, long after the real fix had shipped: 41 of the 90 announcements in the
`moodefense` DynamoDB table were affected, and the currently-installed 4.5.13 was (incorrectly)
matched by 7 announcements instead of the 1 that's actually still applicable.

- `cloudformation.yaml` (`moodefensefetchposts`): `parse_affected_ranges()` now takes the
  announcement's `versions_fixed` text too. For a bare-branch mention it looks up that branch's
  fix version (new `branch_of()`/`last_vulnerable_patch()` helpers) and uses "fix version minus
  one patch" as the upper bound, falling back to the old `.999999` sentinel only if no matching
  fix version was found at all. Deployed via `aws cloudformation deploy` to the
  `moodefense-security-pipeline` stack.
- One-time backfill (not part of the deployed stack): recomputed `versions_affected_ranges` via
  `UpdateItem` for the 41 existing DynamoDB rows the RSS feed itself won't refresh (it only
  exposes the last ~20 posts) - same logic as the Lambda fix, applied retroactively.
- `classes/algorithms/algorithm.php`: `version_in_range()` no longer needs the extra
  version-fixed-aware correction that briefly lived here while the Lambda fix was being validated
  - since ranges are now fully correct at the source, it's back to the original plain two-argument
  min/max bounds check.
- Verified with a from-scratch, independent re-parse of every announcement's raw
  "Versions affected"/"Versions fixed" text (not touching the pre-computed ranges at all) against
  a matrix of Moodle versions (3.9 through 5.2): the live API, the real `version_in_range()` code,
  and the independent parser all agree exactly, on every version, down to the individual
  announcement ID.

### Fixed: correctness bugs in algorithms 1, 6, 9 and the notification pipeline

- `algorithm.php::get_teachersids()`: guard against an empty `staffroles` setting.
  `$DB->get_in_or_equal()` throws on an empty array, and every algorithm's constructor calls this
  unconditionally, so an unset `staffroles` setting previously made it impossible to construct
  *any* algorithm at all.
- `algorithm.php::process_file()`: the file-integrity snapshot (`build.json`) is now keyed by file
  path instead of content hash, so two byte-identical files (empty files, duplicated `index.php`
  stubs, repeated `LICENSE` files) no longer collapse into a single entry and silently disappear
  from the snapshot.
- `algorithm_9.php`: a missing/corrupt `build.json` (unreadable or invalid JSON) is now detected
  and skipped with an `mtrace()` notice, instead of being treated as "nothing on record" and
  reporting every file under `$CFG->dirroot` as newly added.
- `algorithm_6.php`: restored the "geolocation lookup failed" guard that `algorithm_5.php` already
  had but this one was missing, which let a failed IP lookup fall through and raise a bogus
  incident with an empty country.
- `notifier.php`: looking up the suspected user now uses `IGNORE_MISSING` instead of `MUST_EXIST`,
  and a non-real/system user id (e.g. the virtual "noreply" user, id `-10`, which could surface
  here if a core event about that virtual user was captured as a log entry) is now treated the
  same as no user - falling back to the simple notification template instead of throwing and
  aborting the whole algorithm run. Also logs `Algorithm {id} complete. Send email to user:
  {email}: true/false` per recipient during a run, for visibility into task output.
- `issues.php`: cast `$record->algorithm` to `int` before the strict `in_array()` check that
  decides whether an issue is user-attributed or system-wide - it arrives from the DB layer as a
  string, so algorithm 9/10 rows were being treated as user-attributed.
- `security_announcement_task.php`: fixed a stray fragment of non-English text that had ended up
  in an `mtrace()` message; added a fallback for `explode(',', get_config(...))` returning `false`
  when the setting is unset (deprecated in PHP 8.1+).
- `process.php`: same `explode()`/`get_config()` fallback; dropped an unused constructor argument
  (`algorithm::__construct()` takes none).
- `amd/src/table.js`: `initFilterPanel()` now actually uses the `isOpenByDefault` argument it's
  called with (`issues.php` already passed one), instead of ignoring it and hardcoding the panel
  to always start open.

### Fixed: stored XSS and a missing capability check

- `review.php`: the four JSON blobs embedded into `<script type="application/json">` blocks by
  `templates/review.mustache` (triple-mustache, unescaped) are now encoded with
  `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`. Without this, a file path
  containing `</script><script>...` - i.e. exactly the kind of file algorithm 9 exists to flag -
  could inject and run script in the browser of the admin reviewing the file-integrity page.
- `templates/dashboard.mustache`: `chartdata`/`severitydata` now render via double-mustache
  (`{{chartdata}}`) instead of triple (`{{{chartdata}}}`) into the `data-chartdata`/
  `data-severitydata` attributes; `amd/src/dashboard.js` already reads them back via
  `element.getAttribute()`, which round-trips HTML-escaped JSON correctly. Without this, a role
  renamed to include `' onmouseover=...` would break out of the attribute and inject markup for
  every dashboard viewer.
- `classes/external/get_country_code.php`: added the `require_capability('logstore/moodefense:view', ...)`
  check this web service was missing - its capability was declared in `db/services.php` but never
  actually enforced, so any authenticated user could call it as a free IP-geolocation proxy backed
  by the site's paid Moodefense API quota.

### Added: privacy disclosure for external data sharing

`classes/privacy/provider.php` declared its two local tables but never disclosed that the plugin
also sends personal data off-site: IP addresses to the Moodefense API (algorithms 5/6, the
country-code web service) for geolocation, and the affected user's full name/username/ID
number/last access/last IP by email to the addresses in "Emails to send notifications" (or to
site admins, if none are configured). Under the Moodle plugin checklist this is an approval
blocker for external-system integration.

- Added `add_external_location_link()` entries for both destinations (`moodefense_api`,
  `moodefense_email`), and completed both tables' field lists (`logstore_moodefense_log` was
  missing `useragentint`, `sessionidentifier`, `realuserid`, `relateduserid`, `courseid`, `other`;
  `logstore_moodefense_issues` was missing `message`, `comment`, `severity`).
- `lang/en/logstore_moodefense.php`: added the corresponding `privacy:metadata:*` strings.

### Added: algorithm-driven issue-details modal, moved to Mustache/CSS

Each algorithm now declares which modal fields apply to its issues
(`algorithm::get_modal_fields()`/`$modalredirect`), so `issues.php` only ships the data an
algorithm's manifest actually needs and `table.js` renders whatever fields it's given with no
per-algorithm branching.

- The modal body moves out of `table.js` string-concatenation into
  `templates/issue_details.mustache`; its inline styles move into `css/issues.css`
  (`.moodefense-modal`/`.moodefense-details`).
- The deprecated `core/modal_factory` is replaced with `core/modal_save_cancel`.
- Fixed "Class curl not found" in `get_country_code.php`, `algorithm_5.php` and `algorithm_6.php`:
  `filelib.php` isn't guaranteed loaded on the web-service/cron bootstrap path these run on.

### Fixed: PostgreSQL compatibility in algorithms 2, 4, 5, 7

Raw SQL in these free-tier algorithms used MySQL-only syntax that fails outright on Postgres -
found and verified by standing up a parallel Postgres-backed install of this same plugin/Moodle
version and running every algorithm end to end against synthetic incidents.

- `GROUP_CONCAT(DISTINCT ...)` doesn't exist on Postgres at all (algorithms 2, 4, 5). Added
  `algorithm::sql_group_concat_distinct()`, a cross-database helper (the same approach core uses
  in `core_reportbuilder\local\aggregation\groupconcatdistinct`), since
  `moodle_database::sql_group_concat()` has no `DISTINCT` support.
- `GROUP_CONCAT(... SEPARATOR '|')` is MySQL-only syntax (algorithm 4); switched to
  `$DB->sql_group_concat()`.
- Non-aggregated columns selected alongside `GROUP BY` (algorithms 4, 5, 7) are silently
  tolerated by MySQL but rejected outright by Postgres, which enforces the ANSI SQL `GROUP BY`
  rules; wrapped them in `MAX()`/aggregated equivalents.
- Verified end to end on both PostgreSQL and MySQL: all 10 algorithms run error-free and detect
  equivalent synthetic incidents on both databases.

### Fixed: ESLint global reference blocking the AMD grunt build

`review.js` references the third-party `Tabulator` constructor (loaded as a plain global
`<script>`, not an AMD dependency) without declaring it, which `grunt amd`'s `eslint:amd` step
flags as `no-undef` and aborts on - identical to how `dashboard.js` already declares
`/* global Chart */` for Chart.js. Added the matching `/* global Tabulator */`.

### Fixed: issue-details modal blocking on N+1 country-code lookups

Every IP shown in the modal (up to a couple dozen for a "multiple IP addresses" incident)
triggered its own `get_country_code` web-service call, each capable of falling back to a live
external API request when local GeoIP2 can't resolve it - the modal waited on all of them before
it could even open. Measured one such call at 597ms.

- `amd/src/table.js`: only await country codes for the first 3 IPs shown by default; the rest
  resolve in the background after the modal is already visible and get patched into the DOM as
  they arrive.
- `classes/external/get_country_code.php` + `db/caches.php`: cache lookup results per IP (new
  `geoip` cache area, 30 day TTL, since an IP's geolocation essentially never changes). Repeat
  lookups of the same IP: 597ms -> 1.1ms.
- Measured effect: modal open time on a 12-IP incident went from several seconds (blocked on cold
  external calls) to ~0.2s once the visible IPs are cached.

### Fixed: stale plugin stylesheets surviving a version upgrade

`issues.php`, `dashboard.php` and `review.php` each linked their CSS as a bare static path with no
theme-rev cache busting, unlike Moodle's own theme CSS. Apache serves them with only
`Last-Modified`/`ETag` and no `Cache-Control` header, so a browser can keep serving a cached copy
on its own heuristic freshness lifetime, surviving even a normal (non-hard) refresh across a
plugin upgrade. Concretely: the `.moodefense-modal` width rule added to `css/issues.css` above was
invisible in a browser that had cached the pre-upgrade file, making the details modal render at
Bootstrap's wider default instead of the intended 700px - looked like a regression but was
actually stale CSS. Appended the plugin version as a query param to each of these links so the URL
itself changes whenever the CSS does.

### Fixed: file-integrity check breaking on old snapshots and large trees

- `algorithm_9.php`: a `build.json` saved by a pre-refactor version of this plugin is keyed by
  content hash rather than by path, with no `hash` field in its entries at all. Comparing that
  against the current path-keyed scan wouldn't just warn - literally nothing would match, so
  every file in `dirroot` would be reported as both "deleted" (old) and "added" (current).
  Detected from the first entry and bailed out the same way as an already-handled
  corrupt/truncated snapshot, instead of flooding the issues table. This matters because
  `db/install.php` only builds the initial snapshot on a *fresh* install - any site upgrading from
  before this plugin's path-keyed refactor keeps its old-format `build.json` and hits this on the
  very next scheduled run.
- `algorithm.php` (`scan_directory()`): excludes `.git` and `node_modules` from the recursive
  scan. Neither is ever deployed/served, both can hold tens of thousands of files, and scanning
  them added nothing to a *deployed code tampering* check while pushing memory usage past PHP's
  limit on an install where they happen to exist inside `dirroot` (e.g. after running
  `npm install` for the grunt build). Confirmed on one install: 53k files scanned -> out-of-memory
  fatal (128MB limit) with them included, 29.7k files and no OOM without.

### Fixed: duplicate-userid warning and silent data loss in algorithm 8

`query[0]`'s `DISTINCT` applies to the whole selected row (`userid`, `ip`, `useragent`,
`timecreated`, ...), not just `userid` - a staff member with more than one qualifying login in the
window comes back as multiple rows sharing the same `userid`. `get_records_sql` requires that
first column to be unique and would silently drop all but one such row while logging a "Duplicate
value found in column userid" debug warning, with no guarantee which row (i.e. which IP/timestamp)
survived. Switched to `get_recordset_sql` and explicitly keep the most recent login per user
instead of relying on the query to already guarantee uniqueness.

### Fixed: Excel export always returning zero rows, and row ordering

The "Export to Excel" button carries the current page's querystring through verbatim, and
`issues.php`'s own default URL always includes `filterstatus=-1` (meaning "all statuses").
`export.php` read that same param as `PARAM_TEXT` and matched it with
`stripos($statustext, $filterstatus)` - i.e. literally searched for the substring `"-1"` inside a
status label like "Opened", which never matches, so every row was filtered out. In practice this
meant clicking Export from the default, unfiltered view - the common case - always downloaded a
file with only the header row. Read it as `PARAM_INT` with the same "-1 means all" semantics
`issues.php` already uses, and compare against the numeric status instead of substring-matching
translated text.

Also added an explicit `ORDER BY`: `get_recordset()` with no sort returns rows in whatever order
the DB engine happens to produce, which visibly changed after an `UPDATE` (Postgres relocating the
tuple) rather than reflecting insertion order. Sorted by `dateissue DESC` to match `issues.php`'s
own default view.

Verified all of the above (row content, special characters, multi-IP formatting, status text,
"System" attribution, row order) against a real exported `.xlsx`, not just row counts.
