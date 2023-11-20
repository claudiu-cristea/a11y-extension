<?php

namespace Behat\A11yExtension\Exception;

class A11yCompatibilityFailure extends \Exception
{
    public function __construct(
        string $message,
        protected array $report,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getReport(): array
    {
        return $this->report;
    }
}
