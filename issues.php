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
 * Security issues management page for MooDefense log store.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use logstore_moodefense\local\algorithm_type;
use logstore_moodefense\local\issue_details;
use logstore_moodefense\local\issues_filter;
use logstore_moodefense\local\severity;
use logstore_moodefense\local\status;

require_once(__DIR__ . '/../../../../../config.php');

require_login();
$context = context_system::instance();
require_capability('logstore/moodefense:view', $context);

$filters = issues_filter::from_request();
[
    'filterusername' => $filterusername,
    'filterseverity' => $filterseverity,
    'filterstatus' => $filterstatus,
    'filterip' => $filterip,
    'filtercomments' => $filtercomments,
    'filterroles' => $filterroles,
] = $filters;
$sort    = optional_param('sort', '', PARAM_ALPHA);
$page    = optional_param('page', 0, PARAM_INT);
$perpage = min(max(optional_param('perpage', 50, PARAM_INT), 1), 500);

$PAGE->set_context($context);
$PAGE->set_url('/admin/tool/log/store/moodefense/issues.php', $filters + ['sort' => $sort, 'page' => $page]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('moodefenseissues', 'logstore_moodefense'));
$PAGE->set_heading(get_string('moodefenseissues', 'logstore_moodefense'));

// These plugin CSS files are served as bare static links (no theme-rev cache busting), so
// browsers can cache them indefinitely on a heuristic freshness lifetime and keep serving a
// stale copy across plugin upgrades even on a normal (non-hard) refresh. Append the plugin
// version so the URL itself changes whenever the CSS does.
$cssrev = get_config('logstore_moodefense', 'version');
$PAGE->requires->css(new moodle_url('/admin/tool/log/store/moodefense/css/dashboard.css', ['v' => $cssrev]));
$PAGE->requires->css(new moodle_url('/admin/tool/log/store/moodefense/css/issues.css', ['v' => $cssrev]));

// Data + pagination.
$totalcount = issues_filter::count($filters);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('logstore_moodefense/banner', algorithm_type::premium_banner());

// Filters, result count and page actions.
$severityoptions = ['' => get_string('all_severity', 'logstore_moodefense')];
foreach (array_reverse(severity::cases()) as $severity) {
    $severityoptions[$severity->value] = $severity->label();
}
$statusoptions = ['-1' => get_string('all_statuses', 'logstore_moodefense')];
foreach (status::cases() as $status) {
    $statusoptions[$status->value] = $status->label();
}
$rolesoptions = ['' => get_string('all_roles', 'logstore_moodefense')];
foreach (get_all_roles() as $role) {
    if ($role->shortname !== 'admin') {
        $rolesoptions[$role->shortname] = role_get_name($role, $context);
    }
}
$select = fn(string $name, string $label, array $options) => [
    'name' => $name,
    'label' => $label,
    'options' => array_map(fn($value, $text) => [
        'value' => $value,
        'text' => $text,
        'selected' => (string)$value === (string)$filters[$name],
    ], array_keys($options), $options),
];
$reseturl = new moodle_url($PAGE->url);
$reseturl->remove_params(array_merge(array_keys($filters), ['page']));

echo $OUTPUT->render_from_template('logstore_moodefense/issues_toolbar', [
    'action' => $PAGE->url->out_omit_querystring(),
    'textfilters' => [
        ['name' => 'filterusername', 'label' => get_string('column_username', 'logstore_moodefense'), 'value' => $filterusername],
        ['name' => 'filterip', 'label' => get_string('column_ip', 'logstore_moodefense'), 'value' => $filterip],
        ['name' => 'filtercomments', 'label' => get_string('column_comments', 'logstore_moodefense'), 'value' => $filtercomments],
    ],
    'selects' => [
        $select('filterseverity', get_string('column_severity', 'logstore_moodefense'), $severityoptions),
        $select('filterstatus', get_string('column_status', 'logstore_moodefense'), $statusoptions),
        $select('filterroles', get_string('column_roles', 'logstore_moodefense'), $rolesoptions),
    ],
    'wasfiltered' => issues_filter::is_active($filters),
    'reseturl' => $reseturl->out(false),
    'showing' => $totalcount ? get_string('showingissues', 'logstore_moodefense', [
        'from' => $page * $perpage + 1,
        'to' => min(($page + 1) * $perpage, $totalcount),
        'total' => $totalcount,
    ]) : '',
    'canmanage' => has_capability('logstore/moodefense:manage', $context),
    'sort' => $sort,
]);

$na = get_string('notapplicable', 'logstore_moodefense');
$rows = [];
foreach (issues_filter::get_page($filters, $sort, $page * $perpage, $perpage) as $record) {
    $username = $record->uid ? fullname($record) : $na;
    $data = json_decode($record->data, true) ?: [];
    $ips = issue_details::ips($data);
    $type = algorithm_type::tryFrom((int)$record->algorithm);

    $rows[] = [
        'id'            => (int)$record->id,
        'algorithm'     => (int)$record->algorithm,
        'algorithmname' => get_string('algorithm' . $record->algorithm, 'logstore_moodefense'),
        'status'        => status::label_of((int)$record->status),
        'comment'       => $record->comment ?? '',
        'message'       => $record->message,
        'severity'      => $record->severity,
        'severityclass' => strtolower($record->severity),
        'username'      => $username,
        'roles'         => $record->roles,
        'userid'        => (int)$record->userid,
        'lastip'        => $ips[0] ?? $na,
        'time'          => userdate($record->dateissue, '%d %b, %H:%M'),
        'logurl'        => issue_details::log_url($record)->out(false),
        // Everything the details modal shows (amd/src/table.js, templates/issue_details.mustache).
        'detailsdata'   => json_encode([
            'id'            => (int)$record->id,
            'status'        => (int)$record->status,
            'description'   => get_string('algorithm' . $record->algorithm . '_case', 'logstore_moodefense'),
            'comment'       => $record->comment ?? '',
            'timecreated'   => (int)$record->dateissue,
            'severity'      => $record->severity,
            'algorithm'     => (int)$record->algorithm,
            'algorithmname' => get_string('algorithm' . $record->algorithm, 'logstore_moodefense'),
            'message'       => $record->message,
            // The fields of its algorithm; all user fields for one no longer shipped.
            'fields'        => issue_details::fields($type?->details() ?? algorithm_type::USER_DETAILS, $record, $data),
        ]),
    ];
}

echo $OUTPUT->render_from_template('logstore_moodefense/issues', [
    // The № header sorts by number: latest first, then oldest first.
    'sorturl' => (new moodle_url($PAGE->url, ['sort' => $sort === 'iddesc' ? 'idasc' : 'iddesc', 'page' => 0]))->out(false),
    'sortdesc' => $sort === 'iddesc',
    'sortasc' => $sort === 'idasc',
    'rows' => $rows,
    'pagingbar' => $totalcount > $perpage ? $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url) : '',
]);

$PAGE->requires->js_call_amd('logstore_moodefense/table', 'init');

echo $OUTPUT->footer();
