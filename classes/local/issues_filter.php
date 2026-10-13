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

namespace logstore_moodefense\local;

/**
 * The issues table filters shared by issues.php and export.php.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issues_filter {
    /**
     * Reads the filters of the issues page from its URL, for the page and its Excel export.
     *
     * @return array Filter name => value; filterstatus is -1 for "any".
     */
    public static function from_request(): array {
        return [
            'filterusername' => optional_param('filterusername', '', PARAM_TEXT),
            'filterseverity' => optional_param('filterseverity', '', PARAM_ALPHA),
            'filterstatus' => optional_param('filterstatus', -1, PARAM_INT),
            'filterip' => optional_param('filterip', '', PARAM_TEXT),
            'filtercomments' => optional_param('filtercomments', '', PARAM_TEXT),
            'filterroles' => optional_param('filterroles', '', PARAM_ALPHANUMEXT),
        ];
    }

    /**
     * Whether any filter is set, to offer resetting them.
     *
     * @param array $filters As returned by from_request().
     * @return bool
     */
    public static function is_active(array $filters): bool {
        return $filters['filterstatus'] >= 0 || array_filter(array_diff_key($filters, ['filterstatus' => 0]));
    }

    /**
     * Counts the matching issues, for the "Showing x-y of z" line and the paging bar. Joins the users
     * only when filtering on their names: counting does not need them otherwise.
     *
     * @param array $filters As returned by from_request().
     * @return int
     */
    public static function count(array $filters): int {
        global $DB;

        [$sql, $params] = self::get_sql($filters, 'COUNT(1)', $filters['filterusername'] !== '');
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * The ORDER BY of the issues table: latest first, or by number (№) when the admin sorted by it,
     * shared by the page and the export so that both list issues in the same order.
     *
     * @param string $sort 'iddesc', 'idasc', or anything else for the default.
     * @return string
     */
    public static function order_by(string $sort): string {
        return match ($sort) {
            'iddesc' => 'i.id DESC',
            'idasc' => 'i.id ASC',
            default => 'i.dateissue DESC, i.id DESC',
        };
    }

    /**
     * Loads one page of the issues table: the matching issues with their user's name fields.
     *
     * The page's IDs are picked first from an index alone, then only those rows are read: sorting
     * whole rows to skip to a deep page would read the entire table.
     *
     * @param array $filters As returned by from_request().
     * @param string $sort See order_by().
     * @param int $from Number of issues to skip.
     * @param int $num Number of issues on the page.
     * @return \stdClass[] In page order.
     */
    public static function get_page(array $filters, string $sort, int $from, int $num): array {
        global $DB;

        [$sql, $params] = self::get_sql($filters, 'i.id', $filters['filterusername'] !== '');
        $ids = array_keys($DB->get_records_sql("$sql ORDER BY " . self::order_by($sort), $params, $from, $num));
        if (!$ids) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_sql(
            'SELECT i.*, u.id AS uid' . self::user_fields() . "
               FROM {logstore_moodefense_issues} i
          LEFT JOIN {user} u ON u.id = i.userid
              WHERE i.id $insql",
            $inparams
        );
        return array_map(fn($id) => $records[$id], $ids);
    }

    /**
     * Builds the SQL selecting the matching issues, joined to their user (alias u, may be null), for
     * counting, paging and exporting them with the same conditions.
     *
     * @param array $filters As returned by from_request().
     * @param string|null $fields Fields to select; by default the issue, uid (null if the user is gone)
     *     and the user's name fields.
     * @param bool $withusers Whether to join the users; required by the default fields.
     * @return array [sql, params], without ORDER BY.
     */
    public static function get_sql(array $filters, ?string $fields = null, bool $withusers = true): array {
        global $DB;

        $fields ??= 'i.*, u.id AS uid' . self::user_fields();
        $conditions = [];
        $params = [];
        $like = function (string $field, string $param, string $value) use ($DB, &$conditions, &$params) {
            $conditions[] = $DB->sql_like($field, ":$param", false);
            $params[$param] = '%' . $DB->sql_like_escape($value) . '%';
        };

        if ($filters['filterseverity'] !== '') {
            $conditions[] = 'i.severity = :severity';
            $params['severity'] = $filters['filterseverity'];
        }
        if ($filters['filterstatus'] >= 0) {
            $conditions[] = 'i.status = :status';
            $params['status'] = $filters['filterstatus'];
        }
        if ($filters['filterroles'] !== '') {
            $like('i.roles', 'roles', $filters['filterroles']);
        }
        if ($filters['filtercomments'] !== '') {
            $like('i.comment', 'comment', $filters['filtercomments']);
        }
        if ($filters['filterip'] !== '') {
            // IP addresses live in the JSON data column; any IP recorded for the issue matches.
            $like('i.data', 'ip', $filters['filterip']);
        }
        if ($filters['filterusername'] !== '') {
            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
            $like($fullname, 'username', $filters['filterusername']);
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $join = $withusers ? 'LEFT JOIN {user} u ON u.id = i.userid' : '';
        $sql = "SELECT $fields
                  FROM {logstore_moodefense_issues} i
                       $join
                $where";

        return [$sql, $params];
    }

    /**
     * The user's name fields, with a leading comma, for fullname() on the issues page and export.
     *
     * @return string
     */
    private static function user_fields(): string {
        return \core_user\fields::for_name()->get_sql('u')->selects;
    }
}
