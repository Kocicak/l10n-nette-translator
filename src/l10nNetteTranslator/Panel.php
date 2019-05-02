<?php

namespace l10nNetteTranslator;

use Nette\SmartObject;
use Tracy\IBarPanel;

class Panel implements IBarPanel
{
    use SmartObject;
    /** @var \l10nNetteTranslator\Translator */
    protected $translator;

    public function __construct(\l10nNetteTranslator\Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Return's panel ID
     *
     * @return string
     */
    public function getId()
    {
        return __CLASS__;
    }

    /**
     * Returns the code for the panel tab
     *
     * @return string
     */
    public function getTab()
    {
        $lang_code = $this->translator->getActiveLanguageAndPlural()->getLanguage()->getIso639_1();
        $namespace = $this->translator->getActiveNamespace();

        ob_start();
        require __DIR__ . '/Templates/tab.phtml';

        return ob_get_clean();
    }

    /**
     * Returns the code for the panel itself
     *
     * @return string
     */
    public function getPanel()
    {
        $lang_code = $this->translator->getActiveLanguageAndPlural()->getLanguage()->getIso639_1();
        $namespace = $this->translator->getActiveNamespace();

        ob_start();
        require __DIR__ . '/Templates/panel.phtml';

        return ob_get_clean();
    }
}
