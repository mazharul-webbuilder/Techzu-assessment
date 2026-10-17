<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Import;
use Illuminate\Contracts\Auth\Authenticatable;

class ImportPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        return $actor instanceof Admin;
    }

    public function view(Authenticatable $actor, Import $import): bool
    {
        return $actor instanceof Admin;
    }

    public function create(Authenticatable $actor): bool
    {
        return $actor instanceof Admin;
    }
}
