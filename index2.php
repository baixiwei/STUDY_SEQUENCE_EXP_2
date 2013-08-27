<!doctype html>
<?php

include('p101_database_connect.php');

session_start();

$sid = SID; //Session ID #
$authenticated = $_SESSION['CAS'];
$target_url = 'http://perceptsconcepts.psych.indiana.edu/experiments/mmm-tutorial-fall13/index2.php';

//send user to CAS login if not authenticated
if (!$authenticated) {
  $_SESSION['CAS'] = true;
  header('Location: https://cas.iu.edu/cas/login?cassvc=IU&casurl='.$target_url);
  exit;
}

if ($authenticated) {
  //validate since authenticated
  if (isset($_GET["casticket"])) {
	//set up validation URL to ask CAS if ticket is good
	$_url = 'https://cas.iu.edu/cas/validate';
	$cassvc = 'IU';  //search kb.indiana.edu for "cas application code" to determine code to use here in place of "appCode"
	$casurl = $target_url; //same base URLsent
	$params = "cassvc=$cassvc&casticket=$_GET[casticket]&casurl=$casurl";
	$urlNew = "$_url?$params";

	//CAS sending response on 2 lines.  First line contains "yes" or "no".  If "yes", second line contains username (otherwise, it is empty).
	$ch = curl_init();
	$timeout = 5; // set to zero for no timeout
	curl_setopt ($ch, CURLOPT_URL, $urlNew);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	ob_start();
	curl_exec($ch);
	curl_close($ch);
	$cas_answer = ob_get_contents();
	ob_end_clean();
	
	//split CAS answer into access and user
	list($access,$user) = split("\n",$cas_answer,2);
	$access = trim($access);
	$user = trim($user);
		
	//set user and session variable if CAS says YES
	if ($access == "yes") {
        $_SESSION['user'] = $user;
		
		// $user is the IU username
	}
  }
  else
  {
     $_SESSION['CAS'] = true;
     header('Location: https://cas.iu.edu/cas/login?cassvc=IU&casurl='.$target_url);
     exit;
  }
}

// username should be inside the session variable at this point
if(isset($_SESSION['user'])){

	$studentid = checkSID($_SESSION['user']);
	
	if($studentid == -1)
	{
		echo 'Adding to DB';
		// if no id, add them to database.
		
		// get condition assignment
		$conds = array('SELF_REGULATED', 'BLOCKED', 'RANDOM', 'INTERLEAVED');
		$condition = array_rand($conds);

		$insert = mysql_query('INSERT INTO allusers (username, cond) VALUES (\''.$_SESSION['user'].'\','.$condition.')');
		if($insert) {
			$studentid = checkSID($_SESSION['user']);
			
			// add to progress table
			$insert = mysql_query('INSERT INTO subjectprogress (sid) VALUES ('.$studentid.')');
		} else {
			echo mysql_error();
		}
	} elseif($studentid == -2) {
		// there was a problem with the mysql database
		//echo 'db problem';
	} else {
		// already had subject in database.
		//echo 'Student ID: '.$studentid;	
		$condition = getCondition($_SESSION['user']);
	}
	
	$_SESSION['studentid'] = $studentid;
	$_SESSION['condition'] = $condition;
}

function checkSID($username) {
	$result = mysql_query('SELECT sid FROM allusers WHERE username=\''.mysql_real_escape_string($username).'\'');
	$studentid = -2;
	
	if($result) {
		$arr = mysql_fetch_array($result);
		if($arr){
			// if there is an id, store it as $studentid
			$studentid = $arr['sid'];
		} else {
			$studentid = -1;
		}
	} 
	
	return $studentid;
}
	
function getCondition($username) {
	$result = mysql_query('SELECT cond FROM allusers WHERE username=\''.mysql_real_escape_string($username).'\'');
	$c = 'NULL';
	if($result) {
		$arr = mysql_fetch_array($result);
		if($arr) { $c = $arr['cond']; }
	}
	return $c;
}
	
?>
<html>
<head>
<title>Mean, Median, and Mode Tutorial</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
<script src="startExperiment.js" type="text/javascript"></script>
<script src="jquery-csv.js"></script>
<link rel="stylesheet" type="text/css" href="styles.css" />
<link rel="icon" type="image/x-icon" href="favicon.ico" />
</head>
<body>
<div id="wrapper">
	<div id="welcome">
		
	</div>
</div>
</body>
<script type="text/javascript">
var sid = <?php echo $_SESSION['studentid']; ?>;
var condition = "<?php echo $_SESSION['condition']; ?>";

