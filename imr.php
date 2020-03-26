<?php
header('Content-Type: text/plain');
//PHP IMR v3 
// Based on Austins PHP IMR v2
//Ported by Skeletor to PHP7.x with mysqli for database queries and IMR class instead of globals for setting-/getting the settings
//Todo, move the functions into the class, minor bug fixes, various cleaning of the code
// DEBUG ERRORS WITH : $conn->query("INSERT INTO `debug` (`string`) VALUES ('$_SERVER[QUERY_STRING]')");

// MYSQL CONFIG FILE : dbconnect.php
include "db3.php";
// CONNECT TO THE MYSQL

$imr_settings= new IMR();

// DECLARATIONS
$imr_settings->MAX_PLAYERS 		= 50; 							// MOST AMOUNT OF PLAYERS ALLOWED IN THE IMR
$imr_settings->MAX_PER_GAME 		= 10; 							// MOST AMOUNT OF PLAYERS ALLOWED IN THE GAME
$imr_settings->MAX_GAMES 			= 15; 							// MOST AMOUNT OF GAMES ALLOWED IN THE IMR
$imr_settings->TIMEOUT   			= time() - 15;   				// HOW MANY SECONDS BEFORE A PLAYER HAS TIMED OUT
$imr_settings->OLD_USER_REMOVAL 	= time() - 60*60*24;			// HOW MANY SECONDS TO REMOVE OLD USERS FROM THE OLDUSERLIST TABLE (60*60*24=A DAY)
$imr_settings->URL 				= "http://www.hoverrace.org";	// WEBSITE ADDRESS

// DISECTING THE QUERY STRING FOR FUTURE USAGE
$CMD = explode("%%", $_SERVER["QUERY_STRING"]);

// IF NO QUERY STRING, THEN STOP WITH ERROR 201: Not on-line
if(!$_SERVER["QUERY_STRING"]) ErrorCode(201);

// STRIP THE =
$CMD[0] = substr($CMD[0], 1, strlen($CMD[0])-1);

// IF THE CLIENT IS NOT HOVERRACE AND THEY ARE NOT ACCESSING THE USERLIST, TURN THEM AWAY
//if(GetClient() != "HoverRace" AND $CMD[0] != "WWW_ULIST") exit;

// PERFORM THE FUNCTION
$CMD[0]($conn, $CMD,$imr_settings);

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// REFRESHING THE IMR                                                                                        //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function REFRESH($conn, $CMD,$imr_settings) {
	$old_user_removal=$imr_settings->OLD_USER_REMOVAL;
