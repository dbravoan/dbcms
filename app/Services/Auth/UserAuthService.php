<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Services\UserAuthServiceInterface;
use App\DTOs\Auth\LoginUserDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\Exceptions\DomainServiceException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAuthService implements UserAuthServiceInterface
{
    public function __construct(private readonly OAuthScopeGrantService $grants, private readonly TokenRevocationService $revocations) {}

    /**
     * {@inheritdoc}
     */
    public function registerUser(RegisterUserDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            if (str_ends_with(strtolower($dto->email), '@service.dbcms.invalid') || strtolower($dto->email) === 'mcp-agent@db-cms.io') {
                throw new DomainServiceException('This identity is reserved for a service account.', 422, 'RESERVED_SERVICE_IDENTITY');
            }
            if (User::where('email', $dto->email)->exists()) {
                throw new DomainServiceException('A user with this email address already exists.', 409, 'USER_EMAIL_EXISTS');
            }

            /** @var User $user */
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
            ]);

            return $user;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(LoginUserDTO $dto): array
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user || (bool) $user->getAttribute('is_service_account') || ! Hash::check($dto->password, $user->password)) {
            throw new DomainServiceException('Invalid email or password credentials provided.', 401, 'INVALID_CREDENTIALS');
        }

        $this->grants->validate($user, $dto->scopes);
        $tokenResult = $user->createToken($dto->deviceName, $dto->scopes);
        $tokenModel = $tokenResult->getToken();
        $expiresAt = $tokenModel && isset($tokenModel->expires_at)
            ? (string) $tokenModel->expires_at
            : now()->addDays(15)->toIso8601String();

        return [
            'user' => $user,
            'token' => $tokenResult->accessToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function revokeTokens(User $user): bool
    {
        $this->revocations->forUser($user);

        return true;
    }
}
