# MooDefense log store (logstore_moodefense)

A Moodle log store that records events together with the request's IP address and user agent, and
periodically analyses them for suspicious activity: shared devices, password guessing, account use from
many IP addresses and admin logins from unexpected countries. Detected incidents are listed on an issues
page and a dashboard, and administrators are notified by Moodle notification and email.

## Incident types

| # | Incident type |
|---|---------------|
| 2 | Staff and Student Login from the Same Device |
| 4 | User Activity from Multiple IP Addresses |
| 5 | Admin Login from an Unauthorized Country |
| 7 | Aggressive Password Guessing |
| 10 | Moodle Security Announcement Match |

Detection runs on the local database. Incident type 5 and the issue details find the country of an IP
address in the site's own GeoIP2 database when one is set up (*Site administration > Location >
Location settings*, `geoip2file`), otherwise in the GeoLite2 City database shipped in `source/`.

Incident type 10 compares the moodle.org security announcements with the installed Moodle version.
It reads them from the Moodefense API, set up with *API URL* and *Token for API* in the settings, once
a day in the *MooDefense Security Announcement Check* scheduled task (09:00); without them it does
nothing. Only the request with the token is sent: no data about the site or its users.

The settings also list the incident types of MooDefense Premium (staff and student activity without
prior login, staff logins from unauthorized countries, dormant staff account logins, code base
tampering); selecting one shows how to get it.

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
