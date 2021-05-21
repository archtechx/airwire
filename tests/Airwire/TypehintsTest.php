<?php

use Airwire\Airwire;
use Airwire\Attributes\Encode;
use Airwire\Component;
use Airwire\Attributes\Wired;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('description');
        $table->unsignedInteger('price');
        $table->string('secret')->nullable();
        $table->json('variants')->default('[]');
        $table->timestamps();
    });

    Airwire::component('typehint-component', TypehintComponent::class);

    Airwire::typeTransformer(
        type: MyDTO::class,
        decode: fn (array $data) => new MyDTO($data['foo'], $data['abc']),
        encode: fn (MyDTO $dto) => ['foo' => $dto->foo, 'abc' => $dto->abc],
    );
});

// beforeEach(fn () => DB::table('products')->truncate());

afterEach(fn () => Schema::dropIfExists('products'));

test('untyped properties are set directly from the json data', function () {
    foreach ([1, 'foo', ['a' => 'b']] as $value) {
        expect(Airwire::test(TypehintComponent::class)
            ->state(['notype' => $value])
            ->send()->data('notype')
        )->toBe($value);
    }
});

test('strings and numbers are cast to the required type', function () {
    expect(Airwire::test(TypehintComponent::class)
        ->state(['price' => '1'])
        ->send()->data('price')
    )->toBe(1);

    expect(Airwire::test(TypehintComponent::class)
        ->state(['name' => 123])
        ->send()->data('name')
    )->toBe('123');
});

test('received model attributes are converted to unsaved model instances', function () {
    $model = Airwire::test(TypehintComponent::class)
        ->state(['model' => ['name' => 'foo', 'price' => '100', 'variants' => [
            ['price' => 200, 'color' => 'black']
        ]]])
        ->hydrate()->model;

    expect($model)->toBeInstanceOf(Product::class);
    expect($model->name)->toBe('foo');
    expect($model->price)->toBe(100); // Types are converted per the casts
    expect($model->variants)->toBe([['price' => 200, 'color' => 'black']]); // Array casts are supported
});

test('received model attributes must be fillable', function () {
    $model = Airwire::test(TypehintComponent::class)
        ->state(['model' => ['name' => 'foo', 'price' => '100', 'secret' => 'bar']])
        ->hydrate()->model;

    expect($model)->toBeInstanceOf(Product::class);
    expect($model->name)->toBe('foo');
    expect($model->bar)->toBe(null); // Not fillable
});

test('model properties can be hidden', function () {
    Product::create(['id' => 1, 'name' => 'foo', 'price' => 10, 'description' => 'bar']);

    expect(Airwire::test(TypehintComponent::class)
        ->call('first')
        ->send()->call('first')
    )->toBeArray()->toHaveKey('created_at')->not()->toHaveKey('updated_at');
});

test('received model ids are converted to model instances', function () {
    Product::create(['id' => 1, 'name' => 'foo', 'price' => 10, 'description' => 'bar']);

    $model = Airwire::test(TypehintComponent::class)
        ->state(['model' => 1])
        ->hydrate()->model;

    expect($model)->toBeInstanceOf(Product::class);
    expect($model->id)->toBe(1);
    expect($model->exists())->toBe(true);
});

test('sent models are converted to arrays', function () {
    Product::create(['id' => 1, 'name' => 'foo', 'price' => 10, 'description' => 'bar']);

    expect(Airwire::test(TypehintComponent::class)
        ->call('first')
        ->send()->call('first')
    )->toBeArray()->toHaveKey('id', 1);
});

test('custom DTOs can be used', function () {
    // Sending
    expect(Airwire::test(TypehintComponent::class)
        ->state(['dto' => [
            'foo' => 'bar',
            'abc' => 123,
        ]])
        ->hydrate()
        ->dto
    )->toBeInstanceOf(MyDTO::class)->toHaveKey('foo', 'bar')->toHaveKey('abc', 123);

    // Receiving
    expect(Airwire::test(TypehintComponent::class)
        ->state(['dto' => [
            'foo' => 'bar',
            'abc' => 123,
        ]])
        ->send()
        ->data('dto')
    )->toBe(['foo' => 'bar', 'abc' => 123]);
});

test('model can be passed to a method', function () {
    expect(TypehintComponent::test()
        ->call('save', ['name' => 'foo', 'price' => 10, 'description' => 'bar'])
        ->send()
        ->call('save')
    )->toBe('1');

    expect(Product::count())->toBe(1);
});

// todo wired attribute flags on methods as well
test('models can be encoded back to the id', function () {
    Product::create(['id' => 1, 'name' => 'foo', 'price' => 10, 'description' => 'bar']);

    expect(TypehintComponent::test()
        ->state(['model2' => 1])
        ->send()->data('model2')
    )->toBe(1);
});

class TypehintComponent extends Component
{
    #[Wired]
    public $notype;

    #[Wired]
    public string $name;

    #[Wired]
    public int $price;

    #[Wired]
    public Product $model;

    #[Wired] #[Encode(method: 'getKey')] // todo add the same feature for Decode (but then we may have to update the type generator)
    public Product $model2;

    #[Wired]
    public MyDTO $dto;

    #[Wired]
    public function first(): Product
    {
        return Product::first();
    }

    #[Wired]
    public function save(Product $model): string
    {
        $model->save();

        return $model->id;
    }
}

class Product extends Model
{
    public $fillable = ['id', 'name', 'price', 'description', 'variants'];
    public $hidden = ['updated_at'];

    public $casts = [
        'price' => 'int',
        'variants' => 'array',
    ];
}

class MyDTO
{
    public function __construct(
        public string $foo,
        public int $abc,
    ) {}
}
