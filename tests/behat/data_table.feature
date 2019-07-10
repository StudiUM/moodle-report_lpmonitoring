@report @javascript @report_lpmonitoring
Feature: Display learning plan ratings details
  As a learning plan appreciator
  In order to rate competencies on learning plan
  I need to view course competencies ratings

  Background:
    Given the lpmonitoring fixtures exist
    And I log in as "appreciator"
    And I follow "List of courses"
    When I follow "Medicine"
    And I click on "//div[contains(@class, 'custom-courseadmin-menu')]" "xpath_element"
    Then I should see "Monitoring of learning plans"
    And I follow "Monitoring of learning plans"

  Scenario: View the competency report in courses
    Given I set the field "templateSelectorReport" to "Medicine Year 1"
    When I set the field with xpath "(//input[contains(@id, 'form_autocomplete_input')])" to "Pablo"
    Then I should see "Pablo Menendez" item in the autocomplete list
    And I click on "Pablo Menendez" item in the autocomplete list
    And I press "Apply"
    And I click on "//ul/li/a[contains(@href, '#report-content')]" "xpath_element"
    And I click on "//td[contains(@class, 'searchable')]/a[contains(., 'Competency A')]" "xpath_element"
    And "User competency summary" "dialogue" should be visible
    And I should see "Competency A" in the "User competency summary" "dialogue"  
    And I click on "Close" "button" in the "User competency summary" "dialogue"

    # First verification of the evidences or else it raises an exception.
    And I click on "//tr[contains(@class, 'odd')]/td[contains(@class, 'searchable')][2]//a[contains(@class, 'listevidence')]" "xpath_element"
    And "List of evidence" "dialogue" should be visible
    And I click on "Close" "button" in the "List of evidence" "dialogue"
    And I should see "not good" in the "//tr[contains(@class, 'odd')]/td[contains(@class, 'course-cell')][2]//a" "xpath_element"
    And I click on "//tr[contains(@class, 'odd')]/td[contains(@class, 'course-cell')][2]//a" "xpath_element"
    And "User competency summary" "dialogue" should be visible
    And I should see "Competency A" in the "User competency summary" "dialogue"
    And I should see "not good" dd in "Rating" dt
    And I should see "The competency rating was manually set in the course 'Course: Genetic'." dd in "Evidence" dt
    And I click on "Close" "button" in the "User competency summary" "dialogue"
    And I should see "not qualified" in the "//tr[contains(@class, 'even')]/td[contains(@class, 'course-cell')][2]//a" "xpath_element"
    And I click on "//tr[contains(@class, 'even')]/td[contains(@class, 'course-cell')][2]//a" "xpath_element"
    And "User competency summary" "dialogue" should be visible
    And I should see "Competency B" in the ".moodle-dialogue-base[aria-hidden='false'] .competency-heading" "css_element"
    And I should see "not qualified" dd in "Rating" dt
    And I should see "The competency rating was manually set in the course 'Course: Genetic'." dd in "Evidence" dt
    And I click on "Close" "button" in the "User competency summary" "dialogue"

    # We double check with an other User.
    And I click on "//a[contains(@class, 'prevplan')]" "xpath_element"
    And I click on "//a[contains(@class, 'prevplan')]" "xpath_element"
    And I should see "good" in the "//tr[contains(@class, 'odd')]/td[contains(@class, 'course-cell')][2]//a" "xpath_element"
    And I click on "//tr[contains(@class, 'odd')]/td[contains(@class, 'course-cell')][2]//a" "xpath_element"
    And "User competency summary" "dialogue" should be visible
    And I should see "Competency A" in the ".competency-heading" "css_element"
    And I should see "good" dd in "Rating" dt
    And I should see "The competency rating was manually set in the course 'Course: Genetic'." dd in "Evidence" dt
    And I click on "Close" "button" in the "User competency summary" "dialogue"

  Scenario: Check with a course hidden for students
    Given I set the field "templateSelectorReport" to "Medicine Year 1"
    When I set the field with xpath "(//input[contains(@id, 'form_autocomplete_input')])" to "Pablo"
    Then I should see "Pablo Menendez" item in the autocomplete list
    And I click on "Pablo Menendez" item in the autocomplete list
    And I press "Apply"
    And I click on "//ul/li/a[contains(@href, '#report-content')]" "xpath_element"
    And I set the field with xpath "(//input[contains(@id, 'table-search-columns')])" to "Psycho"
    And I should see "good" in "Competency A" row "Psychology" column of "main-table" table
    And I click on "//tr[contains(., 'Competency A')]//td[contains(@class, 'course-cell') and not(contains(@class, 'filtersearchhidden'))]//a" "xpath_element"
    And "User competency summary" "dialogue" should be visible
    And I should see "Competency A" in the ".competency-heading" "css_element"
    And I should see "good" dd in "Rating" dt
    And I click on "Close" "button" in the "User competency summary" "dialogue"
    # Check the course is correctly hidden
    And I follow "List of courses"
    And I follow "Medicine"
    And I should see "Genetic"
    And I should not see "Psychology"