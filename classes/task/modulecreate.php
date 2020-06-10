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
 * A scheduled task for scripted database integrations.
 *
 * @package    local_modulecreate - template
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_modulecreate\task;
use stdClass;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/local/extdb/classes/task/extdb.php');

/**
 * A scheduled task for scripted external database integrations.
 *
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modulecreate extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'local_modulecreate');
    }

    /**
     * Run sync.
     */
    public function execute() {

        global $CFG, $DB;

        $externaldb = new \local_extdb\extdb();
        $name = $externaldb->get_name();

        $externaldbtype = $externaldb->get_config('dbtype');
        $externaldbhost = $externaldb->get_config('dbhost');
        $externaldbname = $externaldb->get_config('dbname');
        $externaldbencoding = $externaldb->get_config('dbencoding');
        $externaldbsetupsql = $externaldb->get_config('dbsetupsql');
        $externaldbsybasequoting = $externaldb->get_config('dbsybasequoting');
        $externaldbdebugdb = $externaldb->get_config('dbdebugdb');
        $externaldbuser = $externaldb->get_config('dbuser');
        $externaldbpassword = $externaldb->get_config('dbpass');
        $tablewrite = get_string('remotetablewrite', 'local_modulecreate');
        $tablecat = get_string('remotetablecat', 'local_modulecreate');
        $tablecourses = get_string('remotetablecourses', 'local_modulecreate');
        $tableenrols = get_string('remotetableenrols', 'local_modulecreate');

        // Database connection and setup checks.
        // Check connection and label Db/Table in cron output for debugging if required.
        if (!$externaldbtype) {
            echo 'Database not defined.<br>';
            return 0;
        } else {
            echo 'Database: ' . $externaldbtype . '<br>';
        }
        // Check writable table - usr_data_assessments.
        if (!$tablewrite) {
            echo 'Writable Table not defined.<br>';
            return 0;
        } else {
            echo 'Writable Table: ' . $tablewrite . '<br>';
        }
        // Check remote categories table - usr_data_student_assessments.
        if (!$tablecat) {
            echo 'Categories Table not defined.<br>';
            return 0;
        } else {
            echo 'Categories Table: ' . $tablecat . '<br>';
        }
        // Check remote courses table - usr_data_assessments.
        if (!$tablecourses) {
            echo 'Courses Table not defined.<br>';
            return 0;
        } else {
            echo 'Courses Table: ' . $tablecourses . '<br>';
        }
        // Check remote enrolments table - usr_data_student_assessments.
        if (!$tableenrols) {
            echo 'Enrolments Table not defined.<br>';
            return 0;
        } else {
            echo 'Enrolments Table: ' . $tableenrols . '<br>';
        }

        echo 'Starting connection...<br>';

        // Report connection error if occurs.
        if (!$extdb = $externaldb->db_init(
            $externaldbtype,
            $externaldbhost,
            $externaldbuser,
            $externaldbpassword,
            $externaldbname)) {
            echo 'Error while communicating with external database <br>';
            return 1;
        }

        /* Get data to create module/course lists for course creation
         * ---------------------------------------------------------- */
        /* Categories - to create overarching pages within School/SubjComm/Domain (ExtDb)
         */
        $catsites = array();
        if ($tablecat) {
            // Get external table name.
            $cattable = $tablecat;
            // Read data from table.
            $sql = $externaldb->db_get_sql($cattable, array(), array(), true);
            if ($catrs = $extdb->Execute($sql)) {
                if (!$catrs->EOF) {
                    while ($cats = $catrs->FetchRow()) {
                        $cats = array_change_key_case($cats, CASE_LOWER);
                        $cats = $externaldb->db_decode($cats);
                        $catsites[] = $cats;
                    }
                }
                $catrs->Close();
            } else {
                // Report error if required.
                $extdb->Close();
                echo 'Error reading data from the external course table<br>';
                return 4;
            }
        }
        echo ' Catsites done<br>';
        /* catsites() ->
         *               id
         *               category_name
         *               category_idnumber
         *               parent_cat_idnumber
         *               deleted
         */

        /* Compare enrolments and MAV list to create Taught Module list. (ExtDb)
         * This only includes MAVs on SITS with at least 1 person enrolled
         * whether Mod/Mav Tutor, Co-tutor or student. */
        $enrolledcourseslist = array();
        if ($tableenrols) {
            // Get external table name.
            $enrolstable = $tableenrols;
            $coursetable = $tablecourses;

            // Read data from table.
            $sql = "SELECT * FROM " . $coursetable . " WHERE (course_idnumber LIKE '%18/19' OR course_idnumber LIKE '%19/20') AND course_idnumber IN
                (SELECT distinct course FROM " . $enrolstable .")";
            if ($enrcrsrs = $extdb->Execute($sql)) {
                if (!$enrcrsrs->EOF) {
                    while ($encrs = $enrcrsrs->FetchRow()) {
                        $encrs = array_change_key_case($encrs, CASE_LOWER);
                        $encrs = $externaldb->db_decode($encrs);
                        $enrolledcourseslist[] = $encrs;
                    }
                }
                $enrcrsrs->Close();
            } else {
                // Report error if required.
                $extdb->Close();
                echo 'Error reading data from the external course table<br>';
                return 4;
            }
        }
        $enrolledcourses = $enrolledcourseslist;
        echo ' Enrols done<br>';
        /* enrolledcourses() ->
         *                       course_idnumber
         *                       course_fullname
         *                       course_shortname
         *                       course_startdate
         *                       category_idnumber
         */

        // Staff Sandbox Pages.
        // --------------------
        // Find id of the staff_SB category. If there isn't one then bypass whole section.
        if ($DB->record_exists('course_categories', array('idnumber' => 'staff_SB'))) {
            $sbcat = $DB->get_record('course_categories', array('idnumber' => 'staff_SB'), 'id');
            $sbcategory = $sbcat->id;
        } else {
            $sbcategory = 0;
        }
        // Array of staff user data.
        $select = "SELECT * FROM {user} WHERE email LIKE '%@glos.ac.uk'"; // Pattern match for staff email accounts.
        $sandboxes = $DB->get_records_sql($select);

        echo ' Sandboxes done3<br>';
        /* sandboxes() ->
         *                       * FROM mdl_user
         *                       idnumber
         */

        /* Make courses list to add to course creation table.
         * NOTE: This script simply creates a table for Moodle to create *new* pages
         * It does NOT manage them into correct categories if there are changes.
         * ------------------------------------------------------------------------- */

        $newsite = array();
        $siteslist = array();
        $sites = array();
        $category = new stdClass;
        // Loop through categories array to create course site details for each category.
        foreach ($catsites as $page) {
            if ($page['category_idnumber']) {
            $pageidnumber = 'CRS-' . $page['category_idnumber'];
            if (!$DB->record_exists('course',
                array('idnumber' => $pageidnumber))) { // Only add if doesn't already exist in mdl_course.
                $newsite['fullname'] = $page['category_name'];
                // Category sites do not have both shortname and idnumber so use category idnumber for both.
                // Prefix with CRS for ease of identifying in front end UI and Db searches.
                $newsite['shortname'] = $pageidnumber;
                $newsite['idnumber'] = $pageidnumber;
                // Get category id for the relevant category idnumber - this is what is needed in the table.
                $categoryidnumber = $page['category_idnumber'];
                $category = $DB->get_record('course_categories', array('idnumber' => $categoryidnumber));
                $newsite['categoryid'] = $category->id;
            }
            $siteslist[] = $newsite;
            }
        }
        echo ' Catsites prepped and added<br>';

        // Loop through taughtmodules array to create course site details for each category.
        foreach ($enrolledcourses as $page) {
            if ($page['category_idnumber']) {

            $pageidnumber = $page['course_idnumber'];
            if (!$DB->get_records('course',
                array('idnumber' => $pageidnumber))) { // Only add if doesn't already exist in mdl_course.
                    $newsite['fullname'] = $page['course_fullname'];
                    $newsite['shortname'] = $page['course_shortname'];
                    $newsite['idnumber'] = $pageidnumber;
                    // Get category id for the relevant category idnumber - this is what is needed in the table.
                    $categoryidnumber = $page['category_idnumber'];
                    if ($DB->record_exists('course_categories', array('idnumber' => $categoryidnumber))) {
                        $category = $DB->get_record('course_categories', array('idnumber' => $categoryidnumber));
                    } else {
                        $category = $DB->get_record('course_categories', array('idnumber' => 'MISC'));
                    }
                    echo $page['course_shortname'].':';
                    echo $pageidnumber.':';
                    echo $categoryidnumber.':';
                    echo $category->id.'<br>';
                    $newsite['categoryid'] = $category->id;
            }
            $siteslist[] = $newsite;
            }
        }
        echo ' Enrol sites prepped and added<br>';

        // Loop through staff sandbox array to create course site details for each category.
        // Find id of the staff_SB category. If there isn't one then bypass whole section.
        foreach ($sandboxes as $page) {
            if ($page->idnumber) {
                $pageidnumber = $page->idnumber;
                if (!$DB->get_records('course',
                    array('idnumber' => $pageidnumber))) { // Only add if doesn't already exist.
                    $newsite['fullname'] = $pageidnumber . " Sandbox Test Page";
                    $newsite['shortname'] = $pageidnumber . "_SB";
                    $newsite['idnumber'] = $pageidnumber . "_SB";
                    $newsite['categoryid'] = $sbcategory;
                }
                $siteslist[] = $newsite;
            }
        }
        echo ' Sandbox sites prepped and added<br>';
        $sites = array_filter($siteslist);

        // Write $sites[] to external database table.
        // ==========================================
        // Get external table to write to.
        if ($tablewrite) {
            $writetable = $tablewrite;

            /* Drop existing table contents and refill
             * --------------------------------------- */
            $sql = 'TRUNCATE '.$writetable;
            $extdb->Execute($sql);

            foreach ($sites as $ns) {
                echo '-----------=====================--------------';
                print_r($ns);
                $fullname = $ns['fullname'];
                $shortname = $ns['shortname'];
                $idnumber = $ns['idnumber'];
                $categoryid = $ns['categoryid'];

                // Strip special characters from fullname and shortname.
                // Remove ' from fullname if present (prevents issues with sql line).
                $fullname = str_replace("'", "", $fullname);
                $shortname = str_replace("'", "", $shortname);

                // Remove em dash, replace with -.
                $emdash = html_entity_decode('&#x2013;', ENT_COMPAT, 'UTF-8');
                $fullname = str_replace($emdash, '-', $fullname);
                $shortname = str_replace($emdash, '-', $shortname);

                $emdash2 = html_entity_decode('&#8212;', ENT_COMPAT, 'UTF-8');
                $fullname = str_replace($emdash2, '-', $fullname);
                $shortname = str_replace($emdash2, '-', $shortname);

                $fullname = str_replace('\u2014', '-', $fullname);
                $shortname = str_replace('\u2014', '-', $shortname);

                // Remove ? from fullname.
                $fullname = str_replace("?", "", $fullname);
                $shortname = str_replace("?", "", $shortname);

                // Remove &.
                $fullname = str_replace("&", " and ", $fullname);
                $shortname = str_replace("&", " and ", $shortname);

                    // Set new coursesite in table by inserting the data created above.
                    $sql = "INSERT INTO " . $writetable . " (course_fullname,course_shortname,course_idnumber,category_id)
                        VALUES ('" . $fullname . "','" . $shortname . "','" . $idnumber . "','" .$categoryid . "')";
                    $extdb->Execute($sql);

            }
        }
        // Free memory.
        $extdb->Close();
        // End of External Database data section.

    }

}