/*
// check if they already have seen consent form
$.ajax({
	type: 'post',
	cache: false,
	url: 'check_consent.php',
	data: {"subjid": sid},
	success: function(data) { 
		if(data==1)
		{
			// they have seen consent form
			start();
		} else {
			// show consent form
			show_consent_form();
		}
	}
});



function show_consent_form() {
	$("#welcome").html(
		'<h1>Welcome to the tutorial on mean, median, and mode.</h1>\
		<p>Before starting, you need to decide whether or not you give your consent to have your data analyzed for research purposes.\
		You will need to complete the tutorial in order to receive credit for the homework assignment, but you may choose whether \
		or not your responses are analyzed for research purposes.</p> \
		<button id="startbtn" type="button">View Consent Form</button>'
	);

	// consent
	$("#startbtn").click(function(){

		$("#welcome").hide();
		
		// TODO: see if they have already seen the consent form, and skip if they have.
		$("#wrapper").load("consent_form.html" + "?time=" + (new Date().getTime()), function(){
			// what to do after loading
			$("#wrapper").append('<button type="button" id="consentBtn">Start Tutorial</button>');
			$("#consentBtn").click(function(){
				// check to see if they gave consent
				var consent = $("#consent_checkbox").is(':checked');
				var data = [[{"sid": sid, "consent_given": consent}]];
				
				// write their choice to the database
				$.ajax({
					type: 'post',
					cache: false,
					url: 'submit_data_mysql.php',
					data: {"table": "consent", "json": JSON.stringify(data)},
					success: function(data) { start(); }
				});
				
				// update subject progress in database
				$.ajax({
					type: 'post',
					cache: false,
					url: 'update_progress.php',
					data: {"sid": sid , "flag": "consent"}
				});
			});
		});
		
	});
}*/

// starting experiment

// Add subject id and condition into prepend_data
var prepend_data = { "subjid": sid, "condition": condition }

// registerYokeId: store the yoked id for a subject in the database
function registerYokeId(subject, yoke){
	$.ajax({
		type: 'post',
		cache: false,
		url: 'update_yokeid.php',
		data: {"subjid": subject, "yokeid":yoke },
		success: function(data)
		{
			// do nothing?
		},
		error: function()
		{
			alert( "There was an error connecting to the database. Error code Y2.");
		}
	});	
}

// yokeThenStart: retrieves information about yoked (previous) participant,
// which is used to determine which options are available to current participant
// this is a temporary version which Josh will hopefully replace with a better version
// the version should ensure that participants who were already yoked would be assigned the same yoked sid on reload
// and have something to make sure it fails gracefully
function yokeThenStart( condition, callback ) {
	$.ajax({type: 'post', cache: false, url: 'get_yokeid.php', data: {"subjid": sid},
		success: function(data1)
		{
			 $.ajax({ type: "GET", cache: false, url: "yoked_data.csv", dataType: "text",
             success: function(data2) {
                var rows            = $.csv.toObjects(data2);
                var yoked_sids      = [ 9, 15, 19, 43, 53, 54, 56, 60, 68, 85, 120, 134, 148, 157, 168, 176, 177, 179, 181, 188, 191, 200, 216, 236, 237, 240, 241, 257, 267, 279, 284, 288, 290, 300, 309, 314, 315, 318, 327, 348, 353, 382, 390, 392, 396, 414, 420, 427, 443, 448, 452, 456, 458, 463, 470, 473, 478, 501, 503, 508, 510, 513, 516, 525, 528, 540, 553, 585, 586, 587, 597, 598, 604, 608, 613, 631, 638, 655, 662, 671, 698, 706, 724, 733, 741, 744, 758, 761, 774, 776, 781, 791, 802, 810, 826 ];
                var yoked_sid       = yoked_sids[ Math.floor( Math.random()*(yoked_sids.length) ) ];
				if(data1!=""){
					yoked_sid = parseInt(data1);
				} else {
					// log yoked id in database
					registerYokeId(sid, yoked_sid)
				}
                var category_seq    = [];
                var data_seq        = [];
                var complete_targs  = [0,0,0];
                var tot_targ        = 0;
                for ( var i=0; i<rows.length; i++ ) {
                    if ( rows[i].sid==yoked_sid ) {
                        category_seq.push( rows[i].nextCategory );
                        data_seq.push( rows[i].nextData );
                        complete_targs[ {"Mean":0,"Median":1,"Mode":2}[rows[i].category] ] += 1;
                        tot_targ += 1;
                    }
                }
				
				//
				callback( { "yoked_sid": yoked_sid, "category_seq": category_seq, "data_seq": data_seq, "complete_targs": complete_targs, "tot_targ": tot_targ } );
                },
             error: function(data) {
                // warning! danger of infinite loop if you uncomment the line below
                // getYokingInfo( yoked_sid );
                console.log( "An error occurred." );
                }
           }) ;
		},
		error: function()
		{
			alert( "There was an error connecting to the database. Error code Y1.");
		}
	});	



   
}

if ( condition == SELF_REGULATED ) {
    // if in the self-regulated condition, start the experiment
    startExperiment( external_content, display_loc, prepend_data, condition );
} else {
    // if in any of the other conditions, yoke to a previous participant and then start the experiment
    yokeThenStart( condition, function( yoking_info ) {
        prepend_data["yoked_sid"] = yoking_info["yoked_sid"];
        startExperiment( external_content, display_loc, prepend_data, condition, yoking_info );
    } );
}
</script>
</html>
