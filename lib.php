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
 * YUI text editor integration.
 *
 * @package    editor_atto
 * @copyright  2013 Damyon Wiese  <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This is the texteditor implementation.
 * @copyright  2013 Damyon Wiese  <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class atto_texteditor extends texteditor {
    /**
     * Is the current browser supported by this editor?
     *
     * Of course!
     * @return bool
     */
    public function supported_by_browser() {
        return true;
    }

    /**
     * Returns array of supported text formats.
     * @return array
     */
    public function get_supported_formats() {
        // FORMAT_MOODLE is not supported here, sorry.
        return [FORMAT_HTML => FORMAT_HTML];
    }

    /**
     * Returns text format preferred by this editor.
     * @return int
     */
    public function get_preferred_format() {
        return FORMAT_HTML;
    }

    /**
     * Does this editor support picking from repositories?
     * @return bool
     */
    public function supports_repositories() {
        return true;
    }

    /**
     * Use this editor for given element.
     *
     * Available Atto-specific options:
     *   atto:toolbar - set to a string to override the system config editor_atto/toolbar
     *
     * Available general options:
     *   context - set to the current context object
     *   enable_filemanagement - set false to get rid of the managefiles plugin
     *   autosave - true/false to control autosave
     *
     * Options are also passed through to the plugins.
     *
     * @param string $elementid
     * @param array $options
     * @param null $fpoptions
     */
    public function use_editor($elementid, ?array $options = null, $fpoptions = null) {
        global $PAGE;

        // The signature allows a null $options, but the body dereferences it as an array
        // (array_key_exists(), $options['context'], ...). Guard against a fatal TypeError on PHP 8+.
        $options = $options ?? [];

        if (array_key_exists('atto:toolbar', $options)) {
            $configstr = $options['atto:toolbar'];
        } else {
            $configstr = get_config('editor_atto', 'toolbar');
        }

        $grouplines = explode("\n", $configstr);

        $groups = [];

        foreach ($grouplines as $groupline) {
            $line = explode('=', $groupline);
            if (count($line) > 1) {
                $group = trim(array_shift($line));
                $plugins = array_map('trim', explode(',', array_shift($line)));
                $groups[$group] = $plugins;
            }
        }

        $modules = ['moodle-editor_atto-editor'];
        $options['context'] = empty($options['context']) ? \core\context\system::instance() : $options['context'];

        $jsplugins = [];
        foreach ($groups as $group => $plugins) {
            $groupplugins = [];
            foreach ($plugins as $plugin) {
                // Do not die on missing plugin.
                if (!core_component::get_component_directory('atto_' . $plugin)) {
                    continue;
                }

                // Remove manage files if requested.
                if ($plugin == 'managefiles' && isset($options['enable_filemanagement']) && !$options['enable_filemanagement']) {
                    continue;
                }

                $jsplugin = [];
                $jsplugin['name'] = $plugin;
                $jsplugin['params'] = [];
                $modules[] = 'moodle-atto_' . $plugin . '-button';

                component_callback('atto_' . $plugin, 'strings_for_js');
                $extra = component_callback('atto_' . $plugin, 'params_for_js', [$elementid, $options, $fpoptions]);

                if ($extra) {
                    $jsplugin = array_merge($jsplugin, $extra);
                }
                // We always need the plugin name.
                $PAGE->requires->string_for_js('pluginname', 'atto_' . $plugin);
                $groupplugins[] = $jsplugin;
            }
            $jsplugins[] = ['group' => $group, 'plugins' => $groupplugins];
        }

        $PAGE->requires->strings_for_js([
                'editor_command_keycode',
                'editor_control_keycode',
                'plugin_title_shortcut',
                'textrecovered',
                'autosavefailed',
                'autosavesucceeded',
                'errortextrecovery',
                'richtexteditor',
            ], 'editor_atto');
        $PAGE->requires->strings_for_js([
                'warning',
                'info',
            ], 'moodle');
        $PAGE->requires->yui_module(
            $modules,
            'Y.M.editor_atto.Editor.init',
            [$this->get_init_params($elementid, $options, $fpoptions, $jsplugins)]
        );
    }

    /**
     * Create a params array to init the editor.
     *
     * @param string $elementid
     * @param array|null $options
     * @param array|null $fpoptions
     * @param array|null $plugins
     * @return array
     */
    protected function get_init_params($elementid, ?array $options = null, ?array $fpoptions = null, $plugins = null) {
        global $PAGE;

        $directionality = get_string('thisdirection', 'langconfig');
        $strtime        = get_string('strftimetime');
        $strdate        = get_string('strftimedaydate');
        $lang           = current_language();
        $autosave       = true;
        $autosavefrequency = get_config('editor_atto', 'autosavefrequency');
        if (isset($options['autosave'])) {
            $autosave       = $options['autosave'];
        }
        $contentcss     = $PAGE->theme->editor_css_url()->out(false);

        // Autosave disabled for guests and not logged in users.
        if (isguestuser() || !isloggedin()) {
            $autosave = false;
        }
        // Note <> is a safe separator, because it will not appear in the output of s().
        $pagehash = sha1($PAGE->url . '<>' . s($this->get_text()));
        $params = [
            'elementid' => $elementid,
            'content_css' => $contentcss,
            'contextid' => $options['context']->id,
            'autosaveEnabled' => $autosave,
            'autosaveFrequency' => $autosavefrequency,
            'language' => $lang,
            'directionality' => $directionality,
            'filepickeroptions' => [],
            'plugins' => $plugins,
            'pageHash' => $pagehash,
        ];
        if ($fpoptions) {
            $params['filepickeroptions'] = $fpoptions;
        }
        return $params;
    }
}
