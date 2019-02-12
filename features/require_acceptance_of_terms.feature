@checkout
Feature: Requiring a customer to accept terms before checking out
  In order to abide by regulations in certain countries
  As a customer
  I have to accept the terms before checking out

  Background:
    Given the store operates on a single channel in "United States"
    And this channel requires customers to accept terms

  @ui @javascript
  Scenario: Accepting terms
    Given I want to place my order
    When I check check the requirements checkbox
    Then the order should be successfully placed