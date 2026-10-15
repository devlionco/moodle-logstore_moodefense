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
 * Issues page: details modal, clear confirmation, export and filter toggle.
 *
 * @module     logstore_moodefense/table
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/modal_save_cancel',
    'core/modal_events',
    'core/str',
    'core/ajax',
    'core/templates',
    'core/notification',
], function($, ModalSaveCancel, ModalEvents, Str, Ajax, Templates, Notification) {

    // Labels used inside templates/issue_details.mustache are fetched there via the {{#str}}
    // helper - only the strings this controller itself needs (button text, confirm dialogs,
    // status option labels) are listed here.
    const STRINGS = [
        {key: 'viewdetails', component: 'logstore_moodefense'},
        {key: 'close', component: 'logstore_moodefense'},
        {key: 'confirm_clear', component: 'logstore_moodefense'},
        {key: 'yes', component: 'core'},
        {key: 'no', component: 'core'},
        {key: 'confirmation', component: 'logstore_moodefense'},
        {key: 'save', component: 'core'},
        {key: 'status_opened', component: 'logstore_moodefense'},
        {key: 'status_notrelevant', component: 'logstore_moodefense'},
        {key: 'status_resolved', component: 'logstore_moodefense'},
    ];

    /**
     * Fetch the country codes of several IP addresses in a single web service request.
     *
     * Never awaited before showing the modal - see resolveCountryCodes().
     *
     * @param {Array<string>} ips IP addresses.
     * @returns {Promise<Array<string|null>>} Country codes in the same order, null where unresolved.
     */
    function fetchCountryCodes(ips) {
        const requests = ips.map(function(ip) {
            return {methodname: 'logstore_moodefense_get_country_code', args: {ip: ip}};
        });
        return Promise.all(Ajax.call(requests).map(function(promise, index) {
            return promise.then(function(response) {
                return (response.success && response.countrycode) ? response.countrycode : null;
            }).catch(function(error) {
                // eslint-disable-next-line no-console
                console.error('Error fetching country code for ' + ips[index] + ':', error);
                return null;
            });
        }));
    }

    /**
     * Resolve the country codes of the modal's IP addresses and add them next to each address once
     * they arrive, so the modal itself never waits for the lookups.
     *
     * @param {jQuery} modalRoot The modal's root element.
     * @returns {void}
     */
    function resolveCountryCodes(modalRoot) {
        const $links = modalRoot.find('.ip-item-link');
        if (!$links.length) {
            return;
        }
        fetchCountryCodes($links.map(function() {
            return $(this).text();
        }).get()).then(function(countryCodes) {
            countryCodes.forEach(function(countryCode, index) {
                if (countryCode) {
                    $links.eq(index).after($('<span>', {'class': 'ip-country-code'}).text(' (' + countryCode + ')'));
                }
            });
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Build the {value, text, selected} options for the status <select>.
     *
     * @param {number} currentStatus The issue's current status (0, 1 or 2).
     * @param {Object} t Translated strings, keyed by string identifier.
     * @returns {Array<Object>}
     */
    function buildStatusOptions(currentStatus, t) {
        return [t.status_opened, t.status_notrelevant, t.status_resolved].map(function(text, value) {
            return {value: value, text: text, selected: value === (currentStatus || 0)};
        });
    }

    /**
     * Build the full template context for the details modal.
     *
     * @param {Object} data Decoded details data for one issue.
     * @param {Object} t Translated strings, keyed by string identifier.
     * @returns {Object}
     */
    function buildDetailsContext(data, t) {
        return {
            statusoptions: buildStatusOptions(data.status, t),
            time: new Date(data.timecreated * 1000).toLocaleString(),
            algorithm: data.algorithm,
            algorithmname: data.algorithmname,
            message: data.message,
            severity: data.severity,
            description: data.description,
            comment: data.comment || '',
            // Built by issues.php, see \logstore_moodefense\local\issue_details.
            fields: data.fields || [],
        };
    }

    /**
     * Wire up the "N more / less" toggle for a long IP list inside an already-rendered modal.
     *
     * @param {jQuery} modalRoot The modal's root element.
     * @returns {void}
     */
    function wireIpToggle(modalRoot) {
        modalRoot.on('click', '.ip-toggle-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $icon = $btn.find('i');
            const $text = $btn.find('.toggle-text');
            const expand = $icon.hasClass('fa-angle-down');

            $btn.closest('.ip-address-container').find('.ip-item[data-hidden]').toggleClass('d-none', !expand);
            $icon.toggleClass('fa-angle-down', !expand).toggleClass('fa-angle-up', expand);
            const label = expand
                ? Str.get_string('ipsless', 'logstore_moodefense')
                : Str.get_string('ipsmore', 'logstore_moodefense', $btn.data('more'));
            label.then(function(text) {
                $text.text(text);
                return null;
            }).catch(Notification.exception);
        });
    }

    /**
     * Wire up the details modal's save button: persists the status/comment and reloads on
     * success.
     *
     * @param {Object} modal The created modal instance.
     * @param {number} recordid The issue's id.
     * @returns {void}
     */
    function wireDetailsSave(modal, recordid) {
        modal.getRoot().on(ModalEvents.save, function() {
            Ajax.call([{
                methodname: 'logstore_moodefense_save_modal_data',
                args: {
                    recordid: recordid,
                    status: modal.getRoot().find('#statusSelect').val(),
                    comment: modal.getRoot().find('#commentText').val(),
                },
                done: function(response) {
                    if (response.success) {
                        modal.hide();
                        location.reload();
                    }
                },
                fail: function(error) {
                    // eslint-disable-next-line no-console
                    console.error('Error saving data:', error);
                }
            }]);
        });
    }

    /**
     * Render and show the issue details modal.
     *
     * @param {Object} data Decoded details data for one issue.
     * @param {Object} t Translated strings, keyed by string identifier.
     * @returns {Promise<void>}
     */
    async function showDetailsModal(data, t) {
        const context = buildDetailsContext(data, t);
        const html = await Templates.render('logstore_moodefense/issue_details', context);

        try {
            const modal = await ModalSaveCancel.create({
                title: t.viewdetails,
                body: html,
                buttons: {save: t.save, cancel: t.close},
                large: true,
                show: true,
                // Otherwise every dialogue opened stays in the page, hidden, with the same element ids.
                removeOnClose: true,
            });

            // Sizing/spacing for this modal lives in css/issues.css under .moodefense-modal.
            modal.getRoot().addClass('moodefense-modal');

            wireIpToggle(modal.getRoot());
            wireDetailsSave(modal, data.id);
            resolveCountryCodes(modal.getRoot());
            modal.getRoot().on(ModalEvents.cancel, function() {
                modal.hide();
            });
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error('Modal error:', error);
        }
    }

    /**
     * Show a yes/no confirmation modal (used by the "clear history" action).
     *
     * @param {Object} options
     * @param {string} options.title Modal title.
     * @param {string} options.body Modal body text.
     * @param {Function} options.onConfirm Called on yes.
     * @param {Object} t Translated strings, keyed by string identifier.
     * @returns {Promise<void>}
     */
    async function showConfirmModal(options, t) {
        try {
            const modal = await ModalSaveCancel.create({
                title: options.title,
                body: options.body,
                buttons: {save: t.yes, cancel: t.no},
                show: true,
            });

            modal.getRoot().on(ModalEvents.save, options.onConfirm);
            modal.getRoot().on(ModalEvents.cancel, function() {
                modal.hide();
            });
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error('Modal error:', error);
        }
    }

    /**
     * Register the click handler that opens the issue details modal.
     *
     * @param {Object} t Translated strings, keyed by string identifier.
     * @returns {void}
     */
    function registerDetailsHandler(t) {
        $(document).on('click', '.view-details-btn', async function(e) {
            e.preventDefault();
            try {
                await showDetailsModal(JSON.parse(this.dataset.details), t);
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('Error parsing details:', error);
            }
        });
    }

    /**
     * Register the "clear all history" button's confirmation modal.
     *
     * @param {Object} t Translated strings, keyed by string identifier.
     * @returns {void}
     */
    function registerClearTableHandler(t) {
        $(document).on('click', '#clear-table-btn', function(e) {
            e.preventDefault();
            showConfirmModal({
                title: t.confirmation,
                body: t.confirm_clear,
                onConfirm: function() {
                    Ajax.call([{
                        methodname: 'logstore_moodefense_clear_table',
                        args: {},
                        done: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        },
                        fail: function(error) {
                            // eslint-disable-next-line no-console
                            console.error('Error calling logstore_moodefense_clear_table:', error);
                        }
                    }]);
                },
            }, t);
        });
    }

    /**
     * Register the "Filter" button, which collapses and expands the filter form (open by
     * default; css/issues.css animates it). A collapsed form is inert, so it cannot be tabbed into.
     *
     * @returns {void}
     */
    function registerFilterToggle() {
        $(document).on('click', '#toggle-filter-btn', function() {
            const panel = document.getElementById(this.getAttribute('aria-controls'));
            const collapsed = panel.classList.toggle('collapsed');
            panel.inert = collapsed;
            this.setAttribute('aria-expanded', String(!collapsed));
        });
    }

    /**
     * Register the "export to Excel" button, carrying over the current filters.
     *
     * @returns {void}
     */
    function registerExportHandler() {
        $(document).on('click', '#export-excel-btn', function(e) {
            e.preventDefault();
            const params = new URLSearchParams(window.location.search);
            const exportUrl = M.cfg.wwwroot + '/admin/tool/log/store/moodefense/export.php';
            window.location.href = params.toString() ? (exportUrl + '?' + params.toString()) : exportUrl;
        });
    }

    return {
        /**
         * Initialise the issues page: details modal, clear confirmation, export link and filter toggle.
         *
         * @returns {void}
         */
        init: function() {
            // eslint-disable-next-line promise/always-return
            Str.get_strings(STRINGS).then(function(values) {
                const t = {};
                STRINGS.forEach(function(s, i) {
                    t[s.key] = values[i];
                });

                registerDetailsHandler(t);
                registerClearTableHandler(t);
            }).catch(function(error) {
                // eslint-disable-next-line no-console
                console.error('Error loading strings:', error);
            });

            registerExportHandler();
            registerFilterToggle();
        }
    };
});
