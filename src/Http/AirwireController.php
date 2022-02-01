<?php

declare(strict_types=1);

namespace Airwire\Http;

use Airwire\Airwire;
use Airwire\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AirwireController
{
    public function __invoke(Request $request, string $component, string $target = null)
    {
        return response()->json($this->response($component, $request->input(), $target));
    }

    public function response(string $component, array $input, string $target = null): array
    {
        $validator = $this->validator($input + ['component' => $component]);

        if ($validator->fails()) {
            return [
                'data' => $input['state'] ?? [],
                'metadata' => [
                    'errors' => $validator->errors(),
                ],
            ];
        }

        return $this->makeComponent($component, $input['state'] ?? [], $target)
            ->handle($input['changes'] ?? [], $input['calls'] ?? [], $target)
            ->response();
    }

    public function makeComponent(string $component, array $state): Component
    {
        return new Airwire::$components[$component]($state);
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'component' => ['required', function ($attribute, $value, $fail) {
                if (! Airwire::hasComponent($value)) {
                    $fail("Component {$value} not found.");
                }
            }],
            'state' => ['nullable', function ($attribute, $value, $fail) {
                if (! is_array($value)) {
                    $fail('State must be an array.');
                }
                foreach ($value as $k => $v) {
                    if (! is_string($k)) {
                        $fail("[State] Property name must be a string, {$k} given.");
                    }
                }
            }],
            'changes' => ['nullable', function ($attribute, $value, $fail) {
                if (! is_array($value)) {
                    $fail('Changes must be an array.');
                }

                foreach ($value as $k => $v) {
                    if (! is_string($k)) {
                        $fail("[Changes] Property name must be a string, {$k} given.");
                    }
                }
            }],
            'calls' => ['nullable', function ($attribute, $value, $fail) {
                if (! is_array($value)) {
                    $fail('Calls must be an array.');
                }

                foreach ($value as $k => $v) {
                    if (! is_string($k)) {
                        $fail("[Calls] Method name must be a string, {$k} given.");
                    }
                }
            }],
        ]);
    }
}
