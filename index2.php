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
	$yoked_id = -1;
	
	if($studentid == -1)
	{
		// echo 'Adding to DB';
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
		if($condition > 0) // if yoked...
		{
			$r = mysql_query('SELECT yokeid FROM allusers WHERE username=\''.mysql_real_escape_string($_SESSION['user']).'\'');
			if($r) {
				$arr = mysql_fetch_array($r);
				$y = $arr['yokeid'];
				if($y!="") { $yoked_id = $y; }
			}
		}
	}
	
	$_SESSION['studentid'] = $studentid;
	$_SESSION['condition'] = $condition;
	$_SESSION['yokeid'] = $yoked_id;
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
var condition = <?php echo $_SESSION['condition']; ?>;
var yokedId = <?php echo $_SESSION['yokeid']; ?>;


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
}

// starting experiment

// Add subject id and condition into prepend_data
var prepend_data = { "subjid": sid, "cond": condition }

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
	$.ajax({ type: "GET", cache: false, url: "yoked_data.csv", dataType: "text",
		success: function(data) {
			var rows            = $.csv.toObjects(data);
			var yoked_sids      = [ 9, 15, 19, 43, 53, 54, 56, 60, 68, 85, 120, 134, 148, 157, 168, 176, 177, 179, 181, 188, 191, 200, 216, 236, 237, 240, 241, 257, 267, 279, 284, 288, 290, 300, 309, 314, 315, 318, 327, 348, 353, 382, 390, 392, 396, 414, 420, 427, 443, 448, 452, 456, 458, 463, 470, 473, 478, 501, 503, 508, 510, 513, 516, 525, 528, 540, 553, 585, 586, 587, 597, 598, 604, 608, 613, 631, 638, 655, 662, 671, 698, 706, 724, 733, 741, 744, 758, 761, 774, 776, 781, 791, 802, 810, 826 ];
			var yoked_sid       = yoked_sids[ Math.floor( Math.random()*(yoked_sids.length) ) ];
			if(yokedId > -1){
				yoked_sid = yokedId;
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
			callback( { "yoked_sid": yoked_sid, "category_seq": category_seq, "data_seq": data_seq, "complete_targs": complete_targs, "tot_targ": tot_targ } );
		},
		error: function(data) {
			// warning! danger of infinite loop if you uncomment the line below
			// getYokingInfo( yoked_sid );
			console.log( "An error occurred. Error code CSV1" );
			alert( "An error occurred. Error code CSV1" );
		}
   }) ;   
}

