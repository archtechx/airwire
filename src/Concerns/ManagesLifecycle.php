<?php

namespace Airwire\Concerns;

use Illuminate\Validation\ValidationException;

trait ManagesLifecycle
{
    public function hydrateComponent(): bool
    {
        if ($this->strictValidation && $this->validate(throw: false) === false) {
            return false;
        }

        if (method_exists($this, 'hydrate')) {
            $hydrate = app()->call([$this, 'hydrate'], $this->requestState);

            if (is_bool($hydrate)) {
                return $hydrate;
            }
        }

        return true;
    }

    public function dehydrateComponent(): void
    {
        try {
            $this->validate();

            if (method_exists($this, 'dehydrate')) {
                app()->call([$this, 'dehydrate'], $this->requestState);
            }
        } catch (ValidationException) {}

        if (isset($this->errors) && ! $this->hasBeenReset) {
            $this->metadata['errors'] = $this->errors->toArray();
        } else {
            $this->metadata['errors'] = [];
        }

        $this->metadata['readonly'] = array_unique(array_merge($this->readonly, $this->getReadonlyProperties()));
    }

    public function updating(string $property, mixed $new, mixed $old): bool
    {
        return true;
    }

    public function updated(string $property, mixed $value): void
    {
    }

    public function changed(array $changes): void
    {
    }
}
