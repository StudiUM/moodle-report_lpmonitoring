@report @javascript @report_lpmonitoring
Feature: Display learning plan template statistics
  As a learning plan admin
  In order to display statistics of competency for a given template
  I need choose a template

  Background:
    Given the lpmonitoring fixtures exist
    And I log in as "appreciator"
    And I follow "Courses"
    When I follow "Medicine"
    And I expand "Reports" node
    Then I should see "Statistics for learning plans"
    And I follow "Statistics for learning plans"

  Scenario: Read template competencies statistics
    Given I click on ".templatelist .form-autocomplete-downarrow" "css_element"
    And I should see "Medicine Year 1" item in the autocomplete list
    And I should see "Medicine Year 2" item in the autocomplete list
    And I click on "Medicine Year 2" item in the autocomplete list
    When I press "Apply"
    Then I should see "Competency A"
    And I should see "Competency B"
    And I should see "3/3" in "totalnbusers" of the competency "Competency A"
    And I click on "totalnbusers" of the competency "Competency A"
    And "User list" "dialogue" should be visible
    And "Robert Smith" row "Rated" column of "totallistusers" table should contain "Yes"
    And "William Presley" row "Rated" column of "totallistusers" table should contain "Yes"
    And "Frederic Simson" row "Rated" column of "totallistusers" table should contain "Yes"
    And I set the field "Search" to "William"
    And I should see "William Presley" in the "User list" "dialogue"
    And I should not see "Robert Smith" in the "User list" "dialogue"
    And I should not see "Frederic Simson" in the "User list" "dialogue"
    And I click on "Close" "button" in the "User list" "dialogue"
    And I should see "2" for "not good" in the row "2" of "Competency A" rating
    And I should see "1" for "good" in the row "3" of "Competency A" rating
    And I click on "2" for "not good" in the row "2" of "Competency A" rating
    And "Linked users" "dialogue" should be visible
    And I should see "Frederic Simson" in the "Linked users" "dialogue"
    And I should see "Robert Smith" in the "Linked users" "dialogue"
    And I should not see "William Presley" in the "Linked users" "dialogue"
    And I click on "Close" "button" in the "Linked users" "dialogue"
    And I click on "1" for "good" in the row "3" of "Competency A" rating
    And "Linked users" "dialogue" should be visible
    And I should not see "Frederic Simson" in the "Linked users" "dialogue"
    And I should not see "Robert Smith" in the "Linked users" "dialogue"
    And I should see "William Presley" in the "Linked users" "dialogue"