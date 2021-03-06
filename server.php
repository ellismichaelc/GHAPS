<?
// GHAPS - GitHub Auto-Publish Server
//
// Michael Ellis / Mike@MCECreations.com
// 08.21.13 / Updated: 04.22.15

chdir(dirname(__FILE__));

$error_log = "/var/log/ghaps.log";

$cmd_prior = "git fetch --all";
$cmd_pull  = "git pull";
$cmd_fpull = "git reset --hard origin";

$repo_dirs = array("mysite"         => "/var/www/live/",
				   "mystagingsite"  => "/var/www/staging/");
				   
$php_cmd   = "php -q";
$sock_port = 8181;
$ip_list   = array("127.0.0.1",
			       "24.233.176.168",
				   "204.232.175.64/27",
				   "192.30.252.0/22"); 


// END CONFIG //
$cmd_prior  .= " 2>&1";
$cmd_pull   .= " 2>&1";
$cmd_fpull  .= " 2>&1";

$server_ver  = '1.3';
$server_name = "GHAPS v{$server_ver}";
$dest_file   = "server_run.php";
$filename    = $_SERVER['SCRIPT_FILENAME'];

$spacer_char = "#";
$spacer      = "\n" . str_repeat($spacer_char, 90) . "\n";

if($filename !== $dest_file) {
	while(true) {
		// Delete duplicate
		@unlink($dest_file);
		
		// Copy me to duplicate
		if(!@copy($filename, $dest_file)) {
			die("Fatal error: Cannot copy file to '{$dest_file}'\n");
		}
		
		// Launch duplicate file
		system("{$php_cmd} $dest_file");
		
		if(@unlink("dnr")) {
			// DO NOT RESUSCITATE!
			
			echo " * Received DNR. Quitting.\n";
			exit;
		}
		
		echo " * Process died. Attempting restart in 2 seconds..\n";
		sleep(2);
	}
	
	exit;
}

/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////

@unlink($_SERVER['SCRIPT_FILENAME']);

$sock   = false;
system("clear");

echo "###########################################################################################\n";
echo "########################  GitHub URL Callback Auto-Publish Server  ########################\n";
echo "###########################################################################################\n\n";

echo " * Starting listening on port {$sock_port} .. ";
$sock = @socket_create_listen($sock_port);

if(!$sock) {
	echo "[FAILED] [ERROR: ";
	echo socket_strerror(socket_last_error());
	echo "]\n\n";
	exit;
}

echo "[DONE]\n-------------------------------------------------------------------------------------------\n";

