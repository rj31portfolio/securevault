<?php
namespace App\Policies;
use App\Models\{User,VaultFile};
class VaultFilePolicy {
    public function view(User $user,VaultFile $file): bool { return $user->hasPermission('files.manage') || $user->id === $file->user_id; }
    public function delete(User $user,VaultFile $file): bool { return $user->hasPermission('files.manage'); }
}
