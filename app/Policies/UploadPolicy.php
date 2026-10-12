<?php

namespace App\Policies;

use App\Models\StoreUser;
use App\Models\Upload;

/**
 * Which uploaded files a store account may open, preview or delete: its own,
 * and any file for the admin and staff accounts that run the library. The
 * uploader took any upload by id for any caller, and only stopped a seller
 * (not a customer) from deleting someone else's.
 */
class UploadPolicy
{
    public function viewAny(StoreUser $user): bool
    {
        return $this->managesUploads($user);
    }

    public function view(StoreUser $user, Upload $upload): bool
    {
        return $this->managesUploads($user) || (int) $upload->user_id === $user->id;
    }

    public function delete(StoreUser $user, Upload $upload): bool
    {
        return $this->view($user, $upload);
    }

    private function managesUploads(StoreUser $user): bool
    {
        return in_array($user->user_type, ['admin', 'staff'], true);
    }
}
