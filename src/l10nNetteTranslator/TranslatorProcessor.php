<?php

namespace l10nNetteTranslator;

use Nette\Application\Responses\JsonResponse;
use Nette\Http\IRequest;
use Nette\InvalidStateException;
use Nette\SmartObject;

class TranslatorProcessor
{
    use SmartObject;

    const PARAMETER = 'l10nNTP';

    /** @var \Nette\Http\IRequest */
    private $request;

    /** @var \l10nNetteTranslator\Translator */
    private $translator;

    private $payload = [
        'actions' => [],
        'language' => null,
        'languages' => [],
        'texts' => [],
        'select' => null,
        'message' => null,
    ];

    public function __construct(Translator $translator, IRequest $request)
    {
        $this->translator = $translator;
        $this->request = $request;
    }

    protected function createHash($value)
    {
        return hash('crc32b', self::PARAMETER . '-' . $value);
    }

    protected function getRequestData()
    {
        $request_data = (array)$this->request->getPost(self::PARAMETER);
        $request_data += [
            'action' => null,
            'key' => null,
            'language' => null,
            'texts' => []
        ];

        if ($request_data['key'] && empty($request_data['id'])) {
            $request_data['id'] = $this->createHash($request_data['key']);
        }

        return $request_data;
    }

    protected function getPayload()
    {
        return $this->payload;
    }

    protected function initAction()
    {
        $this->loadLanguagesAction();
        $this->loadListAction();
    }

    protected function loadLanguagesAction()
    {
        foreach ($this->translator->getLanguageAndPlurals() as $language_and_plural) {
            $language = $language_and_plural->getLanguage();
            $code = $language->getIso639_1();

            $this->payload['languages'][$code] = [
                'code' => $code,
                'namespace' => $this->translator->getActiveNamespace(),
                'original_name' => $language->getOriginalName(),
                'english_name' => $language->getEnglishName()
            ];
        }

        $active_language_and_plural = $this->translator->getActiveLanguageAndPlural();

        $this->payload['language'] = $active_language_and_plural->getLanguage()->getIso639_1();
        $this->payload['namespace'] = $this->translator->getActiveNamespace();
        $this->payload['plurals_count'] = $active_language_and_plural->getPlural()->getPluralsCount();
        $this->payload['actions'][] = 'buildLanguages';
        $this->payload['actions'][] = 'setLanguage';
        $this->payload['actions'][] = 'buildPluralsForm';
    }

    protected function loadListAction()
    {
        $translator = $this->translator->getTranslator();
        $untranslated = $translator->getUntranslated();
        $translated = $translator->getTranslated();

        $keys = array_keys($translated + $untranslated);
        natsort($keys);

        foreach ($keys as $key) {
            $hash = $this->createHash($key);
            $this->payload['texts'][$hash] = [
                'id' => $hash,
                'key' => $key,
                'status' => (int)!isset($untranslated[$key]),
                'texts' => isset($translated[$key]) ? $translated[$key] : []
            ];
        }

        $this->payload['actions'][] = 'buildList';
    }

    protected function saveTextAction(array $request_data)
    {
        $translator = $this->translator->getTranslator();
        //$translator->removeText($request_data['key']);
        $active_language_and_plural = $this->translator->getActiveLanguageAndPlural();
        $plurals_count = $active_language_and_plural->getPlural()->getPluralsCount();

        for ($plural = 0; $plural < $plurals_count; $plural += 1) {
            if (isset($request_data['texts']) && isset($request_data['texts'][$plural])) {
                $translator->saveText($request_data['key'], $request_data['texts'][$plural], $plural);
            } else {
                $translator->saveText($request_data['key'], null, $plural);
            }
        }

        //$this->loadListAction();
        $this->payload['select'] = $request_data['id'];
        $this->payload['actions'][] = 'refreshRow';
        $this->payload['actions'][] = 'successSave';
        $this->payload['texts']['id'] = $request_data['id'];
        $this->payload['texts']['key'] = $request_data['key'];
        $this->payload['texts']['status'] = 1;
        $this->payload['texts']['texts'] = (empty($request_data['texts']) ? [] : $request_data['texts']);
    }

    protected function removeTextAction(array $request_data)
    {
        $translator = $this->translator->getTranslator();

        if ($translator->removeText($request_data['key'])) {
            $this->payload['actions'][] = 'successRemoveRow';
            $this->payload['rowKey'] = $request_data['id'];
        } else {
            $this->payload['actions'][] = 'clean';
        }
    }

    protected function callActionByRequest(array $request_data)
    {
        $action = $request_data['action'] . 'Action';

        if (!method_exists($this, $action)) {
            throw new InvalidStateException(sprintf('Action "%s" not found', $request_data['action']));
        }

        call_user_func([$this, $action], $request_data);
    }

    public function run()
    {
        $request_data = $this->getRequestData();

        if ($request_data['action']) {
            try {
                if (isset($request_data['namespace'])) {
                    $this->translator->setActiveNamespace($request_data['namespace']);
                }

                if (isset($request_data['language'])) {
                    $this->translator->setActiveLanguageCode($request_data['language']);
                }

                $this->callActionByRequest($request_data);
            } catch (InvalidStateException $e) {
                $this->payload['message'] = $e->getMessage();
            }

            return new JsonResponse($this->payload);
        }

        return null;
    }
}
