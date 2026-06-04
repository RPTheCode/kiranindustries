<?php

namespace App\Models;

use App\Traits\AutoApplyPermissionCheck;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Media extends SpatieMedia
{
    use AutoApplyPermissionCheck;

    public function scopeWithPermissionCheck($query)
    {
        return $this->applyPermissionScope($query, $this->getTable());
    }
}
