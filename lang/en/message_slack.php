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
 * Strings for slack message plugin.
 *
 * @package message_slack
 * @author  Mike Churchward
 * @copyright  2017 onwards Mike Churchward (mike.churchward@poetgroup.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addtoslack'] = 'Add to Slack';
$string['clientid'] = 'Client ID';
$string['clientsecret'] = 'Client secret';
$string['configclientid'] = 'Client ID from your App Credentials (https://api.slack.com/apps).';
$string['configclientsecret'] = 'Client Secret from your App Credentials (https://api.slack.com/apps).';
$string['configteamurl'] = 'Slack team URL client data below is for, and users must be part of.';
$string['connectioncancelled'] = 'The Slack connection was not completed.';
$string['connectslackaccount'] = 'Connect your {$a} slack account ';
$string['invalidoauthresponse'] = 'Invalid OAuth response.';
$string['invalidslackuser'] = 'Incorrect Slack user id returned.';
$string['manageslackaccount'] = 'Remove your {$a} slack account connection ';
$string['noclientid'] = 'No defined client_id';
$string['noclientsecret'] = 'No defined client_secret';
$string['notconfigured'] = 'The Slack server hasn\'t been configured so Slack messages cannot be sent';
$string['pluginname'] = 'Slack';
$string['privacy:metadata:preference:message_processor_slack_access_token'] = 'The Slack access token received when the user connected Slack.';
$string['privacy:metadata:preference:message_processor_slack_channel'] = 'The Slack channel chosen by the user to receive notifications.';
$string['privacy:metadata:preference:message_processor_slack_channel_id'] = 'The ID of the Slack channel chosen by the user.';
$string['privacy:metadata:preference:message_processor_slack_configuration_url'] = 'The Slack URL to manage the connection of the user.';
$string['privacy:metadata:preference:message_processor_slack_url'] = 'The Slack incoming webhook URL that receives the notifications of the user.';
$string['privacy:metadata:preference:message_processor_slack_user_id'] = 'The Slack user ID of the user.';
$string['privacy:metadata:slack'] = 'Notifications are sent to the Slack incoming webhook chosen by the user.';
$string['privacy:metadata:slack:fullmessage'] = 'The text of the notification.';
$string['slackusername'] = 'Your Slack username (@username)';
$string['teamurl'] = 'Team URL';
