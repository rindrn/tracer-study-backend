<?php
// app/Models/Transactional/PersonalAccessToken.php
namespace App\Models\Transactional;
 
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;
 
class PersonalAccessToken extends SanctumToken
{
    protected $connection = 'oltp';
}