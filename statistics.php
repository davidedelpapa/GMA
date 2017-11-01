<?php
/*
 * Debug Code
 */
ini_set('display_errors', 'On');
error_reporting(E_ALL);

/*
 * configuration
 */
$config = parse_ini_file("config.ini", true);

/* ----------------------------------------------------- */
/*                      SERVER SETUP                     */
/* ----------------------------------------------------- */

$db_connection = new mysqli($config['mysql']['host'], $config['mysql']['user'], $config['mysql']['password'], $config['mysql']['database'], $config['mysql']['port']);
if ($db_connection->connect_error) {
    error_log('MySql Connection failed: ' . $db_connection->connect_error);
    http_response_code(500);
    die(1);
}

/* ----------------------------------------------------- */
/*                      SIMPLE STATS                     */
/* ----------------------------------------------------- */

////////// PARAMETERS /////////////
$BOUNCE_SECONDS = 25; /// <- Less than this is considered a bounce
$TIMESTAMP_FORMAT = "mdyGis"; /// <- Used to get a timestamp from GMA's database
// ----------------------------- //

////////// FUNCTIONS  /////////////

/// getCurrentTileCount($key) -> int
///
/// Gets the current (=not historic record) tile count relative to the key passed as argument.
function getCurrentTileCount($key)
{
    global $db_connection;
    $q_ctc = "SELECT count(*) AS total FROM TileRecords JOIN SessionRecords WHERE TileRecords.sessionid = SessionRecords.id AND SessionRecords.pkey = '" . $key . "';";
    $result_q_ctc = $db_connection->query($q_ctc);
    if (!$result_q_ctc) {
        return 0;
    }
    $ret=$result_q_ctc->fetch_assoc();
    $result_q_ctc->free();
    return $ret['total'];
}

/// getCurrentVisitsCount($key) -> int
///
/// Gets the current (=not historic record) visits count relative to the key passed as argument.
function getCurrentVisitsCount($key)
{
    global $db_connection;
    $q_cvc = "SELECT count(*) AS total FROM SessionRecords WHERE SessionRecords.pkey = '" . $key . "';";
    $result_q_cvc = $db_connection->query($q_cvc);
    if (!$result_q_cvc) {
        return 0;
    }
    $ret=$result_q_cvc->fetch_assoc();
    $result_q_cvc->free();
    return $ret['total'];
}

/// getCurrentTotalVisitTime($key) -> int
///
/// Gets the current (=not historic record) total visit time (in seconds) relative to the key passed as argument.
function getCurrentTotalVisitTime($key)
{
    global $db_connection, $TIMESTAMP_FORMAT;
    $q_cvc = "SELECT firstaccess, lastaccess FROM SessionRecords WHERE SessionRecords.pkey = '" . $key . "';";
    $result_q_cvc = $db_connection->query($q_cvc);
    if (!$result_q_cvc) {
        return 0;
    }
    $res=$result_q_cvc->fetch_all();
    $result_q_cvc->free();
    $diff = 0;
    foreach ($res as $line) {
        $date1 = DateTime::createFromFormat($TIMESTAMP_FORMAT, $line[0]);
        $date2 = DateTime::createFromFormat($TIMESTAMP_FORMAT, $line[1]);
        $interval = date_diff($date2, $date1);
        // Gets all in seconds
        $difference = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        
        $diff += $difference;
    }
    return $diff;
}

/// getCurrentTotalBounces($key) -> int
///
/// Gets the current (=not historic record) total bounces 
/// relative to the key passed as argument and the parameter $BOUNCE_SECONDS.
function getCurrentTotalBounces($key)
{
    global $db_connection, $TIMESTAMP_FORMAT, $BOUNCE_SECONDS;
    $q_cvc = "SELECT firstaccess, lastaccess FROM SessionRecords WHERE SessionRecords.pkey = '" . $key . "';";
    $result_q_cvc = $db_connection->query($q_cvc);
    if (!$result_q_cvc) {
        return 0;
    }
    $res=$result_q_cvc->fetch_all();
    $result_q_cvc->free();
    $bounces = 0;
    foreach ($res as $line) {
        $d1 = DateTime::createFromFormat($TIMESTAMP_FORMAT, $line[0]);
        $d2 = DateTime::createFromFormat($TIMESTAMP_FORMAT, $line[1]);
        $interval = $d2->diff($d1);
        if ((($interval->h) < 1) && (($interval->i) < 1) && (($interval->s) < $BOUNCE_SECONDS)) {
            $bounces++;
        }
    }
    return $bounces;
}

