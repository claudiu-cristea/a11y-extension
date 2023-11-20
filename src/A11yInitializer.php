<?php

namespace Behat\A11yExtension;

use Behat\A11yExtension\Context\A11yContext;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;

class A11yInitializer implements ContextInitializer
{
    public function __construct(protected array $config)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context): void
    {
        if ($context instanceof A11yContext) {
            $context
                ->setAxeScriptSrc($this->config['axe_script_src'])
                ->setStandardTags($this->config['standard_tags'])
                ->setReportsDir($this->config['reports_dir']);
        }
    }
}
