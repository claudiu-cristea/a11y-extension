# Accessibility Extension 
[![ci](https://github.com/claudiu-cristea/a11y-extension/actions/workflows/ci.yml/badge.svg)](https://github.com/claudiu-cristea/a11y-extension/actions/workflows/ci.yml)

This library provides step definitions for checking the Accessibility Compliance in Behat scenarios.

## Installation

```shell
composer require lovers-of-behat/table-extension
```

## Configuration

Add the extension and context to your test suite in `behat.yml`:

```yaml
suites:
  default:
    contexts:
      - Behat\A11yExtension\Context\A11yContext
  extensions:
    Behat\A11yExtension:
      # You can also use a local path accessible by the webserver 
      axe_script_src: https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.8.2/axe.min.js
      # See https://github.com/dequelabs/axe-core/blob/develop/doc/API.md#axe-core-tags
      standard_tags:
        wcag2a: WCAG 2.1 A
        wcag2aa: WCAG 2.1 AA
        wcag2aaa: WCAG 2.1 AAA
      # Where to store the accessibility reports containing the violations              
      reports_dir: /tmp
```

## Usage

```gherkin
# Go to the page you want to check
When I go to "path/to/page.html"

# This uses the standard tags defined in behat.yml
Then the page meets accessibility standards
    
# Check only violations beyond a given severity. In this example only checks for
# violations with 'serious' and 'critical' impact.
# See https://github.com/dequelabs/axe-core/blob/develop/doc/issue_impact.md    
Then the page meets at least serious accessibility standards

# Check violations given a list of custom tags
# See https://github.com/dequelabs/axe-core/blob/develop/doc/API.md#axe-core-tags
Then the page meets accessibility standards for tags "wcag2a,cat.aria,cat.keyboard,section508"    

# Combine severity limitation with custom tags
Then the page meets at least moderate accessibility standards for tags "wcag2a,cat.aria,cat.keyboard,section508"    
```

## Development

Running tests locally:

```shell
git clone https://github.com/claudiu-cristea/a11y-extension.git
cd a11y-extension
PHP_VERSION="8.1" docker-compose up -d
PHP_VERSION="8.1" docker-compose exec php composer install
PHP_VERSION="8.1" docker-compose exec php composer test
```
