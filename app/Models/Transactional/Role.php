<?php

namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'roles';
    protected $fillable   = ['name', 'label', 'description', 'scope'];
}
