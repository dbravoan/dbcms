<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;
use App\Enums\OAuthScope;

readonly class RegisterUserDTO extends BaseDTO
{
    /**
     * @param  list<OAuthScope>  $scopes
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public array $scopes = [OAuthScope::READ_CONTENT]
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<OAuthScope> $scopes */
        $scopes = array_map(
            fn (string|OAuthScope $scope) => $scope instanceof OAuthScope ? $scope : OAuthScope::from((string) $scope),
            (array) ($data['scopes'] ?? [OAuthScope::READ_CONTENT->value])
        );

        return new self(
            name: (string) $data['name'],
            email: (string) $data['email'],
            password: (string) $data['password'],
            scopes: $scopes
        );
    }
}
