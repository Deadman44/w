<?php

/*
 *Hier befinden sich die Funktion für das Lizenzierungssystem
 *Von uns nicht geschriebene Funktion sind gesondert markiert und hier kurz aufgelistet.
 * Die Quelle dieser Funktionen wird als Kommentar darüber geschrieben
 * --create_hash();
 * --create_hash_with_salt();
 * --validate_password();
 * --slow_equals();
 * --pbkdf2();
 * 
 * 
 */


/*
 * Password hashing with PBKDF2.
 * Author: havoc AT defuse.ca
 * www: https://defuse.ca/php-pbkdf2.htm
 * 
 * CODE ist public domain
 * http://crackstation.net/hashing-security.htm#phpsourcecode
 * 
 */

// These constants may be changed without breaking existing hashes.
define("PBKDF2_HASH_ALGORITHM", "sha512");
define("PBKDF2_ITERATIONS", 1000);
define("PBKDF2_SALT_BYTES", 64);
define("PBKDF2_HASH_BYTES", 64);

define("HASH_SECTIONS", 4);
define("HASH_ALGORITHM_INDEX", 0);
define("HASH_ITERATION_INDEX", 1);
define("HASH_SALT_INDEX", 2);
define("HASH_PBKDF2_INDEX", 3);

function create_hash($password)
{
    // format: algorithm:iterations:salt:hash
    $salt = base64_encode(mcrypt_create_iv(PBKDF2_SALT_BYTES, MCRYPT_DEV_RANDOM));
    return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" .  $salt . ":" .
        base64_encode(pbkdf2(
            PBKDF2_HASH_ALGORITHM,
            $password,
            $salt,
            PBKDF2_ITERATIONS,
            PBKDF2_HASH_BYTES,
            true
        ));
}

// http://crackstation.net/hashing-security.htm#phpsourcecode salt
function create_hash_with_salt($password, $salt)
{
    // format: algorithm:iterations:salt:hash
    
    return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" .  $salt . ":" .
        base64_encode(pbkdf2(
            PBKDF2_HASH_ALGORITHM,
            $password,
            $salt,
            PBKDF2_ITERATIONS,
            PBKDF2_HASH_BYTES,
            true
        ));
}

//http://crackstation.net/hashing-security.htm#phpsourcecode
function validate_password($password, $good_hash)
{
    $params = explode(":", $good_hash);
    if(count($params) < HASH_SECTIONS)
       return false;
    $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);
    return slow_equals(
        $pbkdf2,
        pbkdf2(
            $params[HASH_ALGORITHM_INDEX],
            $password,
            $params[HASH_SALT_INDEX],
            (int)$params[HASH_ITERATION_INDEX],
            strlen($pbkdf2),
            true
        )
    );
}

