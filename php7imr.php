<?php


include('db.php');

$maxclient = 50; 			// MOST AMOUNT OF PLAYERS ALLOWED IN THE IMR
$maxplayersingame = 10; 	// MOST AMOUNT OF PLAYERS ALLOWED IN THE GAME
$maxgames =  15; 			// MOST AMOUNT OF GAMES ALLOWED IN THE IMR
$timeout   = time();
$timeout   -= 15;			// HOW MANY SECONDS BEFORE A PLAYER HAS TIMED OUT
$chatrem   = time();
$chatrem   -= 30;			// HOW MANY SECONDS BEFORE CHAT IS REMOVED FROM THE DATABASE

// GETTING THEIR IP
$playerip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

// DEBUG ERRORS WITH : $result = mysql_query("INSERT INTO debug (string) VALUES ('$_SERVER[QUERY_STRING]')",$db);

// DISECTING THE QUERY STRING FOR FUTURE USAGE
//echo $_SERVER["QUERY_STRING"];
$command = explode("%%", $_SERVER["QUERY_STRING"]);
//print_r($command);
///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// REFRESHING THE IMR                                                                                        //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

if ($command[0]=="=REFRESH") {
	// UPDATE AND PRINT USERLIST
	// STRIP - OFF OF THE USERID
    $refreshid = split("-",$command[1]);
		// CHECK IF THERE IS ANY NEED TO UPDATE THE USERLIST BY CHECKING THE TIMESMAMP OF THE LAST PERSON JOINED
		// AGAINST THE LAST TIMESTAMP OF YOU REFRESHING THE USERLIST
	//	print "USER 17 NEW\n";
      //     print "\n";
        //   	print "CoolBot2" . "\n";

								 
   $tquery = "select company_id from features_company_members where member_email = '$username'";
//echo "$tquery";
$tresult = $conn->query($tquery)or trigger_error($conn->error."[$tquery]");


          $querycts="SELECT * FROM userlist ORDER BY JoinedIMR DESC LIMIT 1";
		$resultcts = $conn->query($querycts)or trigger_error($conn->error."[$querycts]");
		$myrowcts = $resultcts->fetch_array();
     $querymts="SELECT * FROM userlist WHERE UserID=$refreshid[0] and PlayerListUpdate<$myrowcts[JoinedIMR]";
	$resultmts = $conn->query($querymts)or trigger_error($conn->error."[$querymts]");
 		if ($myrowmts = $resultmts->fetch_array()) {
 		// CHECKS IF ANY OF THE CLIENT SLOTS ARE TAKEN
    	for ( $clientcount=1; $clientcount <= $maxclient; $clientcount++) {
    	    $result = $conn->query("SELECT * FROM userlist WHERE UserID=$clientcount and TimeStamp >$timeout") or trigger_error($conn->error."dsfsfsdfsd");
    	
	

		if ($myrow = $result->fetch_array()) {
    		    // TELLS THE IMR TO ADD THIS CLIENT TO THE PLAYER LIST
            	print "USER $myrow[UserID] NEW\n";
            	print "\n";
            	print urldecode($myrow["UserName"]) . "\n";
    		} else {
    		    // TELLS THE IMR THERE IS NOBODY IN THIS CLIENT SLOT
    		    print "USER $clientcount DEL\n";
    		    // CHECK IF THEY TIMED OUT AND REMOVE THEM FROM THE DATABASE
    		    $resulttimeout = $conn->query("SELECT * FROM userlist WHERE UserID=$clientcount and TimeStamp<$timeout");
				
				
    			if ($myrowtimeout = $resulttimeout->fetch_array()) {
    			    $result = $conn->query("INSERT INTO chat (UserID,UserName,Message,TimeStamp) VALUES ('0','','$myrowtimeout[UserName] has left the IMR!',". time() .")");
				if ($myrowtimeout[UserName] =="CoolBot"){}else{	
    		    	$result = $conn->query("DELETE FROM userlist WHERE UserID='$clientcount' LIMIT 1");
    			}}

    		}
    	}
    	// UPDATES THE TIMESTAMP OF WHEN YOU LAST UPDATED THE USERLIST TO THE SAME
		// AS THE LAST PERSON TO JOIN THE ROOM
    	$result = $conn->query("UPDATE userlist SET PlayerListUpdate=$myrowcts[JoinedIMR] WHERE UserID=$refreshid[0]");
 		}
 		// UPDATE THE PLAYERS TIMESTAMP SHOWING HE IS ACTIVE IN THE IMR
    	$result = $conn->query("UPDATE userlist SET TimeStamp=" . time() . " WHERE UserID=$refreshid[0]");

	






	// CHECKS WHEN THE CLIENT LAST UPDATED THE GAME LIST AGAINST WHEN THE LAST GAME WAS CREATED
        $resultcts = $conn->query("SELECT * FROM userlist ORDER BY StartedGame DESC LIMIT 1");
		$myrowcts = $resultcts->fetch_array();
    	$resultmts = $conn->query("SELECT * FROM userlist WHERE UserID=$refreshid[0] and GameListUpdate<$myrowcts[StartedGame]");
 		if ($myrowmts = $resultmts->fetch_array()) {



//ES LIST

    	for ( $gamescount=0; $gamescount <= $maxgames; $gamescount++) {
    	    $result = $conn->query("SELECT * FROM gamelist WHERE GameID=$gamescount");
    		if ($myrow = $result->fetch_array()) {
    		    // COUNTS THE PLAYERS IN THE GAME
    		    $resultpc = $conn->query("SELECT COUNT(*) AS PlayerCount FROM gameplayers WHERE GameID=$gamescount");
    		    $myrowpc = $resultpc->fetch_array();
    		    // TELLS THE IMR TO ADD THIS GAME TO THE GAME LIST
            	print "GAME $myrow[GameID] NEW $myrow[UserID]\n";
            	print urldecode($myrow["GameName"]) . "\n";
            	print urldecode($myrow["Track"]) . "\n";
            	print "$myrow[GameIP]\n";
            	print "$myrow[Port] $myrow[Laps] $myrow[Weapons] $myrowpc[PlayerCount]\n";
                $resultshowpl = $conn->query("SELECT * FROM gameplayers WHERE GameID=$gamescount ORDER BY JoinOrder");
            	if ($myrowshowpl = $resultshowpl->fetch_array()) {
            	    do {
            			print "$myrowshowpl[UserID] ";
            	    } while ($myrowshowpl = $resultshowpl->fetch_array());
            	}
            	print "\n" ;
    		} else {
    		    print "GAME $gamescount DEL\n";
    		}
    	}


 		// PRINT GAMES TO THE GAMES LIST
    	for ( $gamescount=1; $gamescount <= $maxgames; $gamescount++) {
    	    $result = $conn->query("SELECT * FROM gamelist WHERE GameID=$gamescount");
    		if ($myrow = $result->fetch_array()) {
    		    // COUNTS THE PLAYERS IN THE GAME
    		    $resultpc =$conn->query("SELECT COUNT(*) AS PlayerCount FROM gameplayers WHERE GameID=$gamescount");
    		    $myrowpc = $resultpc->fetch_array();
    		    // TELLS THE IMR TO ADD THIS GAME TO THE GAME LIST
            	print "GAME $myrow[GameID] NEW $myrow[UserID]\n";
            	print urldecode($myrow["GameName"]) . "\n";
            	print urldecode($myrow["Track"]) . "\n";
            	print "$myrow[GameIP]\n";
            	print "$myrow[Port] $myrow[Laps] $myrow[Weapons] $myrowpc[PlayerCount]\n";
            	// WRITES THE PLAYER'S ID'S INTO SOME FORM OF ARRAY FOR THE PLAYERLIST
                $resultshowpl = $conn->query("SELECT * FROM gameplayers WHERE GameID=$gamescount ORDER BY JoinOrder");
            	if ($myrowshowpl = $resultshowpl->fetch_array()) {
            	    do {
            			print "$myrowshowpl[UserID] ";
            	    } while ($myrowshowpl = $resultshowpl->fetch_array());
            	}
            	print "\n" ;
    		} else {
    		    print "GAME $gamescount DEL\n";
    		}
    	}
    	// UPDATES THE TIMESTAMP OF WHEN YOU LAST UPDATED THE GAMELIST TO THE SAME
		// AS THE LAST TIME A GAME WAS UPDATED
    	$result =  $conn->query("UPDATE userlist SET GameListUpdate=$myrowcts[StartedGame] WHERE UserID=$refreshid[0]");
 		}

	// CHECK IF THERE ARE ANY NEW MESSAGES TO DISPLAY
	$resultmts = $conn->query("SELECT * FROM userlist WHERE UserID=$refreshid[0]");
 	$myrowmts = $resultmts->fetch_array();
	$resultcts = $conn->query("SELECT * FROM chat ORDER BY TimeStamp DESC LIMIT 1");
	$myrowcts = $resultcts->fetch_array();
	// PRINT NEW MESSAGES
	if ($myrowcts["TimeStamp"]>$myrowmts["MessageTimeStamp"]) {
        $resultshowmess =  $conn->query("SELECT * FROM chat WHERE TimeStamp>$myrowmts[MessageTimeStamp] ORDER BY TimeStamp");
        if ($myrowshowmess = $resultshowmess->fetch_array()) {
        	do {
        	    // PRINTS OFF THE NEW MESSAGE AND WHO IT IS BY
				print "CHAT\n" . urldecode($myrowshowmess["UserName"]) . "» " . urldecode($myrowshowmess["Message"])."\n";
        	} while ($myrowshowmess =$resultshowmess->fetch_array());
        }
        $result = $conn->query("UPDATE userlist SET MessageTimeStamp=$myrowcts[TimeStamp] WHERE UserID=$refreshid[0]");
	}
//J..see if there's a message waiting for the individual

##search the indidivual messages table 

$result = $conn->query("select sender,recipient,message from privatemessages where recipient = '$refreshid[0]'");
	while ($row = $result->fetch_array()) {
print "CHAT\n $row[2]\n";
}

//delete the messages to the indivildal from the messages db now that the have been read
$x=$conn->query("delete from privatemessages where recipient = '$refreshid[0]'");


 	// AFTER WHATEVER THE CHAT REMOVAL IS SET TO IN SECONDS, THE CHAT MESSAGES ARE DELETED FROM THE DATABASE
 	// TO SAVE IT BECOMING HUGE
  	$result = $conn->query("DELETE FROM chat WHERE TimeStamp<$chatrem");

  	print "SUCCESS\n";
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ADDING THE USER 																							 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

if ($command[0]=="=ADD_USER") {
$agent =$_SERVER['HTTP_USER_AGENT'];

if  ($agent)
{
//echo "ag $agent";
//exit;
}

//mysql_close();
$ip =$_SERVER['REMOTE_ADDR'];
#see if they are already in the players database

#set the data and put it into variables
#$query  = "SELECT name, subject, message FROM contact";

#                   0          1           2         3   4      5     6     7    8       9
$query = "SELECT playerIP FROM hoverplayers WHERE playerName='$command[5]'";
$result = mysql_query($query);
$messageCount= 0;
$num_rows = mysql_num_rows($result);
while($row = mysql_fetch_row($result))
{
$playerID= $row[0];
$playerName = $row[1];
$playerAgent= $row[2];
$playerIP=  $row[2];

}


#if the player has been regged before from this ip do nothing
if ($num_rows < 1){



$agent =$_SERVER['HTTP_USER_AGENT'];

if ($ip=="190.92.44.40")
{
$reg="\[0-0\]";

}

else
{
//$reg="(nonreg.)";
}
// Insert a row of information into the table
mysql_query("INSERT INTO hoverplayers
(playerName,playerAgent,playerIP) VALUES('$command[5]$reg' , '$agent', '$ip') ") 
or die(mysql_error()); 



}



    // CHECK FOR OLD CLIENTS AND REMOVE THEM, ALSO STOPS IMR FILLING
   	$result = mysql_query("DELETE FROM userlist WHERE TimeStamp<$timeout",$db);
   	// CHECK FOR SAME NAME AND ADD A NUMBER ONTO THE END IF THERE IS A SAME NAME IN THE IMR
   	$result = mysql_query("SELECT * FROM userlist WHERE UserName='$command[5]'",$db);
   	if ($myrow = mysql_fetch_array($result)) {
  		do {
  		    $count++;
  		    $alteredcommand5 = $command[5] . $count;
            $resultcheck = mysql_query("SELECT * FROM userlist WHERE UserName='$alteredcommand5'",$db);
            if (($myrowcheck = mysql_fetch_array($resultcheck))==FALSE) {
                $UniqueName=TRUE;
            }
		} while ($UniqueName==FALSE);
		$command[5]=$alteredcommand5;
   	}
	// CHECK HOW MANY PEOPLE ARE IN THE IMR AND ASSIGN A UNIQUE USERID
	do {
	$UserID++;
	$result = mysql_query("SELECT * FROM userlist WHERE UserID=$UserID",$db);
    if ($myrow = mysql_fetch_array($result)==FALSE) {
        $OriginalID=TRUE;
    }
	} while ($OriginalID==FALSE) ;
	// GIVE AN ERROR IF THERE ARE ALREADY THE MAXIMUM AMOUNT OF CLIENTS IN THE ROOM
	if ($UserID > $maxclient) {
		print "ERROR 102\n" ;
		exit;
	}
	// TELL THE CLIENT THAT JOINING WAS A SUCCESS
	print "SUCCESS\n";
	print "USER_ID $UserID\n";
	// RETRIEVE THE TIMESTAMP OF THE LAST MESSAGE ON THE IMR, SO THAT THE REFRESH COMMAND PICKS UP NEW MESSAGES
	$resultmts = mysql_query("SELECT * FROM chat ORDER BY TimeStamp DESC",$db);
    if ($myrowmts = mysql_fetch_array($resultmts)) {
        $MessageTimeStamp=$myrowmts["TimeStamp"];
    } else {
        // IF THE CHAT DATABASE IS EMPTY, IT GIVES THE MESSAGE TIMESTAMP A "1" VALUE
        $MessageTimeStamp=1;
    }
    // TELL THE IMR A NEW PLAYER HAS JOINED
	
	$num = Rand (1,6); 

	//Based on the random number, gives a quote  
	switch ($num) {
	case 1: $ran="Whats up?"; break; 
	case 2: $ran="Hows it hangin??"; break; 
		case 3: $ran="Ready to race?"; break; 
			case 4: $ran="Time to get some Hover on"; break; 
				case 5: $ran="Are you ready ro rummmmmmble?"; break; 
					case 6: $ran="Whats the craic?"; break; 
	}
   // $result = mysql_query("INSERT INTO chat (UserID,UserName,Message,TimeStamp) VALUES ('0','CoolBot','Hey $command[5] ($playerip) $ran',". time() .") ",$db);
	// INSERT USER INTO DATABASE

	
	
	if ($ip=="190.92.44.40")
{
$reg="\[0-0\]";

}

else
{
//$reg="(nonreg.)";
}

	$result = mysql_query("INSERT INTO userlist (UserName,UserID,RegKey,Version,Key2,Key3,TimeStamp,MessageTimeStamp,JoinedIMR,PlayerListUpdate,GameListUpdate,StartedGame) VALUES ('$command[5]$reg','$UserID','$command[1]','$command[2]','$command[3]','$command[4]','". time() ."',$MessageTimeStamp,'". time() ."','". time() ."','1','1')",$db);
	
	// DOES AN INITIAL PLAYER AND GAME LIST REFRESH TO UPDATE THE USERLIST
	
	// CHECKS IF ANY OF THE CLIENT SLOTS ARE TAKEN
    	for ( $clientcount=1; $clientcount <= $maxclient; $clientcount++) {
		
    	    $result = mysql_query("SELECT * FROM userlist WHERE UserID=$clientcount and TimeStamp>$timeout",$db);
    		if ($myrow = mysql_fetch_array($result)) {
    		    // TELLS THE IMR TO ADD THIS CLIENT TO THE PLAYER LIST
            	print "USER $myrow[UserID] NEW\n";
            	print "\n";
            	print urldecode($myrow["UserName"]) . "\n";
    		} else {
    		    // TELLS THE IMR THERE IS NOBODY IN THIS CLIENT SLOT
    		    print "USER $clientcount DEL\n";
    		}
    	}
    	
    // PRINT GAMES TO THE GAMES LIST

    	for ( $gamescount=1; $gamescount <= $maxgames; $gamescount++) {
    	    $result = mysql_query("SELECT * FROM gamelist WHERE GameID=$gamescount",$db);
    		if ($myrow = mysql_fetch_array($result)) {
    		    // COUNTS THE PLAYERS IN THE GAME
    		    $resultpc = mysql_query("SELECT COUNT(*) AS PlayerCount FROM gameplayers WHERE GameID=$gamescount",$db);
    		    $myrowpc = mysql_fetch_array($resultpc);
    		    // TELLS THE IMR TO ADD THIS GAME TO THE GAME LIST
            	print "GAME $myrow[GameID] NEW $myrow[UserID]\n";
            	print urldecode($myrow["GameName"]) . "\n";
            	print urldecode($myrow["Track"]) . "\n";
            	print "$myrow[GameIP]\n";
            	print "$myrow[Port] $myrow[Laps] $myrow[Weapons] $myrowpc[PlayerCount]\n";
                $resultshowpl = mysql_query("SELECT * FROM gameplayers WHERE GameID=$gamescount ORDER BY JoinOrder",$db);
            	if ($myrowshowpl = mysql_fetch_array($resultshowpl)) {
            	    do {
            			print "$myrowshowpl[UserID] ";
            	    } while ($myrowshowpl = mysql_fetch_array($resultshowpl));
            	}
            	print "\n" ;
    		} else {
    		    print "GAME $gamescount DEL\n";
    		}
    	}
	// ADDS TO THE SMALL INTRO MESSAGE
 # print "CHAT\nWelcome to the VIP Room\n";
     print "CHAT\n--\n";
  //    print "CHAT\nBig shout to Andrew, the award winning photographer!\n";
	  print "CHAT\n--\n";
if (num_regrows<1){
//print "CHAT\nYour ip address is not registered to any player. If you are a HoverNet member then please sign in to http://hoverrace.co.uk and add this address to your account.\n";
//print "CHAT\nIf you are not a HoverNet member yet then please sign up on the site to access your scores\n";	
}

$address = $_SERVER['REMOTE_ADDR'];//Here you can specify the address you want to check ports
$port = "80"; //Here you can specify the port you want to check from $address
$checkport = fsockopen($address, $port, $errnum, $errstr, 2); //The 2 is the time of ping in secs

//Here down you can put what to do when the port is closed
if(!$checkport){
    //print "CHAT\nWARNING: $address You might need to change some settings on your router/hub so that you can race othere people. Go to http://www.hoverrace.co.uk/?page=help for more information.\n"; //Only will echo that msg
}else{ 

//And here, what you want to do when the port is open
     //  echo "The port ".$port." from ".$address." seems to be open."; //The msg echoed if port is open
}

  print "CHAT\n". "--LATEST LAP TIMES--" ."\n";

$file = file( "/opt/lampp/htdocs/hover/scores.txt");
for ($i = count($file)-5; $i < count($file); $i++) {
 // echo $file[$i] . "";  

$j =$file[$i];


$j= trim(preg_replace('/\s+/', ' ', $j));
 
 if ($j){
 print "CHAT\n". $j."\n";
  }
}
 
 

}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// REMOVING THE USER FROM THE IMR 																			 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

if ($command[0]=="=DEL_USER") {
	// REMOVE THE CLIENT FROM THE IMR AS HE LEAVES
	$leaveid = split("-",$command[1]);
	$resultmts = mysql_query("SELECT * FROM userlist WHERE UserID=$leaveid[0]",$db);
	$myrowmts = mysql_fetch_array($resultmts);
	// TELL THE ROOM HE HAS LEFT
	$result = mysql_query("INSERT INTO chat (UserID,UserName,Message,TimeStamp) VALUES ('0','','$myrowmts[UserName] has left the IMR!',". time() .")",$db);
	// REMOVE HIM FROM THE USERLIST
  	$result = mysql_query("DELETE FROM userlist WHERE UserID='$leaveid[0]' LIMIT 1",$db);
  	// REMOVE HIM FROGAMEM ANY GAMES (BECAUSE WHEN YOU START A GAME, THEY ONLY LEAVE THE ROOM)
  	$result = mysql_query("DELETE FROM gamelist WHERE UserID='$leaveid[0]-0'",$db);
$ip =$_SERVER['REMOTE_ADDR'];
    $resultvfv = mysql_query("DELETE FROM gamelist WHERE GameIP='$ip'",$db);
    $resultb = mysql_query("DELETE FROM gameplayers WHERE UserID='$leaveid[0]-0'",$db);
    // FORCES A RELOAD ON THE USERLIST AND GAMELIST (BECAUSE WHEN YOU START A GAME, THEY ONLY LEAVE THE ROOM)
    $result = mysql_query("UPDATE userlist SET PlayerListUpdate=1, GameListUpdate=1",$db);
	print "SUCCESS\n";
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ADDING CHAT TO THE CHAT DATABASE 																		 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

if ($command[0]=="=ADD_CHAT") {
    // STRIP - OFF OF THE USERID AND UPDATE THE TIMESTAMP
    $addchatid = split("-",$command[1]);
    $result = mysql_query("SELECT * FROM userlist WHERE UserID=$addchatid[0]",$db);
    if ($myrow = mysql_fetch_array($result)) {
        $UserName = $myrow["UserName"];
    }
    

$msg=addslashes($command[2]);
$msg =str_replace("%2f", "/", $msg);
//@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','$msg')");
#see if there's any commands given	
	if ($msg=="/mur")
	{
		@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','mong')");
	}

if ($msg=="/news")
	{
		$news = require("/opt/lampp/htdocs/martsart/rss2/bbc.php");
//@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','mong')");
@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','$news')");
print "CHAT\n--\n";

	print "SUCCESS\n";
exit;	
}




if (strtolower($msg)=="hi")
	{
	
//@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','mong')");
@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','CoolBot» Well hello there, ". $UserName ."   ')");
print "CHAT\n--\n";

	print "SUCCESS\n";
exit;	
}


if (strtolower($msg)=="no")
	{
	   $result = mysql_query("INSERT INTO chat (UserID,UserName,Message,TimeStamp) VALUES ('$command[1]','$UserName','". addslashes($command[2])."',". time() .")",$db);


	
//@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','mong')");
@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','CoolBot» Ah go on go on go on   ')");
print "CHAT\n--\n";

	print "SUCCESS\n";
exit;	
}





if (strtolower($msg)=="What time is it?")
	{
	
//@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','mong')");
@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','CoolBot» Time you bought a watch, ". $UserName ."   ')");
print "CHAT\n--\n";

	print "SUCCESS\n";
exit;	
}



if ($msg=="/scores")
	{
$scores= showscores($conn);
mysql_select_db($databasename,$db);

@mysql_query("insert into privatemessages(recipient,message) values('$addchatid[0]','$scores')");
print "CHAT\n--\n";

	print "SUCCESS\n";
exit;	
}



// INSERT THE CHAT INTO THE DATABASE
    $result = mysql_query("INSERT INTO chat (UserID,UserName,Message,TimeStamp) VALUES ('$command[1]','$UserName','". addslashes($command[2])."',". time() .")",$db);


	
	
#write the chat to a txt file
$msg=addslashes($command[2]);
writeFile($UserName,$msg);	
	}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ADDING A GAME TO THE DATABASE 																			 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////





if ($command[0]=="=ADD_GAME") {
	// FIND A SUITABLE SLOT OUT OF THE AVAILABLE GAME SPACES AVAILABLE TO ADD THE GAME INTO
    do {
        $gamecount++;
        $result = mysql_query("SELECT * FROM gamelist WHERE GameID=$gamecount",$db);
    	if (($myrow = mysql_fetch_array($result))==FALSE) {
    	    // IF THERE IS NO GAME IN THE $GAMECOUNT SLOT, IT WILL PUT THE CLIENTS GAME HERE
    	    $resultgame = mysql_query("INSERT INTO gamelist (GameID,UserID,GameName,Track,Laps,Weapons,GameIP,Port) VALUES ('$gamecount','$command[1]','$command[2]','$command[3]','$command[4]','$command[5]','$playerip','$command[6]')",$db);
    	    $resultgameplayers = mysql_query("INSERT INTO gameplayers (GameID,UserID,JoinOrder) VALUES ('$gamecount','$command[1]','1')",$db);
    	    // THIS TELLS THE GAMELIST TO UPDATE ON REFRESH
    	    $result = mysql_query("UPDATE userlist SET StartedGame=".time()." WHERE UserID='$command[1]'",$db);
			print "SUCCESS\n";
			print "GAME_ID $gamecount-$command[1]\n";
			$gotgameslot=TRUE;
    	} elseif ($gamecount==$maxgames) {
    	    // THIS WARNS THAT THERE ARE ALREADY ALL GAME SLOTS FILLED
    	    print "ERROR 402\n" ;
    	    exit;
    	}
    } while ($gotgameslot==FALSE);
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// REMOVING A GAME FROM THE DATABASE 																		 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

if ($command[0]=="=DEL_GAME") {
	// DELETES THE GAME FROM THE GAMELIST
    $result = mysql_query("DELETE FROM gamelist WHERE GameID='$command[1]' and UserID='$command[2]' LIMIT 1",$db);
    $result = mysql_query("DELETE FROM gameplayers WHERE GameID='$command[1]'",$db);
    // TELLS THE CLIENT TO UPDATE HIS GAMELIST
    $result = mysql_query("UPDATE userlist SET StartedGame=".time()." WHERE UserID='$command[2]'",$db);
    print "SUCCESS\n";
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// JOINING A GAME 																							 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

if ($command[0]=="=JOIN_GAME") {
	// ASSIGN A PLAYER A SLOT IN THE GAME FROM 2-9 (JOINORDER IS SIMPLY FOR DISPLAY PURPOSES ON THE PLAYERLIST)
	for ($playersingame=2; $playersingame<$maxplayersingame; $playersingame++) {
	$resultmts = mysql_query("SELECT * FROM gameplayers WHERE JoinOrder=$playersingame",$db);
		if (($myrow = mysql_fetch_array($result))==FALSE) {
		    // INSERT INTO THE GAMEPLAYERS DATABASE (FOR DISPLAYING ON THE PLAYERLIST)
    		$resultgameplayers = mysql_query("INSERT INTO gameplayers (GameID,UserID,JoinOrder) VALUES ('$command[1]','$command[2]','$playersingame')",$db);
    		// CUT SHORT THE FOR LOOP
    		$playersingame=$maxplayersingame;
    		// TELLS THE CLIENT TO UPDATE HIS GAMELIST
    		$result = mysql_query("UPDATE userlist SET StartedGame=".time()." WHERE UserID='$command[2]'",$db);
		}
	}
	print "SUCCESS\n";
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// LEAVING A GAME 																							 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

if ($command[0]=="=LEAVE_GAME") {
	// REMOVE THEMSELVES FROM THE GAMEPLAYERS DATABASE(AND THE GAMES PLAYERLIST)
    $result = mysql_query("DELETE FROM gameplayers WHERE GameID='$command[1]' and UserID='$command[2]'",$db);
    // TELLS THE CLIENT TO UPDATE HIS GAMELIST
    $result = mysql_query("UPDATE userlist SET StartedGame=".time()." WHERE UserID='$command[2]'",$db);
    print "SUCCESS\n";
}
//print_r($_SERVER);
?>
<?php

function writeFile($UserName,$msg){
	$fn = "/opt/lampp/htdocs/hover/chat.txt";
	$maxlines = 9;
	$nick_length = 9;

	/* Set this to a minimum wait time between posts (in sec) */
	$waittime_sec = 0;	
	
	/* spam keywords */
	$spam[] = "nigger";
	$spam[] = "cum";
	$spam[] = "dick";
	$spam[] = "EAT coon";
$msg =str_replace("%20", " ", $msg);
$msg =str_replace("%3f", "?", $msg);
	/* IP's to block */
	$blockip[] = "72.60.167.89";

	/* spam, if message IS exactly that string */	
	$espam[] = "ajax";
	
	/* Get Message & Nick from the Request and Escape them */
	#$msg = $_REQUEST["m"];
	$msg = htmlspecialchars(stripslashes($msg));

	//$n = $_REQUEST["n"];
	$n = $UserName;
	$n = htmlspecialchars(stripslashes($n));

	if (strlen($n) >= $nick_length) { 
		$n = substr($n, 0, $nick_length); 
	} else { 
		for ($i=strlen($n); $i<$nick_length; $i++) $n .= "&nbsp;";
	}

	if ($waittime_sec > 0) {
		$lastvisit = $_COOKIE["lachatlv"];
		setcookie("lachatlv", time());
 
		if ($lastvisit != "") {
			$diff = time() - $lastvisit;
			if ($diff < 5) { die();	}
		} 
	}

	if ($msg != "")  {
		if (strlen($msg) < 2) { die(); }
		if (strlen($msg) > 3) { 
			/* Smilies are ok */
			if (strtoupper($msg) == $msg) { die(); }
		}
		if (strlen($msg) > 150) { die(); }
		if (strlen($msg) > 15) { 
			if (substr_count($msg, substr($msg, 6, 8)) > 1) { die(); }
		}

		foreach ($blockip as $a) {
			if ($_SERVER["REMOTE_ADDR"] == $a) { die(); }
		}
		
		$mystring = strtoupper($msg);
		foreach ($spam as $a) {	
			 if (strpos($mystring, strtoupper($a)) === false) {
			 	/* Everything Ok Here */
			 } else {
			 	die();
			 }
		}		

		foreach ($espam as $a) {
			if (strtoupper($msg) == strtoupper($a)) { die(); }		
		}
				
		$handle = fopen ($fn, 'r'); 
		$chattext = fread($handle, filesize($fn)); fclose($handle);
		
		$arr1 = explode("\n", $chattext);

		if (count($arr1) > $maxlines) {
			/* Pruning */
			$arr1 = array_reverse($arr1);
			for ($i=0; $i<$maxlines; $i++) { $arr2[$i] = $arr1[$i]; }
			$arr2 = array_reverse($arr2);			
		} else {
			$arr2 = $arr1;
		}
		
		$chattext = implode("\n", $arr2);

		if (substr_count($chattext, $msg) > 2) { die(); }
		 
		$out = $chattext . "<font size=\"-1\">".$n . "&raquo;&nbsp;" . $msg . "<br>\n";
		$out = str_replace("\'", "'", $out);
		$out = str_replace("\\\"", "\"", $out);
		
		$handle = fopen ($fn, 'w'); fwrite ($handle, $out); fclose($handle);				
	}



}


function showscores($conn){
$ip =$_SERVER['REMOTE_ADDR'];


	$result = mysql_query("SELECT trackname,craft,laptime,playerip,playername FROM hoverscoreslaps WHERE playerip='$ip' ORDER BY trackname AND laptime DESC");
$num_rows = mysql_num_rows($result);
if ($num_rows < 1){
echo "\nCHAT\nYour ip has no scores to display. You need to play HoverRace to get scores. \n";
return;
}


while($row = mysql_fetch_row($result))
{
    $trackname = $row[0];
$craft = $row[1];
$laptime = ($row[2]/1000);
$playerip = $row[3];
$playername = $row[4];

#get the craft
if ($craft=="0"){
$craft="Basic";
}

if ($craft=="3"){
$craft="E-On";
}



if ($laptime){
$scores= "$scores $trackname $laptime $craft\nCHAT";
}
}

mysql_close;
$scores="$scores\n";
return ($scores);
}

?>
