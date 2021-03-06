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
 * On-the-fly conversion of Moodle lang strings to TinyMCE expected JS format.
 *
 * @package    editor_tinymce
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_UPGRADE_CHECK', true);

require('../../../../config.php');

$lang  = optional_param('elanguage', 'en', PARAM_SAFEDIR);
$theme = optional_param('etheme', 'advanced', PARAM_SAFEDIR);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/lib/editor/tinymce/extra/strings.php');

if (!get_string_manager()->translation_exists($lang, false)) {
    $lang = 'en';
}

$string = get_string_manager()->load_component_strings('editor_tinymce', $lang);

// Process the $strings to match expected tinymce lang array structure.
$result = array();

foreach ($string as $key=>$value) {
    $parts = explode(':', $key);
    if (count($parts) != 2) {
        // Ignore non-TinyMCE strings.
        continue;
    }

    $result[$parts[0]][$parts[1]] = $value;
}

// Add subplugin strings, accept only those with proper pluginname prefix with colon.
foreach (get_plugin_list('tinymce') as $component => $ignored) {
    $componentstrings = get_string_manager()->load_component_strings(
            'tinymce_' . $component, $lang);
    foreach ($componentstrings as $key => $value) {
        if (strpos($key, "$component:") !== 0) {
            // Ignore normal lang strings.
            continue;
        }
        $parts = explode(':', $key);
        if (count($parts) != 2) {
            // Ignore malformed strings with more colons.
            continue;
        }
        $result[$parts[0]][$parts[1]] = $value;
    }
}

$output = 'tinyMCE.addI18n({'.$lang.':'.json_encode($result).'});';

// If PHP's internal gzip output compression is on, we won't be able to correctly
// determine the value of the content header. Instead, we'll turn it off, and perform
// the same gzip encoding ourself.
if(ini_get('zlib.output_compression')) {

    // Turn off PHP's built-in zlib compression...
    ini_set('zlib.output_compression','Off');

    // And compress the data ourselves. This allow us to correctly set the 
    // content-length header.
    $output = gzencode($output,6);

    // Let the recieving browser know that the data will be gzip-compressed.
    @header('Content-Encoding: gzip');
}

$lifetime = '10'; // TODO: increase later
@header('Content-type: text/javascript; charset=utf-8');
@header('Content-length: '.strlen($output));
@header('Last-Modified: '. gmdate('D, d M Y H:i:s', time()) .' GMT');
@header('Cache-control: max-age='.$lifetime);
@header('Expires: '. gmdate('D, d M Y H:i:s', time() + $lifetime) .'GMT');
@header('Pragma: ');

echo $output;

