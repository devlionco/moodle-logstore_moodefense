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
 * English language strings for the Moodefense log store plugin.
 *
 * @package    logstore_moodefense
 * @copyright  Fensive <avishay@fensive.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activeincidenttypes'] = 'Active Incident Types';
$string['algorithm1'] = 'Staff Activity Without Prior Login';
$string['algorithm10'] = 'Moodle Security Announcement Match';
$string['algorithm10_case'] = 'A published moodle.org security announcement lists this site\'s installed Moodle version among the affected versions. Review the ticket details in the message field and update to the fixed version referenced there as soon as possible.';
$string['algorithm1_case'] = 'Detection of activity by a staff or admin user originating from an IP address and User Agent for which no prior authentication has been recorded for that user account. This situation raises suspicion of Session Hijacking, indicating that the user’s session identifiers may have been compromised and reused by an unauthorized party.';
$string['algorithm2'] = 'Staff and Student Login from the Same Device';
$string['algorithm2_case'] = 'Detection of cases where a staff or admin user logged into the system from the same IP address and User Agent that was recently used for a student login event. This behavior may indicate that a staff member’s credentials were stolen and used by an unauthorized party.';
$string['algorithm3'] = 'Student Activity Without Prior Login';
$string['algorithm3_case'] = 'Detection of activity by a student user originating from an IP address and User Agent for which no prior authentication has been recorded for that user account. This situation raises suspicion of Session Hijacking, indicating that the user’s session identifiers may have been compromised and reused by an unauthorized party.';
$string['algorithm4'] = 'User Activity from Multiple IP Addresses';
$string['algorithm4_case'] = 'Detection of a user performing system actions from ten or more different IP addresses within a short period of time (default: 30 minutes). This behavior may indicate the use of obfuscation mechanisms such as VPN, Proxy, API Gateway, or Tor to conceal the attacker’s identity or evade security controls.';
$string['algorithm5'] = 'Admin Login from an Unauthorized Country';
$string['algorithm5_case'] = 'A login event by an admin user from an IP address associated with a country that is not included in the list of authorized countries defined in the system.';
$string['algorithm6'] = 'Staff Login from an Unauthorized Country';
$string['algorithm6_case'] = 'A login event by a staff user (e.g., Teacher) from an IP address associated with a country that is not included in the list of authorized countries defined in the system.';
$string['algorithm7'] = 'Aggressive Password Guessing';
$string['algorithm7_case'] = 'The analysis detects password guessing (brute-force) attacks against user accounts. Detection is triggered when 50 or more failed authentication attempts against the same account are recorded from the same IP address within 30 minutes. If a successful login occurs for a user account following failed attempts, the risk level is classified as High; if only failed attempts are observed, the risk level is Medium.';
$string['algorithm8'] = 'Dormant Staff Account Login';
$string['algorithm8_case'] = 'Detection of a login event by a staff or admin user after a prolonged period of inactivity (six months or more). Such a sudden login may indicate the use of credentials that were forgotten, stolen, or compromised, and could suggest attempted access by an unauthorized entity.';
$string['algorithm9'] = 'Modification in Moodle Web Root Directory';
$string['algorithm9_case'] = 'Detection of changes in the main directory served by the Moodle Web service, including file additions, modifications, or deletions. Unexpected changes in this directory may indicate a system compromise, code injection, or the upload of a WebShell that enables remote server-side command execution.';
$string['algorithmcompleteemail'] = 'Algorithm {$a->algorithm} complete. Send email to user: {$a->email}';
$string['algorithmsnotifications'] = 'Algorithms with notifications';
$string['algorithmsnotifications_desc'] = 'Select which algorithms should send admin notifications when incidents are detected.';
$string['all_roles'] = 'All roles';
$string['all_severity'] = 'All severities';
$string['all_statuses'] = 'All statuses';
$string['apierror'] = 'Moodefense API request failed: {$a}';
$string['apiurl'] = 'API URL';
$string['bruteforce'] = 'IP {$a->ip} made brute force attack, related user {$a->userid}.';
$string['bruteforceunknownuser'] = 'IP {$a->ip} performs a brute force attack with no user role.';
$string['buffersize'] = 'Write buffer size';
$string['cachedef_geoip'] = 'Countries of IP addresses';
$string['clearallhistory'] = 'Clear all history';
$string['close'] = 'Close';
$string['column_actions'] = 'Actions';
$string['column_comment'] = 'Comment';
$string['column_comments'] = 'Comments';
$string['column_incidentid'] = 'Incident ID';
$string['column_incidenttype'] = 'Incident Type';
$string['column_ip'] = 'IP';
$string['column_lastip'] = 'Last IP';
$string['column_message'] = 'Message';
$string['column_number'] = '№';
$string['column_roles'] = 'Roles';
$string['column_severity'] = 'Severity';
$string['column_status'] = 'Status';
$string['column_time'] = 'Time';
$string['column_userid'] = 'User ID';
$string['column_username'] = 'User Name';
$string['column_viewdetails'] = 'View Details';
$string['confirm_clear'] = 'Are you sure you want to remove all history?';
$string['confirmation'] = 'Confirmation';
$string['contactus'] = 'Contact us';
$string['dashboard'] = 'MooDefense Dashboard';
$string['datasaved'] = 'Data saved successfully.';
$string['detect'] = 'Detect';
$string['detectalgorithm'] = 'Detect {$a}';
$string['emailstosendnotification'] = 'Emails to send notifications';
$string['emailstosendnotification_desc'] = 'Enter one or more email addresses (comma separated) that should receive notifications. They are sent through the site\'s <a href="{$a}">outgoing mail configuration</a>.';
$string['eventissuescleared'] = 'MooDefense issues cleared';
$string['eventissueupdated'] = 'MooDefense issue updated';
$string['exporttoexcel'] = 'Export to Excel';
$string['freeversion'] = 'Free version';
$string['fullmessage_full'] = 'Hello {$a->admin_fullname},

