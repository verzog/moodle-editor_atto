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

namespace editor_atto\admin;

/**
 * Admin setting for the Atto toolbar.
 *
 * @package    editor_atto
 * @copyright  2014 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toolbar_setting extends \admin_setting_configtextarea {
    /**
     * Validate data.
     *
     * This ensures that:
     * - Plugins are only used once,
     * - Group names are unique,
     * - Lines match: group = plugin[, plugin[, plugin ...]],
     * - There are some groups and plugins defined,
     * - The plugins used are installed.
     *
     * @param string $data
     * @return mixed True on success, else error message.
     */
    public function validate($data) {
        $result = parent::validate($data);
        if ($result !== true) {
            return $result;
        }

        $lines = explode("\n", $data);
        $groups = [];
        $plugins = [];

        foreach ($lines as $line) {
            if (!trim($line)) {
                continue;
            }

            $matches = [];
            if (!preg_match('/^\s*([a-z0-9]+)\s*=\s*([a-z0-9]+(\s*,\s*[a-z0-9]+)*)+\s*$/', $line, $matches)) {
                $result = get_string('errorcannotparseline', 'editor_atto', $line);
                break;
            }

            $group = $matches[1];
            if (isset($groups[$group])) {
                $result = get_string('errorgroupisusedtwice', 'editor_atto', $group);
                break;
            }
            $groups[$group] = true;

            $lineplugins = array_map('trim', explode(',', $matches[2]));
            foreach ($lineplugins as $plugin) {
                if (isset($plugins[$plugin])) {
                    $result = get_string('errorpluginisusedtwice', 'editor_atto', $plugin);
                    break 2;
                } else if (!\core_component::get_component_directory('atto_' . $plugin)) {
                    $result = get_string('errorpluginnotfound', 'editor_atto', $plugin);
                    break 2;
                }
                $plugins[$plugin] = true;
            }
        }

        // We did not find any groups or plugins.
        if (empty($groups) || empty($plugins)) {
            $result = get_string('errornopluginsorgroupsfound', 'editor_atto');
        }

        return $result;
    }
}
