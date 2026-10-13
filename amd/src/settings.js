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
 * Adds "Test API" and "Send test email" buttons next to their respective settings.
 *
 * @module     logstore_moodefense/settings
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification', 'core/modal'], function(Ajax, Str, Notification, Modal) {

    /**
     * Adds a test button + result indicator after the given input/textarea, which runs the given
     * webservice request and reports its outcome.
     *
     * @param {object} params
     * @param {Element} params.anchor Element after which the button/result wrapper is inserted.
     * @param {string} params.buttonstringkey Lang string key for the button label.
     * @param {string} params.testingstringkey Lang string key for the "in progress" text.
     * @param {string} params.okstringkey Lang string key for the success text.
     * @param {string} params.errorstringkey Lang string key for the error text (takes {$a}).
     * @param {Function} params.request Calls the webservice at click time, returns its promise.
     */
    var addTestButton = function(params) {
        Str.get_strings([
            {key: params.buttonstringkey, component: 'logstore_moodefense'},
            {key: params.testingstringkey, component: 'logstore_moodefense'},
            {key: params.okstringkey, component: 'logstore_moodefense'},
        ]).then(function(strings) {
            var strButton = strings[0];
            var strTesting = strings[1];
            var strOk = strings[2];

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-secondary btn-sm mt-2';
            button.textContent = strButton;

            var result = document.createElement('span');
            result.className = 'ml-2';

            var wrapper = document.createElement('div');
            wrapper.appendChild(button);
            wrapper.appendChild(result);
            params.anchor.parentNode.insertBefore(wrapper, params.anchor.nextSibling);

            button.addEventListener('click', function() {
                button.disabled = true;
                result.className = 'ml-2 text-muted';
                result.textContent = strTesting;

                params.request().then(function(response) {
                    if (response.success) {
                        result.className = 'ml-2 text-success font-weight-bold';
                        result.textContent = strOk;
                        return null;
                    }
                    return Str.get_string(params.errorstringkey, 'logstore_moodefense', response.message);
                }).catch(function(error) {
                    return Str.get_string(params.errorstringkey, 'logstore_moodefense', error.message || String(error));
                }).then(function(errortext) {
                    if (errortext !== null) {
                        result.className = 'ml-2 text-danger font-weight-bold';
                        result.textContent = errortext;
                    }
                    button.disabled = false;
                    return;
                });
            });

            return;
        }).catch(Notification.exception);
    };

    return {
        /**
         * "Active incident types" setting (classes/admin/setting_algorithms.php): an incident type
         * that is not detected cannot notify, and premium ones open a dialogue explaining how to get
         * MooDefense Premium.
         *
         * @param {string} settingid Id of the setting's element.
         * @param {string} contact Email address to ask about MooDefense Premium.
         */
        initAlgorithms: function(settingid, contact) {
            var setting = document.getElementById(settingid);
            if (!setting) {
                return;
            }
            // Fetched up front, so the dialogue opens with its text rather than a spinner.
            var contactbody = Str.get_string('premiumalgorithm_contact', 'logstore_moodefense', contact);
            setting.addEventListener('change', function(e) {
                var notify = e.target.dataset.notify && document.getElementById(e.target.dataset.notify);
                if (notify) {
                    notify.disabled = !e.target.checked;
                    notify.checked = e.target.checked;
                }
            });
            setting.addEventListener('click', function(e) {
                var button = e.target.closest('[data-action="moodefense-premium-algorithm"]');
                if (!button) {
                    return;
                }
                Modal.create({
                    title: button.dataset.name,
                    body: contactbody,
                    show: true,
                    removeOnClose: true,
                }).catch(Notification.exception);
            });
        },

        init: function() {
            var urlInput = document.getElementById('id_s_logstore_moodefense_apiurl');
            var tokenInput = document.getElementById('id_s_logstore_moodefense_token');
            // Both are needed: admin/search.php may show one setting without the other.
            if (urlInput && tokenInput) {
                addTestButton({
                    anchor: urlInput,
                    buttonstringkey: 'testapi',
                    testingstringkey: 'testapi_testing',
                    okstringkey: 'testapi_ok',
                    errorstringkey: 'testapi_error',
                    request: function() {
                        return Ajax.call([{
                            methodname: 'logstore_moodefense_test_api',
                            args: {apiurl: urlInput.value.trim(), token: tokenInput.value.trim()},
                        }])[0];
                    }
                });
            }

            // Severities to notify about: locked while algorithm 10 does not notify. Unlocked again on
            // submit, as a disabled field would save an empty selection.
            var severities = document.getElementById('id_s_logstore_moodefense_securityannouncementseverities');
            var notify = document.getElementById('id_s_logstore_moodefense_algorithms_notifications_10');
            if (severities && notify) {
                var lock = function() {
                    severities.disabled = notify.disabled || !notify.checked;
                };
                lock();
                // Detect also changes Notify, without an event of its own: watch the whole form.
                notify.form.addEventListener('change', lock);
                notify.form.addEventListener('submit', function() {
                    severities.disabled = false;
                });
            }

            var emailsInput = document.getElementById('id_s_logstore_moodefense_emailstosendnotification');
            if (emailsInput) {
                addTestButton({
                    anchor: emailsInput,
                    buttonstringkey: 'testemail',
                    testingstringkey: 'testemail_testing',
                    okstringkey: 'testemail_ok',
                    errorstringkey: 'testemail_error',
                    request: function() {
                        return Ajax.call([{
                            methodname: 'logstore_moodefense_test_email',
                            args: {emails: emailsInput.value.trim()},
                        }])[0];
                    }
                });
            }
        }
    };
});
