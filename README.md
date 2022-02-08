> Note: **Development is currently paused**. It will be resumed after we launch [Lean Admin](https://lean-admin.dev) this year.

# Airwire

*A lightweight full-stack component layer that doesn't dictate your front-end framework*

[Demo](https://github.com/archtechx/airwire-demo)

## Introduction

Airwire is a thin layer between your Laravel code and your JavaScript.

It lets you write Livewire-style OOP components like this:

```php
class CreateUser extends Component
{
    #[Wired]
    public string $name = '';

    #[Wired]
    public string $email = '';

    #[Wired]
    public string $password = '';

    #[Wired]
    public string $password_confirmation = '';

    public function rules()
    {
        return [
            'name' => ['required', 'min:5', 'max:25', 'unique:users'],
            'email' => ['required', 'unique:users'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }

    #[Wired]
    public function submit(): User
    {
        $user = User::create($this->validated());

        $this->meta('notification', __('users.created', ['id' => $user->id, 'name' => $user->name]));

        $this->reset();

        return $user;
    }
}
```

Then, it generates a TypeScript definition like this:

```ts
interface CreateUser {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    submit(): AirwirePromise<User>;
    errors: { ... }

    // ...
}
```

And Airwire will wire the two parts together. It's up to you what frontend framework you use (if any), Airwire will simply forward calls and sync state between the frontend and the backend.

The most basic use of Airwire would look like this:

```ts
let component = Airwire.component('create-user')

console.log(component.name); // your IDE knows that this is a string

component.name = 'foo';

component.errors; // { name: ['The name must be at least 10 characters.'] }

// No point in making three requests here, so let's defer the changes
component.deferred.name = 'foobar';
component.deferred.password = 'secret123';
component.deferred.password_confirmation = 'secret123';

// Watch all received responses
component.watch(response => {
    if (response.metadata.notification) {
        alert(response.metadata.notification)
    }
})

component.submit().then(user => {
    // TS knows the exact data structure of 'user'
    console.log(user.created_at);
})
```

## Installation

*Laravel 8 and PHP 8 are needed.*

First install the package via composer:
```
composer require archtechx/airwire
```

Then go to your `webpack.mix.js` and register the watcher plugin. It will refresh the TypeScript definitions whenever you make a change to PHP code:

```js
mix.webpackConfig({
    plugins: [
        new (require('./vendor/archtechx/airwire/resources/js/AirwireWatcher'))(require('chokidar')),
    ],
})
```

Next, generate the initial TS files:

```
php artisan airwire:generate
```

This will create `airwire.ts` and `airwired.d.ts`. Open your `app.ts` and import the former:

```ts
import Airwire from './airwire'
```

If you have an `app.js` file instead of an `app.ts` file, change the file suffix and update your `webpack.mix.js` file:

```diff
- mix.js('resources/js/app.js', 'public/js')
+ mix.ts('resources/js/app.ts', 'public/js')
```

If you're using TypeScript for the first time, you'll also need a `tsconfig.json` file in the the root of your project. You can use this one to get started:

```json
{
  "compilerOptions": {
    "target": "es2017",
    "strict": true,
    "module": "es2015",
    "moduleResolution": "node",
    "experimentalDecorators": true,
    "sourceMap": true,
    "skipLibCheck": true
  },
  "include": ["resources/js/**/*"]
}
```

And that's all! Airwire is fully installed.

## PHP components

### Creating components

To create a component run the `php artisan airwire:component` command.

```
php artisan airwire:component CreateUser
```

The command in the example will create a file in `app/Airwire/CreateUser.php`.

Next, register it in your AppServiceProvider:

```php
// boot()

Airwire::component('create-user', CreateUser::class);
```

### Wired properties and methods

Component properties and methods will be shared with the frontend if they use the `#[Wired]` attribute (in contrast to Livewire, where `public` visibility is used for this).

This means that your components can use properties (even public) just fine, and they won't be shared with the frontend until you explicitly add this attribute.

```php
class CreateTeam extends Component
{
    #[Wired]
    public string $name; // Shared

    public string $owner; // Not shared

    public function hydrate()
    {
        $this->owner = auth()->id();
    }
}
```

### Lifecycle hooks

As showed in the example above, Airwire has useful lifecycle hooks:

```php
public function hydrate()
{
    // Executed on each request, before any changes & calls are made
}

public function dehydrate()
{
    // Executed when serving a response, before things like validation errors are serialized into array metadata
}

public function updating(string $property, mixed $value): bool
{
    return false; // disallow this state change
}

public function updatingFoo(mixed $value): bool
{
    return true; // allow this state change
}

public function updated(string $property, mixed $value): void
{
    // execute side effects as a result of a state change
}

public function updatedFoo(mixed $value): void
{
    // execute side effects as a result of a state change
}

public function changed(array $changes): void
{
    // execute side effects $changes has a list of properties that were changed
    // i.e. passed validation and updating() hooks
}
```

### Validation

Airwire components use **strict validation** by default. This means that no calls can be made if the provided data is invalid.

To disable strict validation, set this property to false:
```php
public bool $strictValidation = false;
```

Note that disabling strict validation means that you're fully responsible for validating all incoming input before making any potentially dangerous calls, such as database queries.

```php
public array $rules = [
    'name' => ['required', 'string', 'max:100'],
];

// or ...
public function rules()
{
    return [ ... ];
}

public function messages()
{
    return [ ... ];
}

public function attributes()
{
    return [ ... ];
}
```

### Custom types

Airwire supports custom DTOs. Simply tell it how to decode (incoming requests) and encode (outgoing responses) the data:

```php
Airwire::typeTransformer(
    type: MyDTO::class,
    decode: fn (array $data) => new MyDTO($data['foo'], $data['abc']),
    encode: fn (MyDTO $dto) => ['foo' => $dto->foo, 'abc' => $dto->abc],
);
```

This doesn't require changes to the DTO class, and it works with any classes that extend the class.

### Models

A type transformer for models is included by default. It uses the `toArray()` method to generate a JSON-friendly representation of the model (which means that things like `$hidden` are respected).

It supports converting received IDs to model instances:
```php
// received: '3'
public User $user;
```

Converting arrays/objects to unsaved instances:
```php
// received: ['name' => 'Try Airwire on a new project', 'priority' => 'highest']
public function addTask(Task $task)
{
    $task->save();
}
```

Converting properties/return values to arrays:
```php
public User $user;
// response: {"name": "John Doe", "email": "john@example.com", ... }

public find(string $id): Response
{
    return User::find($id);
}
// same response as the property
```

If you wish to have even more control over how the data should be encoded, on a property-by-property basis, you can add a `Decoded` attribute. This can be useful for returning the id of a model, even if a property holds its instance:
```php
#[Wired] #[Encode(method: 'getKey')]
public User $user; // returns '3'

#[Wired] #[Encode(property: 'slug')]
public Post $post; // returns 'introducing-airwire'

#[Wired] #[Encode(function: 'generateHashid')]
public Post $post; // returns the value of generateHashid($post)
```

### Default values

You can specify default values for properties that can't have them specified directly in the class:

```php
#[Wired(default: [])]
public Collection $results;
```

These values will be part of the generated JS files, which means that components will have correct initial state even if they're initialized purely on the frontend, before making a single request to the server.

### Readonly values

Properties can also be readonly. This tells the frontend not to send them to the backend in request data.

A good use case for readonly properties is data that's only written by the server, e.g. query results:

```php
// Search/Filter component

#[Wired(readonly: true, default: [])]
public Collection $results;
```

### Mounting components

Components can have a `mount()` method, which returns initial state. This state is not accessible when the component is instantiated on the frontend (unlike default values of properties), so the component requests the data from the server.

A good use case for `mount()` is `<select>` options:

```php
public function mount()
{
    return [
        'users' => User::all()->toArray(),
    ]
}
```

Mount data is often readonly, so the method supports returning values that will be added to the frontend component's readonly data:

```php
public function mount()
{
    return [
        'readonly' => [
            'users' => User::all()->toArray(),
        ],
    ];
}
```

### Metadata

You can also add metadata to Airwire responses:

```php
public function save(User $user): User
{
    $this->validate($user->getAttributes());

    if ($user->save()) {
        $this->metadata('The user was saved with an id of ' . $user->id);
    } else {
        throw Exception("The user couldn't be created.");
    }
}
```

This metadata will be accessible to response watchers which are documented in the next section.

## Frontend

Airwire provides several helpers on the frontend.

### Global watcher

All responses can be watched on the frontend. This is useful for displaying notifications and rendering exceptions.

```ts
// Component-specific
component.watch(response => {
    // ...
});

// Global
Airwire.watch(response => {
    // response.data

    if (response.metadata.notification) {
        notify(response.metadata.notification)
    }

    if (response.metadata.errors) {
        notify('You entered invalid data.', { color: 'red' })
    }
}, exception => {
    alert(exception)
})
```

### Reactive helper

Airwire lets you specify a helper for creating singleton proxies of components. They are used for integrating with frontend frameworks.

For example, integrating with Vue is as easy as:

```ts
import { reactive } from 'vue'

Airwire.reactive = reactive
```

### Integrating with Vue.js

As mentioned above, you can integrate Airwire with Vue using a single line of code.

If you'd also like a `this.$airwire` helper (to avoid having to use `window.Airwire`), you can use our Vue plugin. Here's how an example `app.ts` might look like:

```ts
import Airwire from './airwire';

import { createApp, reactive } from 'vue';

createApp(require('./components/Main.vue').default)
    .use(Airwire.plugin('vue')(reactive))
    .mount('#app')

declare module 'vue' {
    export interface ComponentCustomProperties {
        $airwire: typeof window.Airwire
    }
}
```

```ts
data() {
    return {
        component: this.$airwire.component('create-user', {
            name: 'John Doe',
        }),
    }
},
```

### Integrating with Alpine.js

> Note: The Alpine integration hasn't been tested, but we *expect* it to work correctly. We'll be reimplementing the Vue demo in Alpine soon.

Alpine doesn't have a `reactive()` helper like Vue, [so we created it](https://github.com/archtechx/alpine-reactive).

There's one caveat: it's not global, but rather component-specific. It works with a list of components to update when the data mutates.

For that reason, you'd need to pass the reactive helper inside the component:

```html
<div x-data="{
    component: Airwire.component('create-user', {
        name: 'John Doe',
    }, $reactive)
}"></div>
```

To simplify that, you may use our Airwire plugin which provides an `$airwire` helper:

```html
<div x-data="{
    component: $airwire('create-user', {
        name: 'John Doe',
    })
}"></div>
```

To use the plugin, use this call **before importing Alpine**:

```
Airwire.plugin('alpine')()
```

## Testing

Airwire components are fully testable using fluent syntax:

```php
// Assertions against responses use send()
test('properties are shared only if they have the Wired attribute', function () {
    expect(TestComponent::test()
        ->state(['foo' => 'abc', 'bar' => 'xyz'])
        ->send()
        ->data
    )->toBe(['bar' => 'xyz']); // foo is not Wired
});

// Assertions against component state use hydrate()
test('properties are shared only if they have the Wired attribute', function () {
    expect(TestComponent::test()
        ->state(['foo' => 'abc', 'bar' => 'xyz'])
        ->hydrate()->bar
    )->toBe('xyz'); // foo is not Wired
});
```

You can look at the [package's tests](https://github.com/archtechx/airwire/blob/master/tests/Airwire/ValidationTest.php) to see real-world examples.

## Protocol spec

Airwire components aren't signed or fingerprinted in any way. They're completely stateless just like a REST API, which allows for instantiation from the frontend. This is in contrast to Livewire which doesn't allow any direct state changes — they all have to be "approved" and signed by the backend.

The best way to think about Airwire is simply an OOP wrapper around a REST API. Rather than writing low-level controllers and routes, you write expressive object-oriented components.

### Request

```json
{
    "state": {
        "foo": "abcdef"
    },
    "changes": {
        "foo": "bar"
    },
    "calls": {
        "save": [
            {
                "name": "Example task",
                "priority": "highest"
            }
        ]
    }
}
```

### Response

```json
{
    "data": {
        "foo": "abcdef"
    },
    "metadata": {
        "errors": {
            "foo": [
                "The name must be at least 10 characters."
            ]
        },
        "exceptions": {
            "save": "Insufficient permissions."
        }
    }
}
```

### State

The state refers to the old state, before any changes are made. The difference isn't big, since Airwire doesn't blindly trust the state, but it is separated from changes in the request.

One use of this are the `updating`, `updated`, and `changed` lifecycle hooks.

### Changes

If the change is not allowed, Airwire will silently fail and simply exclude the change from the request.

### Calls

Calls are a key-value pair of methods and their arguments.

If the execution is not allowed, Airwire will silently fail and simply exclude the call from the request.

If the execution results in an exception, Airwire will also add `methodName: { exception object }` to the `exceptions` part of the metadata.

Exceptions have a complete type definition in TypeScript.

### Validation

Validation is executed on the combination of the current state and the new changes.

Properties that failed validation will have an array of error strings in the `errors` object of the metadata.

## Compared to other solutions

Due to simply being a REST API layer between JavaScript code and a PHP file, Airwire doesn't have to be used *instead* of other libraries. You can use it with anything else.

Still, let's compare it with other libraries to understand when each solution works the best.

### Livewire

Livewire is specifically for returning HTML responses generated using Blade.

Most of our API is inspired by Livewire, with a few minor improvements (such as the use of PHP attributes) that were found *as a result of using Livewire*.

The best way to think about Livewire and Airwire is that Livewire supports Blade (purely server-rendered), whereas Airwire supports JavaScript (purely frontend-rendered).

Neither one has the ability to support the other approach, so the main deciding factor is what you're using for templating.

(This comparison is putting aside all of the ecosystem differences; it only looks at the tech.)

### Inertia.js

Inertia is best thought of as an alternative router for Vue/React/etc. There is some similarity in how Airwire and Inertia are used for a couple of use cases, but for the most part they're very different, since Inertia depends on *visits*, whereas Airwire has no concept of visits or routing.

Inertia and Airwire pair well for specific UI components — say that you use Inertia for most things on your frontend, but then you want to build a really dynamic component that sends a lot of requests to the backend (e.g. due to real-time input validation). You could simply install Airwire and use it for that one component, while using Inertia for everything else.
