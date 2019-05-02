<?php

namespace l10nNetteTranslator\Storage;

use l10n\Translator\Translator;
use Nette\Database\Context;
use Nette\InvalidArgumentException;

class TranslatorDbStorage implements IStorage
{

    /** @var Context */
    private $db;

    /** @var Translator */
    private $translator;


    public function __construct(Context $db)
    {
        $this->db = $db;
    }

    /**
     * Setter translatoru
     *
     * @param \l10nNetteTranslator\Translator $translator
     */
    public function setTranslator(\l10nNetteTranslator\Translator $translator)
    {
        $this->translator = $translator;
    }

    public function getTranslator()
    {
        if (!($this->translator instanceof \l10nNetteTranslator\Translator)) {
            throw new InvalidArgumentException('l10nNetteTranslator\Translator is not set');
        }
        return $this->translator;
    }


    public function save(Translator $translator)
    {
        foreach ($translator->getUntranslated() as $text => $translations) {
            if (reset($translations) === true) {
                continue;
            }

            $idLocText = (int)$this->db->query('SELECT id FROM localization_text WHERE ns = ? AND text = ?', $this->getNamespace(), $text)->fetchField();

            if (empty($idLocText)) {
                $this->db->query("
                    INSERT INTO localization_text (ns, text) VALUES (?, ?);
                ", $this->getNamespace(), $text);
            }
        }
    }

    public function saveOne($text, $translate, $plural)
    {
        if (!isset($translate)) {
            $this->delete($text, $plural);
            return;
        }

        $language = $this->getLanguage();

        $idLocText = (int)$this->db->query('SELECT id FROM localization_text WHERE ns = ? AND text = ?', $this->getNamespace(), $text)->fetchField();

        if (empty($idLocText)) {
            $this->db->query("
                INSERT INTO localization_text (ns, text) VALUES (?, ?);
            ", $this->getNamespace(), $text);

            $this->db->query("
                INSERT INTO localization (text_id, lang, variant, translation) VALUES (LAST_INSERT_ID(), ?, ?, ?);
            ", $language->getIso639_1(), $plural, $translate);
        } else {
            $idLoc = (int)$this->db->query('
                SELECT id FROM localization WHERE text_id = ? AND lang = ? AND variant = ?
            ', $idLocText, $language->getIso639_1(), $plural)->fetchField();

            if (empty($idLoc)) {
                $this->db->query("
                    INSERT INTO localization (text_id, lang, variant, translation) VALUES (?, ?, ?, ?);
                ", $idLocText, $language->getIso639_1(), $plural, $translate);
            } else {
                $this->db->query("
                    UPDATE localization SET translation = ? WHERE id = ?
                ", $translate, $idLoc);
            }
        }
    }


    public function load(Translator $translator)
    {
        $list = $this->getListTransaltions(
            $this->getLanguage()->getIso639_1(),
            $this->getNamespace()
        );

        foreach ($list as $row) {
            if (isset($row->translation)) {
                $translator->setText($row->text, $row->translation, (int)$row->variant);
            } else {
                $translator->setUntranslated($row->text, (int)$row->variant, true);
            }
        }
    }

    public function delete($key, $plural = 0)
    {
        $idLocText = (int)$this->db->query('SELECT id FROM localization_text WHERE ns = ? AND text = ?', $this->getNamespace(), $key)->fetchField();

        if (!empty($idLocText)) {
            if (func_num_args() == 2) {
                return (bool)$this->db->query("
                    DELETE FROM localization WHERE text_id = ? AND lang = ? AND variant = ? LIMIT 1
                ", $idLocText, $this->getLanguage()->getIso639_1(), $plural);
            } else {
                return (bool)$this->db->query("
                    DELETE FROM localization WHERE text_id = ? AND lang = ?
                ", $idLocText, $this->getLanguage()->getIso639_1());
            }
        }
        return false;
    }


    protected function getLanguage()
    {
        return $this->getTranslator()->getActiveLanguageAndPlural()->getLanguage();
    }


    private function getListTransaltions($langCode, $ns)
    {
        return $this->db->query('
            SELECT l.translation, l.variant, lt.text FROM localization_text AS lt
            LEFT JOIN localization AS l ON lt.id = l.text_id AND l.lang = ?
            WHERE
                lt.ns = ?
            ORDER BY lt.text ASC
        ', $langCode, $ns)->fetchAll();
    }

    private function getNamespace()
    {
        return $this->getTranslator()->getActiveNamespace();
    }
}