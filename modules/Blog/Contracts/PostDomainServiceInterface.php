<?php

declare(strict_types=1);

namespace Modules\Blog\Contracts;

use App\Models\Market;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Blog\Models\Post;

interface PostDomainServiceInterface
{
    /** @param array<string,mixed> $data */
    public function createPost(array $data, ?User $actor = null): Post;

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function updatePost(Post $post, array $data): array;

    /** @return array<string,mixed> */
    public function editData(Post $post): array;

    public function deletePost(Post $post): void;

    /** @return LengthAwarePaginator<int, Post> */
    public function getFeed(?Market $market = null, ?string $locale = null, int $perPage = 12, ?int $page = null): LengthAwarePaginator;
}
