<?php

namespace Airwire\Concerns;

use Illuminate\Contracts\Validation\Validator as AbstractValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait ManagesValidation
{
    public bool $strictValidation = true;

    public MessageBag $errors;

    /** @throws ValidationException */
    public function validate(string|array $properties = null, bool $throw = true): bool
    {
        $validator = $this->validator($properties);

        if ($validator->fails()) {
            if (isset($this->errors)) {
                foreach ($validator->errors()->toArray() as $property => $errors) {
                    foreach ($errors as $error) {
                        if (! in_array($error, $this->errors->get($property))) {
                            $this->errors->add($property, $error);
                        }
                    }
                }
            } else {
                $this->errors = $validator->errors();
            }

            if ($throw) {
                $validator->validate();
            } else {
                return false;
            }
        }

        return true;
    }

    /** @throws ValidationException */
    public function validated(string|array $properties = null): array
    {
        return $this->validator($properties)->validated();
    }

    public function validator(string|array $properties = null): AbstractValidator
    {
        $state = array_merge($this->getState(), $this->changes);
        $rules = $this->rules();
        $messages = $this->messages();
        $attributes = $this->attributes();

        $properties = $properties
            ? Arr::wrap($properties)
            : $this->getSharedProperties();

        $state = collect($state)->only($properties)->toArray();
        $rules = collect($rules)->only($properties)->toArray();
        $messages = collect($messages)->only($properties)->toArray();
        $attributes = collect($attributes)->only($properties)->toArray();

        return Validator::make($state, $rules, $messages, $attributes);
    }

    public function rules()
    {
        return $this->rules ?? [];
    }

    public function messages()
    {
        return [];
    }

    public function attributes()
    {
        return [];
    }
}
