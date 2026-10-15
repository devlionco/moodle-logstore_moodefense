# MooDefense log store (logstore_moodefense)

A Moodle log store that records events together with the request's IP address and user agent, and
periodically analyses them for suspicious activity: shared devices, password guessing, account use from
many IP addresses and admin logins from unexpected countries. Detected incidents are listed on an issues
page and a dashboard, and administrators are notified by Moodle notification and email.

## Editions: Free and Premium (Pro)

This plugin is the **free edition** of MooDefense. **[MooDefense Premium](#moodefense-premium-pro)**
(Pro) detects five more incident types; everything else works the same in both editions.

| # | Incident type | Free | Premium |
|---|---------------|:----:|:-------:|
| 2 | Staff and Student Login from the Same Device | ✅ | ✅ |
| 4 | User Activity from Multiple IP Addresses | ✅ | ✅ |
| 5 | Admin Login from an Unauthorized Country | ✅ | ✅ |
| 7 | Aggressive Password Guessing | ✅ | ✅ |
| 10 | Moodle Security Announcement Match | ✅ | ✅ |
| 1 | Staff Activity Without Prior Login | – | ✅ |
| 3 | Student Activity Without Prior Login | – | ✅ |
| 6 | Staff Login from an Unauthorized Country | – | ✅ |
| 8 | Dormant Staff Account Login | – | ✅ |
| 9 | Modification in Moodle Web Root Directory | – | ✅ |

### What the free edition detects

- **Staff and Student Login from the Same Device (2)** – a staff or admin user logs in from the IP
  address and user agent a student recently used: the staff credentials may have been stolen.
- **User Activity from Multiple IP Addresses (4)** – one user acts from 10 or more IP addresses within
  30 minutes: a VPN, proxy or Tor hiding an attacker.
- **Admin Login from an Unauthorized Country (5)** – an admin logs in from a country outside
  *Permitted geolocations*.
- **Aggressive Password Guessing (7)** – 50 or more failed logins to one account from one IP address
  within 30 minutes; High severity if a login then succeeds.
- **Moodle Security Announcement Match (10)** – a moodle.org security announcement lists the installed
  Moodle version as affected.

Detection runs on the local database. Incident type 5 and the issue details find the country of an IP
address in the site's own GeoIP2 database when one is set up (*Site administration > Location >
Location settings*, `geoip2file`), otherwise in the GeoLite2 City database shipped in `source/`.

Incident type 10 compares the moodle.org security announcements with the installed Moodle version.
It reads them from the Moodefense API, set up with *API URL* and *Token for API* in the settings, once
a day in the *MooDefense Security Announcement Check* scheduled task (09:00); without them it does
nothing. Only the request with the token is sent: no data about the site or its users.

## MooDefense Premium (Pro)

The free edition catches attacks on the login itself: guessed passwords, shared devices, logins from
the wrong country. **Premium also catches the attacker who is already inside** – with a stolen
session, with old staff credentials, or with files planted on the server.

### What Premium adds

| # | Incident type | The threat it catches |
|---|---------------|-----------------------|
| 1 | **Staff Activity Without Prior Login** | **Session hijacking of staff and admins.** Activity from an IP address and user agent that never logged in to the account: someone reuses a stolen session, no password needed. |
| 3 | **Student Activity Without Prior Login** | **Session hijacking of students** – the same check for student accounts. |
| 6 | **Staff Login from an Unauthorized Country** | **Stolen teacher accounts.** Extends the country check of the free edition from admins to all staff, e.g. teachers. |
| 8 | **Dormant Staff Account Login** | **Forgotten accounts reused.** A staff or admin account logs in after six months or more without activity: credentials that were forgotten, leaked or stolen. |
| 9 | **Modification in Moodle Web Root Directory** | **Server compromise.** Files added, changed or deleted in the Moodle web root: code injection or an uploaded web shell running commands on the server. |

### What you get

- All five Premium incident types on top of the five of the free edition – **10 in total**.
- The same workflow for every incident type: choose what is detected and what notifies, get Moodle
  notifications and email, triage issues on the issues page, follow them on the dashboard and export
  them to Excel.

The free edition already lists the Premium incident types in its settings (*Premium* button) and shows
a *Contact us* banner in the settings, on the issues page and on the dashboard.

### Get MooDefense Premium

Contact Fensive to get MooDefense Premium (Pro) or to ask about it:

- **Email:** [sales@fensive.co.il](mailto:sales@fensive.co.il)

## Requirements

- Moodle 4.5 to 5.2, on any database Moodle supports (CI runs the tests on PostgreSQL and MariaDB)

## Installation

1. Copy the plugin to `admin/tool/log/store/moodefense` (`public/admin/tool/log/store/moodefense` on
   Moodle 5.1+).
2. Visit *Site administration > Notifications* to complete the installation.
3. Enable *MooDefense* in *Site administration > Plugins > Logging > Manage log stores*.

## Configuration

*Site administration > Plugins > Logging > MooDefense*:

- **Active Incident Types** / **Algorithms with notifications** – what is detected and what notifies.
- **Roles of staff** – roles treated as staff (course or category level) by incident type 2.
- **Permitted geolocations** – comma-separated country codes, e.g. `IL, US`, for incident type 5
  (by default the site's default country; empty turns the check off).
- **Emails to send notifications** – extra recipients; site administrators are notified otherwise.

Detection runs every 5 minutes in the *MooDefense Processing* scheduled task, and old log entries
are removed daily by *MooDefense log table cleanup* according to *Keep logs for*.

## Usage

- *Site administration > Reports > MooDefense Issues* – filter, export to Excel and triage issues
  (status and comment per issue).
- *Site administration > Reports > MooDefense Dashboard* – users by role, issues by severity and the
  latest issues.

Access is controlled by the `logstore/moodefense:view` and `logstore/moodefense:manage` capabilities
(granted to managers by default).

## Privacy

The log entries are exported and deleted on request like those of the standard log. The detected
issues are exported with a user's data, but kept when it is deleted: they are the evidence of
security incidents, kept on the grounds of the site's legitimate interest in its security (GDPR
articles 6(1)(f) and 17(3)(e)), so that a suspect cannot have the evidence against them erased.
Administrators delete issues on the issues page ("Clear all history"), which is logged.

## Tests

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite logstore_moodefense_testsuite

php admin/tool/behat/cli/init.php
vendor/bin/behat --config <behat_dataroot>/behatrun/behat/behat.yml --tags=@logstore_moodefense
```

## License

GNU GPL v3 or later.

This product includes GeoLite2 data created by MaxMind, available from
[https://www.maxmind.com](https://www.maxmind.com) (`source/GeoLite2-City.mmdb`, GeoLite2 End User
License Agreement).
