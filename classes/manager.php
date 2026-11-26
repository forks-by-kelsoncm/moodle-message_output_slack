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

namespace message_slack;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Slack helper manager class
 *
 * @package message_slack
 * @author  Mike Churchward
 * @copyright  2017 onwards Mike Churchward (mike.churchward@poetgroup.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** Slack OAuth v2 authorisation URL. */
    const AUTHORIZE_URL = 'https://slack.com/oauth/v2/authorize';

    /** Slack OAuth v2 token exchange URL. */
    const ACCESS_URL = 'https://slack.com/api/oauth.v2.access';

    /** Slack token test URL. */
    const AUTH_TEST_URL = 'https://slack.com/api/auth.test';

    /** User preference names used to keep the Slack connection of each user. */
    const PREFERENCES = [
        'message_processor_slack_user_id',
        'message_processor_slack_access_token',
        'message_processor_slack_channel',
        'message_processor_slack_channel_id',
        'message_processor_slack_configuration_url',
        'message_processor_slack_url',
    ];

    /** @var \stdClass Plugin configuration. */
    protected $config;

    /**
     * @var bool[] Moodle can call is_user_configured repeatedly. Cache the curl result per user id.
     */
    private $validated = [];

    /**
     * Constructor. Loads all needed data.
     */
    public function __construct() {
        $this->config = get_config('message_slack');
    }

    /**
     * Send the message to the incoming webhook of the user.
     *
     * @param string $message The message content to send to Slack.
     * @param int $userid The Moodle user id that is being sent to.
     * @return bool
     */
    public function send_message($message, $userid) {
        // Get the user's incoming webhook URL. If not configured, abort.
        if (empty($webhookurl = get_user_preferences('message_processor_slack_url', '', $userid))) {
            return true;
        }

        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $response = $curl->post($webhookurl, json_encode(['text' => self::slackify_message($message)]));

        // Check if the incoming webhook has been removed by the user. If it has, the user will need to re-establish the connection.
        $response = trim((string)$response);
        if (in_array($response, ['No service', 'no_service', 'invalid_token', 'channel_not_found', 'channel_is_archived'])) {
            $this->clear_user_connection($userid);
        }

        return true;
    }

    /**
     * Filter content to Slack's message formatting.
     *
     * Links become <url|text>, line breaking tags become line breaks and any other tag is removed.
     * JSON encoding (quotes, backslashes and line breaks) is done when the message is sent.
     *
     * @param string $message Message to slackify.
     * @return string The slackified string.
     */
    public static function slackify_message($message) {
        // Change linked text to Slack style with a placeholder for adding back at the end.
        $message = preg_replace('/<a .*?href=["\'](.*?)["\'].*?>(.*?)<\/a>/', '<slack ${1}|${2}>', $message);

        // Change <br>, <div> and <p> to \n.
        $message = preg_replace(['/<br\s*?\/?>/', '/<p.*?>/', '/<div.*?>/', '/<\/p>/', '/<\/div>/'], ["\n", "\n", "\n"], $message);

        // Clean any remaining tags except the ones we marked as placeholders.
        $message = preg_replace('/<(?!slack ).*?>/', '', $message);

        // Finally, restore marked slack tags.
        return str_replace('<slack ', '<', $message);
    }

    /**
     * Return the requested configuration item or null. Should have been loaded in the constructor.
     *
     * @param string $configitem The requested configuration item.
     * @return mixed The requested value or null.
     */
    public function config($configitem) {
        return $this->config->{$configitem} ?? null;
    }

    /**
     * Return the HTML for the user preferences form.
     *
     * @param \stdClass $preferences The user preferences.
     * @param int $userid Moodle id of the user in question.
     * @return string The HTML for the form.
     */
    public function config_form($preferences, $userid) {
        $configurationurl = $preferences->slack_configuration_url ?? '';
        if (!empty($configurationurl)) {
            return \html_writer::link(
                new \moodle_url($configurationurl),
                get_string('manageslackaccount', 'message_slack', s($this->config('teamurl')))
            );
        }

        // The state variable confirms that the same user that sent this, receives it.
        $buttonurl = new \moodle_url(self::AUTHORIZE_URL, [
            'scope' => 'incoming-webhook',
            'client_id' => $this->config('clientid'),
            'redirect_uri' => $this->redirect_uri(),
            'state' => $this->state_var($userid),
        ]);
        $image = \html_writer::empty_tag('img', [
            'alt' => get_string('addtoslack', 'message_slack'),
            'height' => 40,
            'width' => 139,
            'src' => 'https://platform.slack-edge.com/img/add_to_slack.png',
            'srcset' => 'https://platform.slack-edge.com/img/add_to_slack.png 1x, ' .
                'https://platform.slack-edge.com/img/add_to_slack@2x.png 2x',
        ]);
        return get_string('connectslackaccount', 'message_slack', s($this->config('teamurl'))) .
            \html_writer::link($buttonurl, $image);
    }

    /**
     * Construct a state variable to use with the OAuth 2 API.
     *
     * @param int $userid The id of the Moodle user.
     * @return string A constructed state variable for this user (Moodle's sesskey).
     */
    public function state_var($userid) {
        return sesskey();
    }

    /**
     * Return the redirect URI to handle the callback for OAuth.
     *
     * @return string The URI.
     */
    public function redirect_uri() {
        return (new \moodle_url('/message/output/slack/slackconnect.php'))->out(false);
    }

    /**
     * Handle the token issuing steps of the Slack OAuth v2 flow (see https://api.slack.com/authentication/oauth-v2).
     *
     * The oauth.v2.access call returns a JSON object like:
     *     {"ok": true,
     *      "access_token": "xoxb-...",
     *      "token_type": "bot",
     *      "scope": "incoming-webhook",
     *      "team": {"name": "Team", "id": "T000"},
     *      "authed_user": {"id": "U000"},
     *      "incoming_webhook": {"channel": "#channel", "channel_id": "C000",
     *          "configuration_url": "https://team.slack.com/services/B000",
     *          "url": "https://hooks.slack.com/services/T000/B000/XXXX"}
     *     }
     *
     * @param string $code The temporary authorization code.
     * @param string $state The security identifier to verify the call was made from Moodle (sesskey).
     * @param int $userid The Moodle user ID.
     * @return bool Success of the operation.
     * @throws \moodle_exception When the plugin is not configured or Slack returns an invalid answer.
     */
    public function issue_token($code, $state, $userid) {
        if ($this->state_var($userid) != $state) {
            debugging('Error - unexpected state variable received. Possible exploit.');
            return false;
        }
        if (empty($clientid = $this->config('clientid'))) {
            throw new \moodle_exception('noclientid', 'message_slack');
        }
        if (empty($clientsecret = $this->config('clientsecret'))) {
            throw new \moodle_exception('noclientsecret', 'message_slack');
        }

        $curl = new \curl();
        $args = [
            'client_id' => $clientid,
            'client_secret' => $clientsecret,
            'code' => $code,
            'redirect_uri' => $this->redirect_uri(),
        ];
        $response = json_decode((string)$curl->post(self::ACCESS_URL, $args));

        if (empty($response->ok) || empty($response->incoming_webhook->url) || empty($response->authed_user->id)) {
            $error = $response->error ?? $curl->error ?? '';
            throw new \moodle_exception('invalidoauthresponse', 'message_slack', '', null, $error);
        }

        $slackuserid = get_user_preferences('message_processor_slack_user_id', '', $userid);
        if (!empty($slackuserid) && ($slackuserid != $response->authed_user->id)) {
            throw new \moodle_exception('invalidslackuser', 'message_slack');
        }

        $this->set_user_connection(
            $userid,
            $response->authed_user->id,
            $response->access_token,
            $response->incoming_webhook->channel ?? '',
            $response->incoming_webhook->channel_id ?? '',
            $response->incoming_webhook->configuration_url ?? '',
            $response->incoming_webhook->url
        );

        $this->validated[$userid] = true;

        return true;
    }

    /**
     * Set the user connection data for a slack button incoming webhook.
     *
     * @param int $userid The Moodle user ID to set data for.
     * @param string $slackuserid The Slack user ID.
     * @param string $accesstoken The Slack access token for this user's webhook.
     * @param string $channel The Slack channel name chosen by the user.
     * @param string $channelid The ID of the previous Slack channel.
     * @param string $configurationurl The URL to manage a connected Slack webhook.
     * @param string $url The webhook URL to use for this user's messages.
     */
    public function set_user_connection($userid, $slackuserid, $accesstoken, $channel, $channelid, $configurationurl, $url) {
        set_user_preferences([
            'message_processor_slack_user_id' => $slackuserid,
            'message_processor_slack_access_token' => $accesstoken,
            'message_processor_slack_channel' => $channel,
            'message_processor_slack_channel_id' => $channelid,
            'message_processor_slack_configuration_url' => $configurationurl,
            'message_processor_slack_url' => $url,
        ], $userid);
    }

    /**
     * Clear the user's slack connection information.
     *
     * @param int $userid The Moodle user to clear the info for.
     */
    public function clear_user_connection($userid) {
        foreach (self::PREFERENCES as $name) {
            unset_user_preference($name, $userid);
        }
        $this->validated[$userid] = false;
    }

    /**
     * Validate a configured Slack user connection.
     *
     * @param int $userid The Moodle userid.
     * @param bool $force Force the validation check, regardless of the cached value.
     * @return bool
     */
    public function validate_user_connection($userid, $force = false) {
        // If previously determined in this run, return the cached validated value.
        if (!$force && isset($this->validated[$userid])) {
            return $this->validated[$userid];
        }

        // If no token set, no valid connection.
        if (!($token = get_user_preferences('message_processor_slack_access_token', null, $userid))) {
            $this->validated[$userid] = false;
            return false;
        }

        // Slack does not accept tokens in the query string for new apps: send it in the header.
        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . $token);
        $response = json_decode((string)$curl->post(self::AUTH_TEST_URL, ''));
        if (!empty($response->ok)) {
            $this->validated[$userid] = true;
            return true;
        }
        if (isset($response->error) && in_array($response->error, ['token_revoked', 'invalid_auth', 'account_inactive'])) {
            $this->clear_user_connection($userid);
            return false;
        }

        // Not sure why it failed, so return true (could be a bad internet connection?).
        return true;
    }
}
