    var basedate  = '2014-08-10'; // we hide the actual date and only use the day of week, so it doesn't really matter what this is
    var daystart  = '09:00:00';   // time to begin timetable and constrain event beginnings to
    var dayend    = '21:00:00';   // time to end timetable and constrain event ends to

    var cal_id  = 0;
    var cal_mut = 0;
    var cal_title = "";
    var mode = 0;   // toggle for type of new events created
    var id = 0;     // internal event id counter
    var edited = 0; // remember whether we need to prompt to save changes

    // log out
    function logout(){
        window.location="./?logout";
    }

    // change password
    function changepw(){
        window.location="./?change_pw";
    }

    // called before page load
    function setActiveCalendar(id){
        cal_id = id;
    }

    // called when dropdown item is selected
    function showNewCalendar(obj){
        if(!edited || (edited && confirm("You have unsaved changes. Are you sure you want to discard them and activate a different calendar?"))){
            cal_id = obj.value;
            edited = 0;
            revert();
        }
        else{
            obj.value = cal_id;
        }
    }

    // push events to server
    function save(){
        if(cal_mut == 0){
            alert("This calendar is read-only.");
            return;
        }
    
        $("#dialog").html("<h1>Saving...</h1>");
        $("#dialog").fadeIn(0);
    
        var a = $("#calendar").fullCalendar('clientEvents');
        
        var json = "{\"events\":[";
        if(a.length > 0){
            for(i = 0; i < a.length; i++){
                var t = (a[i].className == "pref_event") ? 0 : 1;
                var s = a[i].start.format("YYYY-MM-DD HH:mm:ss");
                var e = a[i].end.format("YYYY-MM-DD HH:mm:ss");
                json = json + "{\"type\": "+t+", \"start\":\""+s+"\", \"end\":\""+e+"\"},";
            }
            json = json.substr(0,json.length-1);
        }
        json = json + "]}";
        
        var x = $.post("./ajax.php?method=post_events&calid="+cal_id, {events: json});
        
        x.done(function(data, status, x){
            var a = $.parseJSON(data);
            if(a.success == 0){
                throwError(a.error);
            }
            else{
                $("#dialog h1").fadeOut(50,function(){
                    $("#dialog h1").html("Schedule saved.");
                    $("#dialog h1").fadeIn(100);
                    $("#dialog").delay(500).fadeOut(500);
                    edited = 0;
                });
            }
        });

        x.fail(ajaxFailHandler);

        
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
        $("#controls table#btype td").removeClass('selected');
        $("#controls table#btype td:nth-child("+(m+1)+")").addClass('selected');
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

    // return true iff start >= daystart and end <= dayend
    function checkConstraints(event){
    
        // make sure start is after daystart
        if(moment(event.start).isBefore(event.start.format("YYYY-MM-DD") + "T" + daystart + ".000Z"))
            return false;

        // make sure end is before dayend
        if(moment(event.end).isAfter(event.start.format("YYYY-MM-DD") + "T" + dayend + ".000Z"))
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
    function addEvent(type, start, end, mutable){
            var title = "";
			var eventData = {
				id:        id++,
				title:     (type == 0) ? 'Preference' : 'Conflict',
				className: (type == 0) ? 'pref_event' : 'conf_event',
				start:     start,
				end:       end,
				editable:  mutable
			};
			$('#calendar').fullCalendar('renderEvent', eventData, true);
    }

    // strip timezone and normalize time
    function normalizeTime(m){
        return moment(m).utc().subtract('minutes', moment().zone());
    }

    // event edit form submit callback
    function updateEvent(form, eid){
        var doupdate = false;
        var event    = $("#calendar").fullCalendar('clientEvents', eid)[0];
        var newstart = normalizeTime(moment(basedate + " " + form.start.value , "YYYY-MM-DD HH:mm a").day(form.dow.value));
        var newend   = normalizeTime(moment(basedate + " " + form.end.value   , "YYYY-MM-DD HH:mm a").day(form.dow.value));
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

    function throwError(desc){
        $("#dialog").html(
            "<h2>An error occurred.</h2>" +
            "<h3>"+desc+"</h3>" +
            "<p class='err'>Please refresh the page and try again. If this problem persists, please contact the administrator.</p>"
        );

        $("#dialog").fadeIn(50);
    }

    function ajaxFailHandler(x, status, err){
            switch(status){
                case 'timeout':
                    throwError("The request timed out.");
                    break;
                case 'error':
                    throwError(err);
                    break;
                case 'abort':
                    throwError("Request aborted.");
                    break;
                case 'parsererror':
                    throwError("JSON parse error");
                    break;
                case null:
                default:
                    throwError("An unknown error occurred.<br/>"+err);
                    break;
            }
        }

    // load events from server
    function fetchEvents(){    
        var x = $.getJSON("./ajax.php?method=get_events&calid="+cal_id, {});

        x.done(function(data, status, x){
            if(data.success == 0){
                throwError(data.error)
            }
            else{
                cal_mut = data.data.mutable;
                cal_title = data.data.title;
                var a = data.data.events;
                for(i = 0; i < a.length; i++){
                    addEvent(a[i].type, a[i].start, a[i].end, a[i].mutable);
                }
                $("#calhead h3").html(cal_title);
                $("#dialog").fadeOut(200, function(){
                    if(cal_mut == 0)
                        $("#romsg").slideDown(100);
                    else
                        $("#romsg").slideUp(100);
                });
                edited = 0;
            }
        });

        x.fail(ajaxFailHandler);
    }

    // set up calendar
    $(document).ready(function() {
        $("p.msg").click(function(){
            $("p.msg").slideUp(250);
        });
    
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
			    if(checkConstraints({start: start, end: end}) && checkOverlap({start: start, end: end}) && cal_mut != 0){
    				addEvent(mode, start, end, 1);
    				edited = 1;
    			}
				$('#calendar').fullCalendar('unselect');
			},

            // click on event
			eventClick: function(event, jevent, view){
			    if(event.editable)
                    editEvent(event.id);
			},

			// callback for event moved
			eventDrop: function(event, delta, revert){
                if(!checkConstraints(event) || !checkOverlap(event) || !event.editable)
                    revert();
                else
                    edited = 1;
			},

			// callback for event resized
			eventResize: function(event, delta, revert, jevent, ui, view){
                if(!checkConstraints(event) || !checkOverlap(event) || !event.editable)
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

			header: {
			    left: '',
                center: 'title',
                right: ''
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
