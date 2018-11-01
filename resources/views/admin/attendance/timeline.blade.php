@extends('layouts.admin')
@section('Heading')
    <a href="{{route('attendance.create')}}" class="btn btn-info btn-rounded m-t-10 float-right"><span class="fas fa-plus" ></span> Add Attendance</a>
    <h3 class="text-themecolor">Dashboad</h3>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>
@stop
@section('content')
<div class="card">
    <div class="card-body">
        <div class="panel-body">
            <span style="float: right;">
                <select class="form-control" id="selectOffice">
                    <option value="0" @if($branch_id == 0) selected @endif>All Offices</option>
                    @foreach($office_locations as $office_location)
                    <option value="{{$office_location->id}}" @if($branch_id == $office_location->id) selected @endif>{{$office_location->name}}</option>
                    @endforeach
                </select>
            </span>
        </div>
        
        <div id="calendar">
        </div>
    </div>
</div>

@push('scripts')
<link href="{{asset('assets/plugins/fullcalendar-3.9.0/fullcalendar.min.css')}}" rel='stylesheet' />
<link href="{{asset('assets/plugins/fullcalendar-3.9.0/fullcalendar.print.css')}}" rel='stylesheet' media='print' />
<link href="{{asset('assets/plugins/fullcalendar-3.9.0/scheduler.min.css')}}" rel='stylesheet' />
<script src="{{asset('assets/plugins/fullcalendar-3.9.0/2.22.2-moment.min.js')}}"></script>
<script src="{{asset('assets/plugins/fullcalendar-3.9.0/fullcalendar.min.js')}}"></script>
<script src="{{asset('assets/plugins/fullcalendar-3.9.0/scheduler.min.js')}}"></script>
<script type="text/javascript">
    $(document).ready(function () {
        $('#calendar').fullCalendar({
            defaultView: 'timelineMonth',
            weekends: 'Boolean',
            dow: [ 1, 2, 3, 4, 5 ],
            header: {
                left: 'today prev,next',
                center: 'title',
                right: 'timelineDay,timelineWeek,timelineMonth'
            },
            slotWidth : 60,
            resourceColumns: [
                {
                    labelText: 'Employees',
                    field: 'firstname',
                },
            ],
            "eventClick":function(event, jsEvent, view) {
                if (event.title.search('Birthday') != -1) {
                    // window.location = "{{route('employees')}}/"+event.resourceId + "/" + event.date;
                    // console.log('found');
                }
                if (event.title.search('present') !== -1) {
                    window.location = "{{route('attendance.create')}}/"+event.resourceId + "/" + event.date;
                }
                if (event.title.search('leave') != -1) {
                    window.location = "{{route('leaves')}}/show/"+event.resourceId;
                }
            },
            resources:{!! $employees !!},
            events:{!! $events !!}
        });

        $("#selectOffice").change(function(e){
            var url = "{{route('timeline')}}/" + $(this).val();
            
            if (url) {
                window.location = url; 
            }
            return false;
        });
    });
</script>
@endpush
@stop