/// getCurrentTotalZooms($key) -> int
///
/// Gets the current (=not historic record) total zooms for the key considered
function getCurrentTotalZooms($key)
{
    global $db_connection;
    $q_cvc = "SELECT z, sessionid FROM TileRecords JOIN SessionRecords ON SessionRecords.id = TileRecords.sessionid WHERE SessionRecords.pkey = '" . $key . "' ORDER BY TileRecords.id;";
    $result_q_cvc = $db_connection->query($q_cvc);
    if (!$result_q_cvc) {
        return 0;
    }
    $res=$result_q_cvc->fetch_all();
    $result_q_cvc->free();
    $count = 0;
    $tmp_z = -1;
    $tmp_s = "";
    foreach ($res as $line) {
        $z = $line[0];
        $sid = $line[1];
        if ($sid != $tmp_s) {
            $tmp_s = $sid;
            $count--;
            $tmp_z = -1;
        }
        if ($z != $tmp_z) {
             $tmp_z = $z;
             $count++;
        }
    }
    return $count;
}

/// getCurrentTotalPans($key) -> int
///
/// Gets the current (=not historic record) total zooms for the key considered
/// TODO: Right now it is a trick --> Get a usable algorithm
function getCurrentTotalPans($key)
{
    $total_tiles_per_key = getCurrentTileCount($key);
    $number_of_sessions = getCurrentVisitsCount($key);
    $total_zooms = getCurrentTotalZooms($key);
    $pans = ($total_tiles_per_key - ($total_zooms*6) - ( $number_of_sessions*6));
    if ($pans < 0) {
        $pans = 0;
    }
    return $pans;
}

// ALL AVILABLE INFO //////////////////////////////////////

/// currentTrafficInfo($key) -> array
///
/// Gets an array of information regarding the key considered.
/// Gets absolute (basic) values from the relative functions, 
/// and calculate the remaining accordingly.
function currentTrafficInfo($key)
{
    // ABSOLUTE INFO
    $total_tiles_per_key = getCurrentTileCount($key);
    $number_of_sessions = getCurrentVisitsCount($key);
    $bounced_sessions = getCurrentTotalBounces($key);
    $total_zooms = getCurrentTotalZooms($key);
    $total_visit_time = getCurrentTotalVisitTime($key);
    // Right now it is a trick, so repeated, even if collected
    //$total_pans = getCurrentTotalPans($key);
    $total_pans = ($total_tiles_per_key - ($total_zooms*6) - ( $number_of_sessions*6));
    if ($total_pans < 0) {
        $total_pans = 0;
    }

    // CALCULATED INFO
    $total_interactions = $total_zooms + $total_pans;
    if ($number_of_sessions == 0) {
         $average_session_time = 0;
         $bounce_rate = 0;
         $zooming_rate = 0;
         $panning_rate = 0;
         $interaction_rate = 0;
         $average_interaction_time = 0;
         $average_tiles_per_session = 0;
    } else {
        // Average Tiles per Session
        if ($total_tiles_per_key == 0) {
            $average_tiles_per_session = 0;
        } else {
            $average_tiles_per_session = round($total_tiles_per_key / $number_of_sessions);
        }
        // Average Session Time
        if ($total_visit_time == 0) {
            $average_session_time = 0;
        } else {
            $average_session_time = round($total_visit_time / $number_of_sessions);
        }
        // Average Interaction Time
        if ($total_visit_time == 0 || $total_interactions == 0) {
            $average_interaction_time = 0;
        } else {
            $average_interaction_time = round($total_visit_time / $total_interactions);
        }
        // Bounce Rate
        if ($bounced_sessions == 0) {
            $bounce_rate = 0;
        } else {
            $bounce_rate = (($bounced_sessions / $number_of_sessions) * 100);
        }
        // Zooming rate
        if ($total_zooms == 0) {
            $zooming_rate = 0;
        } else {
            $zooming_rate = (($total_zooms / $number_of_sessions) * 100);
        }
        // Panning Rate
        if ($total_pans == 0) {
            $panning_rate = 0;
        } else {
            $panning_rate = (($total_pans / $number_of_sessions) * 100);
        }
        // Interaction Rate
        if ($total_interactions == 0) {
            $interaction_rate = 0;
        } else {
            $interaction_rate = (($total_interactions / $number_of_sessions) * 100);
        }
    }

    return [
        $total_tiles_per_key,
        $number_of_sessions,
        $bounced_sessions,
        $total_visit_time,
        $total_zooms,
        $total_pans,
        $total_interactions,
        $average_tiles_per_session,
        $average_session_time,
        $average_interaction_time,
        $bounce_rate,
        $zooming_rate,
        $panning_rate,
        $interaction_rate
    ];
}