while($sock) {
	// Listening
	
	$client    = @socket_accept($sock);
	$client_ip = "Error";
	$request   = "";
	
	if($client) {
		// A client has connected
		// Get IP address
		socket_getpeername($client, $client_ip);
		
		echo " * Client connected: {$client_ip}\n";
		
		if(!addressInRange($client_ip)) {
			echo " * Client REJECTED.\n";
			
			// Send dummy response
			echo " * Sending client response .. ";
			
			$output  = "HTTP/1.0 403 Forbidden\r\n";
			$output .= "Server: {$server_name}\r\n";
			$output .= "Expires: Jan 1 1981 12:00:00 GMT\r\n";
			$output .= "Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0\r\n";
			$output .= "Pragma: no-cache\r\n";
			$output .= "Connection: Close\r\n";
			$output .= "Content-Type: text/html\r\n\r\n";
            $send    = socket_write($client, $output);
            
            if($send) echo "Sent $send bytes.\n";
            else      echo "FAILED.\n";

			//error_log("Illegal post hook request from '$client_ip': '$request'");
		} else {
			echo " * Client approved.\n";
			
			// Receive data from client
			$data = "";
			while($read = socket_read($client, 256)) {
				echo "   --> Received " . strlen($read) . " bytes\n";
				$data .= $read;
				
				preg_match("/content\-length: ([0-9]*)/", strtolower($data), $matches);
				if(!empty($matches[1])) $content_length = trim($matches[1]);
				
				if(isset($content_length)) {
					// There's a content length param, so let's make sure we read
					// all the way to the end of the data, else stop on double line break.
					
					$data_cut    = trim(substr($data, strpos($data, "\r\n\r\n")));
					$data_length = strlen($data_cut);
					
					if($data_length == $content_length) break;
				} elseif(strpos($data, "\r\n\r\n") !== false) break;
			}
			
			_log("Received GitHub request: \n{$data}");
	
			// Grab request
			preg_match("/(GET)|(POST) (.*?) HTTP.*/", $data, $matches);
			
			if(!empty($matches[3])) {
				$request = preg_replace("/[^a-zA-Z]/", "", $matches[3]);
				
				// Send dummy response
				echo " * Sending client response .. ";
				
				$output  = "HTTP/1.1 200 OK\r\n";
				$output .= "Server: {$server_name}\r\n";
				$output .= "Expires: Jan 1 1981 12:00:00 GMT\r\n";
				$output .= "Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0\r\n";
				$output .= "Pragma: no-cache\r\n";
				$output .= "Connection: Close\r\n";
				$output .= "Content-Type: text/html\r\n\r\n";
	            $send    = socket_write($client, $output);
	            
	            if($send) echo "Sent $send bytes.\n";
	            else      echo "FAILED.\n";
	            
	            // GRAB THE PAYLOAD
	            
	            $payload = trim(substr($data_cut, strpos($data_cut, "payload=") + 8));  
	            $payload = urldecode($payload);
	            $updated = strstr($payload, "server.php") ? true : false;
	            $payload = json_decode($payload);
	            
	            $repo_id = $payload->repository->name;
	            
	            
	            if(isset($repo_dirs[$repo_id])) {
		            
	            	$new_dir = $repo_dirs[$repo_id];
	            	
	            	echo " * Repository identified: $repo_id\n";
		            echo " * Changing directory to: " . $new_dir . "\n";
		            
		            if(!@chdir($new_dir)) goto close_socket;
		            
	            } else {
		            
		            echo " * I have no folder location for: {$repo_id}, exiting!\n";
		            
		            goto close_socket; 
		            
	            }
	            
	            handleRequest($client_ip, 'pull');
	            
	            if($updated) {
		            echo " * Apparently I am outdated! Restarting.\n";
		            handleRequest($client_ip, 'restart'); 
	            }
            } else {
	            echo " * Couldn't identify request! [FAILED]\n";
            }
		}
		
		close_socket:
		
		// Close socket
		socket_close($client);
		echo " * Client closed.\n";
		
		// Handle request
		if(!empty($request)) $result = handleRequest($client_ip, $request);
		
		echo "-------------------------------------------------------------------------------------------\n";
	} else {
		break;
	}
}

// Just to be safe
@socket_close($sock);

function _log($string) {
	
	global $error_log;
	
	return error_log($string, 3, $error_log);
	
}
 

function addressInRange($ip_to_check) {
	global $ip_list;
	
	foreach($ip_list as $ip) {
		if(!strstr($ip, "/")) {
			if($ip_to_check == $ip) return true;
		} else {
			list ($ip_addr, $mask) = explode ("/", $ip);
			
			$ip_addr  = ip2long($ip_addr);
			$ip_chk   = ip2long($ip_to_check);

			$ip_mask = ~((1 << (32 - $mask)) - 1);
			$net     = $ip_chk & $ip_mask;
			
			if(($net == $ip_addr)) return true;
		}
	}
}

function handleRequest($client_ip, $request) {
	global $sock, $cmd_pull, $cmd_fpull, $cmd_prior, $spacer;
	
	if(empty($request)) return false;
	
	echo " * Received request: '$request'\n";

	if($request == 'restart') {
		socket_close($sock); 
	}
	
	if($request == 'quit' || $request == 'exit') {
		@fopen("dnr", "w");
		handleRequest($client_ip, 'restart');
	}
	
	if($request == 'pull') {
		echo "\n#########################################\n";
		
		$result = "";
		
		// First we attempt the 'prior' string
		if(!empty($cmd_prior)) {
			echo "\$ " . $cmd_prior . "\n\n";
			echo trim(shell_exec($cmd_prior));
			echo "\n#########################################\n";
		}

		// Next we attempt to pull
		if(!empty($cmd_pull)) {
			echo "\$ " . $cmd_pull . "\n\n";
			$result = shell_exec($cmd_pull);
			echo trim($result);
			
			if(stristr($result, "abort") || stristr($result, "merge_head")) {
				$force_pull = true;
				echo "\n#########################################\n";
			}
		}

		// If a force pull is needed
		if(!empty($cmd_fpull) && isset($force_pull) && $force_pull == true) {
			echo "\$ " . $cmd_fpull . "\n\n";
			echo trim(shell_exec($cmd_fpull));	
		}
		
		echo $spacer . "\n";
	}
}
?>