function start(){
	$("#wrapper").html('<div id="target"></div>');
	var display_loc = $("#target");
	var external_content = {
        "pretest_questions": [
            { "number": 1,
              "text": "1.  Five pizzas were given quality scores by an expert taster.  Their scores were: Pizza World = 8, Slices! = 3, Pisa Pizza = 2, Pizza a go-go = 4, Crusty's = 8. What are the mode, median and mean for this data set?",
              "answers": [ "A) mode = 8, median = 5, mean = 4", "B) mode = 5, median = 8, mean = 4", "C) mode = 8, median = 4, mean = 5", "D) mode = 5, median = 4, mean = 8" ],
              "correct_response": 2 },
            { "number": 2,
              "text": "2.  Imagine a vocabulary test in which 15 students do very well, getting scores of 98, 99, and 100 out of 100 possible points.  However, the remaining 3 students get very poor scores: 5, 8, and 9.  Will the mode be less than or more than the mean?",
              "answers": [ "A) the mode will be less than the mean", "B) the mode will be more than the mean", "C) the mode and mean will be the same", "D) more information is needed about the particular scores" ],
              "correct_response": 1 },
            { "number": 3,
              "text": "3.  There are 7 players on a particular basketball team.  On a particular game, the median number of points scored by each player was 12 and no two players scored the same number of points.  If the lowest and highest scoring players are not considered, what will be the median of the remaining 5 players' scores?",
              "answers": [ "A) more information is needed about the particular scores", "B) 8", "C) 10", "D) 12" ],
              "correct_response": 3 },
            { "number": 4,
              "text": "4.  Three children in a family have shoe sizes of 5, 10, and 9.  What are mean and median for the shoes sizes in this family?",
              "answers": [ "A) mean = 9, median = 10", "B) mean = 9, median = 9", "C) mean = 8, median = 10", "D) mean = 8, median = 9" ],
              "correct_response": 3 }
        ],
        // number of questions should be the same for each category
        "training_questions": [ 
            {prbID: 1, text: "The scores of several students on a 50-point pop quiz are shown below.", ques: "students' test scores", min: 10, max: 50},
			{prbID: 2, text: "The data below shows the numbers of stories of several buildings in a neighborhood.", ques: "number of stories", min: 1, max:50},
			{prbID: 3, text: "In a marketing research study, several consumers each rated how much they liked a product on a scale of 1 to 100. Their ratings are shown below.", ques: "consumers' ratings", min: 1, max: 100},
			{prbID: 4, text: "Several fishermen went fishing on the same day. Below you can find how many fish the different fishermen caught.", ques: "number of fish caught", min: 0, max: 30},
			{prbID: 5, text: "The ages of a group of friends are shown below.", ques: "age in this group", min: 19, max: 35},
			{prbID: 6, text: "The grades of a group of students in a Psych course are shown below.", ques: "grade in the Psych course", min:50, max:90},
			{prbID: 7, text: "Below are the number of books a student read each month in the past few months.", ques: "number of books read", min:0, max:20},
			{prbID: 8, text: "The weight, in pounds, of people in a restaurant is shown below.", ques: "weight in this group of people", min:80, max: 170},
			{prbID: 9, text: "The price, in dollars, of the items in Mary's shopping cart is shown below.", ques: "price of the products in this purchase", min:2, max:30},
			{prbID: 10, text: "The time each student spent doing an online exercise for a Neuroanatomy course is shown below, in minutes.", ques: "time spent doing the exercise", min:15, max:50},
			{prbID: 11, text: "The list below shows the monthly earnings of the employees of a video store, in dollars.", ques: "employee's earnings", min:1000, max:2000},
			{prbID: 12, text: "The number of students served in a college cafeteria in the past few months is shown below.", ques:"number of students served", min: 90, max:200},
			{prbID: 13, text: "The number of students attending a workshop on \"Research Ethics\" each time it was offered is shown below.", ques:"number of attendees", min:10, max:30},
			{prbID: 14, text: "Zach's scores in the quizzes of a science course are shown below.", ques:"scores", min: 10, max:80},
			{prbID: 15, text: "The scores below show the total fat content in some products.", ques:"fat content", min:4, max:40},
			{prbID: 16, text: "A middle school teacher takes students' attendance at 9 am every day. The number of students in the classroom at that time in the last few classes is shown below.", ques: "number of students present at 9am", min:15, max:30},
			{prbID: 17, text: "The data below shows the total caloric content of several dishes.", ques: "caloric content", min:80, max:400},
			{prbID: 18, text: "The total points scored by several high school basketball players are shown below.", ques:"points scored", min:40, max:60},
			{prbID: 19, text: "Below is the number of hours each student in a small college course spends watching TV each week.", ques:"hours spent watching TV for students in this class", min:1, max:20},
			{prbID: 20, text: "Anna practices swimming everyday. Below are the durations of her last practice sessions, in minutes.", ques:"time practicing", min:30, max:90},
			{prbID: 21, text: "The number of exercises completed by each student preparing for an algebra exam is shown below.", ques:"number of exercises completed", min:2, max:32},
			{prbID: 22, text: "The weight for each of several cereal brands sold in a store is shown below, in grams.", ques: "weight of cereal boxes", min:200, max:650},
			{prbID: 23, text: "Below are the mean temperatures in a series of days in a city.", ques:"temperature in the city", min:30, max:70},
			{prbID: 24, text: "The price of a commodity in 10 different cities is given below.", ques: "price of the commodity", min:100, max:200},
			{prbID: 25, text: "The height of the players of a team is shown below, in inches.", ques:"height of the players", min:70, max:90},
			{prbID: 26, text: "The total number of bikes sold in the past few days in a shop is shown below.", ques:"number of bikes sold", min:0, max:20},
			{prbID: 27, text: "The sizes of the bicycles owned by the students in a class are listed below, in inches.", ques: "size of the bicycles", min:20, max:40},
			{prbID: 28, text: "The points scored in each game by a middle school's football team are shown below.", ques: "scores", min:8, max:30},
			{prbID: 29, text: "The number of DVDs rented at a local store in the past few days are shown below.", ques: "movies rented during this period", min:1, max:30},
			{prbID: 30, text: "The maximum length, in feet, of several whales is listed below.", ques: "whale length", min:30, max:70},
			{prbID: 31, text: "The number of visitors to a local museum each month in the past few months is shown below.", ques: "number of visitors", min:1, max:40},
			{prbID: 32, text: "A group of friends went bowling. Below are their scores.", ques:"scores", min:10, max:300}
		]
    };


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
}
</script>
</html>
