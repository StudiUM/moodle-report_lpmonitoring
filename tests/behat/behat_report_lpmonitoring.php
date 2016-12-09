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
 * Step definition to generate database fixtures for learning plan report.
 *
 * @package    report_lpmonitoring
 * @category   test
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Step definition for learning plan report.
 *
 * @package    report_lpmonitoring
 * @category   test
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_report_lpmonitoring extends behat_base {

    /**
     * Checks, that the specified element contains the specified text in the competency detail rating.
     *
     * @Then /^I should see "(?P<rating>[^"]*)" for "(?P<text>[^"]*)" in the row "(?P<row>[^"]*)" of "(?P<element>[^"]*)" rating$/
     * @throws ElementNotFoundException
     * @throws ExpectationException
     * @param int $numberrating
     * @param string $scalevalue
     * @param int $rownumber
     * @param string $competencyname
     */
    public function i_see_nbrating_of_the_scalevalue_in_the_competency($numberrating, $scalevalue, $rownumber, $competencyname) {

        // Building xpath.
        $xpath = "//table[contains(@class, 'tile_info') and "
                . "ancestor-or-self::div[contains(., '$competencyname')]]/"
                . "tbody/tr[$rownumber]/td[contains(., '$scalevalue')]/following-sibling::td[1]";
        $this->execute("behat_general::assert_element_contains_text",
            array($numberrating, $xpath, "xpath_element")
        );
    }

    /**
     * Checks, that the specified element contains the specified text in the competency detail block.
     *
     * @Then /^I should see "(?P<text>[^"]*)" in "(?P<class>[^"]*)" of the competency "(?P<competency>[^"]*)"$/
     * @throws ElementNotFoundException
     * @throws ExpectationException
     * @param string $texttoverify
     * @param string $targetclass
     * @param string $compname
     */
    public function i_see_text_in_element_of_the_competency_detail($texttoverify, $targetclass, $compname) {

        // Building xpath.
        $xpath = '';
        switch ($targetclass) {
            case 'totalnbcourses':
                $xpath = "//a[contains(@class, '$targetclass') and ancestor-or-self::div[contains(., '$compname')]]";
                break;
            case 'totalnbusers':
                $xpath = "//a[contains(@class, '$targetclass') and ancestor-or-self::div[contains(., '$compname')]]";
                break;
            case 'listevidence':
                $xpath = "//a[contains(@class, '$targetclass') and ancestor-or-self::div[contains(., '$compname')]]";
                break;
            case 'level-proficiency':
                $xpath = "//div[contains(., '$compname')]/div/div/div/div/div[contains(@class, '$targetclass')]";
                break;
            case 'finalrate':
                $xpath = "//div[contains(., '$compname')]/div/div/div/div/div/span[contains(@class, 'label')]";
                break;
            case 'level':
                $xpath = "//span[contains(@class, '$targetclass') and ancestor-or-self::div/h4/a[contains(., '$compname')]]";
                break;
            case 'no-data-available':
                 $xpath = "//div[contains(., '$compname')]/div/div/div/"
                    . "table/tbody/tr/td/div[contains(@class, '$targetclass')]";
                break;
            case 'incourse':
                $xpath = "//div[contains(@class, '$targetclass') and ancestor-or-self::div/div/h4/a[contains(., '$compname')]]";
                break;
        }

        $this->execute("behat_general::assert_element_contains_text",
            array($texttoverify, $xpath, "xpath_element")
        );
    }

    /**
     * Click on the specified element contains the specified text in the competency detail block.
     *
     * @Then /^I click on "(?P<class>[^"]*)" of the competency "(?P<competency>[^"]*)"$/
     * @throws ElementNotFoundException
     * @throws ExpectationException
     * @param string $targetclass
     * @param string $competencyname
     */
    public function i_click_on_element_of_the_competency_detail($targetclass, $competencyname) {

        // Building xpath.
        $xpath = '';
        switch ($targetclass) {
            case 'totalnbcourses':
                $xpath = "//a[contains(@class, '$targetclass') and ancestor-or-self::div[contains(., '$competencyname')]]";
                break;
            case 'totalnbusers':
                $xpath = "//a[contains(@class, '$targetclass') and ancestor-or-self::div[contains(., '$competencyname')]]";
                break;
            case 'listevidence':
                $xpath = "//a[contains(@class, '$targetclass') and ancestor-or-self::div[contains(., '$competencyname')]]";
                break;
            case 'rate-competency':
                $xpath = "//div[contains(., '$competencyname')]/div/div/div/div/button[contains(@class, '$targetclass')]";
                break;
        }

        $this->execute("behat_general::i_click_on", array($xpath, "xpath_element"));
    }

    /**
     * Click on the specified element contains the specified text in the competency detail rating.
     *
     * @Then /^I click on "(?P<rating>[^"]*)" for "(?P<text>[^"]*)" in the row "(?P<row>[^"]*)" of "(?P<element>[^"]*)" rating$/
     * @throws ElementNotFoundException
     * @throws ExpectationException
     * @param int $numberrating
     * @param string $scalevalue
     * @param int $rownumber
     * @param string $competencyname
     */
    public function i_click_on_rating_of_the_scalevalue_in_the_competency($numberrating, $scalevalue, $rownumber, $competencyname) {

        // Building xpath.
        $xpath = "//table[contains(@class, 'tile_info') and "
                . "ancestor-or-self::div[contains(., '$competencyname')]]/tbody/"
                . "tr[$rownumber]/td[contains(., '$scalevalue')]/following-sibling::td[1]/a[contains(., '$numberrating')]";
        $this->execute('behat_general::i_click_on', array($xpath, "xpath_element"));
    }

    /**
     * Open/close the competency detail block.
     *
     * @Then /^I toggle the "(?P<competency_string>(?:[^"]|\\")*)" detail$/
     * @throws ElementNotFoundException
     * @throws ExpectationException
     * @param int $competency
     */
    public function i_toggle_the_competency_detail_block($competency) {

        // Building xpath.
        $xpath = "//a[contains(@class, 'collapse-link') and ancestor-or-self::div/h4/a[contains(., '$competency')]]";
        $this->execute('behat_general::i_click_on', array($xpath, "xpath_element"));
    }

    /**
     * Should see item from autocomplete list.
     *
     * @Given /^I should see "([^"]*)" item in the autocomplete list$/
     *
     * @param string $item
     */
    public function i_should_see_item_in_the_autocomplete_list($item) {
        $xpathtarget = "//ul[@class='form-autocomplete-suggestions']//li//span//span[contains(.,'" . $item . "')]";

        $this->execute('behat_general::should_exist', [$xpathtarget, 'xpath_element']);
    }

    /**
     * Should not see item from autocomplete list.
     *
     * @Given /^I should not see "([^"]*)" item in the autocomplete list$/
     *
     * @param string $item
     */
    public function i_should_not_see_item_in_the_autocomplete_list($item) {
        $xpathtarget = "//ul[@class='form-autocomplete-suggestions']//li//span//span[contains(.,'" . $item . "')]";

        $this->execute('behat_general::should_not_exist', [$xpathtarget, 'xpath_element']);
    }

}