// http://crackstation.net/hashing-security.htm#phpsourcecode
// Compares two strings $a and $b in length-constant time.
function slow_equals($a, $b)
{
    $diff = strlen($a) ^ strlen($b);
    for($i = 0; $i < strlen($a) && $i < strlen($b); $i++)
    {
        $diff |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $diff === 0;
}

/*
 * http://crackstation.net/hashing-security.htm#phpsourcecode
 * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
 * $algorithm - The hash algorithm to use. Recommended: SHA256
 * $password - The password.
 * $salt - A salt that is unique to the password.
 * $count - Iteration count. Higher is better, but slower. Recommended: At least 1000.
 * $key_length - The length of the derived key in bytes.
 * $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
 * Returns: A $key_length-byte key derived from the password and salt.
 *
 * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
 *
 * This implementation of PBKDF2 was originally created by https://defuse.ca
 * With improvements by http://www.variations-of-shadow.com
 */
function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
{
    $algorithm = strtolower($algorithm);
    if(!in_array($algorithm, hash_algos(), true))
        die('PBKDF2 ERROR: Invalid hash algorithm.');
    if($count <= 0 || $key_length <= 0)
        die('PBKDF2 ERROR: Invalid parameters.');

    $hash_length = strlen(hash($algorithm, "", true));
    $block_count = ceil($key_length / $hash_length);

    $output = "";
    for($i = 1; $i <= $block_count; $i++) {
        // $i encoded as 4 bytes, big endian.
        $last = $salt . pack("N", $i);
        // first iteration
        $last = $xorsum = hash_hmac($algorithm, $last, $password, true);
        // perform the other $count - 1 iterations
        for ($j = 1; $j < $count; $j++) {
            $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
        }
        $output .= $xorsum;
    }

    if($raw_output)
        return substr($output, 0, $key_length);
    else
        return bin2hex(substr($output, 0, $key_length));
}


/*
 * Beginn eigene Funktionen
 */

//überprüft Kombination Email,Passwort und Lizenzschlüssel
function check_license_key($Qemail,$Qpass,$license_key) //mit password für user, da man ansonsten geklaute lizenz mit emailliste 
        //vergleichen kann, hat man einen treffer, muss man nur noch password stehlen
{

    if(!checkUserLogin($Qemail, $Qpass))
    {
        //echo "Wrong Login";
        return false;
    }
    
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    $lkeyArr = explode(":", $license_key);
    $random = $lkeyArr[0]; //die vom user gegeben random zahl
    $hashed_random = $lkeyArr[1]; //test... //die hmac, also random und salt;; 
       
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    
    $skey = "NO KEY ERROR";
    $hserial ="NO SERIAL ERROR";
    $active = "0";
    
    if($mysqli->connect_errno)
    {
            //echo "FAIL" . $user . $pwd;
            return false;
    }
    else
    {
            $query = "SELECT SKEY,HSERIAL,ACTIVE from USER where EMAIL = ? ";
            $result = $mysqli->prepare($query);
            $result->bind_param('s',$Qemail);
            $result->execute();
            $result->bind_result($skey,$hserial,$active);
            while($result->fetch())
            {
                    //noch keine fehlerüberprüfung...
            }     

    }
    $mysqli->close();
    
    
    if(strcmp($active,"1") == 0)
    {
        echo "False".$active;
        return "False";
    }

    /*
     * erstes überprüfung kann umgangen werden, fall an eine gültige lizenz ein doppelpunkt
     * angehängt wird, die zweite überprüfung schlägt dann aber fehl!
     * deswegen beide wichtig
     * wobei der angesprochene angriff bedingt, dass man den originalkey hat
     * ggfls muss bei eingaben auf doppelpunkt gefiltert werden...
     */
    $hashed_random_candidate = explode(':',create_hash_with_salt($random, $skey))[3];
    if($hashed_random_candidate != $hashed_random)
    {
        //echo $hashed_random . " ORIGINAL" . "<br>";
        //echo $hashed_random_candidate . " GESENDET" . "<br>";
        //echo "SERIAL WRONG -- hashed_random_error" .  "<br>";
        return false;
    }
    
    $hserial_candidate = hash("sha512",$license_key);
    
    if($hserial_candidate != $hserial)
    {
        //echo $hserial_candidate . " gesendet ". " <br>";
        //echo $hserial . " original " . " <br>";
        //echo "Serial Wrong -- hserial error";
        return false;
    }
    
    return true;
    
}

//Gebe HSERIAL aus Datenbank aus (gehashter Lizenzschlüssel)
function get_hserial($Qemail)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(':', $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];

    $hserial ="nofetched";

    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    
    	if($mysqli->connect_errno)
	{
		echo "FAIL" . $user . $pwd;
	}
	else
	{
		$query = 'SELECT HSERIAL from USER where email=? ';
		$result = $mysqli->prepare($query);
                $result->bind_param('s',$Qemail);
		$result->execute();

		$result->bind_result($hserial);
		while($result->fetch())
		{
			
		}  
	}
        $mysqli->close();
        return $hserial;
    
}

function license_exists($Qemail)
{

    if(get_hserial($Qemail) != null)
    {
        //echo get_hserial($Qemail);
        return true;
    }
    
    //echo "license not available";
    return false;
    
    
}

/*
Erzeugt Lizenzschlüssel, verarbeitet diesen und sendet ihn zum Server
 * IN der DAtenbank wird der Salt (SKEY) und die gehasthe vollständige Seriennummer gespeichert (HSERIAL)
*/

