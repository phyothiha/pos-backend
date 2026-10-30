<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

function resetJwtAuthentication(): void
{
    Auth::forgetGuards();
    app('tymon.jwt')->unsetToken();
}

test('logs in using a username and returns a usable JWT', function () {
    $user = User::factory()->create(['name' => 'Demo Admin', 'username' => 'admin']);

    $response = $this->postJson(route('v1.auth.login'), ['username' => 'admin', 'password' => 'password']);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
        ->assertJsonPath('token_type', 'bearer')
        ->assertJsonPath('expires_in', 3600)
        ->assertJsonMissingPath('password');
    expect(Hash::check('password', $user->getRawOriginal('password')))->toBeTrue();
    resetJwtAuthentication();
    $this->withToken($response->json('access_token'))->getJson(route('v1.auth.me'))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $user->id, 'name' => 'Demo Admin', 'username' => 'admin']]);
});

test('returns 401 for invalid credentials without revealing account existence', function (string $username, string $password) {
    User::factory()->create(['username' => 'admin']);

    $this->postJson(route('v1.auth.login'), compact('username', 'password'))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Invalid username or password.']);
    $this->assertDatabaseCount(User::class, 1);
})->with([
    'incorrect password' => ['admin', 'incorrect'],
    'unknown username' => ['missing', 'password'],
]);

test('returns 422 when login credentials are missing', function () {
    $this->postJson(route('v1.auth.login'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username', 'password']);
});

test('returns 422 for invalid login credential formats', function (array $credentials) {
    $this->postJson(route('v1.auth.login'), $credentials)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['username', 'password']);
})->with([
    'non-string credentials' => [['username' => [], 'password' => []]],
    'oversized credentials' => [['username' => str_repeat('a', 256), 'password' => str_repeat('a', 256)]],
]);

test('returns 429 after five login attempts', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('v1.auth.login'), ['username' => 'missing', 'password' => 'incorrect'])
            ->assertUnauthorized();
    }

    $this->postJson(route('v1.auth.login'), ['username' => 'missing', 'password' => 'incorrect'])
        ->assertTooManyRequests();
});

test('returns 401 when a protected endpoint has no token', function (string $method, string $path) {
    $this->json($method, '/api/v1/'.$path)->assertUnauthorized();
})->with([
    ['GET', 'auth/me'],
    ['POST', 'auth/logout'],
    ['POST', 'auth/refresh'],
    ['GET', 'inventory/products'],
    ['POST', 'inventory/products'],
    ['PATCH', 'inventory/products/1'],
    ['DELETE', 'inventory/products/1'],
    ['GET', 'inventory/categories'],
    ['POST', 'inventory/categories'],
    ['GET', 'inventory/brands'],
    ['POST', 'inventory/brands'],
    ['GET', 'inventory/payment-platforms'],
    ['POST', 'inventory/payment-platforms'],
    ['GET', 'inventory/invoices'],
    ['POST', 'inventory/invoices'],
    ['PATCH', 'inventory/invoices/1/payment'],
]);

test('returns 401 for a malformed token', function () {
    $this->withToken('invalid-token')->getJson(route('v1.auth.me'))->assertUnauthorized();
});

test('returns 401 when an access token expires', function () {
    $this->freezeTime();
    $token = Auth::guard('api')->login(User::factory()->create());
    $this->travel(61)->minutes();
    resetJwtAuthentication();

    $this->withToken($token)->getJson(route('v1.auth.me'))->assertUnauthorized();
});

test('refreshes an expired access token and invalidates the original', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $token = Auth::guard('api')->login($user);
    $this->travel(61)->minutes();
    resetJwtAuthentication();

    $response = $this->withToken($token)->postJson(route('v1.auth.refresh'))
        ->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

    expect($response->json('access_token'))->not->toBe($token);
    resetJwtAuthentication();
    $this->withToken($response->json('access_token'))->getJson(route('v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
    resetJwtAuthentication();
    $this->withToken($token)->postJson(route('v1.auth.refresh'))->assertUnauthorized();
});

test('returns 401 when the token refresh window has expired', function () {
    $this->freezeTime();
    $token = Auth::guard('api')->login(User::factory()->create());
    $this->travel(15)->days();
    resetJwtAuthentication();

    $this->withToken($token)->postJson(route('v1.auth.refresh'))->assertUnauthorized();
});

test('logs out and prevents reuse or refresh of the token', function () {
    $token = Auth::guard('api')->login(User::factory()->create());
    resetJwtAuthentication();

    $this->withToken($token)->postJson(route('v1.auth.logout'))
        ->assertOk()
        ->assertExactJson(['message' => 'Successfully logged out.']);

    resetJwtAuthentication();
    $this->withToken($token)->getJson(route('v1.auth.me'))->assertUnauthorized();
    resetJwtAuthentication();
    $this->withToken($token)->postJson(route('v1.auth.refresh'))->assertUnauthorized();
});

test('allows a bearer token to access inventory', function () {
    $token = Auth::guard('api')->login(User::factory()->create());
    resetJwtAuthentication();

    $this->withToken($token)->getJson(route('v1.inventory.products.index'))->assertOk();
});
