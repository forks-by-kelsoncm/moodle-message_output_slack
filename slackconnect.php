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
 * Slack user connection handler.
 *
 * @package message_slack
 * @author  Mike Churchward
 * @copyright  2017 onwards Mike Churchward (mike.churchward@poetgroup.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

// State is used to confirm the operation is genuine. We expect the Moodle sesskey to be in this variable.
$state = required_param('state', PARAM_ALPHANUM);
$code = optional_param('code', '', PARAM_TEXT);
// Slack sends "error" instead of "code" when the user cancels the authorisation.
$error = optional_param('error', '', PARAM_ALPHANUMEXT);

$PAGE->set_url(new moodle_url('/message/output/slack/slackconnect.php'));
$PAGE->set_context(context_system::instance());

confirm_sesskey($state);

$returnurl = new moodle_url('/message/notificationpreferences.php', ['userid' => $USER->id]);
if ($error !== '' || $code === '') {
    redirect($returnurl, get_string('connectioncancelled', 'message_slack'), null, \core\output\notification::NOTIFY_WARNING);
}

$slackmanager = new message_slack\manager();
$slackmanager->issue_token($code, $state, $USER->id);

redirect($returnurl);
