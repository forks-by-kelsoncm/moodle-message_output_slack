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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use message_slack\privacy\provider;

/**
 * Tests for the privacy provider.
 *
 * @package    message_slack
 * @copyright  2026 Kelson da Costa Medeiros <kelsoncm@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \message_slack\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Metadata lists every preference and the external location.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('message_slack'))->get_collection();
        $this->assertCount(count(manager::PREFERENCES) + 1, $items);
    }

    /**
     * Preferences of the user are exported.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        (new manager())->set_user_connection(
            $user->id,
            'U1',
            'xoxb-1',
            '#general',
            'C1',
            'https://x.slack.com/services/B1',
            'https://hooks.slack.com/services/T1/B1/X1'
        );

        provider::export_user_preferences($user->id);
        $prefs = writer::with_context(\context_system::instance())->get_user_preferences('message_slack');
        $this->assertEquals('U1', $prefs->message_processor_slack_user_id->value);
        $this->assertEquals('https://hooks.slack.com/services/T1/B1/X1', $prefs->message_processor_slack_url->value);
    }
}
