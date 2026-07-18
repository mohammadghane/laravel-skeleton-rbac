<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable;

    protected $fillable = ['key', 'value', 'center_id', 'user_id'];
}
