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
 * Strings for component 'local_modulecreate', language 'en'.
 *
 * @package   local_modulecreate
 * @copyright 2017 RMOelmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'modulecreate';
$string['pluginname_desc'] = 'This plugin does not actually create the new modules - it creates a table which is then used by the external database enrol core plugin to create the modules.';

$string['remotetablewrite'] = 'usr_ro_modules';
$string['remotetablecat'] = 'usr_data_categories';
$string['remotetablecourses'] = 'usr_data_courses';
$string['remotetableenrols'] = 'usr_enrolment_integration';