function create_license_key($Qemail,$forgotten_license)
{
    $forgotten_license = "NOINTEREST";
    
    if( license_exists($Qemail)) //  && $forgotten_license == "false")
    {
        echo "Lizenzkey existiert bereits" ;
        return false;
    }
    
    $random = base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_RANDOM)); // 128 bit random
    $salt = base64_encode(mcrypt_create_iv(PBKDF2_SALT_BYTES, MCRYPT_DEV_RANDOM)); 
    $hashed_random = explode(":",create_hash_with_salt($random,$salt));
     
    $license_key = $random.":".$hashed_random[3]; //nur hashed_random ausgeben, kein salt und params
    $hserial = hash("sha512", $license_key);
    
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $insert = 'UPDATE USER SET skey=?, hserial=? WHERE email =? ';
            $eintrag = $mysqli->prepare($insert);
            $eintrag->bind_param('sss',$salt,$hserial,$Qemail);
            $eintrag->execute();
            //echo "<br>";
            //echo "<br>";
            //echo "<br>";
            //echo $license_key;
            //echo "<br>";
            //echo "<br>";
            //echo "<br>";
            
            return $license_key;

    }


    //return $license_key . "ERRRRRRRRR"; //temporär
    $mysqli->close();
    




}

//gibt aus dem String ALGO:ITERATIONS:SALT:HASH nur die ersten 3 Werte zurück
function returnSaltFromAll($entry)
{
    $params = explode(":",$entry);
    $saltBig = $params[0].":".$params[1].":".$params[2];
    return $saltBig;
}

//gibt aus dem String ALGO:ITERATIONS:SALT:HASH nur den HASH zurück
function returnHashFromAll($entry)
{
    $params = explode(":",$entry);
    return $params[3];
}
//rekonstruiert aus SALTBIG und HASH wieder die Form ALGO:ITERATIONS:SALT:HASH
function reconstructAll($saltBig,$hash)
{
    return $saltBig.":".$hash;
}

//Zugangsinformationen der Datenbank liegen in diesem Ordner und werden ausgelesen
//Die Zugangsdaten sollten nie direkt im QUellcode stehen
function getCredentialsFromFile()
{
    $handle = fopen("C:/w/cube_license_frontend/credentials/database.txt","r");
    
    //eine zeile lesen, die oberste
    $buffer = fgets($handle);
    $userpw = explode(":", $buffer);
    $user = $userpw[0];
    $pwd = $userpw[1];
    fclose($handle);
    
    return $user.":".$pwd;
}

//Neuen User zu Datenbank hinzufügen (=Registrierung)
function addNewUserToDB($nname, $vname, $email, $pass)
{
    //separierung von hash und salt, salt bsteht auch aus algo-nr und iterations...
    $securePass = create_hash($pass);
    $secureHash = returnHashFromAll($securePass);
    $secureSalt = returnSaltFromAll($securePass);
    
    if(!check_email($email))
    {
       echo "<h3 class=\"meldung\"> EMAIL im falschen Format </h3>";
       return false; 
    }
    
    if(!check_EmailUnique($email))
    {
        echo "<h3 class=\"meldung\"> EMAIL existiert bereits </h3>";
       return false; 
    }
	
    if(!check_password($pass))
    {
        echo "<h3 class=\"meldung\"> Passwort entspricht nicht den Richtlinien </h3>";
        return false;
    }
    
    
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    

    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno)
	{
		echo "FAIL";
	}
	else
	{
		
		$insert = 'INSERT INTO USER(NNAME, VNAME, EMAIL, PASS, PSALT) VALUES(?,?,?,?,?)';
		$eintrag = $mysqli->prepare($insert);
		$eintrag->bind_param('sssss',$nname, $vname,$email,$secureHash,$secureSalt);
		$eintrag->execute();
		
		if ($eintrag->affected_rows == 1)
        {
            echo "<h3 class=\"ok\">Der neue Eintrage wurde hinzugef&uuml;gt.</h3> <br />";
        }
        else
        {
            echo "<h3 class=\"meldung\">Der Eintrag konnte nicht hinzugef&uuml;gt werden.</h3>";
        }	
	}
	
	$mysqli->close();
}