Title: Security issues for the algorithm {$a->algorithmname}
ID: {$a->suspecteduserid}
Fullname: {$a->user_fullname}
Username: {$a->username}
Idnumber: {$a->idnumber}
Lastaccess: {$a->lastaccess}
Lastip: {$a->lastip}
Profile: {$a->profileurl}
Link to the log of this user for today: {$a->logurl}
Link to the table of issues: {$a->issuesurl}';
$string['fullmessage_simple'] = 'Hello {$a->admin_fullname},

Title: Security issues for the algorithm {$a->algorithmname}

Link to the table of issues: {$a->issuesurl}';
$string['fullmessagehtml_full'] = '<p>Hello {$a->admin_fullname},</p>
<p>Title: Security issue for the algorithm <b>{$a->algorithmname}</b></p>
<p>ID: {$a->suspecteduserid}</p>
<p>Fullname: {$a->user_fullname}</p>
<p>Username: {$a->username}</p>
<p>Idnumber: {$a->idnumber}</p>
<p>Lastaccess: {$a->lastaccess}</p>
<p>LastIP: {$a->lastip}</p>
<p><a href="{$a->profileurl}">View profile</a></p>
<p><a href="{$a->logurl}">View user log for today</a></p>
<p><a href="{$a->issuesurl}">View table of issues</a></p>';
$string['fullmessagehtml_simple'] = '<p>Hello {$a->admin_fullname},</p>
<p>Title: Security issue for the algorithm <b>{$a->algorithmname}</b></p>
<p><a href="{$a->issuesurl}">View table of issues</a></p>';
$string['ipaddressfromunpermittedlocation'] = 'IP address of user ID {$a->userid} originates from an unpermitted location: {$a->country}';
$string['ipsless'] = 'less';
$string['ipsmore'] = '{$a} more';
$string['issuesbyseverity'] = 'Count of Issues by Severity';
$string['jsonformat'] = 'JSON format';
$string['jsonformat_desc'] = 'Use standard JSON format instead of PHP serialised data in the \'other\' database field.';
$string['lastissues'] = 'Last Issues';
$string['logguests_desc'] = 'Whether to also log events of guests and users who are not logged in. Failed logins are always logged: password guessing detection needs them.';
$string['manyips'] = 'Activity from many different IP addresses within a short time.';
$string['messageprovider:securityalert'] = 'Security alert notifications';
$string['modal_comment'] = 'Comment';
$string['modal_cve_identifier'] = 'CVE';
$string['modal_description'] = 'Description';
$string['modal_incidentid'] = 'Incident ID';
$string['modal_incidenttype'] = 'Incident Type';
$string['modal_ips'] = 'IPs';
$string['modal_link'] = 'Announcement';
$string['modal_loglink'] = 'Log link';
$string['modal_message'] = 'Message';
$string['modal_profilelink'] = 'Profile link';
$string['modal_roles'] = 'Roles';
$string['modal_severity'] = 'Severity';
$string['modal_status'] = 'Status';
$string['modal_time'] = 'Time';
$string['modal_useragent'] = 'User Agent';
$string['modal_userid'] = 'User ID';
$string['modal_username'] = 'User Name';
$string['modal_versions_affected'] = 'Affected versions';
$string['modal_versions_fixed'] = 'Fixed in';
$string['moodefense:manage'] = 'Manage MooDefense issues and settings';
$string['moodefense:view'] = 'View MooDefense issues and dashboard';
$string['moodefenseissues'] = 'MooDefense Issues';
$string['noincidents'] = 'No incidents found';
$string['notapplicable'] = 'N/A';
$string['notify'] = 'Notify';
$string['notifyalgorithm'] = 'Notify about {$a}';
$string['otherroles'] = 'Others';
$string['otherrolesheader'] = '{$a} other roles';
$string['permittedgeolocation'] = 'Permitted geolocations';
$string['permittedgeolocation_desc'] = 'Comma-separated country codes site admins may log in from, e.g. IL, PL, US. Logins from other countries raise an issue. Empty turns the check off.';
$string['pluginname'] = 'MooDefense';
$string['premium'] = 'Premium';
$string['premiumalgorithm_contact'] = 'This incident type is part of MooDefense Premium. To get it, contact us at <a href="mailto:{$a}">{$a}</a>.';
$string['premiumbanner'] = 'MooDefense Premium detects {$a} more incident types.';
$string['privacy:metadata:core_message'] = 'When a security issue is detected, site administrators are sent a notification describing the affected user.';
$string['privacy:metadata:logstore_moodefense_issues'] = 'MooDefense detected security issues. They are exported with a user\'s data, but kept when it is deleted: they are the evidence of security incidents, kept for the site\'s legitimate interest in its security. Administrators delete them on the issues page.';
$string['privacy:metadata:logstore_moodefense_issues:comment'] = 'An administrator\'s comment on the issue.';
$string['privacy:metadata:logstore_moodefense_issues:data'] = 'Additional data about the security issue, such as IP addresses and user agents; for shared-device issues, also the ID, IP address and user agent of the suspected second user.';
$string['privacy:metadata:logstore_moodefense_issues:dateissue'] = 'The time of the activity that caused the issue.';
$string['privacy:metadata:logstore_moodefense_issues:message'] = 'A human-readable description of the detected security issue.';
$string['privacy:metadata:logstore_moodefense_issues:roles'] = 'The roles of the user at the time of detection.';
$string['privacy:metadata:logstore_moodefense_issues:severity'] = 'The severity level assigned to the issue.';
$string['privacy:metadata:logstore_moodefense_issues:status'] = 'The review status an administrator set for the issue.';
$string['privacy:metadata:logstore_moodefense_issues:timecreated'] = 'The time when the issue was detected.';
$string['privacy:metadata:logstore_moodefense_issues:userid'] = 'The ID of the user associated with the issue.';
$string['privacy:metadata:logstore_moodefense_log'] = 'MooDefense event log table.';
$string['privacy:metadata:logstore_moodefense_log:anonymous'] = 'Whether the event was flagged as anonymous.';
$string['privacy:metadata:logstore_moodefense_log:courseid'] = 'The ID of the course the event occurred in, where applicable.';
$string['privacy:metadata:logstore_moodefense_log:eventname'] = 'The event name.';
$string['privacy:metadata:logstore_moodefense_log:ip'] = 'The IP address of the user at the time of the event.';
$string['privacy:metadata:logstore_moodefense_log:ipint'] = 'A numeric form of the IP address, used to find events from the same address.';
$string['privacy:metadata:logstore_moodefense_log:origin'] = 'The origin of the request (web, cli, ws, etc.).';
$string['privacy:metadata:logstore_moodefense_log:other'] = 'Additional event-specific data captured by the underlying Moodle event.';
$string['privacy:metadata:logstore_moodefense_log:realuserid'] = 'The ID of the real user, when the event was triggered while logged in as another user.';
$string['privacy:metadata:logstore_moodefense_log:relateduserid'] = 'The ID of a second user related to the event, where applicable.';
$string['privacy:metadata:logstore_moodefense_log:sessionidentifier'] = 'An identifier derived from the request, used to correlate related events.';
$string['privacy:metadata:logstore_moodefense_log:timecreated'] = 'The time when the event was created.';
$string['privacy:metadata:logstore_moodefense_log:useragent'] = 'The user agent string of the browser.';
$string['privacy:metadata:logstore_moodefense_log:useragentint'] = 'A numeric hash of the user agent string, used to detect device/browser changes.';
$string['privacy:metadata:logstore_moodefense_log:userid'] = 'The ID of the user who triggered the event.';
$string['privacy:metadata:moodefense_email'] = 'When a security issue is detected, details about the affected user are emailed to the addresses configured in "Emails to send notifications" (or to site administrators if none are configured).';
$string['privacy:metadata:moodefense_email:fullname'] = 'The affected user\'s full name.';
$string['privacy:metadata:moodefense_email:idnumber'] = 'The affected user\'s ID number.';
$string['privacy:metadata:moodefense_email:lastaccess'] = 'The affected user\'s last access time.';
$string['privacy:metadata:moodefense_email:lastip'] = 'The affected user\'s last known IP address.';
$string['privacy:metadata:moodefense_email:username'] = 'The affected user\'s username.';
$string['privacy:path:issues'] = 'Security issues';
$string['roles'] = 'Count of Users By Role';
$string['security_issue_subject'] = 'Security Issue Found: {$a->algorithmname} | {$a->levelsecurity}';
$string['securityannouncement'] = '{$a->ticketid}: {$a->title} | CVE: {$a->cve} | Affected versions: {$a->affected} | Fixed in: {$a->fixed} | {$a->link}';
$string['securityannouncementseverities'] = 'Security announcement severities to notify';
$string['securityannouncementseverities_desc'] = 'Select which moodle.org security announcement severities notify admins when they affect this site\'s installed Moodle version. Issues are raised for every severity. Available when Notify is selected for Moodle Security Announcement Match.';
$string['selectalgorithms_desc'] = 'Detect: look for this incident type. Notify: tell administrators when it is found. Select a premium incident type to see how to get MooDefense Premium.';
$string['severitycritical'] = 'Critical';
$string['severityhigh'] = 'High';
$string['severitylow'] = 'Low';
$string['severitymedium'] = 'Medium';
$string['severityminor'] = 'Minor';
$string['severityserious'] = 'Serious';
$string['showingissues'] = 'Showing {$a->from}–{$a->to} of {$a->total}';
$string['siteadmins'] = 'admins';
$string['smallmessage_full'] = 'Security issue detected for User with ID: {$a->suspecteduserid} (Algorithm {$a->algorithmname})';
$string['smallmessage_simple'] = 'Security issue detected (Algorithm {$a->algorithmname})';
$string['staffroles'] = 'Roles of staff';
$string['staffroles_desc'] = 'Select the roles that should be considered managers. Users assigned to the selected roles will be treated as managers.';
$string['status_notrelevant'] = 'Not Relevant';
$string['status_opened'] = 'Opened';
$string['status_resolved'] = 'Resolved';
$string['status_unknown'] = 'Unknown';
$string['taskcleanup'] = 'MooDefense log table cleanup';
$string['taskprocessing'] = 'MooDefense Processing';
$string['tasksecurityannouncement'] = 'MooDefense Security Announcement Check';
$string['testapi'] = 'Test API';
$string['testapi_empty'] = 'API URL and token are required';
$string['testapi_error'] = 'Error: {$a}';
$string['testapi_ok'] = 'OK';
$string['testapi_testing'] = 'Testing...';
$string['testemail'] = 'Test email';
$string['testemail_body'] = 'This is a test email from the MooDefense log store plugin, confirming that this address can receive notifications.';
$string['testemail_error'] = 'Error: {$a}';
$string['testemail_failed'] = 'failed to send to {$a}';
$string['testemail_invalid'] = 'invalid addresses: {$a}';
$string['testemail_noaddresses'] = 'no email addresses entered';
$string['testemail_ok'] = 'OK';
$string['testemail_subject'] = 'MooDefense test email';
$string['testemail_testing'] = 'Sending...';
$string['tokenforapi'] = 'Token for API';
$string['tokenforapi_desc'] = 'Moodefense API key, needed by the Moodle Security Announcement Match incident type.';
$string['urlforapi_desc'] = 'Moodefense API URL, needed by the Moodle Security Announcement Match incident type.';
$string['user_suspected_access'] = 'User with ID {$a->suspecteduid} logged in from the same device as staff member with ID {$a->targetuid}.';
$string['viewallissues'] = 'View all issues';
$string['viewdetails'] = 'Security details';
$string['viewlogs'] = 'View Logs';
$string['viewprofile'] = 'View Profile';
