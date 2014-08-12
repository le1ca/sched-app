<?php

session_start();

require_once 'sql_class.php';
require_once 'session_class.php';

// sql config
$sql_host      = 'localhost';
$sql_user      = 'proctor-schedule';
$sql_pass      = 'sql_password_goes_here';
$sql_db        = 'proctor-schedule';

// create objects
$database     = Database::Connect($sql_host, $sql_user, $sql_pass, $sql_db);
$user_session = Session::Initialize();

if(isset($_GET['get_events'])){
    $str = "{\"events\":[";
    foreach($user_session->get_events() as $v){
        $str .= "{\"type\":{$v['type']},\"start\":\"{$v['start']}\",\"end\":\"{$v['end']}\"},";
    }
    $str = substr($str, 0, strlen($str) - 1);
    $str .= "]}";
    echo $str;
    exit;
}

if(isset($_GET['post_events'])){
    $j = json_decode($_POST['events'], true);
    $user_session->clear_events();
    foreach($j['events'] as $e){
        $user_session->add_event($e['type'], $e['start'], $e['end']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Proctor Scheduling</title>
    <script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>
    <script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/moment.js/2.7.0/moment.min.js"></script>
    <script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/fullcalendar/2.0.2/fullcalendar.min.js"></script>
    <link rel="stylesheet" type="text/css" href="http://cdnjs.cloudflare.com/ajax/libs/fullcalendar/2.0.2/fullcalendar.css"/>
    <style type="text/css">
        body{
            position:absolute;
            margin:0px;
            padding:0px;
            height:100%;
            width:100%;
            font-size:10pt;
            font-family:Arial, sans-serif;
        }
        
        #calendar{
           width:800px;
           border:1px solid;
           border-radius:4px;
        }
        
        .fc-header{
            display:none;
        }
        .fc-state-highlight{
            background:inherit;
        }
        .fc-event-title{
            text-align:center;
        }
        
        .cal_cont{
            width:800px;
            margin:auto;
        }
        
        #controls{
            margin-bottom:12px;
            text-align:center;
        }
        #controls table th{
            border-bottom:1px solid;
        }
        #controls table td{
            width:80px;
            text-align:center;
            border-radius:4px;
            border-right:1px solid;
            border-left:1px solid;
        }
        #controls table{
            border:1px solid;
            border-radius:4px;
            margin:auto;
            display:inline-block;
        }
        #controls table+table{
            margin-left:22px;
        }
        #controls table td{
            height:32px;
            width:60px;
            font-size:8.5pt;
            padding:2px;
        }
        
        #controls table td:hover{
            box-shadow: inset 0px 0px 3px rgba(0,0,0,0.25);
            cursor:pointer;
        }
        
        #controls table td:nth-child(1){
            background-color:rgba(0,150,0,0.1);
        }
        #controls table#btype td.selected:nth-child(1), #controls table td:nth-child(1):active{
            background-color:rgba(0,150,0,0.7);
            color:#ffffff;
        }
        #controls table td:nth-child(2){
            background-color:rgba(150,0,0,0.1);
        }
        #controls table#btype td.selected:nth-child(2), #controls table td:nth-child(2):active{
            background-color:rgba(150,0,0,0.7);
            color:#ffffff;            
        }
        
        .pref_event{
            background-color:rgba(0,150,0,0.8);
            color:#000000;
        }
        .conf_event{
            background-color:rgba(150,0,0,0.8);
            color:#000000;
        }

        #dialog{
            position:fixed;
            padding-top:120px;
            top:0;
            left:0;
            height:100%;
            width:100%;
            z-index:9999;
            background-color:rgba(0,0,0,0.5);
            overflow:none;
        }

        #dialog form{
            display:none;
            background:#ffffff;
            border:1px solid black;
            margin:auto;
            box-shadow:3px 4px 8px rgba(0,0,0,0.2);
            padding:5px;
            border-radius:6px;
            width:240px;
        }

        #dialog form table{
            width:240px;
        }

        #dialog table td:first-child{
            text-align:right;
            width:38%;
            font-weight:bold;
        }

        #dialog table th{
            border-bottom:1px solid;
            padding-bottom:2px;
            font-size:12pt;
        }

        #dialog table tr:nth-child(2) td{
            padding-top:9px;
        }

        #dialog_proto{
            display:none;
        }

        .submitrow td{
            padding-top:10px;
            text-align:center !important;
        }

        .visible{
            display:block;
        }

        #dialog form button:hover, #dialog form input[type="submit"]:hover{
            cursor:pointer;
        }

        #dialog h1{
            text-align:center;
            color:#ffffff;
            font-size:24pt;
        }
    </style>
    <script type="text/javascript">
    var basedate  = '2014-08-10'; // we hide the actual date and only use the day of week, so it doesn't really matter what this is
    var daystart  = '09:00:00';   // time to begin timetable and constrain event beginnings to
    var dayend    = '21:00:00';   // time to end timetable and constrain event ends to

    var mode = 0;   // toggle for type of new events created
    var id = 0;     // internal event id counter
    var edited = 0; // remember whether we need to prompt to save changes

    // log out
    function logout(){
        window.location="./?logout";
    }

    // push events to server
    function save(){
        $("#dialog").html("<h1>Saving...</h1>");
        $("#dialog").fadeIn(0);
    
        var a = $("#calendar").fullCalendar('clientEvents');
        var json = "{\"events\":[";
        for(i = 0; i < a.length; i++){
            var t = (a[i].className == "pref_event") ? 0 : 1;
            var s = a[i].start.format("YYYY-MM-DD HH:mm:ss");
            var e = a[i].end.format("YYYY-MM-DD HH:mm:ss");
            json = json + "{\"type\": "+t+", \"start\":\""+s+"\", \"end\":\""+e+"\"},";
        }
        json = json.substr(0,json.length-1) + "]}";
        $.post("./?post_events", {events: json}, function(data, status, x){
            $("#dialog h1").fadeOut(50,function(){
                $("#dialog h1").html("Schedule saved.");
                $("#dialog h1").fadeIn(100);
                $("#dialog").delay(500).fadeOut(500);
                edited = 0;
            });
            
        });
    }

    // reload events from server
    function revert(){
        if(edited && !confirm("Are you sure you want to revert your changes?"))
            return;

        $("#dialog").html("<h1>Loading...</h1>");
        $("#dialog").fadeIn(0);
        $("#calendar").fullCalendar('removeEvents');
        fetchEvents();
    }

    // change type of new events
    function setType(m){
        mode = m;
        $("#controls table td").removeClass('selected');
        $("#controls table td:nth-child("+(m+1)+")").addClass('selected');
    }

    // generic method to create a list of <option> elements from an array, choosing one as 'selected'
    function makeOptionsList(arr, sel){
        var str = "";
        for(i = 0; i < arr.length; i++){
            var d = arr[i];
            var s = (sel == d) ? " selected='selected'" : "";
            str += "<option id='"+d+"'"+s+">"+d+"</option>";
        }
        return str;
    }

    // create list of <option>s for preference/conflict setting
    function getTypeOptions(sel){
        return makeOptionsList(['Preference','Conflict'], sel);
    }

    // create list of <option>s for day of week
    function getDowOptions(sel){
        return makeOptionsList(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], sel);
    }

    // remove timzeone from moment (necessary when parsing time from string)
    function normalizeTime(m){
        return moment(m).utc().subtract('m', moment().zone());
    }

    // return true iff start >= daystart and end <= dayend
    function checkConstraints(event){
        // make sure start is after daystart
        if(moment(event.start).isBefore(normalizeTime(event.start.format("YYYY-MM-DD") + " " + daystart)))
            return false;

        // make sure end is before dayend
        if(moment(event.end).isAfter(normalizeTime(event.start.format("YYYY-MM-DD") + " " + dayend)))
            return false;

        return true;
    }

    // return true if no other event overlaps with this event
    function checkOverlap(event){
        var a = $("#calendar").fullCalendar('clientEvents');
        for(i = 0; i < a.length; i++)
            if(a[i].id != event.id && moment(a[i].end).isAfter(moment(event.start)) && moment(a[i].start).isBefore(moment(event.end)))
                return false;
        return true;
    }

    // add a new event to the calendar (explicit)
    function addEvent(type, start, end){
            var title = "";
			var eventData = {
				id:        id++,
				title:     (type == 0) ? 'Preference' : 'Conflict',
				className: (type == 0) ? 'pref_event' : 'conf_event',
				start:     start,
				end:       end
			};
			$('#calendar').fullCalendar('renderEvent', eventData, true);
    }

    // event edit form submit callback
    function updateEvent(form, eid){
        var doupdate = false;
        var event    = $("#calendar").fullCalendar('clientEvents', eid)[0];
        var newstart = normalizeTime(moment(basedate + " " + form.start.value).day(form.dow.value));
        var newend   = normalizeTime(moment(basedate + " " + form.end.value).day(form.dow.value));
        var newtitle = form.type.value;
        var newclass = (form.type.value == "Preference") ? "pref_event" : "conf_event";

        // submit
        if(form.subopt == "Submit"){
        
            // sanity check for timestamps
            if(!newstart.isBefore(newend)){
                alert('Error: End time must be after start time.');
                return; // keep dialog open on error
            }

            // constraint check
            if(!checkConstraints({start: newstart, end: newend})){
                alert('Error: Times must be between '+daystart+' and '+dayend+'.');
                return;
            }

            // overlap check
            if(!checkOverlap({start: newstart, end: newend, id: eid})){
                alert('Error: This block overlaps with another block.');
                return;
            }

            doupdate = true;
        }
        // delete
        else if(form.subopt == "Delete"){
            if(!confirm("Are you sure you want to delete this block?"))
                return;
            edited = 1;
            $("#calendar").fullCalendar('removeEvents', event.id);
        }

        // apply changes if no errors
        if(doupdate == true){
            event.start = newstart;
            event.end   = newend;
            event.title = newtitle;
            event.className = newclass;
            $("#calendar").fullCalendar('updateEvent', event);
            edited = 1;
        }

        // close dialog
        $("#dialog form").fadeOut(150, function(){
            $("#dialog").fadeOut(100);
        });
        
    }

    // create event edit form (calendar click callback)
    function editEvent(id){
        var event = $("#calendar").fullCalendar('clientEvents', id)[0];
        var selected_type = (event.className == "pref_event") ? "Preference" : "Conflict";
        var selected_dow  = event.start.format("ddd");
        var dialog_html   = $("#dialog_proto").html();

        dialog_html = dialog_html.replace(/%ID%/g, id.toString());
        dialog_html = dialog_html.replace(/%TYPEO%/g, getTypeOptions(selected_type));
        dialog_html = dialog_html.replace(/%DOWO%/g, getDowOptions(selected_dow));
        dialog_html = dialog_html.replace(/%START%/g, event.start.format("hh:mm a"));
        dialog_html = dialog_html.replace(/%END%/g, event.end.format("hh:mm a"));
        
        $("#dialog").html(dialog_html);
        $("#dialog").fadeIn(400, function(){
            $("#dialog form").fadeIn(150);
        });
    }

    // load events from server
    function fetchEvents(){    
        $.get("./?get_events", {}, function(data, status, x){
            var a = $.parseJSON(data).events;
            for(i = 0; i < a.length; i++){
                addEvent(a[i].type, a[i].start, a[i].end);
            }
            $("#dialog").fadeOut();
            edited = 0;
        });
    }

    // set up calendar
    $(document).ready(function() {
        $('#calendar').fullCalendar({
            defaultDate:  basedate,
            defaultView:  'agendaWeek',
            allDaySlot:   false,
            slotDuration: {minutes: 30},
            snapDuration: {minutes: 5},
            minTime:      daystart,
            maxTime:      dayend,
            selectable:   true,
			selectHelper: true,
			editable:     true,

			// create an event with select operation
			select: function(start, end) {
			    if(checkConstraints({start: start, end: end}) && checkOverlap({start: start, end: end})){
    				addEvent(mode, start, end);
    				edited = 1;
    			}
				$('#calendar').fullCalendar('unselect');
			},

            // click on event
			eventClick: function(event, jevent, view){
                editEvent(event.id);
			},

			// callback for event moved
			eventDrop: function(event, delta, revert){
                if(!checkConstraints(event) || !checkOverlap(event))
                    revert();
                else
                    edited = 1;
			},

			// callback for event resized
			eventResize: function(event, delta, revert, jevent, ui, view){
                if(!checkConstraints(event) || !checkOverlap(event))
                    revert();
                else
                    edited = 1;
			},
			
			// strip dates and only show day of the week
			viewRender: function(view, element){
                $("#calendar .fc-view-agendaWeek thead th").each(function(){
                    $(this).html($(this).html().split(' ')[0]);
                });
			},
			
			events: []
        });

        // populate it
        fetchEvents();
    });

    // confirm to save changes when closing page
    $(window).bind("beforeunload", function(e) {
        if(edited)
            return "You have unsaved changes. Leaving this page will result in the changes being discarded.";
    });
    </script>