//Überprüft Logindaten (Webobefläche)
function checkUserLogin($Qemail, $Qpass)
{
    
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    
    $id = "null";
    $email = "null";
    $nname = "null";
    $vname = "null";
    $pass = "null";
    $psalt = "null";

    
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    
    	if($mysqli->connect_errno)
	{
		echo "FAIL" . $user . $pwd;
	}
	else
	{
		$query = "SELECT ID,EMAIL,NNAME,VNAME,PASS,PSALT from USER where EMAIL = ? ";
		$result = $mysqli->prepare($query);
                $result->bind_param('s',$Qemail);
		$result->execute();
		$result->bind_result($id,$email,$nname,$vname,$pass,$psalt);
		while($result->fetch())
		{
			//echo $id . $email . $nname . $vname . $pass . $psalt. "<br>";
		}
                
                $allPass = reconstructAll($psalt, $pass);
                if(!validate_password($Qpass, $allPass))
                {
                    //echo "wrong password";
                    $mysqli->close();
                    return false;
                }
                else
                {
                    //echo "succ login";
                }
                
        
	}
        $mysqli->close();
        return true;
}

//Entspricht die Email dem Format X@Y.DOMAIN?
//REGEXP von http://www.php.de/php-einsteiger/86149-erledigt-spam-schutz-mit-e-mail-formular.html
function check_email($email)
{        
    if(preg_match('/^[^\x00-\x20()<>@,;:\\".[\]\x7f-\xff]+(?:\.[^\x00-\x20()<>@,;:\\".[\]\x7f-\xff]+)*\@[^\x00-\x20()<>@,;:\\".[\]\x7f-\xff]+(?:\.[^\x00-\x20()<>@,;:\\".[\]\x7f-\xff]+)+$/i', $email))
	{
            return true;
	}
	else
	{
	    return false;
	}
}

// Existiert die Email bereits in der Datenbank?
function check_EmailUnique($Qemail)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    $id = "noid";
    $email ="noemail";
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    
    	if($mysqli->connect_errno)
	{
		echo "FAIL" . $user . $pwd;
	}
	else
	{
                $exists = 0;
		$query = "SELECT ID,EMAIL from USER where EMAIL = ? ";
		$result = $mysqli->prepare($query);
                $result->bind_param('s',$Qemail);
		$result->execute();
		$result->bind_result($id,$email);
		while($result->fetch())
		{
			$exists++;
		}
                
                
                if($exists >=1)
                {
                    $mysqli->close();
                    //echo "email already exists";
                    return false;
                }

	}
        $mysqli->close();
        return true;
    
}


//entspricht Passwort den Richtlinien?
function check_password($password)
{
	if(check_password_length($password) && check_password_number_included($password))
	{
		return true;
	}
	else
	{
		return false;
	}
}

// Passwortlänge überprüfen
function check_password_length($password)
{
	if(strlen($password) >= 10)
	{
		return true;
	}
	else
	{
		echo "<h3 class=\"meldung\"> Passwortlänge zu kurz, bitte erneut registrieren. </h3>";
		return false;
	}
}
//Enthält Passwort eine Zahl?
function check_password_number_included($password)
{
	if(preg_match( "/\d+/", $password ))
	{
		return true;
	}
	else
	{
		echo "<h3 class=\"meldung\">Keine Zahlen enthalten, bitte erneut registrieren.</h3>";
		return false;
	}
}

//Passwort wechseln (altes benötigt)
function change_Password($Qemail,$oldpass, $pass)
{
    if(!checkUserLogin($Qemail, $oldpass))
    {
        echo "<h3 class=\"meldung\"> Aktuelles Passwort nicht korrekt </h3>";
        return false;
    }
    if(!check_password($pass))
    {
        echo "<h3 class=\"meldung\">Bitte anderes Passwort eingeben</h3>";
    }
    
    $securePass = create_hash($pass);
    $secureHash = returnHashFromAll($securePass);
    $secureSalt = returnSaltFromAll($securePass);
    
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {
            $insert = 'UPDATE USER SET PASS = ?,PSALT=? WHERE email =? ';
            $eintrag = $mysqli->prepare($insert);
            $eintrag->bind_param('sss',$secureHash,$secureSalt,$Qemail);
            $eintrag->execute();
    }

    $mysqli->close();   
}

