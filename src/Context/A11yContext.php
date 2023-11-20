<?php

namespace Behat\A11yExtension\Context;

use Behat\A11yExtension\Exception\A11yCompatibilityFailure;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\DocumentElement;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * @see https://www.deque.com/axe/
 * @see https://github.com/dequelabs/axe-core
 */
class A11yContext extends RawMinkContext
{
    /**
     * @see https://github.com/dequelabs/axe-core/blob/develop/doc/issue_impact.md
     */
    private const IMPACT = [
        'critical' => 0,
        'serious' => 1,
        'moderate' => 2,
        'minor' => 3,
    ];

    private const LINE_LENGTH = 80;
    protected array $standardTags = [];
    protected string $axeScriptSrc;
    protected string $reportsDir;

    /**
     * @param string $minimumImpact    The minimum impact to check. Allowed values are keys from self::IMPACT.
     * @param string|null $limitToTags A comma separated list of accessibility tags to limit on.
     *
     * @Then the page meets accessibility standards
     * @Then the page meets at least :minimumImpact accessibility standards
     * @Then the page meets accessibility standards for tags :limitToTags
     * @Then the page meets at least :minimumImpact accessibility standards for tags :limitToTags
     */
    public function assertAccessibilityCompliant(string $minimumImpact = 'minor', ?string $limitToTags = null): void
    {
        \assert(isset(self::IMPACT[$minimumImpact]));
        $this->checkDriver();
        $limitToTags = $limitToTags ? array_map('trim', explode(',', $limitToTags)) : array_keys($this->standardTags);
        $this->runAxeChecks($minimumImpact, $limitToTags);
    }

    /**
     * @param string $expectedBrokenRules A comma separated list of accessibility rule expected to break
     * @see https://github.com/dequelabs/axe-core/blob/develop/doc/rule-descriptions.md#rule-descriptions
     *
     * @Then the page doesn't meet accessibility standard for rules :rules rules
     */
    public function assertAccessibilityNotCompliant(string $expectedBrokenRules): void
    {
        try {
            $this->assertAccessibilityCompliant();
            throw new \Exception("The page meets accessibility standards but it should not");
        } catch (A11yCompatibilityFailure $exception) {
            $expectedBrokenRules = array_filter(array_map('trim', explode(',', $expectedBrokenRules)));
            $actualBrokenRules = array_map(fn(array $row): string => $row['id'], $exception->getReport());
        }
        if ($expectedButNotBrokenRules = array_diff($expectedBrokenRules, $actualBrokenRules)) {
            throw new \Exception("The expected '" . implode("', '", $expectedButNotBrokenRules) . "' rule(s) were not broken. Broken rule(s): '" . implode("', '", $actualBrokenRules) . "'");
        }
        if ($brokenButNotExpectedRules = array_diff($actualBrokenRules, $expectedBrokenRules)) {
            throw new \Exception("The '" . implode("', '", $brokenButNotExpectedRules) . "' rule(s) were broken but not expected");
        }
    }

    /**
     * Runs the Axe accessibility check.
     *
     * @param string $minimumImpact
     *   The minimum impact to check. Allowed values are keys from self::IMPACT.
     * @param string[] $limitToTags
     *   A comma separated list of accessibility tags to limit on.
     *
     * @throws \Behat\A11yExtension\Exception\A11yCompatibilityFailure
     *   On accessibility check violations.
     */
    protected function runAxeChecks(string $minimumImpact, array $limitToTags): void
    {
        // Trigger the Axe check (is asynchronous).
        $this->getSession()->executeScript($this->getScript($limitToTags));

        // Axe runs asynchronously, we have to wait until the results are gathered.
        $result = $this->getSession()->getPage()->waitFor(
            30,
            function (DocumentElement $page): ?array {
                if ($axeJsElement = $page->find('xpath', '//script[@data-drupal-selector="axe-js"]')) {
                    $output = $axeJsElement->getAttribute('data-output');
                    return $output ? json_decode($output, true) : null;
                }
                return null;
            }
        );

        if ($result === null) {
            throw new \RuntimeException('Cannot run Axe accessibility check.');
        }

        ['violations' => $violations, 'exception' => $exception] = $result;

        if ($exception) {
            throw new \RuntimeException('Exception thrown while running Axe: ' . json_encode($exception));
        }

        // Filter out failures with low impact.
        $violations = array_filter(
            $violations,
            fn(array $violation): bool => self::IMPACT[$violation['impact']] <= self::IMPACT[$minimumImpact],
        );

        if (!$violations) {
            // Congratulations!
            return;
        }

        // Sort by impact: the most severe on top.
        uasort($violations, function (array $a, array $b): int {
            return self::IMPACT[$a['impact']] <=> self::IMPACT[$b['impact']];
        });

        $report = [];
        foreach ($violations as $violation) {
            $report[] = $this->buildReportEntry($violation);
        }

        if (!$path = tempnam($this->reportsDir, 'a11y.')) {
            throw new \RuntimeException('Cannot create a file for the JSON full report');
        }
        file_put_contents($path, json_encode($violations));
        rename($path, "$path.json");

        throw new A11yCompatibilityFailure(
            "Detected accessibility standards violations\n" .
            str_repeat("=", self::LINE_LENGTH) . "\n" .
            implode("\n", $report) . "\n" .
            "A JSON encoded full report, containing also the offending HTML elements, has been created at $path.json",
            $violations,
        );
    }

