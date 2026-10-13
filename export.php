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
 * Exports the issues of the issues page to Excel (XLSX): the issues matching its filters, in its order.
 *
 * Streamed with Moodle's dataformat API: rows are written to the download as they are read, so any
 * number of issues exports in one file without holding the whole workbook in memory.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');

use logstore_moodefense\local\issues_filter;
use logstore_moodefense\local\status;

require_login();
require_capability('logstore/moodefense:view', context_system::instance());

$filters = issues_filter::from_request();
$sort = optional_param('sort', '', PARAM_ALPHA);

$columns = [];
$names = ['number', 'incidentid', 'incidenttype', 'status', 'comment', 'message', 'severity', 'username', 'roles', 'userid',
    'ip', 'time'];
foreach ($names as $column) {
    $columns[$column] = get_string('column_' . $column, 'logstore_moodefense');
}

[$sql, $params] = issues_filter::get_sql($filters);
$records = $DB->get_recordset_sql("$sql ORDER BY " . issues_filter::order_by($sort), $params);
$na = get_string('notapplicable', 'logstore_moodefense');

\core\dataformat::download_data(
    'moodefense_issues_' . date('Y-m-d_H-i-s'),
    'excel',
    $columns,
    $records,
    function (stdClass $record) use ($na): array {
        // Evidence: algorithm 4 stores a list of IP addresses, the others a single one.
        $data = json_decode($record->data, true) ?: [];
        return [
            (int)$record->id,
            (int)$record->algorithm,
            get_string('algorithm' . $record->algorithm, 'logstore_moodefense'),
            status::label_of((int)$record->status),
            $record->comment ?? '',
            $record->message ?? '',
            $record->severity,
            $record->uid ? fullname($record) : $na,
            $record->roles ?? '',
            (int)$record->userid,
            implode(', ', $data['ips'] ?? [$data['ip'] ?? '']) ?: $na,
            userdate($record->dateissue, '%d.%m.%Y %H:%M:%S'),
        ];
    }
);
$records->close();