//Testfunktion, gibt alle Userdaten aus
function getAllDataFromUser($Qemail)
{
    
    if(check_EmailUnique($Qemail))
    {
        echo " NO USER FOR REQUEST";
        return false;
        
    }
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];

    $id = "null";
    $email = "null";
    $nname = "null";
    $vname = "null";
    $pass = "null";
    $psalt = "null";
    $skey = "null";
    $hserial = "null";
    $active = "null";
    $suspect = "null";

    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    
    	if($mysqli->connect_errno)
	{
		echo "FAIL" . $user . $pwd;
	}
	else
	{
		$query = "SELECT ID,EMAIL,NNAME,VNAME,PASS,PSALT,SKEY,HSERIAL,ACTIVE,SUSPECT from USER where EMAIL = ? ";
		$result = $mysqli->prepare($query);
                $result->bind_param('s',$Qemail);
		$result->execute();
		$result->bind_result($id,$email,$nname,$vname,$pass,$psalt,$skey,$hserial,$active,$suspect);
		while($result->fetch())
		{
                        $psalt = explode(":", $psalt)[2];    //nur salt nehmen, nicht zusatzinfos                        
			$all = $id .":". $email .":". $nname .":". $vname .":". $pass .":". $psalt .":". $skey .":". $hserial . ":".$active.":". $suspect;
		}
                
                
                $allArr = explode(":", $all);
                
        
	}
        $mysqli->close();
        return $allArr;
}




/*
 * Special Authentication Functions
 * 
 */

// Setzt Active-Feld auf 1 (erfolgreicher Login am Cube-Client Vorbedingung)
function setUserActive($Qemail,$ticket)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    $active = 1;
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $insert = 'UPDATE USER SET ACTIVE=? WHERE email =? AND TICKET=?';
            $eintrag = $mysqli->prepare($insert);
            $eintrag->bind_param('sss',$active,$Qemail,$ticket);
            $eintrag->execute();
    }

    $mysqli->close();   
}

// Setzt Active-Feld auf 0(erfolgreiche Beendigung des SPiels Vorbedingung)
function setUserInActive($Qemail,$ticket)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    $active = 0;
    $newticket = "empty";
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $insert = 'UPDATE USER SET ACTIVE=?, TICKET=? WHERE email =? AND TICKET=? ';
            $eintrag = $mysqli->prepare($insert);
            $eintrag->bind_param('ssss',$active,$newticket,$Qemail,$ticket);
            $eintrag->execute();
    }

    $mysqli->close();   
}

// Status des Active-Feld für den User herausfinden
function getUserActive($Qemail,$ticket)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    $active = "ERROR NOT FOUND";
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $query = "SELECT ACTIVE from USER where EMAIL = ? AND TICKET=?";
            $result = $mysqli->prepare($query);
            $result->bind_param('ss',$Qemail,$ticket);
            $result->execute();
            $result->bind_result($active);
            while($result->fetch())
            {
            }     
    }
    
    $mysqli->close();   
    return $active;

}

// Erstellt erstes Authentisierungsticket und speichert es in Datenbank
function createAndReturnTicket($Qemail,$Qpass)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    if(!checkUserLogin($Qemail, $Qpass))
    {
        return false;
    }
    
    $hashAndSalt = create_hash($Qemail.$Qpass);
    $temporaryticket = returnHashFromAll($hashAndSalt); //Salt implizit, gibt nur Hash aus     
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    $chkintegrity = "100"; //Achtung, hier Start-Wunsch-Hash für die erste Überprüfung
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $insert = 'UPDATE USER SET TICKET=?, CHKINTEGRITY=? WHERE email =? ';
            $eintrag = $mysqli->prepare($insert);
            $eintrag->bind_param('sss',$temporaryticket,$chkintegrity,$Qemail);
            $eintrag->execute();
    }

    $mysqli->close();
    
    setUserActive($Qemail, $temporaryticket);
	setTimeStampToDB($Qemail, $temporaryticket);
    return $temporaryticket;
}

// Erstellt alle weiteren Tickets und speichert diese in Datenbank
function createTicketWithOldTicket($Qemail,$ticket)
{
    
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
       
    $hashAndSalt = create_hash($Qemail.$ticket);
    $temporaryticket = returnHashFromAll($hashAndSalt); //Salt implizit, gibt nur Hash aus     
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $insert = 'UPDATE USER SET TICKET=? WHERE email =? AND ticket=? ';
            $eintrag = $mysqli->prepare($insert);
            $eintrag->bind_param('sss',$temporaryticket,$Qemail,$ticket);
            $eintrag->execute();
    }

    $mysqli->close();
	setTimeStampToDB($Qemail,$ticket);
	//dropActive(); << noch buggy wegen blockierenden ssl-aufrufen wegen SAT
    return $temporaryticket;
    
}

