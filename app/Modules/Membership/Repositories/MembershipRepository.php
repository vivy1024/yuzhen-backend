<?php

namespace App\Modules\Membership\Repositories;

use App\Modules\Membership\Repositories\Interfaces\MembershipRepositoryInterface;
use App\Modules\Membership\Models\Membership;
use App\Infrastructure\Database\Repositories\BaseRepository;

class MembershipRepository extends BaseRepository implements MembershipRepositoryInterface
{
    protected $model = Membership::class;

    public function findById(int $id): ?array
    {
        $membership = Membership::find($id);
        return $membership ? $membership->toArray() : null;
    }

    public function getActiveMemberships(): array
    {
        return Membership::active()->ordered()->get()->toArray();
    }

    public function findBySlug(string $slug): ?array
    {
        $membership = Membership::where('slug', $slug)->first();
        return $membership ? $membership->toArray() : null;
    }
}