</head>
<body>
    <div id="dialog_proto">
        <form action="javascript:void(null)" onsubmit="updateEvent(this,%ID%)">
        <table>
            <tr>
                <th colspan='2'>
                    Edit Block
                </th>
            </tr>
            <tr>
                <td>Type</td>
                <td>
                    <select name="type">
                        %TYPEO%
                    </select>
                </td>
            </tr>
            <tr>
                <td>Day</td>
                <td>
                    <select name="dow">
                        %DOWO%
                    </select>
                </td>
            </tr>
            <tr>
                <td>Start</td>
                <td><input name="start" value="%START%" size='8'/></td>
            </tr>
            <tr>
                <td>End</td>
                <td><input name="end" value="%END%" size='8'/></td>
            </tr>
            <tr class='submitrow'>
                <td colspan='2'>
                    <input type='submit' value='Delete' name='submit' onclick="this.form.subopt='Delete';"/>
                    <div style='display:inline-block;width:22px;'>&nbsp;</div>
                    <input type='submit' value='Cancel' name='submit' onclick="this.form.subopt='Cancel';"/>
                    <input type='submit' value='Submit' name='submit' onclick="this.form.subopt='Submit';"/>
                </td>
            </tr>
        </table>
    </div>
    <div id="dialog"><h1>Loading...</h1></div>
    <div class="cal_cont">
        <h2>proctor schedule</h2>

        <div id="controls">
            <table id='btype'>
                <tr>
                    <th colspan="2">New Block</th>
                </tr>
                <tr>
                    <td class='selected' onclick='setType(0)'>Preference</td>
                    <td onclick='setType(1)'>Conflict</td>
                </tr>
            </table>

            <table id='save'>
                <tr>
                    <th colspan="2">Schedule</th>
                </tr>
                <tr>
                    <td onclick='save()'>Save Changes</td>
                    <td onclick='revert()'>Revert Changes</td>
                </tr>
            </table>

            <table id='acct'>
                <tr>
                    <th colspan="2">Account</th>
                </tr>
                <tr>
                    <td onclick='void(null)'>Logged in as <?php echo $user_session->get_name(); ?></td>
                    <td onclick='logout()'>Log out</td>
                </tr>
            </table>
        </div>
    
        <div id="calendar"></div>
    </div>
</body>
</html>
