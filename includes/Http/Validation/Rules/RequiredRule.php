<?php
namespace App\Http\Validation\Rules;

use App\Http\Validation\Rule;
use App\Translation\TranslationManager;
use App\Translation\Translator;

class RequiredRule implements Rule
{
    /** @var Translator */
    private $lang;

    public function __construct()
    {
        $translationManager = app()->make(TranslationManager::class);
        $this->lang = $translationManager->user();
    }

    public function validate($attribute, $value, array $data)
    {
        if (!strlen($value)) {
            return [$this->lang->t('field_no_empty')];
        }

        return [];
    }
}
