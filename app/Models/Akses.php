<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Akses extends Model
{
    protected $table = 'akses';

    protected $fillable = ['user_id', 'aplikasi_id', 'peran'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class, 'aplikasi_id');
    }
}
