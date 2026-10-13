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
 * Settings for Moodefense log store plugin.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add(
        'reports',
        new admin_externalpage(
            'moodefenseissues',
            get_string('moodefenseissues', 'logstore_moodefense'),
            new moodle_url('/admin/tool/log/store/moodefense/issues.php')
        )
    );

    $ADMIN->add(
        'reports',
        new admin_externalpage(
            'logstore_moodefense_dashboard',
            get_string('dashboard', 'logstore_moodefense'),
            new moodle_url('/admin/tool/log/store/moodefense/dashboard.php')
        )
    );

    // The admin tree is otherwise built (and this file re-required) once with fulltree=false, just to
    // render the admin navigation/breadcrumbs, and again with fulltree=true where the actual settings
    // are needed (this page, or admin/search.php matching one of them). Registering settings and
    // js_call_amd() unconditionally would run js_call_amd() twice per request on the fulltree=false pass.
    if ($ADMIN->fulltree) {
        $settings->add(
            new admin_setting_heading(
                'logstore_moodefense/premiumbanner',
                '',
                $OUTPUT->render_from_template(
                    'logstore_moodefense/banner',
                    \logstore_moodefense\local\algorithm_type::premium_banner()
                )
            )
        );

        $settings->add(
            new admin_setting_configcheckbox(
                'logstore_moodefense/logguests',
                new lang_string('logguests', 'core_admin'),
                new lang_string('logguests_desc', 'logstore_moodefense'),
                1
            )
        );

        $settings->add(
            new admin_setting_configcheckbox(
                'logstore_moodefense/jsonformat',
                new lang_string('jsonformat', 'logstore_moodefense'),
                new lang_string('jsonformat_desc', 'logstore_moodefense'),
                1
            )
        );

        $options = [
            0    => new lang_string('neverdeletelogs'),
            1000 => new lang_string('numdays', '', 1000),
            365  => new lang_string('numdays', '', 365),
            180  => new lang_string('numdays', '', 180),
            150  => new lang_string('numdays', '', 150),
            120  => new lang_string('numdays', '', 120),
            90   => new lang_string('numdays', '', 90),
            60   => new lang_string('numdays', '', 60),
            35   => new lang_string('numdays', '', 35),
            10   => new lang_string('numdays', '', 10),
            5    => new lang_string('numdays', '', 5),
            2    => new lang_string('numdays', '', 2),
        ];

        $settings->add(
            new admin_setting_configselect(
                'logstore_moodefense/loglifetime',
                new lang_string('loglifetime', 'core_admin'),
                new lang_string('configloglifetime', 'core_admin'),
                60,
                $options
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'logstore_moodefense/buffersize',
                get_string('buffersize', 'logstore_moodefense'),
                '',
                '50',
                PARAM_INT
            )
        );

        // Moodefense API, used by algorithm 10 (security announcements) only.
        $settings->add(
            new admin_setting_configpasswordunmask(
                'logstore_moodefense/token',
                get_string('tokenforapi', 'logstore_moodefense'),
                get_string('tokenforapi_desc', 'logstore_moodefense'),
                'wCIgKikwuA73NuXFuDvMV86thvE3QGDD7GjSZFR4'
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'logstore_moodefense/apiurl',
                get_string('apiurl', 'logstore_moodefense'),
                get_string('urlforapi_desc', 'logstore_moodefense'),
                'https://apimoodefense.learnapp.io',
                PARAM_URL
            )
        );

        $PAGE->requires->js_call_amd('logstore_moodefense/settings', 'init');

        $roles = get_all_roles();
        $options = [];
        foreach ($roles as $role) {
            $options[$role->id] = $role->shortname;
        }

        $settings->add(
            new admin_setting_configmultiselect(
                'logstore_moodefense/staffroles',
                get_string('staffroles', 'logstore_moodefense'),
                get_string('staffroles_desc', 'logstore_moodefense'),
                [3],
                $options
            )
        );

        // One table for both: which incident types are detected, and which of them notify.
        $notifications = new \logstore_moodefense\admin\setting_algorithm_notifications(
            'logstore_moodefense/algorithms_notifications',
            get_string('algorithmsnotifications', 'logstore_moodefense'),
            get_string('algorithmsnotifications_desc', 'logstore_moodefense')
        );
        $settings->add(
            new \logstore_moodefense\admin\setting_algorithms(
                'logstore_moodefense/algorithms',
                get_string('activeincidenttypes', 'logstore_moodefense'),
                get_string('selectalgorithms_desc', 'logstore_moodefense'),
                $notifications
            )
        );
        $settings->add($notifications);

        $settings->add(
            new admin_setting_configtext(
                'logstore_moodefense/permittedgeolocation',
                new lang_string('permittedgeolocation', 'logstore_moodefense'),
                new lang_string('permittedgeolocation_desc', 'logstore_moodefense'),
                $CFG->country ?? ''
            )
        );

        $settings->add(
            new admin_setting_configtextarea(
                'logstore_moodefense/emailstosendnotification',
                new lang_string('emailstosendnotification', 'logstore_moodefense'),
                new lang_string(
                    'emailstosendnotification_desc',
                    'logstore_moodefense',
                    (new moodle_url('/admin/settings.php', ['section' => 'outgoingmailconfig']))->out()
                ),
                ''
            )
        );

        // Keys are the severities moodle.org itself uses in its security announcements, as returned by
        // the Moodefense API, so algorithm 10 compares them without any mapping.
        $securityannouncementseverityoptions = [
            'Minor'    => get_string('severityminor', 'logstore_moodefense'),
            'Serious'  => get_string('severityserious', 'logstore_moodefense'),
            'Critical' => get_string('severitycritical', 'logstore_moodefense'),
        ];

        // Locked by amd/src/settings.js while algorithm 10 does not notify.
        $settings->add(
            new admin_setting_configmultiselect(
                'logstore_moodefense/securityannouncementseverities',
                get_string('securityannouncementseverities', 'logstore_moodefense'),
                get_string('securityannouncementseverities_desc', 'logstore_moodefense'),
                array_keys($securityannouncementseverityoptions),
                $securityannouncementseverityoptions
            )
        );
    }
}
