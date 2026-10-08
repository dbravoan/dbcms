<?php

declare(strict_types=1);

use App\DTOs\Auth\LoginUserDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\Exceptions\DomainServiceException;
use App\Models\User;
use App\Services\Auth\UserAuthService;
use Illuminate\Support\Facades\Hash;

test('UserAuthService registers new users and hashes password', function (): void {
    $service = app(UserAuthService::class);
    $dto = new RegisterUserDTO(
        name: 'John Doe',
        email: 'john@example.com',
        password: 'PlainTextPassword123'
    );

    $user = $service->registerUser($dto);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('john@example.com')
        ->and(Hash::check('PlainTextPassword123', $user->password))->toBeTrue();
});

test('UserAuthService throws DomainServiceException when email is duplicate', function (): void {
    User::factory()->create(['email' => 'duplicate@example.com']);

    $service = app(UserAuthService::class);
    $dto = new RegisterUserDTO(
        name: 'Jane Doe',
        email: 'duplicate@example.com',
        password: 'password'
    );

    expect(fn () => $service->registerUser($dto))
        ->toThrow(DomainServiceException::class, 'A user with this email address already exists.');
});

test('UserAuthService throws exception on invalid login credentials', function (): void {
    $service = app(UserAuthService::class);
    $dto = new LoginUserDTO(
        email: 'nonexistent@example.com',
        password: 'wrongpassword'
    );

    expect(fn () => $service->authenticate($dto))
        ->toThrow(DomainServiceException::class, 'Invalid email or password credentials provided.');
});
