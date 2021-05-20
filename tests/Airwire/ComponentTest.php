<?php

use Airwire\Airwire;
use Airwire\Attributes\Wired;
use Airwire\Component;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Laravel\withoutExceptionHandling;

beforeEach(fn () => Airwire::component('test-component', TestComponent::class));

test('properties are shared only if they have the Wired attribute', function () {
    expect(TestComponent::test()
        ->state(['foo' => 'abc', 'bar' => 'xyz'])
        ->send()
        ->data
    )->toBe(['bar' => 'xyz', 'results' => [], 'second' => []]); // foo is not Wired
});

test('methods are shared only if they have the Wired attribute', function () {
    expect(TestComponent::test()->call('foo')->send()->call('foo'))->toBeNull();
    expect(TestComponent::test()->call('bar')->send()->call('bar'))->not()->toBeNull();
});

test('exceptions thrown during method execution are returned in the metadata', function () {
    expect(TestComponent::test()->call('brokenMethod')->send()->exceptions())->toHaveKey('brokenMethod')->toHaveCount(1);
    expect(TestComponent::test()->call('brokenMethod')->send()->exceptions('brokenMethod'))->toMatchArray(['message' => 'foobar']);
});

test('readonly properties are not accepted by the component', function () {
    expect(TestComponent::test()->state(['results' => 'foo'])->send()->data)->not()->toHaveKey('readonly');
});

test('mount can return readonly data', function () {
    $response = TestComponent::test()->call('mount')->send();

    expect($response->call('mount'))
        ->toHaveKey('results', 'foo')
        ->not()->toHaveKey('readonly');
});

test('properties can have custom default values', function () {
    expect(TestComponent::test()->hydrate()->getState()['results'])->toBeInstanceOf(Collection::class);
    expect(TestComponent::test()->hydrate()->getState()['results']->all())->toBe([]);
});

test('the frontend can send an array that should be assigned to a collection', function () {
    expect(TestComponent::test()->state(['second' => ['foo' => 'bar']])->hydrate()->second->all())->toBe(['foo' => 'bar']);
});

class TestComponent extends Component
{
    public $foo;

    #[Wired]
    public $bar;

    #[Wired(readonly: true, default: [])]
    public Collection $results;

    #[Wired(default: [])]
    public Collection $second;

    public function mount()
    {
        return [
            'readonly' => [
                'results' => 'foo',
            ],
            'bar' => 'abc',
        ];
    }

    public function foo(): int
    {
        return 1;
    }

    #[Wired]
    public function bar(): int
    {
        return 2;
    }

    #[Wired]
    public function brokenMethod()
    {
        throw new Exception('foobar');
    }
}
