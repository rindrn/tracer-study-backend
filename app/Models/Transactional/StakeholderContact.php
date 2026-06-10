<?php

namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;

class StakeholderContact extends Model
{
    protected $connection = 'oltp';
    protected $fillable = ['alumni_id', 'questionnaire_id', 'contact_type', 'contact_name', 'contact_email', 'alumni_status'];
}
