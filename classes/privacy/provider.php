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

namespace message_slack\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use message_slack\manager;

/**
 * Privacy provider for message_slack.
 *
 * The plugin keeps the Slack connection of each user in user preferences and sends notifications to Slack.
 *
 * @package    message_slack
 * @copyright  2026 Kelson da Costa Medeiros <kelsoncm@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the data stored and sent by the plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (manager::PREFERENCES as $name) {
            $collection->add_user_preference($name, 'privacy:metadata:preference:' . $name);
        }
        $collection->add_external_location_link(
            'slack',
            ['fullmessage' => 'privacy:metadata:slack:fullmessage'],
            'privacy:metadata:slack'
        );
        return $collection;
    }

    /**
     * Export the Slack connection preferences of a user.
     *
     * @param int $userid The user ID.
     */
    public static function export_user_preferences(int $userid) {
        foreach (manager::PREFERENCES as $name) {
            $value = get_user_preferences($name, null, $userid);
            if ($value !== null && $value !== '') {
                writer::export_user_preference(
                    'message_slack',
                    $name,
                    $value,
                    get_string('privacy:metadata:preference:' . $name, 'message_slack')
                );
            }
        }
    }
}
