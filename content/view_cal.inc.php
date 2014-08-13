

    <?php

    function show_calendar_options($sel){
        global $database;
        
        $list = $database->get_calendar_list();
        foreach($list as $v){
            $seltxt = "";
            if($sel == $v['cal_id'])
                $seltxt=" selected='selected'";
            echo "<option value='{$v['cal_id']}'$seltxt>{$v['name']}</option>";
        }
    }

    $active_cal = 0;

    if(isset($_GET['cid']))
        $active_cal = $_GET['cid'];

    ?>

    <script type="text/javascript">
        setActiveCalendar(<?php echo $active_cal;?>);
    </script>

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
        <h2>Proctor Schedule</h2>
        <h4>Welcome, <?php echo $user_session->get_name(); ?>.</h4>
        <?php
            if(isset($_SESSION['message'])){
                echo "<p class='msg'>{$_SESSION['message']} <span class='meta'>Click to dismiss this message.</span></p>";
                unset($_SESSION['message']);
            }
        ?>
        <div id="controls">

            <table id='acal'>
                <tr>
                    <th colspan="2">Active Calendar</th>
                </tr>
                <tr>
                    <td colspan="2">
                        <select onchange="showNewCalendar(this)">
                            <?php show_calendar_options($active_cal); ?>
                        </select>
                    </td>
                </tr>
            </table>
        
            <table id='btype' class='btable'>
                <tr>
                    <th colspan="2">New Block</th>
                </tr>
                <tr>
                    <td class='selected' onclick='setType(0)'>Preference</td>
                    <td onclick='setType(1)'>Conflict</td>
                </tr>
            </table>

            <table id='save' class='btable'>
                <tr>
                    <th colspan="2">Schedule</th>
                </tr>
                <tr>
                    <td onclick='save()'>Save Changes</td>
                    <td onclick='revert()'>Revert Changes</td>
                </tr>
            </table>

            <table id='acct' class='btable'>
                <tr>
                    <th colspan="2">Account</th>
                </tr>
                <tr>
                    <td onclick='changepw()'>Change Password</td>
                    <td onclick='logout()'>Log Out</td>
                </tr>
            </table>
        </div>
        <div id="calhead">
            <h3>&nbsp;</h3>
            <div id="romsg">(Read-Only)</div>
        </div>
        <div id="calendar"></div>
    </div>