// Überprüfungsfunktion, ob Client gültiges Ticket gesendet hat
function permanentcheck($Qemail,$oldticket,$clientHash)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    $active = "ERROR NOT FOUND";
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {
            $query = "SELECT ACTIVE,TICKET from USER where EMAIL = ? AND TICKET=?";
            $result = $mysqli->prepare($query);
            $result->bind_param('ss',$Qemail,$oldticket);
            $result->execute();
            $result->bind_result($active,$ticket);
            while($result->fetch())
            {
            }     
    }
    
    if(!$active)
    {
        return false ; //bug..."False"
    }
    if(strcmp($oldticket, $ticket) != 0)
    {
        return false ;// bug "False";
    }
    
    if(!compareClientWithServerHash($clientHash, $Qemail, $oldticket))
    {
        return false;
    }
    
    $ticket = createTicketWithOldTicket($Qemail, $ticket);
    
    $newHashWish = set_and_get_client_hash_wish($Qemail, $ticket);
    
    $mysqli->close();   
    return "True\n" . $ticket . $newHashWish;
}

/// integration checks

function check_client_hash($Qemail,$ticket,$hash,$challengeNR) //DEPRECATED...
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
    
    $active = "ERROR NOT FOUND";
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $query = "SELECT ACTIVE from USER where EMAIL = ? AND TICKET=?";
            $result = $mysqli->prepare($query);
            $result->bind_param('ss',$Qemail,$ticket);
            $result->execute();
            $result->bind_result($active);
            while($result->fetch())
            {
            }     
    }
}

function set_and_get_client_hash_wish($Qemail,$ticket) //ggfls auf activity fragen... ansonsten lässt sich das einfach von außen überschreiben (da ticket = empty...)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
      
    
    $wish = rand(100,124);
    
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $query = "UPDATE USER SET CHKINTEGRITY = ? WHERE email =? and ticket=? ";
            $result = $mysqli->prepare($query);
            $result->bind_param('sss',$wish,$Qemail,$ticket);
            $result->execute(); 

    }
    
    
    return $wish;  
}

function get_client_hash_wish($Qemail,$ticket)
{
    $credentials = getCredentialsFromFile();
    $credentialsArr = explode(":", $credentials);
    $user = $credentialsArr[0];
    $pwd = $credentialsArr[1];
        
    $wish = 0;
    $mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
    if($mysqli->connect_errno)
    {
            echo "FAIL";
            return "SERVER_ERROR";
    }
    else
    {

            $query = "SELECT CHKINTEGRITY from USER WHERE EMAIL =? and TICKET=?";
            $result = $mysqli->prepare($query);
            $result->bind_param('ss',$Qemail,$ticket);      
            $result->execute();   
            $result->bind_result($wish);
            while($result->fetch())
            {

            }
    }
    
    return $wish;  
    
}

function compareClientWithServerHash($clientHash,$Qemail,$ticket)
{
    $wish = get_client_hash_wish($Qemail, $ticket);
    $hash = hashGameDataWithSalt($wish, $ticket);
    
    if(strcasecmp($hash, $clientHash) == 0)
    {
        return true;
    }
    else
    {
        return false;
    }
}


function hashGameDataWithSalt($wish,$salt)
{
    //dreistellig.... alte konstante version, zum funktionieren muss noch rand(100,124) entfernt werden
    //$datas[100] = "bin/cube.exe";
    //$datas[101] = "bin/SDL.dll";
    
    $datas = readFileData();
    $file = "C:/w/cube/Cube/".$datas[$wish-100]; //fixed path.. ändern
    
    $handle = fopen($file,"rb"); //binary read
    
    $content = fread($handle, filesize($file));  
    $content = $content.$salt;
    
    $hash = hash("SHA1", $content);

    fclose($handle);
    
    $demo = "hashed.txt";
    file_put_contents($demo, $wish, FILE_APPEND);
    file_put_contents($demo, $file, FILE_APPEND);
    file_put_contents($demo, $hash,FILE_APPEND);
    
    return $hash;
}

