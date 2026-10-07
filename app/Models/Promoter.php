<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class Promoter extends Authenticatable
{
    protected $table = 'promoters';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'country_code',
        'profile',
        'status',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}