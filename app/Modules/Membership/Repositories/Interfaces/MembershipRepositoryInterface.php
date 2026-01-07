<?php

namespace App\Modules\Membership\Repositories\Interfaces;

interface MembershipRepositoryInterface
{
    public function findById(int $id): ?array;
    
    public function getActiveMemberships(): array;
    
    public function findBySlug(string $slug): ?array;
}