function createAndStartBat(){
	// Server erzeugt bat-Datei für Single-Player Spiel
	
	
	$file=fopen("C:/w/cube/cube/start.bat","w+");
	$pwd=mt_rand(0,1000);
	$proof=true;
	$port=0;
	
	// Prüfung ob ausgewürfelte Port-Nummer bereits belegt ist
	while($proof){
		$port=mt_rand(30000,50000);
		$fp = fsockopen("localhost", $port);
		if(!$fp){
			echo "Error with Portnumber!";
		} else {
			$proof=false;
		}		
	}
	
	// bat-Datei erzeugen und mit Daten/Cube-Parametern befüllen
	if (is_writable($file) && $port!=0) {
		$string="bin\cube.exe -d -c1 -p".$pwd."-i127.0.0.1 -q".$port." %*";
		fwrite($file,$string);
		fclose();
		exec("C:/w/cube/cube/start.bat");
	} else {
		echo "Bat-File not created!";
	}
}

function destroyStartBat(){

	// Wenn lesbare bat-Datei vorhanden, dann löschen
	if (is_readable("C:/w/cube/cube/start.bat")){
		unlink("C:/w/cube/cube/start.bat");
	} else {
		echo "Bat-File not destroyed! No such file in directory.";
	}
}

function setTimeStampToDB($Qemail,$ticket){
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];
    
	$newTimeStamp = new DateTime();
	$newTimeStamp = $newTimeStamp->getTimestamp();
    
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR (setTimeStampToDB_function)";
	} else {
            $query = "UPDATE USER set TIMESTAMP=? where EMAIL=? AND TICKET=?";
            $result = $mysqli->prepare($query);
            $result->bind_param('sss',$newTimeStamp,$Qemail,$ticket);
            $result->execute();
   
	}
	$mysqli->close();	
}

function getTimeStampFromDB($Qemail){
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];
	
	$timestamp = "null";
    
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR (getTimeStampFromDB_function)";
	} else {
        $query = "SELECT TIMESTAMP from USER where EMAIL=?";
        $result = $mysqli->prepare($query);
        $result->bind_param('s',$Qemail);
        $result->execute();
   		$result->bind_result($timestamp);
		while($result->fetch())
		{
		}  
	}
	//$mysqli->close();
	return $timestamp;
}

function readFileData(){
	//$datei = implode("\n",file("gameData.txt"));
        //$arr = explode(" ",$datei);
        $dat = file("gameData.txt");
        
        for($i = 0; $i < count($dat);$i++)
        {
            $dat[$i] = trim($dat[$i]);
            
        }
        $dat[0] = "bin/cube.exe"; // DEBUG
	return $dat;
}

function dropActive(){
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];
	
	$Qemail = "null";
	
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR (dropActive_function)";
	} else {
		$query = "SELECT EMAIL from USER where ACTIVE=1";
		$result = $mysqli->prepare($query);
		$result->execute();
		$result->bind_result($Qemail);
		while($result->fetch()){
			$mysqli2 = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
			if($mysqli2->connect_errno){
				echo "FAIL";
				return "SERVER_ERROR (dropActive_function)";
			} else {
				if(getTimeStampDifference($Qemail)>300){
					$query2 = "UPDATE USER set ACTIVE=0 where EMAIL=?";
					$result2 = $mysqli2->prepare($query2);
					$result2->bind_param('s',$Qemail);
					$result2->execute();
				} 
			}
			$mysqli2->close(); 
		}
	}
	$mysqli->close(); 
}

function getTimeStampDifference($Qemail){
	
	$oldTimeStamp = getTimeStampFromDB($Qemail);
	$newTimeStamp = new DateTime();
	$newTimeStamp = $newTimeStamp->getTimestamp();
	
	$difference = $newTimeStamp - $oldTimeStamp;
	return $difference;
}

