<?php

use Airwire\Airwire;
use Airwire\Attributes\Wired;
use Airwire\Component;

beforeAll(function () {
    Airwire::component('autovalidated-component', AutovalidatedComponent::class);
    Airwire::component('manually-validated-component', ManuallyValidatedComponent::class);
    Airwire::component('multi-input-component', MultiInputComponent::class);
    Airwire::component('implicitly-validated-component', ImplicitlyValidatedComponent::class);
});

test('validation is executed on the new state (old state + changes)', function () {
    // ❌ Old state was invalid, too short name
    expect(Airwire::test('autovalidated-component')
        ->state(['name' => 'sam'])
        ->send()->errors()
    )->not()->toBeEmpty();

    // ✅ Valid state after changes are applied
    expect(Airwire::test('autovalidated-component')
        ->state(['name' => 'sam'])
        ->changes(['name' => 'sam 123456789'])
        ->send()->errors()
    )->toBeEmpty();

    // ❌ Invalid state after changes arre applied
    expect(Airwire::test('autovalidated-component')
        ->state(['name' => 'sam 123456789'])
        ->changes(['email' => 'sam123456789@toolong.com'])
        ->send()->errors()
    )->not()->toBeEmpty();
});

test('properties CANNOT be changed when validation fails and strict validation is ON', function () {
    expect(Airwire::test('autovalidated-component')
        ->state(['name' => 'original'])
        ->changes(['name' => 'failing'])
        ->send()->data('name')
    )->toBe('original');
});

// Strict validation prevents ANY EXECUTION AT ALL when the received state is invalid
test('properties CANNOT be changed when validation fails for any other properties and strict validation is ON', function () {
    expect(Airwire::test('autovalidated-component')
        ->state(['name' => 'original', 'email' => 'original@email'])
        ->changes(['name' => 'failing', 'email' => 'new@email'])
        ->send()->data
    )->toBe([
        'name' => 'original',
        'email' => 'original@email',
    ]);
});

test('methods CANNOT be called when validation fails and strict validation is ON', function () {
    expect(Airwire::test('autovalidated-component')
        ->state(['name' => 'sam'])
        ->call('foo')
        ->send()->metadata->calls
    )->not()->toHaveKey('foo');
});

test('properties CAN be changed when validation fails and strict validation is OFF', function () {
    expect(Airwire::test('manually-validated-component')
        ->state(['name' => 'sam']) // Failing validation
        ->changes(['name' => 'failing'])
        ->send()->data('name')
    )->toBe('failing');
});

test('only changes are reversed, old state will be returned even if it is invalid', function () {
    $response = Airwire::test('manually-validated-component')
        ->state(['name' => 'sam']) // ❌ Failing validation
        ->call('foo')
        ->send();

    expect($response->data('name'))->toBe('sam');
});

test('when methods call validate() and it fails, execution is stopped', function () {
    $response = Airwire::test('manually-validated-component')
        ->state(['name' => 'sam']) // ❌ Failing validation
        ->call('foo') // No validation
        ->send();

    expect($response->data('name'))->toBe('sam');
    expect($response->call('foo'))->toBe('bar');
    expect($response->call('abc'))->toBe(null);

    $response = Airwire::test('manually-validated-component')
        ->state(['name' => 'sam 123456789', 'email' => 'foo']) // ✅ Passing validation
        ->call('foo')
        ->call('abc') // Manual validation
        ->send();

    expect($response->call('foo'))->toBe('bar');
    expect($response->call('abc'))->toBe('xyz');
});

test('validate can be used to prevent updating', function () {
    expect(MultiInputComponent::test()
        ->state(['name' => 'original', 'email' => 'valid@mail'])
        ->changes(['name' => 'invalid'])
        ->send()->data('name')
    )->toBe('original');
});

test('validated can be used to validate the specified properties and get their values', function () {
    expect(MultiInputComponent::test()
        ->state(['name' => 'invalid', 'email' => 'valid@mail'])
        ->call('method')
        ->send()->call('method')
    )->toBe(null);

    expect(MultiInputComponent::test()
        ->state(['name' => 'very valid', 'email' => 'valid@mail'])
        ->call('method')
        ->send()->call('method')
    )->toBe(['name' => 'very valid', 'email' => 'valid@mail']);
});

test('uncaught validation exceptions in hydrate terminate execution', function () {
    expect(
        ImplicitlyValidatedComponent::test()
            ->state(['foo' => 'abc'])
            ->send()->errors()
    )->toHaveKey('foo');
});

class AutovalidatedComponent extends Component
{
    #[Wired]
    public string $name;

    #[Wired]
    public string $email;

    public $rules = [
        'name' => ['required', 'min:10'],
        'email' => ['nullable', 'max:10'],
    ];

    #[Wired]
    public function foo()
    {
        return 'bar';
    }
}

class ManuallyValidatedComponent extends Component
{
    public bool $strictValidation = false;

    #[Wired]
    public string $name;

    #[Wired]
    public string $email;

    public $rules = [
        'name' => ['required', 'min:10'],
        'email' => ['nullable', 'max:10'],
    ];

    #[Wired]
    public function foo()
    {
        return 'bar';
    }

    #[Wired]
    public function abc()
    {
        $this->validate();

        return 'xyz';
    }
}

class MultiInputComponent extends Component
{
    public bool $strictValidation = false;

    #[Wired]
    public string $name;

    #[Wired]
    public string $email;

    public $rules = [
        'name' => ['required', 'min:10'],
        'email' => ['nullable', 'max:20'],
    ];

    public function updating(string $property, mixed $new, mixed $old): bool
    {
        return $this->validate($property);
    }

    #[Wired]
    public function method()
    {
        return $this->validated();
    }
}

class ImplicitlyValidatedComponent extends Component
{
    public bool $strictValidation = false;

    #[Wired()]
    public string $foo;

    public array $rules = [
        'foo' => ['required', 'min:10'],
    ];

    // validated in dehydrate()
}