    /**
     * Returns the JavaScript code that triggers the Axe accessibility checks.
     *
     * @param array $limitToTags A list of accessibility tags to limit on.
     *
     * @return string
     */
    protected function getScript(array $limitToTags): string
    {
        $options = json_encode([
            'runOnly' => [
                'type' => 'tag',
                'values' => $limitToTags,
            ],
        ]);

        return <<<JavaScript
            (axeJsSrc => {
            const axeRun = () => {
              let output = { violations: [], exception: null };
              const axeJsElement = document.querySelector('script[data-drupal-selector="axe-js"]');
              // We'll store the results directly on the HTML tag.
              axeJsElement.dataset.output = ''
            
              axe.run('body', $options)
                .then(results => {
                  output.violations = results.violations;
                  axeJsElement.dataset.output = JSON.stringify(output);
                })
                .catch(exception => {
                  output.exception = exception;
                  axeJsElement.dataset.output = JSON.stringify(output);
                });
            }
            
            if (document.querySelector('script[data-drupal-selector="axe-js"]')) {
              axeRun();
            }
            else {
              const axeJsElement = document.createElement('script');
              axeJsElement.src = axeJsSrc;
              axeJsElement.type = 'text/javascript';
              axeJsElement.dataset.drupalSelector = 'axe-js';
              axeJsElement.onload = () => axeRun();
              document.head.append(axeJsElement);
            }
            })('$this->axeScriptSrc');
            JavaScript;
    }

    /**
     * Builds a report entry.
     *
     * @param array $violation
     *   The violation array.
     *
     * @return string
     *   A report entry.
     */
    protected function buildReportEntry(array $violation): string
    {
        $entry = [
            'ID' => $violation['id'],
            'Impact' => $violation['impact'],
            'Description' => $violation['description'],
            'Help' => $violation['help'],
            'See' => $violation['helpUrl'],
            'Tags' => implode(', ', $violation['tags']),
        ];

        $indent = array_reduce(
            array_keys($entry),
            fn(int $maxKeyLength, string $key): int => max(strlen($key), $maxKeyLength),
            0,
        ) + 2;

        $text = [];
        foreach ($entry as $key => $value) {
            $wrapped = wordwrap(trim($value), self::LINE_LENGTH - $indent);
            $text[] = str_pad("$key:", $indent) . str_replace("\n", "\n" . str_repeat(' ', $indent), $wrapped);
        }

        return implode("\n", $text) . "\n" . str_repeat('-', self::LINE_LENGTH) . "\n";
    }

    private function checkDriver(): void
    {
        if (!$this->getSession()->getDriver() instanceof Selenium2Driver) {
            throw new \RuntimeException('This step definition only works with @javascript tagged scenarios');
        }
    }

    public function setStandardTags(array $standardTags): self
    {
        $this->standardTags = $standardTags;
        return $this;
    }

    public function setAxeScriptSrc(string $axeScriptSrc): self
    {
        $this->axeScriptSrc = $axeScriptSrc;
        return $this;
    }

    public function setReportsDir(string $reportsDir): self
    {
        $this->reportsDir = $reportsDir;
        return $this;
    }
}