// Bei Verstößen wird eine in den ToS definierte Menge an suspectPoints vergeben
// ab gewisser Menge erfolgt der Bann (Löschung HSERIAL und SKEY) auf dem Server
function incrementSuspects($Qemail,$suspectPoints){ 
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];


        $intPoints = (int)$suspectPoints;
        echo "EMAIL: ".$Qemail;
        echo "AMNT: ".$intPoints;
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR ";
	} else {
		$query = "UPDATE USER set SUSPECT=SUSPECT+? where EMAIL=?";
		$result = $mysqli->prepare($query);
		$result->bind_param('is',$intPoints,$Qemail);
		$result->execute();
	}
	$mysqli->close();
        echo "Verstoß... <br>";
	proofUserBan($Qemail);
}

function proofUserBan($Qemail){ //testweise ohne aktuelles ticket...
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];
	
	$suspectPoints = "null";
    
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR ";
	} else {
        $query = "SELECT SUSPECT from USER where EMAIL=?";
        $result = $mysqli->prepare($query);
        $result->bind_param('s',$Qemail);
        $result->execute();
   		$result->bind_result($suspectPoints);
		while($result->fetch())
		{
		}  
		if($suspectPoints >= 10){
			$ban = "banned";
			$query = "UPDATE USER set HSERIAL=?, SKEY=?, TICKET=? where EMAIL=?";
			$result = $mysqli->prepare($query);
			$result->bind_param('ssss',$ban,$ban,$ban,$Qemail);
			$result->execute();
                        echo " BANN <br>";
		}
	}
	$mysqli->close();
}

function getEmailByTicket($ticket){
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];
	
	$email = "null";
    
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR ";
	} else {
        $query = "SELECT EMAIL from USER where TICKET=?";
        $result = $mysqli->prepare($query);
        $result->bind_param('s',$ticket);
        $result->execute();
   		$result->bind_result($email);
		while($result->fetch())
		{
		} 
	}
	$mysqli->close();
	return $email;
}

function setAndReturnServerAccessTicket($Qemail,$ticket) //client ruft diese ftk auf
                {
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];
     
        $sat = rand(000100,999999); // fix 6-stellig und damit filterbar in c-code
        
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR";
	} else {
            $query = "UPDATE USER set SAT=? where EMAIL=? AND TICKET=?";
            $result = $mysqli->prepare($query);
            $result->bind_param('sss',$sat,$Qemail,$ticket);
            $result->execute();
   
	}
	$mysqli->close();
        return $sat;
        
        /*
         * Zu beachten: sat wird auf jeden fall berechnet und zurückgegeben
         * ggfls aber nicht in db geschrieben wenn parameter nicht stimmen! (ticket, email)
         */
}

function resetServerAccessTicket($Qemail,$sat) //nach beitritt des servers soll ticket zurückgesetzt werden
        //verhindert wiederverwertung nach entzug lizenz o.ä.
                {
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];
     
        $newSat = 000000;
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR";
	} else {
            $query = "UPDATE USER set SAT=? where EMAIL=?";
            $result = $mysqli->prepare($query);
            $result->bind_param('ss',$newSat,$Qemail);
            $result->execute();
   
	}
	$mysqli->close();
        return $sat;
        
        /*
         * Zu beachten: sat wird auf jeden fall berechnet und zurückgegeben
         * ggfls aber nicht in db geschrieben wenn parameter nicht stimmen! (ticket, email)
         */
}

function checkServerAccessTicket($sat, $Qemail) //server ruft diese ftk auf
        {
	$credentials = getCredentialsFromFile();
	$credentialsArr = explode(":", $credentials);
	$user = $credentialsArr[0];
	$pwd = $credentialsArr[1];
	if(strcmp($sat, 000000) == 0)
        {
           return "False" ;
        }
        $active = 0;
	$mysqli = @new mysqli("127.0.0.1",$user,$pwd,"cube_license");
	if($mysqli->connect_errno){
		echo "FAIL";
		return "SERVER_ERROR";
	} else {
        $query = "SELECT ACTIVE from USER where SAT=? AND EMAIL=?";
        $result = $mysqli->prepare($query);
        $result->bind_param('ss',$sat,$Qemail);
        $result->execute();
        $result->bind_result($active);
            while($result->fetch())
            {
            } 
	}
	$mysqli->close();
        if(strcmp($active, "1" == 0))
        {
            resetServerAccessTicket($Qemail, $sat); 
            return "True";
        }
        else
        {
            return "False";
        }
}

?>