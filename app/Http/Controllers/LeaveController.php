<?php

namespace App\Http\Controllers;

use App\Leave;
use App\LeaveType;
use App\Employee;
use App\OrganizationHierarchy;
use App\EmployeeLeaveType;
use App\AttendanceSummary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Traits\MetaTrait;
use Carbon\Carbon;
use DB;
use Session;

class LeaveController extends Controller
{
    use MetaTrait;
    
    public $leave_types = [
        "unpaid_leave" => "Unpaid Leave",
        "half_leave" => "Half Leave",
        "short_leave" => "Short Leave",
        "paid_leave" => "Paid Leave",
        "sick_leave" => "Sick Leave",
        "casual_leave" => "Casual Leave",
    ];

    public $statuses = [
        "pending" => "Pending",
        "approved" => "Approved",
        "declined" => "Declined",
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $employee = Auth::User();
        $this->meta['title'] = 'Show Leaves';  
        $leaves = Leave::where('employee_id', $employee->id)->with('leaveType')->get();

        $consumed_leaves = 0; 
        if ($leaves->count() > 0) {
            foreach ($leaves as $leave) {
                $datefrom = Carbon::parse($leave->datefrom);
                $dateto = Carbon::parse($leave->dateto);
                $consumed_leaves += $dateto->diffInDays($datefrom) + 1;
            }
        }

        return view('admin.leaves.showleaves',$this->metaResponse(),[
            'leaves' => $leaves,
            'consumed_leaves' => $consumed_leaves,
            'employee' => $employee,
        ]);
    }

    public function employeeleaves()
    {
        $this->meta['title'] = 'Show Employee Leaves';  
        $user = Auth::user()->id;
        if ($user == 1) {
            $leaves = Leave::leftJoin('employees', function($join) {
                $join->on('employees.id', '=', 'leaves.employee_id');
                $join->whereIn('leaves.status', ['', 'Pending']);
            });
        }
        else{
            $leaves = Leave::leftJoin('employees', function($join) use ($user) {
                $join->on('employees.id', '=', 'leaves.employee_id');
                $join->whereIn('leaves.status', ['', 'Pending']);
                $join->where(function($q) use ($user) {
                    $q->where('leaves.line_manager', $user)
                    ->orWhere('leaves.point_of_contact', $user);
                });
            });
        }
        
        $leaves = $leaves->with('leaveType')->get([
            'employees.*',
            'leaves.id AS leave_id',
            'leaves.leave_type AS leave_type',
            'leaves.datefrom AS leave_from',
            'leaves.dateto AS leave_dateto',
            'leaves.subject AS leave_subject',
            'leaves.line_manager AS line_manager',
            'leaves.point_of_contact AS point_of_contact',
            'leaves.status AS leave_status',
        ]);

        // dd($leaves->toArray());

        return view('admin.leaves.employeeleaves',$this->metaResponse(),[
            'employees' => $leaves,
        ]);
    }

