<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type', 'datefrom', 'dateto','hourslogged','reason','status', 'cc_to', 'point_of_contact', 'description', 'line_manager', 'subject'
    ];

    public function leaveType()
    {
        return $this->belongsTo('App\LeaveType', 'leave_type', 'id');
    }

}
