<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Moodefense Dashboard page.
 *
 * Displays statistics, charts and latest security issues detected by the Moodefense log store.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use logstore_moodefense\local\algorithm_type;
use logstore_moodefense\local\severity;
use logstore_moodefense\local\status;

require_once(__DIR__ . '/../../../../../config.php');
require_login();

$context = context_system::instance();
require_capability('logstore/moodefense:view', $context);

// Bare static link, no theme-rev cache busting - append the plugin version so browsers
// don't keep serving a stale cached copy across plugin upgrades (see issues.php).
$PAGE->requires->css(new moodle_url(
    '/admin/tool/log/store/moodefense/css/dashboard.css',
    ['v' => get_config('logstore_moodefense', 'version')]
));
$PAGE->requires->js_call_amd('logstore_moodefense/dashboard', 'init');

$PAGE->set_url('/admin/tool/log/store/moodefense/dashboard.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('dashboard', 'logstore_moodefense'));
$PAGE->set_heading(get_string('dashboard', 'logstore_moodefense'));

// Deterministic colour for a role, from its shortname: the same role always gets the same colour.
$rolecolor = fn(string $key): string => 'hsl(' . (crc32($key) % 360) . ', 65%, 55%)';
$excludedroles = ['student', 'guest', 'user', 'frontpage'];
$roleslistlimit = 6;

/* Admins */
$admins = get_admins();
$admincount = count($admins);
$adminrolename = get_string('siteadmins', 'logstore_moodefense');
if ($admincount === 0) {
    $adminrolename .= ' (0)';
} else {
    $adminrolename .= ' (' . $admincount . ')';
}

/* Roles */
[$insql, $inparams] = $DB->get_in_or_equal($excludedroles, SQL_PARAMS_QM, 'param', false);
$sqlroles = "
    SELECT
        r.id,
        r.shortname,
        COALESCE(NULLIF(TRIM(r.name), ''), r.shortname) AS displayname,
        COUNT(DISTINCT ra.userid) AS usercount
    FROM {role} r
    LEFT JOIN {role_assignments} ra ON ra.roleid = r.id
    WHERE r.shortname $insql
    GROUP BY r.id, r.shortname, r.name, r.sortorder
    ORDER BY r.sortorder ASC
";

$allroles = $DB->get_records_sql($sqlroles, $inparams);

$rolecounts = [];

$rolecounts['__admin__'] = [
    'colorkey'    => '__admin__',
    'basename'    => get_string('siteadmins', 'logstore_moodefense'),
    'displayname' => $adminrolename,
    'count'       => (int)$admincount,
    'ismainrole'  => true,
];

foreach ($allroles as $role) {
    $displayname = $role->displayname;
    if ((int)$role->usercount === 0) {
        $displayname .= ' (0)';
    } else {
        $displayname .= ' (' . $role->usercount . ')';
    }
    $rolecounts[$role->shortname] = [
        'colorkey'    => $role->shortname,
        'basename'    => $role->displayname,
        'displayname' => $displayname,
        'count'       => (int)$role->usercount,
        'ismainrole'  => false,
    ];
}

// Main roles (site admins) always come first, then everyone else is sorted purely by
// user count (highest first) so the roles with the fewest users are the ones pushed into
// "Others" once the $roleslistlimit cut-off is applied below.
uasort($rolecounts, function ($a, $b) {
    if ($a['ismainrole'] !== $b['ismainrole']) {
        return $a['ismainrole'] ? -1 : 1;
    }

    if ($a['count'] !== $b['count']) {
        return $b['count'] <=> $a['count'];
    }

    return strcasecmp($a['displayname'], $b['displayname']);
});

$rolesarray = array_map(function ($data) use ($rolecolor) {
    return [
        'name'     => $data['displayname'],
        'basename' => $data['basename'],
        'count'    => $data['count'],
        'color'    => $rolecolor($data['colorkey']),
    ];
}, array_values($rolecounts));

/* Grouping into "Others" only makes sense once there are actually too many roles to
   show comfortably. With a handful of roles, show every one of them as-is - even the
   empty ones - since hiding them serves no purpose. Only past the limit do empty roles
   get pushed out first, then the lowest-count non-empty roles, into "Others". */
if (count($rolesarray) > $roleslistlimit) {
    $nonemptyroles = array_values(array_filter($rolesarray, function ($role) {
        return $role['count'] > 0;
    }));
    $emptyroles = array_values(array_filter($rolesarray, function ($role) {
        return $role['count'] === 0;
    }));

    $visiblerolesarray = array_slice($nonemptyroles, 0, $roleslistlimit);
    $hiddenrolesarray = array_merge(array_slice($nonemptyroles, $roleslistlimit), $emptyroles);
} else {
    $visiblerolesarray = $rolesarray;
    $hiddenrolesarray = [];
}
$othersdetail = [];

if (!empty($hiddenrolesarray)) {
    $othercount = array_sum(array_column($hiddenrolesarray, 'count'));

    // Structured breakdown (already sorted by user count desc), rendered as markup by the
    // template - reused by both the sidebar tooltip and the chart hover tooltip.
    $othersdetail = array_map(function ($role) {
        return ['name' => $role['basename'], 'count' => $role['count']];
    }, array_values($hiddenrolesarray));

    $visiblerolesarray[] = [
        'name'    => get_string('otherroles', 'logstore_moodefense') . ' (' . $othercount . ')',
        'count'   => $othercount,
        'color'   => $rolecolor('__otherroles__'),
        'isother' => true,
    ];
}

$chartlabels = array_column($visiblerolesarray, 'name');
$chartdata   = array_column($visiblerolesarray, 'count');
$chartcolors = array_column($visiblerolesarray, 'color');

// Severity chart: every severity in display order, coloured like the issues table badges.
$severities = severity::cases();
$stored = $DB->get_records_sql_menu("SELECT severity, COUNT(1) FROM {logstore_moodefense_issues} GROUP BY severity");
$severitycounts = array_map(fn(severity $severity) => (int)($stored[$severity->value] ?? 0), $severities);

// Latest issues, most severe first: one small query per severity on the (severity, dateissue) index
// instead of sorting the whole table by a severity rank.
$limit = 5;
$latest = [];
$userfields = \core_user\fields::for_name()->get_sql('u')->selects;
foreach ($severities as $severity) {
    if (count($latest) >= $limit) {
        break;
    }
    $latest = array_merge($latest, array_values($DB->get_records_sql(
        "SELECT i.*, u.id AS uid $userfields
           FROM {logstore_moodefense_issues} i
      LEFT JOIN {user} u ON u.id = i.userid
          WHERE i.severity = :severity
       ORDER BY i.dateissue DESC, i.id DESC",
        ['severity' => $severity->value],
        0,
        $limit - count($latest)
    )));
}

$na = get_string('notapplicable', 'logstore_moodefense');
$issues = array_map(function (stdClass $record) use ($na) {
    // Evidence: algorithm 4 stores a list of IP addresses, the others a single one.
    $data = json_decode($record->data, true) ?: [];
    return [
        'algorithm'     => (int)$record->algorithm,
        'incidenttype'  => get_string('algorithm' . $record->algorithm, 'logstore_moodefense'),
        'status'        => status::label_of((int)$record->status),
        'comment'       => $record->comment ?? '',
        'message'       => shorten_text($record->message ?? '', 60),
        'severity'      => $record->severity,
        'severityclass' => strtolower((string)$record->severity),
        'username'      => $record->uid ? fullname($record) : $na,
        'roles'         => $record->roles ?? '',
        'userid'        => (int)$record->userid,
        'ip'            => ($data['ips'][0] ?? $data['ip'] ?? '') ?: $na,
        'time'          => userdate($record->dateissue, '%d %b, %H:%M'),
    ];
}, $latest);

$templatecontext = [
    'roles'           => $visiblerolesarray,
    'hasothers'       => !empty($othersdetail),
    'othersheader'    => empty($othersdetail)
        ? ''
        : get_string('otherrolesheader', 'logstore_moodefense', count($othersdetail)),
    'othersbreakdown' => $othersdetail,
    'chartdata'       => json_encode([
        'labels'       => $chartlabels,
        'data'         => $chartdata,
        'colors'       => $chartcolors,
        'othersIndex'  => empty($othersdetail) ? null : count($visiblerolesarray) - 1,
        'othersDetail' => $othersdetail,
    ]),
    'severitydata'    => json_encode([
        'labels' => array_map(fn(severity $severity) => $severity->label(), $severities),
        'data'   => $severitycounts,
        'colors' => array_map(fn(severity $severity) => $severity->color(), $severities),
    ]),
    'issues'          => $issues,
    'issuesurl'       => new moodle_url('/admin/tool/log/store/moodefense/issues.php'),
    ...algorithm_type::premium_banner(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('logstore_moodefense/dashboard', $templatecontext);
echo $OUTPUT->footer();
