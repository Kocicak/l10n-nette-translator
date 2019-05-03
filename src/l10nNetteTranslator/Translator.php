<?php

namespace l10nNetteTranslator;

use l10n\Language\ILanguage;
use l10n\Plural\IPlural;
use l10nNetteTranslator\Storage\IStorage;
use Nette\InvalidStateException;
use Nette\Localization\ITranslator;
use Nette\SmartObject;

class Translator implements ITranslator
{
    use SmartObject;

    /** @var \l10nNetteTranslator\LanguageAndPlural[] */
    private $languageAndPlurals;

    /** @var \l10nNetteTranslator\Storage\IStorage */
    private $storage;

    /** @var \l10n\Translator\Translator */
    private $translator;

    /**
     * @var string $activeLanguageCode hodnota pro hlavni mutaci stranky, vyuziti u slovniku
     */
    private $activeLanguageCode = '';

    private $activeNamespace = '';

    /**
     * @param string $code
     */
    protected function testLanguageCode($code)
    {
        if (empty($this->activeNamespace)) {
            throw new InvalidStateException("Translator namespace is not set.");
        }
        if (empty($this->languageAndPlurals[$this->activeNamespace][$code])) {
            throw new InvalidStateException(sprintf('Language with code "%s" is not set', $code));
        }
    }

    /**
     * @param \l10n\Language\ILanguage $language
     * @param \l10n\Plural\IPlural $plural
     * @param string $namespace namespace prekladu
     * @param bool $default
     */
    public function addLanguageAndPlural(ILanguage $language, IPlural $plural, $namespace, $default = false)
    {
        $language_and_plural = new LanguageAndPlural();
        $language_and_plural->setLanguage($language);
        $language_and_plural->setPlural($plural);
        $code = $language->getIso639_1();

        if (!isset($this->languageAndPlurals[$namespace])) {
            $this->languageAndPlurals[$namespace] = [];
        }
        $this->languageAndPlurals[$namespace][$code] = $language_and_plural;

        if ($default || !$this->activeLanguageCode) {
            $this->setActiveNamespace($namespace);
            $this->setActiveLanguageCode($code);
        }
    }

    /**
     * @param string $code
     * @throws InvalidStateException
     */
    public function setActiveLanguageCode($code)
    {
        $this->testLanguageCode($code);
        $this->activeLanguageCode = $code;
        $this->translator = null;
    }

    /**
     * @return string|null
     */
    public function getActiveLanguageCode()
    {
        return $this->activeLanguageCode;
    }

    /**
     * @param string $code
     * @return \l10nNetteTranslator\LanguageAndPlural
     * @throws InvalidStateException
     */
    public function getLanguageAndPluralByCode($code)
    {
        $this->testLanguageCode($code);

        return $this->languageAndPlurals[$this->activeNamespace][$code];
    }

    /**
     * @param string $code
     * @return bool
     */
    public function hasLanguageAndPluralByCode($code)
    {
        return isset($this->languageAndPlurals[$this->activeNamespace][$code]);
    }

    /**
     * @return \l10nNetteTranslator\LanguageAndPlural
     * @throws InvalidStateException
     */
    public function getActiveLanguageAndPlural()
    {
        return $this->getLanguageAndPluralByCode($this->activeLanguageCode);
    }

    /**
     * @return \l10nNetteTranslator\LanguageAndPlural[]
     */
    public function getLanguageAndPlurals()
    {
        if (empty($this->activeNamespace)) {
            throw new InvalidStateException("Translator namespace not set.");
        }
        return $this->languageAndPlurals[$this->activeNamespace];
    }

    /**
     * @return \l10nNetteTranslator\Storage\IStorage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param \l10nNetteTranslator\Storage\IStorage $storage
     */
    public function setStorage(IStorage $storage)
    {
        $this->storage = $storage;
        $this->translator = null;
    }

    /**
     * @return \l10n\Translator\Translator
     */
    public function getTranslator()
    {
        if (!($this->translator instanceof \l10n\Translator\Translator)) {
            $plural = $this->getActiveLanguageAndPlural()->getPlural();
            $storage = $this->getStorage();

            if ($storage) {
                $storage->setTranslator($this);
            }

            $this->translator = new \l10n\Translator\Translator($plural, $storage);
        }

        return $this->translator;
    }

    /**
     * @return string
     */
    public function getActiveNamespace(): string
    {
        return $this->activeNamespace;
    }

    /**
     * @param string $activeNamespace
     */
    public function setActiveNamespace(string $activeNamespace): void
    {
        $this->activeNamespace = $activeNamespace;
        $this->translator = null;
    }

    /**
     * @param string $message
     * @param mixed ...$parameters
     * @return string
     */
    public function translate($message, ...$parameters): string
    {
        $count = 1;
        if (count($parameters) > 0 && is_int($parameters[0])) {
            $count = array_shift($parameters);
        }

        return $this->getTranslator()->translate((string)$message, $count, $parameters);
    }
}
