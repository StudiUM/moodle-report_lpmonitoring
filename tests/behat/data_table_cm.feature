@report @javascript @report_lpmonitoring
Feature: Display learning plan ratings details
  As a learning plan appreciator
  In order to rate competencies on learning plan
  I need to view course modules competencies ratings

  Background:
    Given course module competency grading is enabled
    And the lpmonitoring fixtures exist
    And I log in as "appreciator"
    And I follow "List of courses"
    When I follow "Medicine"
    And I click on "//div[contains(@class, 'custom-courseadmin-menu')]" "xpath_element"
    Then I should see "Monitoring of learning plans"
    And I follow "Monitoring of learning plans"

  Scenario: Filter user learning plan by scales values in activities
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

    # Genetic Activity G1 in both (course and course activity). 
    And I click on "//tr[contains(@class, 'odd')]/td[contains(@class, 'searchable')][2]//a[contains(@class, 'listevidence')]" "xpath_element"
    And "List of evidence" "dialogue" should be visible
    And I click on "Close" "button" in the "List of evidence" "dialogue"
    And I should see "good" in the "//tr[contains(@class, 'odd')]/td[contains(@class, 'evaluation')][3]//a" "xpath_element"
    And I click on "//tr[contains(@class, 'odd')]/td[contains(@class, 'evaluation')][3]//a" "xpath_element"
    And "User competency summary" "dialogue" should be visible
    And I should see "Competency A" in the "User competency summary" "dialogue"
    And I should see "good" dd in "Rating" dt
    And I should see "The competency rating was manually set in the course activity 'Assignment: Activity G1'." dd in "Evidence" dt
    And I click on "Close" "button" in the "User competency summary" "dialogue"
    And I should see "not qualified" in the "//tr[contains(@class, 'even')]/td[contains(@class, 'evaluation')][3]//a" "xpath_element"
    And I click on "//tr[contains(@class, 'even')]/td[contains(@class, 'evaluation')][3]//a" "xpath_element"
    And "User competency summary" "dialogue" should be visible
    And I should see "Competency B" in the "User competency summary" "dialogue"
    And I should see "not qualified" dd in "Rating" dt
    And I should see "The competency rating was manually set in the course activity 'Assignment: Activity G1'." dd in "Evidence" dt
    And I click on "Close" "button" in the "User competency summary" "dialogue"

    # Genetic course in course.
    And I click on "//label[text()='In the course']" "xpath_element"
    And I should see "Competency A" in the "//td[contains(@class, 'searchable')]/a[contains(., 'Competency A')]" "xpath_element"
    And I should see "1" in the "//tr[contains(@class, 'odd')]/td[contains(@class, 'searchable')][2]//a[contains(@class, 'listevidence')]" "xpath_element"
    And I should see "not good" in the "//tr[contains(@class, 'odd')]/td[contains(@class, 'course-cell')][2]//a" "xpath_element"
    And I should see "not qualified" in the "//tr[contains(@class, 'even')]/td[contains(@class, 'course-cell')][2]//a" "xpath_element"
    And I should not see "Activity G1" in the "//tr[contains(@role, 'row')]/th[contains(@class, 'cm-cell')][1]/a[contains(@class, 'nowrapcm')]" "xpath_element"
    
    # Genetic Activity G1 in course activity.
    And I click on "//label[text()='In the course activity']" "xpath_element"
    And I should see "Competency A" in the "//td[contains(@class, 'searchable')]/a[contains(., 'Competency A')]" "xpath_element"
    And I should see "1" in the "//tr[contains(@class, 'odd')]/td[contains(@class, 'searchable')][2]//a[contains(@class, 'listevidence')]" "xpath_element"
    And I should see "good" in the "//tr[contains(@class, 'odd')]/td[contains(@class, 'evaluation')][3]//a" "xpath_element"
    And I should see "not qualified" in the "//tr[contains(@class, 'even')]/td[contains(@class, 'evaluation')][3]//a" "xpath_element"
    And I should not see "Genetic" in the "//tr[contains(@role, 'row')]/th[contains(@class, 'course-cell')][2]/a[contains(., 'Genetic')]" "xpath_element"

  Scenario: Check with a course hidden for students
    Given I set the field "templateSelectorReport" to "Medicine Year 1"
    When I set the field with xpath "(//input[contains(@id, 'form_autocomplete_input')])" to "Pablo"
    Then I should see "Pablo Menendez" item in the autocomplete list
    And I click on "Pablo Menendez" item in the autocomplete list
    And I press "Apply"
    And I click on "//ul/li/a[contains(@href, '#report-content')]" "xpath_element"
    And I click on "//label[text()='In the course activity']" "xpath_element"
    And I set the field with xpath "(//input[contains(@id, 'table-search-columns')])" to "Psycho"
    And I click on "//tr[contains(., 'Competency A')]//td[contains(@class, 'cm-cell') and not(contains(@class, 'filtersearchhidden'))]//a" "xpath_element"
    And "User competency summary" "dialogue" should be visible
    And I should see "Competency A" in the ".competency-heading" "css_element"
    And I should see "Activity Ps1" in the "User competency summary" "dialogue"
    And I should see "good" dd in "Rating" dt
    And I click on "Close" "button" in the "User competency summary" "dialogue"
    # Check the course is correctly hidden
    And I follow "List of courses"
    And I follow "Medicine"
    And I should see "Genetic"
    And I should not see "Psychology"
