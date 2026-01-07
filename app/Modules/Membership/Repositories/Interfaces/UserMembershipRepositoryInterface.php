<?php

namespace App\Modules\Membership\Repositories\Interfaces;

interface UserMembershipRepositoryInterface
{
    public function create(array $data): array;
    
    public function getActiveMembership(int $userId): ?array;
    
    public function extend(int $id, int $days): bool;
    
    public function markAsExpired(int $id): bool;
    
    public function getExpiredMemberships(): array;
    
    public function getTotalActiveMembers(): int;
    
    public function getMembersByLevel(): array;
    
    public function getExpiringSoonCount(): int;
}

