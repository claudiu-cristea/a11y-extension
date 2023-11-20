@javascript
Feature: Test A11y Extension

    Scenario: Accessibility issues are detected

        Given I go to "compliant.html"
        Then the page meets accessibility standards

        Given I go to "non-compliant.html"
        Then the page doesn't meet accessibility standard for rules "image-alt,label,link-name" rules