    public function indexEmployee($id)
    {
        $this->meta['title'] = 'Show Leaves';  
        $leaves = Leave::where('employee_id', $id)->get();
        
        return view('admin.leaves.employeeshowleaves',$this->metaResponse(),['leaves' => $leaves]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $id = Auth::User()->id;
        $this->meta['title'] = 'Create Leave';    
        $OrganizationHierarchy = OrganizationHierarchy::where('employee_id', $id)->with('lineManager')->first();
        $employees = Employee::all();
        return view('admin.leaves.create',$this->metaResponse(),[
            'employees' => $employees,
            'line_manager' => $OrganizationHierarchy->lineManager,
            'leave_types' => LeaveType::all(),
        ]);
    }

    public function EmployeeCreate()
    {
        $this->meta['title'] = 'Create Leave';    
        $employees = Employee::all();
        return view('admin.leaves.create',$this->metaResponse(),['employees' => $employees]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'datefrom' => 'required',
            'dateto' => 'required|after_or_equal:datefrom',
        ]);

        $employee_id = Auth::User()->id;
        $leave_type = $request->leave_type;
        
        $dateFromTime = Carbon::parse($request->datefrom);
        $dateToTime = Carbon::parse($request->dateto);
        
        $consumed_leaves = $dateToTime->diffInDays($dateFromTime) + 1;
        
        $attendance_summaries = AttendanceSummary::where(['employee_id' => $employee_id])
            ->whereDate('date', '>=', $dateFromTime->toDateString())
            ->whereDate('date', '<=', $dateToTime->toDateString())
            ->get();

        if($attendance_summaries->count() > 0){
            $msg = '';
            foreach ($attendance_summaries as $key => $attendance_summary) {
                $msg .= ' '. $attendance_summary->date;
            }
            return redirect()->back()->with('error','Employee was already present on dates: '. $msg);
        }
        
        $leave = Leave::create([
            'employee_id' => $employee_id,
            'leave_type' => $leave_type,
            'datefrom' => $dateFromTime,
            'dateto' => $dateToTime,
            'subject' => $request->subject,
            'description' => $request->description,
            'point_of_contact' => $request->point_of_contact,
            'line_manager' => $request->line_manager,
            'cc_to' => $request->cc_to,
            'status' => 'pending',
        ]);

        if ($leave){
           return redirect()->route('leave.show')->with('success','Leave is created succesfully');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function show(Leave $leave)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->meta['title'] = 'Update Leave';
        
        $employee_id = Auth::User()->id;
        $OrganizationHierarchy = OrganizationHierarchy::where('employee_id', $employee_id)->with('lineManager')->first();
        $employees = Employee::all();
        
        $leave = Leave::find($id);

        return view('admin.leaves.edit',$this->metaResponse(),[
            'employees' => $employees,
            'line_manager' => $OrganizationHierarchy->lineManager,
            'leave_types' => LeaveType::all(),
            'leave' => $leave,
        ]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $employee_id = Auth::User()->id;

        $this->validate($request,[
            'datefrom' => 'required',
            'dateto' => 'required|after_or_equal:datefrom',
        ]);

        $dateFromTime = Carbon::parse($request->datefrom);
        $dateToTime = Carbon::parse($request->dateto);
        
        $consumed_leaves = $dateToTime->diffInDays($dateFromTime) + 1;
        
        $attendance_summaries = AttendanceSummary::where(['employee_id' => $employee_id])
            ->whereDate('date', '>=', $dateFromTime->toDateString())
            ->whereDate('date', '<=', $dateToTime->toDateString())
            ->get();

        if($attendance_summaries->count() > 0){
            $msg = '';
            foreach ($attendance_summaries as $key => $attendance_summary) {
                $msg .= ' '. $attendance_summary->date;
            }
            return redirect()->back()->with('error','Employee was already present on dates: '. $msg);
        }

        $leave = Leave::find($id);
        $leave->employee_id =  $employee_id;
        $leave->leave_type =  $request->leave_type;
        $leave->datefrom =  $dateFromTime;
        $leave->dateto =  $dateToTime;
        $leave->subject =  $request->subject;
        $leave->description =  $request->description;
        $leave->line_manager =  $request->line_manager;
        $leave->point_of_contact = $request->point_of_contact;
        $leave->cc_to =  $request->cc_to;
        $leave->status = $request->status;

        $leave = $leave->save();

        return redirect()->route('leave.show', $employee_id)->with('success','Leave is created succesfully');
    }

    function updateEmployeeLeaveType($employee_id, $leave_type_id){
        

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function updateStatus($id, $status)
    {
        $leave = Leave::find($id);
        if ($leave->status == 'Approved') { // if already approved do nothing
            return redirect()->back()->with('success','Leave already approved');   
        }
        $leave->status = $status;
        $leave->save();
        
        if ($status == 'Approved') {
            $dateFromTime = Carbon::parse($leave->datefrom);
            $dateToTime = Carbon::parse($leave->dateto);
            
            $consumed_leaves = $dateToTime->diffInDays($dateFromTime) + 1;

            $employee_leave_type = EmployeeLeaveType::where([
                'employee_id' => $leave->employee_id,
                'leave_type_id' => $leave->leave_type,
            ])->first();

            $cnt = $employee_leave_type->count -= $consumed_leaves;
            
            DB::statement("UPDATE employee_leave_type SET count = $cnt where employee_id = ".$leave->employee_id." AND leave_type_id = ". $leave->leave_type);
        }

        return redirect()->back()->with('success','Leave status is updated succesfully');   
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Leave  $leave
     * @return \Illuminate\Http\Response
     */
    public function destroy(Leave $leave,$id)
    {
        $leave = Leave::where('employee_id',$id)->first();
        $leave->delete();
        return redirect()->back()->with('success','Leave is deleted succesfully');   
    }

    public function sendEmail($body)
    {
        Mail::raw($body, function($message) use ($request)
        {
            $message->from('kosar@glowlogix.com', 'NWDWMP');

            $message->to($request->email, $request->name)->subject('From WDWM');
        });

        return 'mail sent to '. $request->email;
    }
}