$timeout=$imr_settings->TIMEOUT;
	// UPDATE AND PRINT USERLIST
	// STRIP - OFF OF THE USERID
    $refreshid = explode("-",$CMD[1]);

    // CHECK IF THEY STILL EXIST ON THE USERLIST OR KICK THEM OUT OF THE ROOM
    $result = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$refreshid[0]'");
	if ($myrow = $result->fetch_array())
	{
	    $PLU = $myrow["PlayerListUpdate"];
	    $GLU = $myrow["GameListUpdate"];
	    $MTS = $myrow["MessageTimeStamp"];
	} else ErrorCode(201);

	// CHECK IF THEY TIMED OUT AND REMOVE THEM FROM THE DATABASE
	$result = $conn->query("SELECT * FROM `userlist` WHERE `TimeStamp` < '$timeout'");
	if ($myrow = $result->fetch_array()) {
		do
		{
			ChatMessage($conn,$imr_settings,0,ChatDate(),"$myrow[UserName] has timed out!");
			$conn->query("DELETE FROM `userlist` WHERE `UserID` = '$myrow[UserID]' LIMIT 1");
			$conn->query("DELETE FROM `gameplayers` WHERE `UserID` = '$myrow[UserID]' LIMIT 1");
			$conn->query("DELETE FROM `gamelist` WHERE `UserID` = '$myrow[UserID]' LIMIT 1");
			// REMOVE THEM FROM HOSTING A GAME
			$conn->query("DELETE FROM `gameleave` WHERE `HostName` = '$myrow[UserName]'");
		} while ($myrow = $result->fetch_array());
		// FORCES A RELOAD ON THE USERLIST AND GAMELIST
		$conn->query("UPDATE `userlist` SET `PlayerListUpdate` = '0', `GameListUpdate` = '0'");
		// FORCE THE PLAYER TO LOAD THEM NOW (OTHERWISE HIS PLU AND GLU WOULD BE RETURNED TO 1 WITHOUT RELOADING THE LISTS!)
		$PLU = 0;
		$GLU = 0;
	}

	// REMOVE USERS IN THE OLDUSERLIST TABLE
	$conn->query("DELETE FROM `olduserlist` WHERE `TimeStamp` < '$old_user_removal'");

	// UPDATE THE PLAYERS LIST IF NECESSARY
	if ($PLU == 0) PlayerListUpdate($conn,$imr_settings);

	// UPDATES THE GAME LIST IF NECESSARY, GameListUpdate will return TRUE if the players game has been checked for connectivity
	// to delay the update on the game list
	if ($GLU == 0) if (!GameListUpdate($refreshid[0],$conn,$imr_settings)) $GLU = 1;
	
	// CHECK IF THERE ARE ANY NEW MESSAGES TO DISPLAY
	$result = $conn->query("SELECT * FROM `chat` WHERE `MessageID` > '$MTS' ORDER BY `MessageID`");
	// PRINT NEW MESSAGES
	if ($myrow = $result->fetch_array()) {
        do {
            $show = TRUE;
            
        	// PRIVATE MESSAGE FILTERS
        	if ($myrow["ToUser"] AND $refreshid[0]==$myrow["ToUser"]) $priv = " <private>";
        	elseif ($myrow["ToUser"] AND $refreshid[0] != $myrow["ToUser"]) $show = FALSE;
        	
        	if ($show)
        	{
        	    // PRINTS OFF THE NEW MESSAGE AND WHO IT IS BY
             	PrintIMR("CHAT\n" . urldecode($myrow["UserName"]) . "$priv" . GetChatCode() . " ");
             	$message = $myrow["Message"];
				$message = str_replace("+","%2B",$message);
				//$message = str_replace("\'","'",$message);
				PrintIMR(urldecode($message). "\n");
        	}
        	
        	$mid = $myrow["MessageID"];
        } while ($myrow = $result->fetch_array());
        $midq = ", `MessageTimeStamp` = '$mid'";
	}
	
	// UPDATE THE PLAYERS TIMESTAMP SHOWING HE IS ACTIVE IN THE IMR, ALSO SETS THEIR UPDATES TO 1, AND IF ANY NEW
	// MESSAGES WERE SENT, IT UPDATES THEIR MESSAGE TIMESTAMP TO REFLECT
	$conn->query("UPDATE `userlist` SET `TimeStamp` = '" . time() . "', `PlayerListUpdate` = '1', `GameListUpdate` = '$GLU'$midq WHERE `UserID` = '$refreshid[0]'");

 	// AFTER WHATEVER THE CHAT REMOVAL IS SET TO IN SECONDS, THE CHAT MESSAGES ARE DELETED FROM THE DATABASE
 	// TO SAVE IT BECOMING HUGE
  	// $conn->query("DELETE FROM `chat` WHERE `TimeStamp` < '$chatrem' ORDER BY 'TimeStamp' DESC OFFSET 10");

  	PrintIMR("SUCCESS\n");
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ADDING THE USER 																							 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function ADD_USER($conn, $CMD,$imr_settings) {

	$PlayerIP = GetPlayerIP();
	$timeout=$imr_settings->TIMEOUT;
	 $max_players=$imr_settings->MAX_PLAYERS;
    // CHECK FOR OLD CLIENTS AND REMOVE THEM, ALSO STOPS IMR FILLING
   	$conn->query("DELETE FROM `userlist` WHERE `TimeStamp` < '$timeout'");
   	// CHECK FOR SAME NAME AND ADD A NUMBER ONTO THE END IF THERE IS A SAME NAME IN THE IMR
   	$result = $conn->query("SELECT * FROM `userlist` WHERE `UserName` = '$CMD[5]'");
   	if ($myrow = $result->fetch_array()) {
  		do {
  		    $count++;
  		    $alteredcommand5 = $CMD[5] . $count;
            $resultcheck = $conn->query("SELECT * FROM `userlist` WHERE `UserName` = '$alteredcommand5'");
            if (($myrowcheck =$resultcheck->fetch_array())==FALSE) {
                $UniqueName=TRUE;
            }
		} while ($UniqueName==FALSE);
		$CMD[5]=$alteredcommand5;
   	}
   	
	// CHECK HOW MANY PEOPLE ARE IN THE IMR AND ASSIGN A UNIQUE USERID
	$result = $conn->query("SELECT * FROM `userlist` ORDER BY `UserID`");
    if ($myrow = $result->fetch_array()) {
        do {
        	if($myrow["UserID"] - $UserID > 1) break;
        	else $UserID = $myrow["UserID"];
        } while ($myrow = $result->fetch_array());
    }
    $UserID++;
    
	// GIVE AN ERROR IF THERE ARE ALREADY THE MAXIMUM AMOUNT OF CLIENTS IN THE ROOM
	if ($UserID > $max_players)	ErrorCode(102);

	// TELL THE CLIENT THAT JOINING WAS A SUCCESS
	PrintIMR("SUCCESS\n");
	PrintIMR("USER_ID $UserID\n");
	
	// RETRIEVE THE TIMESTAMP OF THE LAST MESSAGE ON THE IMR, SO THAT THE REFRESH COMMAND PICKS UP NEW MESSAGES
	$resultmts = $conn->query("SELECT * FROM `chat` ORDER BY `MessageID` DESC LIMIT 1");
    if ($myrowmts = $resultmts->fetch_array())  $MessageTimeStamp=$myrowmts["MessageID"];

	// INSERT USER INTO DATABASE CHECKING FOR HIS OLD IP ADDRESS SETTING IN OUR OLDUSERLIST TABLE
	$un=$CMD[5];
	$resultip = $conn->query("SELECT * FROM `olduserlist` WHERE `UserName` = '$un'");

	if ($myrowip = $resultip->fetch_array()) {
        // TELL THE IMR A NEW PLAYER HAS JOINED
        $AltIP = $myrowip["IPAddress"];
        $conn->query("DELETE FROM `olduserlist` WHERE `UserName`='$CMD[5]'");
    } else $AltIP = $PlayerIP;
    
   // ChatMessage($UserID, $UserName, $Message, $ToUser = FALSE, $ToUserName = FALSE,$conn,$imr_settings)
    ChatMessage($conn,$imr_settings,0,ChatDate(),"$CMD[5] has joined the IMR! ($AltIP) [" . GetUserAgent() . "]");
 $sql="INSERT INTO `userlist` (`UserName`,`UserID`,`RegKey`,`Version`,`Key2`,`Key3`,`IPAddress`,`IPAddressOriginal`,`TimeStamp`,`MessageTimeStamp`,`JoinedIMR`,`PlayerListUpdate`,`GameListUpdate`) VALUES ('$CMD[5]','$UserID','$CMD[1]','$CMD[2]','$CMD[3]','$CMD[4]','$AltIP','$PlayerIP','". time() ."','$MessageTimeStamp','". time() ."','1','1')";
	//echo "$sql<br>";
	$conn->query($sql);

	// TELLS EVERYONE ELSE TO UPDATE
	$conn->query("UPDATE `userlist` SET `PlayerListUpdate` = '0'");

	// CHECKS IF ANY OF THE CLIENT SLOTS ARE TAKEN
	PlayerListUpdate($conn,$imr_settings);

    // PRINT GAMES TO THE GAMES LIST
	GameListUpdate($UserID,$conn,$imr_settings);

	// ADDS TO THE SMALL INTRO MESSAGE
    PrintIMR("CHAT\n--\n");

    // PRINT THE INTRO MESSAGE IF ONE EXISTS
/*  
  $resultint = $conn->query("SELECT * FROM `intro`");
    if ($myrowint = $resultint->fetch_array()) {
        $message = $myrowint["Message"];
        $message = str_replace("\n", "\nCHAT\n", $message);
        PrintIMR("CHAT\n$message\n");
        PrintIMR("CHAT\n--\n");
    }*/
	
  PrintIMR("CHAT\n--\n");

}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// REMOVING THE USER FROM THE IMR 																			 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function DEL_USER($conn, $CMD,$imr_settings) {
$max_per_game=$imr_settings->MAX_PER_GAME;
	// REMOVE THE CLIENT FROM THE IMR AS HE LEAVES
	$leaveid = explode("-",$CMD[1]);
	$resultmts = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$leaveid[0]'");
	$myrowmts = $resultmts->fetch_array();
	// ADD THEM TO A STORED DATABASE SO THAT THEIR IP ADDRESS IS REMEMBERED IF THEY HAVE CHANGED IT
	if ($myrowmts["IPAddress"]<>$myrowmts["IPAddressOriginal"]) {
		$conn->query("INSERT INTO `olduserlist` (`UserName`,`IPAddress`,`TimeStamp`) VALUES ('$myrowmts[UserName]','$myrowmts[IPAddress]','". time() ."')");
	}
	
	// IF HE LEFT TO RACE, PUT HIM INTO A TABLE CONTAINING OTHER PLAYERS WHO JUST LEFT
	$resulthost = $conn->query("SELECT * FROM `gamelist` WHERE `UserID`='$leaveid[0]'");
	$resultjoin = $conn->query("SELECT * FROM `gameplayers` WHERE `UserID`='$leaveid[0]'");
	if ($myrowhost = $resulthost->fetch_array())
	{
	    // PRINT THE MESSAGE SAYING PEOPLE ARE LEAVING TO PLAY
	    $resultleave = $conn->query("SELECT * FROM `gameleave` WHERE `GameID`='$myrowhost[GameID]'");
	    $myrowleave =$resultleave->fetch_array();
	    ChatMessage($conn,$imr_settings,0,ChatDate(),urldecode($myrowleave["HostName"]) . " has launched a game.");
		if ($myrowleave["Weapons"]==1) { $weapons = " with weapons."; } else { $weapons = " and no weapons."; }
		$conn->query("INSERT INTO chat (`UserID`,`UserName`,`Message`,`TimeStamp`) VALUES ('0','Game Details','$myrowleave[GameName], $myrowleave[Laps] laps$weapons','". time() ."')");
	    // NOW PRINT THE PARTICIPANTS
	    $players = "<#1>" . urldecode($myrowleave["HostName"]);
	    // NOW ADD THE OTHERS IN ORDER
		$extraplayers = " " . $myrowleave["PlayerNames"];
		if ($extraplayers != " ")
		{
			for ($playersingame=2; $playersingame<=$max_per_game; $playersingame++)
			{
			    if (stripos($extraplayers, "[" . $playersingame . "]"))
			    {
			    	$splitplayer = explode("\[$playersingame\]", $extraplayers);
			    	$splitplayer = explode("\<$playersingame\>", $splitplayer[1]);
			    	$splitplayer = $splitplayer[0];
			    	$players .= ", <#$playersingame>" . urldecode($splitplayer);
				}
			}
		}
	    ChatMessage($conn,$imr_settings,0,"Game Players","$players");
	    $conn->query("DELETE FROM `gameleave` WHERE `GameID` = '$myrowhost[GameID]'");
	} elseif (($myrowjoin = $resultjoin->fetch_array())==FALSE) {
		// TELL THE ROOM HE HAS LEFT
		ChatMessage($conn,$imr_settings,0,ChatDate(),"$myrowmts[UserName] has left the IMR!");
	}
	
	// REMOVE HIM FROM THE USERLIST
  	$conn->query("DELETE FROM `userlist` WHERE `UserID` = '$leaveid[0]' LIMIT 1");
  	// REMOVE HIM FROM ANY GAMES (BECAUSE WHEN YOU START A GAME, THEY ONLY LEAVE THE ROOM)
  	$conn->query("DELETE FROM `gamelist` WHERE `UserID` = '$leaveid[0]'");
    $conn->query("DELETE FROM `gameplayers` WHERE `UserID` = '$leaveid[0]'");
    // FORCES A RELOAD ON THE USERLIST AND GAMELIST (BECAUSE WHEN YOU START A GAME, THEY ONLY LEAVE THE ROOM)
    $conn->query("UPDATE `userlist` SET `PlayerListUpdate` = '0', `GameListUpdate` = '0'");
	PrintIMR("SUCCESS\n");
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ADDING CHAT TO THE CHAT DATABASE 																		 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function ADD_CHAT($conn, $CMD) {

    // STRIP - OFF OF THE USERID AND UPDATE THE TIMESTAMP
    $addchatid = explode("-",$CMD[1]);
    $result = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$addchatid[0]'");
    if ($myrow = $result->fetch_array()) {
        $UserName = $myrow["UserName"];
    }

    if (substr(urldecode($CMD[2]),0,3)=="/ip") {
    	// LET A PLAYER CHANGE HIS IP (USEFUL IF YOU WANT TO USE HAMACHI)
    	/////////////////////////////////////////////////////////////////
    	$changedip = split ("/ip ",urldecode($CMD[2]));
		$resultmts = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$addchatid[0]'");
		$myrowmts = $resultmts->fetch_array();
		// IF NO IP IS GIVEN, SIMPLY CHANGE TO THEIR ORIGINAL IP
		if ($changedip[1]==FALSE) {
	    	$changedip[1] = GetPlayerIP();
		}
		if (is_numeric(str_replace(".", "", $changedip[1]))) {
		    $conn->query("UPDATE `userlist` SET `IPAddress` = '$changedip[1]' WHERE `UserID` = '$addchatid[0]'");
		    $conn->query("UPDATE `gamelist` SET `GameIP` = '$changedip[1]' WHERE `UserID` = '$addchatid[0]'");
    		ChatMessage($conn,$imr_settings,0,ChatDate(),"$myrowmts[UserName] has changed IP to: $changedip[1]");
    		
    		$conn->query("UPDATE `userlist` SET `GameListUpdate` = '0'");
		} else {
		    ChatMessage($conn,$imr_settings,0,ChatDate(),"You cannot change your IP to text.",$addchatid[0]);
		}
	} elseif (substr(urldecode($CMD[2]),0,4)=="/msg") {
		// SENDING PRIVATE MESSAGES
		///////////////////////////

		// SPLIT THE USER/MESSAGE BIT AWAY
    	$split = split ("msg ",urldecode($CMD[2]),2);
    	// SPLIT THE USER AND MESSAGE INTO AN ARRAY
    	$contentsmessage = split (" ","$split[1]", 2);
    	// IF THE USER ID IS NOT ENTERED, THEN TELL THEM HOW TO MESSAGE
    	if(!is_numeric($contentsmessage[0]))
    	{
	    	ChatMessage($conn,$imr_settings,0,ChatDate() . "IMR","Usage: /msg <userid> <message>",$addchatid[0]);
	    	exit;
    	}
    	
		if($contentsmessage[0] == $addchatid[0])
		{
		    ChatMessage($conn,$imr_settings,0,ChatDate() . "IMR","Messaging yourself, eh?",$addchatid[0]);
		    exit;
		}
    	
    	// CHECK IF THE USER ID EXISTS
   		$resultmtsb = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$contentsmessage[0]'");
		if ($myrowmtsb = $resultmtsb->fetch_array()) {
    		// DELIVER THE MESSAGE
    		$message = $contentsmessage[1];
			ChatMessage($conn,$imr_settings,$addchatid[0],ChatDate() . "$UserName",addslashes($message),$myrowmtsb["UserID"],$myrowmtsb["UserName"]);
			ChatMessage($conn,$imr_settings,$addchatid[0],ChatDate() . "$UserName",addslashes($message),$addchatid[0],$myrowmtsb[UserName]);
		} else {
    		// TELL THEM THE PLAYER DOESN'T EXIST
    		ChatMessage($conn,$imr_settings,0,ChatDate() . "IMR","User ID $contentsmessage[0] does not exist.",$addchatid[0]);
		}
	} elseif (substr(urldecode($CMD[2]),0,4)=="/rec") {
		// VIEWING RECORDS FOR HOSTED TRACK
		///////////////////////////////////
		include "command-rec.php";
	// CODE FOR A STATUS CHANGE
	} elseif (substr(urldecode($CMD[2]),0,7)=="/status") {
		// LET A PLAYER CHANGE HIS STATUS
    	/////////////////////////////////
    	$changedstatus = split ("status ",urldecode($CMD[2]));
		$resultname = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$addchatid[0]'");
		$myrowname = $resultname->fetch_array();
		if ($changedstatus[1]) {
			ChatMessage($conn,$imr_settings,0,ChatDate(),"$myrowname[UserName] has changed their status to \'$changedstatus[1]\'");
		} elseif ($myrowname["Status"]) {
		    ChatMessage($conn,$imr_settings,0,ChatDate(),"$myrowname[UserName] has cleared their status");
		}
		$conn->query("UPDATE `userlist` SET `Status` = '$changedstatus[1]' WHERE `UserID` = '$addchatid[0]'");
		// FORCES A RELOAD ON THE USERLIST AND GAMELIST (BECAUSE WHEN YOU START A GAME, THEY ONLY LEAVE THE ROOM)
    	$conn->query("UPDATE `userlist` SET `PlayerListUpdate` = '0', `GameListUpdate` = '0'");
    } elseif (substr(urldecode($CMD[2]),0,3)=="/me") {
		// WRITE A /ME STYLE MESSAGE
		////////////////////////////
		$mecommand = split ("me ",urldecode($CMD[2]),2);
		$resultname = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$addchatid[0]'");
		$myrowname = $resultname->fetch_array();
		if ($mecommand[1]) {
			ChatMessage($conn,$imr_settings,0,ChatDate(),"$myrowname[UserName] $mecommand[1]");
		}
	} elseif (substr(urldecode($CMD[2]),0,6)=="/clear") {
		// CLEAR THE SCREEN
		///////////////////
		$ic=0;
		ChatMessage($conn,$imr_settings,0,"","\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\nCHAT\n\n",$addchatid[0]);
		ChatMessage($conn,$imr_settings,0,"","*Screen cleared*",$addchatid[0]);
	} elseif (substr(urldecode($CMD[2]),0,16)=="/phra-totalranks") {
		// VIEW TOP 5 PHRA TOTAL RANKINGS
		/////////////////////////////////
		//include "command-phratotalranks.php";

	} elseif (substr(urldecode($CMD[2]),0,1)=="/") {
        // TELL THEM THIS COMMAND DOESN'T EXIST
        ///////////////////////////////////////
	    ChatMessage($conn,$imr_settings,0,ChatDate() . "IMR","This command does not exist.",$addchatid[0]);
	} elseif ($CMD[2]) {
 		// INSERT THE CHAT TO THE IMR
 		/////////////////////////////
    	ChatMessage($conn,$imr_settings,$CMD[1],ChatDate() . "$UserName",addslashes($CMD[2]));
	}
	PrintIMR("SUCCESS\n");
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ADDING A GAME TO THE DATABASE 																			 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function ADD_GAME($conn, $CMD,$imr_settings) {
$max_games =$imr_settings->MAX_GAMES;
	// FIND A SUITABLE SLOT OUT OF THE AVAILABLE GAME SPACES AVAILABLE TO ADD THE GAME INTO
    $result = $conn->query("SELECT * FROM `gamelist` ORDER BY `GameID`");
    if ($myrow = $result->fetch_array())
    {
        do {
            if ($myrow["GameID"] - $GameID > 1) break;
            else $GameID = $myrow["GameID"];
        } while ($myrow = $result->fetch_array());
    }
    $GameID++;
    
	if ($GameID <= $max_games) {
	    // IF THERE IS NO GAME IN THE $GAMECOUNT SLOT, IT WILL PUT THE CLIENTS GAME HERE
	    // STRIP - OFF OF THE USERID AND UPDATE THE TIMESTAMP
		$addgameid = explode("-",$CMD[1]);
        $resultmts = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$addgameid[0]'");
		$myrowmts = $resultmts->fetch_array();
		$ip = $myrowmts["IPAddress"];

	    $conn->query("INSERT INTO `gamelist` (`GameID`,`UserID`,`GameName`,`Track`,`Laps`,`Weapons`,`GameIP`,`Port`,`Version`) VALUES ('$GameID','$CMD[1]','" . addslashes($CMD[2]) ."','" . addslashes($CMD[3]) ."','$CMD[4]','$CMD[5]','$ip','$CMD[6]', '" . GetUserAgent() . "')");
	    $conn->query("INSERT INTO `gameleave` (`GameID`,`HostName`,`GameName`,`Laps`,`Weapons`) VALUES ('$GameID','$myrowmts[UserName]','" . addslashes($CMD[2]) ."','$CMD[4]','$CMD[5]')");
	    $conn->query("INSERT INTO `gameplayers` (`GameID`,`UserID`,`JoinOrder`) VALUES ('$GameID','$CMD[1]','1')");
	    // THIS TELLS THE GAMELIST TO UPDATE ON REFRESH
	    $conn->query("UPDATE `userlist` SET `GameListUpdate` = '0'");
		PrintIMR("SUCCESS\n");
		PrintIMR("GAME_ID $GameID-$CMD[1]\n");
	} elseif ($GameID > $max_games) {
	    // THIS WARNS THAT THERE ARE ALREADY ALL GAME SLOTS FILLED
	    ErrorCode(402);
	}
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// CHECK A GAMES CONNECTIVITY TO ENSURE IT WORKS															 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function CHECK_GAME($conn, $CMD) {

    if($CMD[4] != -1)
	{
		$resultmts = $conn->query("SELECT * FROM `gamelist` WHERE `GameID` = '$CMD[4]' AND `UserID` = '$CMD[1]'");
		$myrowmts = $resultmts->fetch_array();
		$gamename = $myrowmts["GameName"];
	}
	if(@fsockopen($CMD[2], $CMD[3], $errno, $errstr, '4'))
	{
		if($CMD[4] != -1) $Delim = "+";
	}
	else
	{
	    $resultmts = $conn->query("SELECT * FROM `userlist` WHERE `UserID` = '$CMD[1]'");
		if($myrowmts = $resultmts->fetch_array())
		{
		    if($CMD[4] != -1) ChatMessage($conn,$imr_settings,$CMD[1],ChatDate() . "IMR","$myrowmts[UserName]\'s game might have connection issues!");
		    ChatMessage($conn,$imr_settings,$CMD[1],ChatDate() . "IMR", "You appear to be behind a router or firewall:  Visit: http://www.hoverrace.com/?page=unabletoconnect",$CMD[1]);
		    $Delim = "-";
		}
	}
	if($CMD[4] != -1) $conn->query("UPDATE `gamelist` SET `GameName` = '" . urlencode("$Delim") . " $gamename' WHERE `GameID` = '$CMD[4]' AND `UserID` = '$CMD[1]'");
	
	$conn->query("UPDATE `userlist` SET `PlayerListUpdate` = '0', `GameListUpdate` = '0'");
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// REMOVING A GAME FROM THE DATABASE 																		 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function DEL_GAME($conn, $CMD) {

	// DELETES THE GAME FROM THE GAMELIST
    $conn->query("DELETE FROM `gamelist` WHERE `GameID` = '$CMD[1]' AND `UserID` = '$CMD[2]' LIMIT 1");
    $conn->query("DELETE FROM `gameleave` WHERE `GameID` = '$CMD[1]' LIMIT 1");
    $conn->query("DELETE FROM `gameplayers` WHERE `GameID` = '$CMD[1]'");
    // TELLS THE CLIENT TO UPDATE HIS GAMELIST
    $conn->query("UPDATE `userlist` SET `GameListUpdate` = '0'");
    PrintIMR("SUCCESS\n");
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// JOINING A GAME 																							 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function JOIN_GAME($conn, $CMD,$imr_settings) {
$max_per_game=$imr_settings->MAX_PER_GAME;
	// CHECK IF THE HOST HAS THE SAME VERSION AS THE CLIENT
 	$resultmts = $conn->query("SELECT * FROM `gamelist` WHERE `GameID` = '$CMD[1]' AND `Version` = '" . GetUserAgent() . "'");
 	if (($myrow = $resultmts->fetch_array())) {
		// ASSIGN A PLAYER A SLOT IN THE GAME FROM 2-10 (JOINORDER IS SIMPLY FOR DISPLAY PURPOSES ON THE PLAYERLIST)
		$oldpid = 1;
		
		$resultmts = $conn->query("SELECT * FROM `gameplayers` WHERE `GameID` = '$CMD[1]' ORDER BY `JoinOrder`");
		if ($myrow = $resultmts->fetch_array()) {
			do {
			    if ($myrow["JoinOrder"] - $oldpid > 1) break;
			    else $oldpid = $myrow["JoinOrder"];
			} while ($resultmts->fetch_array());
		}
		$oldpid++;
		
		if($oldpid <= $max_per_game)
		{
			// INSERT INTO THE GAMEPLAYERS DATABASE (FOR DISPLAYING ON THE PLAYERLIST)
    		$conn->query("INSERT INTO `gameplayers` (`GameID`,`UserID`,`JoinOrder`) VALUES ('$CMD[1]','$CMD[2]','$oldpid')");
    		// REFRESH THE GAMES PLAYERS FOR WHEN THEY LEAVE THE IMR
    		RefreshPlayerNames($CMD[1]);
    		// TELLS THE CLIENT TO UPDATE HIS GAMELIST
    		$conn->query("UPDATE `userlist` SET `GameListUpdate` = '0'");
    		// CHECK THE PLAYER CAN CONNECT
    		if($CMD[3] and $CMD[4]) file_get_contents("http://" . $_SERVER["SERVER_ADDR"] . $_SERVER['PHP_SELF'] . "?=CHECK_GAME%%$CMD[2]%%$PlayerIP%%$CMD[3]%%-1");
		} else {
			ErrorCode(503);
		}
		
		PrintIMR("SUCCESS\n");
 	} else ErrorCode(103);
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// LEAVING A GAME 																							 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function LEAVE_GAME($conn, $CMD) {

	// REMOVE THEMSELVES FROM THE GAMEPLAYERS DATABASE(AND THE GAMES PLAYERLIST)
    $conn->query("DELETE FROM `gameplayers` WHERE `GameID` = '$CMD[1]' AND `UserID` = '$CMD[2]'");
    // TELLS THE CLIENT TO UPDATE HIS GAMELIST
    $conn->query("UPDATE `userlist` SET `GameListUpdate` = '0'");
    
	RefreshPlayerNames($CMD[1]);
        
    PrintIMR("SUCCESS\n");
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////
// DISPLAYING THE PEOPLE IN THE IMR 																							 //
///////////////////////////////////////////////////////////////////////////////////////////////////////////////

function WWW_ULIST($conn, $CMD) {

	// CALLS THE USERLIST
    $result = $conn->query("SELECT * FROM `userlist` ORDER BY `UserID`");
    $myrow = $result->fetch_array();
    // FAKE THE OLD LOOK OF THE USERLIST FOR THE SAKE OF THE IMR DISPLAY ON HR.COM
    print "<HTML>";
	print "<meta http-equiv=\"Refresh\" Content=8 >\n";
	print "<BODY BGCOLOR=\"FFFFFF\" TEXT=\"000000\">\n";
	print "<table border=\"2\" bgcolor=\"E0DFE3\"><tr><td align=\"center\" width=\"150\">Users List</td></tr></table>\n";
	print "<pre><FONT FACE=\"MS Sans Serif\" STYLE=\"font-size : 8 pt\">\n";
	// PRINT EACH NAME
	do {
	print urldecode($myrow[UserName]) . "\n";
	} while ($myrow = $result->fetch_array());
	// END OF FAKING
	print "</font></pre>\n";
	print "</BODY></HTML>";
}

////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
// FUNCTIONS
////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////

function PrintIMR($str)
{
	print $str;
}

function GetClient()
{
	if(GetUserAgent() == "1.23") return "HoverRace";
	else {
	    $u = $_SERVER['HTTP_USER_AGENT'];
		$u = explode("/", $u);
		return $u[0];
	}
}

function GetUserAgent()
{
	$u = $_SERVER['HTTP_USER_AGENT'];

	if(!$u)	return "1.23";
	else {
		$u = explode(" ", $u);
		$u = explode("/", $u[0]);
		return $u[1];
	}
}

function GetPlatform()
{
	if(GetUserAgent() == "1.23") return "Win32";
	else {
	    $u = $_SERVER['HTTP_USER_AGENT'];
		$u = explode(" ", $u);
		return $u[1];
	}
}

function GetChatCode()
{
	if(GetUserAgent() == "1.23") return "�";
    else return utf8_encode("�");
}

function GetPlayerIP()
{
    return isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
}

//////////////////////////////////////////////////////////////////////////////////////
// UPDATE THE GAMELEAVE TABLE'S "PLAYERNAMES" COLUMN TO REFLECT YOUR LEAVING
//////////////////////////////////////////////////////////////////////////////////////
function RefreshPlayerNames($gameid)
{
    // LOOP THE PLAYERS STILL IN THE GAME
    $newpn = "";
    $resultgameleaveadd = $conn->query("SELECT * FROM `gameplayers` WHERE `GameID` = '$gameid' AND `JoinOrder` > '1'");
    if ($myrowleave = $resultgameleaveadd->fetch_array())
    {
        do
		{
		    // GET THE PLAYERS NAME AND ADD THEM TO THE ARRAY
		    $resultgameleavename = $conn->query("SELECT * FROM `userlist` WHERE `UserID`='$myrowleave[UserID]'");
    		$myrowname = $resultgameleavename->fetch_array();
    		$newpn = $newpn . "[$myrowleave[JoinOrder]]$myrowname[UserName]<$myrowleave[JoinOrder]>";
		} while ($myrowleave = $resultgameleaveadd->fetch_array());
    }
    // UPDATE "PLAYERNAMES"
    $conn->query("UPDATE `gameleave` SET `PlayerNames` = '$newpn' WHERE `GameID` = '$gameid'");
}

function ChatDate()
{
	return "<" . date("H:i:s") . "> ";
}

function ErrorCode($errno)
{
	print "ERROR $errno\n";
	exit;
}

function GameListUpdate($UserID,$conn,$imr_settings)
{
	$max_games= $imr_settings->MAX_GAMES;
    // PRINT GAMES TO THE GAMES LIST
    $result = $conn->query("SELECT * FROM `gamelist` ORDER BY `GameID`");
	if($myrow = $result->fetch_array()) {
		do {
	        if ($myrow["GameID"]-$oldgid > 1)
	        {
	            for ( $x = $oldgid+1; $x < $myrow["GameID"]; $x++ ) PrintIMR("GAME $x DEL\n");
	        }
		    // COUNTS THE PLAYERS IN THE GAME, AND MAKES THE PRINT FOR LATER
		    $playercount=0;
		    $playerlist=FALSE;
		    $resultshowpl = $conn->query("SELECT * FROM `gameplayers` WHERE `GameID` = '$myrow[GameID]' ORDER BY `JoinOrder`");
	    	if ($myrowshowpl = $resultshowpl->fetch_array()) {
	    	    do {
	    			$playerlist .= "$myrowshowpl[UserID] ";
	    			$playercount++;
	    	    } while ($myrowshowpl = $resultshowpl->fetch_array());
	    	}
		    // TELLS THE IMR TO ADD THIS GAME TO THE GAME LIST
	    	PrintIMR("GAME $myrow[GameID] NEW $myrow[UserID]\n");
	    	PrintIMR(stripslashes(urldecode($myrow["GameName"])) . "\n");
	    	PrintIMR(stripslashes(urldecode($myrow["Track"])) . "\n");
	    	PrintIMR("$myrow[GameIP]\n");
	    	PrintIMR("$myrow[Port] $myrow[Laps] $myrow[Weapons] $playercount\n");
	    	// WRITES THE PLAYER'S ID'S INTO SOME FORM OF ARRAY FOR THE PLAYERLIST
			PrintIMR("$playerlist \n");

	    	if($myrow["UserID"]==$UserID AND $myrow["Checked"]=="0")
			{
			    $reloadgames = TRUE;
				file_get_contents("http://" . $_SERVER["SERVER_ADDR"] . $_SERVER['PHP_SELF'] . "?=CHECK_GAME%%$myrow[UserID]%%$myrow[GameIP]%%$myrow[Port]%%$myrow[GameID]");
				$conn->query("UPDATE `gamelist` SET `Checked` = '1' WHERE `UserID` = '$myrow[UserID]' AND `GameID` = '$myrow[GameID]'");
				$return = TRUE;
			}
			
			$oldgid = $myrow["GameID"];
	    } while ($myrow = $result->fetch_array());
	}

    for ( $x = $oldgid+1; $x <= $max_games; $x++ ) PrintIMR("GAME $x DEL\n");
    
	return $return;
}

function PlayerListUpdate($conn,$imr_settings)
{
	
$timeout=$imr_settings->TIMEOUT;
$max_players=$imr_settings->MAX_PLAYERS;
    // CHECKS IF ANY OF THE CLIENT SLOTS ARE TAKEN
	$sql="SELECT * FROM `userlist` WHERE `TimeStamp` > '$timeout' ORDER BY `UserID`";
	//echo "$sql<br>";
	$result = $conn->query($sql);
	if($myrow = $result->fetch_array())
	{
		do {
		    if ($myrow["UserID"]-$oldid > 1)
		    {
		        for ( $x = $oldid+1; $x < $myrow["UserID"]; $x++ ) PrintIMR("USER $x DEL\n");
		    }
			// TELLS THE IMR TO ADD THIS CLIENT TO THE PLAYER LIST
	        PrintIMR("USER $myrow[UserID] NEW\n");
	        PrintIMR("\n");
	        PrintIMR("($myrow[UserID]) ");
	        PrintIMR(urldecode($myrow["UserName"]));
	        if ($myrow["Status"]==TRUE) {
	        	PrintIMR(" [" . urldecode($myrow[Status]) . "]");
	        }
			PrintIMR("\n");

			$oldid = $myrow["UserID"];
		} while ($myrow = $result->fetch_array());
	}

	for ( $x = $oldid+1; $x <= $max_players; $x++ ) PrintIMR("USER $x DEL\n");
}

function ChatMessage($conn,$imr_settings,$UserID, $UserName, $Message, $ToUser = FALSE, $ToUserName = FALSE)
{
	// if(GetUserAgent() == "1.23") $Message = url_encode($Message);
    $sql=("INSERT INTO `chat` (`UserID`,`UserName`,`Message`,`TimeStamp`,`ToUser`,`ToUserName`) VALUES ('$UserID','$UserName','" . $Message . "','". time() ."','$ToUser','$ToUserName')");

$conn->query($sql);


}



class IMR {
  // Properties



//use magic methods for now
 protected $values = array();

    public function __get( $key )
    {
        return $this->values[ $key ];
    }

    public function __set( $key, $value )
    {
        $this->values[ $key ] = $value;
    }
}//end class

?>
