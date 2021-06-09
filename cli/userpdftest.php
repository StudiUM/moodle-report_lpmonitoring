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
 * A script to test the PDF generation. This file is temporary and will be deleted after testing.
 *
 * @package report_lpmonitoring
 * @author Jason Maur <jason.maur@umontreal.ca>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2020 onwards Université de Montréal (http://www.umontreal.ca)
 */
define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php'); // Global moodle config file.

global $CFG, $SESSION;
require_once($CFG->dirroot . '/report/lpmonitoring/classes/external/user_pdf.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->libdir . '/pdflib.php');

$user = $DB->get_record("user", array("username" => "admda_pa23519"));
complete_user_login($user);
$SESSION->lang = 'fr';

// Some test users.
$userid = 209500; // A couple test IDs: 189921 and 209500.

$userpdf = new \report_lpmonitoring\external\user_pdf($userid);

// Ensure errors are well explained.
set_debugging(DEBUG_DEVELOPER, true);

file_put_contents('/tmp/jm/out.pdf', base64_decode($userpdf->get_encoded_pdf()));
die("\nDone\n");
