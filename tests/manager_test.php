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

/**
 * Tests for the manager class.
 *
 * @package message_slack
 * @author  Mike Churchward
 * @copyright 2017 onwards Mike Churchward (mike.churchward@poetgroup.org)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \message_slack\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Messages are converted to Slack formatting.
     */
    public function test_slackify_message(): void {
        // Links are rewritten to Slack links.
        $message1 = 'Test link - <a href="http://somelink.com/somelink.html" attr="otherattr">Some link text</a>';
        $expected1 = 'Test link - <http://somelink.com/somelink.html|Some link text>';
        $this->assertEquals($expected1, manager::slackify_message($message1));

        // Tags br, p and div become line breaks.
        $message2 = 'First line<br>Second line<br /><p>Third line</p><div>Fourth line</div>';
        $expected2 = "First line\nSecond line\n\nThird line\nFourth line";
        $this->assertEquals($expected2, manager::slackify_message($message2));

        // Quotes are kept: JSON encoding happens when the message is sent.
        $message3 = 'Single quote - \'. Double quote - ".';
        $this->assertEquals($message3, manager::slackify_message($message3));

        // Any other tag is removed.
        $message4 = '<span>Some text</span>. Some <b>other</b> text.';
        $expected4 = 'Some text. Some other text.';
        $this->assertEquals($expected4, manager::slackify_message($message4));

        // All together.
        $this->assertEquals(
            $expected1 . $expected2 . $message3 . $expected4,
            manager::slackify_message($message1 . $message2 . $message3 . $message4)
        );
    }

    /**
     * The connection is kept in user preferences and cleared per user.
     */
    public function test_user_connection(): void {
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $manager = new manager();

        $manager->set_user_connection(
            $user1->id,
            'U1',
            'xoxb-1',
            '#general',
            'C1',
            'https://x.slack.com/services/B1',
            'https://hooks.slack.com/services/T1/B1/X1'
        );
        $manager->set_user_connection(
            $user2->id,
            'U2',
            'xoxb-2',
            '#general',
            'C2',
            'https://x.slack.com/services/B2',
            'https://hooks.slack.com/services/T1/B2/X2'
        );

        $manager->clear_user_connection($user1->id);
        foreach (manager::PREFERENCES as $name) {
            $this->assertNull(get_user_preferences($name, null, $user1->id));
            $this->assertNotNull(get_user_preferences($name, null, $user2->id));
        }

        // Without a token the user is not configured.
        $this->assertFalse($manager->validate_user_connection($user1->id));
    }

    /**
     * The connection button points to the OAuth v2 authorisation of Slack.
     */
    public function test_config_form(): void {
        $this->resetAfterTest();
        set_config('clientid', '123.456', 'message_slack');
        set_config('teamurl', 'myteam.slack.com', 'message_slack');
        $manager = new manager();

        $html = $manager->config_form((object)['slack_configuration_url' => ''], 2);
        $this->assertStringContainsString('https://slack.com/oauth/v2/authorize?', $html);
        $this->assertStringContainsString('client_id=123.456', $html);
        $this->assertStringContainsString('scope=incoming-webhook', $html);

        $html = $manager->config_form((object)['slack_configuration_url' => 'https://myteam.slack.com/services/B1'], 2);
        $this->assertStringContainsString('https://myteam.slack.com/services/B1', $html);
    }
}
