<?php

declare(strict_types=1);

namespace App\Auth;

final readonly class AdminIdentity
{
    public function __construct(
        public string $id,
        public string $email,
        public ?string $nombre,
        public ?string $avatarUrl
    ) {
    }

    /** @return array{id: string, email: string, nombre: ?string, avatar_url: ?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'nombre' => $this->nombre,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}