// COMPILED STUFF //////////////////////////////////////

function getHistoryInfo($key)
{
    global $db_connection;
    return 0;
}



// GETS STATS FROM TESTING KEY
$key = $config['mysql']['testkey'];
$stats = currentTrafficInfo($key);

$total_tiles_per_key = $stats[0];
$number_of_sessions = $stats[1];
$bounced_sessions = $stats[2];
$total_visit_time = $stats[3];
$total_zooms = $stats[4];
$total_pans = $stats[5];
$total_interactions = $stats[6];
$average_tiles_per_session = $stats[7];
$average_session_time = $stats[8];
$average_interaction_time = $stats[9];
$bounce_rate = $stats[10];
$zooming_rate = $stats[11];
$panning_rate = $stats[12];
$interaction_rate = $stats[13];

?>
<head>
<style>
body {
    background-color: #fff;
    margin: 0;
    padding: 0;
    font-family: "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
        
}
div {
    width: 600px;
    margin: 5em auto;
    padding: 50px;
    background-color: #f0f0f2;
    border-radius: 1em;
}
table {
    font-family: arial, sans-serif;
    border-collapse: collapse;
    width: 65%;
}
th {
    border: 1px solid #dddddd;
    text-align: center;
    padding: 10px;
}
td {
    border: 1px solid #dddddd;
    text-align: center;
    padding: 8px;
}
tr:nth-child(odd) {
    background-color: #fff;
}
tr:nth-child(even) {
    background-color: #ddd;
}
</style>
</head>
<body>
<div>
    <center><h1>GMA Simple Statistics</h1></center>

    <br>

    <center>
    <table style="width: 45%;">
        <tr>
            <th>Parameter</th><th>Value</th>
        </tr>
        <tr>
            <td>Test key considered</td><td><?php echo $key; ?></td>
        </tr>
    </table>
    </center>

    <br>

    <center>
    <table>
        <tr>
            <th>Stat</th><th>Value</th>
        </tr>
        <tr>
            <td>Current tile  count</td><td><?php echo $total_tiles_per_key; ?></td>
        </tr>
        <tr>
            <td>Current visits count</td><td><?php echo $number_of_sessions; ?></td>
        </tr>
        <tr>
            <td>Current average tiles per visit</td><td><?php echo $average_tiles_per_session; ?></td>
        </tr>
        <tr>
            <td>Current total visit time (in seconds)</td><td><?php echo $total_visit_time. 's'; ?></td>
        </tr>
        <tr>
            <td>Current total visit time (in minutes)</td><td><?php echo floor($total_visit_time / 60) . 'm' . $total_visit_time % 60 . 's'; ?></td>
        </tr>
        <tr>
            <td>Current average visit time (in seconds)</td><td><?php echo $average_session_time. 's'; ?></td>
        </tr>
        <tr>
            <td>Current average visit time (in minutes)</td><td><?php echo floor($average_session_time / 60) . 'm' . $average_session_time % 60 . 's'; ?></td>
        </tr>
        <tr>
            <td>Current total bounces</td><td><?php echo $bounced_sessions; ?></td>
        </tr>
        <tr>
            <td>Current bounce rate</td><td><?php echo round($bounce_rate) . '%'; ?></td>
        </tr>
        <tr>
            <td>Current total user interactions</td><td><?php echo $total_interactions; ?></td>
        </tr>
        <tr>
            <td>Current total user interaction rate</td><td><?php echo round($interaction_rate) . '%'; ?></td>
        </tr>
        <tr>
            <td>Current average user interaction per visit</td><td><?php echo $average_interaction_time; ?></td>
        </tr>
        <tr>
            <td>Current total zooms</td><td><?php echo $total_zooms; ?></td>
        </tr>
        <tr>
            <td>Current total pans</td><td><?php echo $total_pans; ?></td>
        </tr>
        <tr>
            <td>Current total zoom rate</td><td><?php echo round($zooming_rate) . '%'; ?></td>
        </tr>
        <tr>
            <td>Current total pan rate</td><td><?php echo round($panning_rate) . '%'; ?></td>
        </tr>
    </table>
    </center>
</div>
</body>