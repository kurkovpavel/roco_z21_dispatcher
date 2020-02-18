<?php
require __DIR__ . '/vendor/autoload.php';
$loop = \React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);
$stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);
$codes=0;
$feedback=array();
$current_place=array();
$command_place=array();
$last_command_train_time=array();
$last_command_train=array();
$timer=array();
$paused=array();
$tick;
$process;
$semaphore="0";

//UDP client creating
//IP and PORT are factory defined
$factory->createClient('192.168.0.111:21105')
	->then(
	function (React\Datagram\Socket $client) use ($stdin) {
		$client->on('message', function($message,$serverAddress,$client)  {
   				parse_answer($message,$client);
		});

		$stdin->on('data', function($data) use ($client) {
                //$client->send(trim($data));
		});
		init($client);
	},
	function(Exception $error) {
		echo "UDP_ERROR: " . $error->getMessage() ."\n";
        });
		

//function is called once when the UDP client is created.
//function sends serial number request and create WS server via subproccess.
function init($client){
	global $loop;
	global $tick;
	global $process;
	global $codes;
	init_feedback();

	$codes = parse_ini_file('codes.ini',true);
	$code = $codes['toZ21']['LAN_GET_SERIAL_NUMBER'];
	send_command($client,$code);
	echo "Websocket server creating..".PHP_EOL;
	$process = new React\ChildProcess\Process('php '.__DIR__.'/server.php start');
	$process->start($loop);
	echo "Websocket process was pushed to main loop".PHP_EOL;
	$process->stdout->on('data', function($data) use ($client) {
		$st = strpos($data, '[DATA')+5;
		$end=strpos($data, 'DATA]');
		$msg = substr($data,$st,$end-$st);
		if ($end-$st>0) {
			$msg=implode(unpack('H*',$msg));
			$codes = parse_ini_file('codes.ini',true);
			contains($msg,$codes['from_clients'],$client);
		} else {
			echo $data;
		}
	});
	//temp locating function
	//locating(1,0,$client);

	echo "Z21 connecting...".PHP_EOL;
}

//function sends codes to Z21 for associated server
//function looks for special symbols for calculating XOR-byte
function send_command($client,$code){
	if (strpos($code, 'XR')!== FALSE) {
		$xor_byte='00';
		$start_pos = strpos($code, 'XR');
		$xor_len=substr($code,$start_pos+2,1);
		for( $i=$xor_len; $i >=1 ; $i-- )
			{
			${"variable$i"} = Hex2String(substr($code,($start_pos-($i*2)),2));
			$xor_byte = bin2hex(pack('H*',$xor_byte) ^ ${"variable$i"});
 		}
		$code = str_replace('XR'.$xor_len,"",$code);
		$code.=$xor_byte;
		}
	$send_len = pack('v*', strlen(hex2bin($code))+2);
	$out = $send_len.hex2bin($code);
	$client->send($out);
}

//function is called everytime when new data comes via UDP from Z21
function parse_answer($message,$client){
	global $codes;
	$message=implode(unpack('H*',$message));
	echo contains($message,$codes['fromZ21'],$client);
}

//function is called when new data comed via UDP and WS.
//function looks for and splits any parts of packets in case of mix-packets
function contains($string, Array $search, $client) {
	foreach ($search as $i) {
	if (strpos($string, $i)!== FALSE) {
		$start_pos = strpos($string, $i);
		if ($start_pos>3) {
			$length=substr($string,$start_pos-4,4);
			$l = hexdec($length[1])+hexdec($length[0])*16+hexdec($length[3])*256+hexdec($length[2])*4096;
			if ((strlen($string)>=$l*2)&&($l>=8)) {
				$code=substr($string,$start_pos,4);
				$key = array_search($code,$search);
				$data=substr($string,$start_pos+4,($l*2)-8); 
				$string = str_replace($length.$i.$data,"",$string);
				analyze($key,$data,$client);
				return contains($string, $search, $client);
			}	
			echo $a++;
		} 
		}
	}
}

//function is called when UDP or WS packet has a true format
function analyze($key,$data,$client){
	global $command_place;
	global $current_place;
	global $feedback;
	global $loop;
	global $timer;
	global $codes;
	global $process;
	global $semaphore;
	global $paused;
	switch ($key) {
	case 'LAN_RMBUS_DATACHANGED':
		$i=hexdec(substr($data,0,2));
		for( $d=0; $d <2 ; $d++ ) {
			$value=hexdec(substr($data,$d*2+2,2));			
			for( $b=0; $b <=7 ; $b++ ) {
				$xor_bit=(extract_bits($value,$b,$b+1)) ^ ($feedback[$i.$d][$b][1]);
				if (($xor_bit===1)&&(extract_bits($value,$b,$b+1))) {
					$delay_timer=$feedback[$i.$d][$b][0];
					$now=microtime(true);
					$time_dif=$now*1.0-$delay_timer;
					if ($i===1){
						locating(3,$d*8+$b+17,$client);
					} else {
						if ($time_dif>1.2) {
							$timer[$i.$d.$b]=$loop->addTimer(1.2, function() use ($feedback,$i,$d,$b,$client) {
								locating(1,$d*8+$b+1,$client);
							});
						} else {
							if ($timer[$i.$d.$b]<>NULL){$loop->cancelTimer($timer[$i.$d.$b]);}
							locating(2,$d*8+$b+1,$client);
						}
					}
					$feedback[$i.$d][$b][0]=microtime(true);	
				}
				$feedback[$i.$d][$b][1]=extract_bits($value,$b,$b+1);
			}		
		}
	break;
	case 'LAN_GET_SERIAL_NUMBER':
		echo 'Connected to Z21, serial number is '.$data.PHP_EOL;
		$ping_timer = $loop->addPeriodicTimer(5, function () use ($codes,$client,&$semaphore)  {
			$code = $codes['toZ21']['LAN_SET_BROADCASTFLAGS'];
			send_command($client,$code); 
			system("gpio mode 1 out");
			
			if ($semaphore==="0") {
				$semaphore="1";
				system("gpio write 1 1");
				$code = $codes['toZ21']['LOCO_FUNCTION_3_F0_ON'];
				send_command($client,$code); 
				} else
				{
				$semaphore="0"; 
				system("gpio write 1 0");
				$code = $codes['toZ21']['LOCO_FUNCTION_3_F0_OFF'];
				send_command($client,$code); 
				}			
		});
		$code = $codes['toZ21']['LAN_X_SET_TRACK_POWER_ON'];
		send_command($client,$code); 
		echo 'Track way was powered on'.PHP_EOL;
		$code = $codes['toZ21']['LAN_SET_BROADCASTFLAGS'];
		send_command($client,$code); 
		echo 'R-Bus subscription added'.PHP_EOL;
		echo 'Starting trains'.PHP_EOL;
		locating(1,0,$client);
		locating(2,0,$client);
		locating(3,0,$client);
	break;

	case 'REQUEST_TRAINS':
		echo "REQUEST_TRAINS";
		$process->stdin->write('CP1='.$current_place[1].','.'CP2='.$current_place[2].','.'CP3='.$current_place[3]);
	break;
	
	case 'GO_TRAIN_1':
		$command_place[1]=hexdec(substr($data,0,2));
		locating(1,$current_place[1],$client);
		echo 'New command to place '.$command_place[1].' for train 1 via WS';
		$process->stdin->write('COM1='.$command_place[1].','.'COM2='.$command_place[2].','.'COM3='.$command_place[3]);
	break;

	case 'GO_TRAIN_2':
		$command_place[2]=hexdec(substr($data,0,2));
		locating(2,$current_place[2],$client);
		echo 'New command to place '.$command_place[2].' for train 2 via WS';
		$process->stdin->write('COM1='.$command_place[1].','.'COM2='.$command_place[2].','.'COM3='.$command_place[3]);
	break;

	case 'GO_TRAIN_3':
		$command_place[3]=hexdec(substr($data,0,2));
		locating(3,$current_place[3],$client);
		echo 'New command to place '.$command_place[3].' for train 3 via WS';
		$process->stdin->write('COM1='.$command_place[1].','.'COM2='.$command_place[2].','.'COM3='.$command_place[3]);
	break;
	
	case 'STOP_TRAINS':
		$code = $codes['toZ21']['LAN_X_SET_TRACK_POWER_OFF'];
		send_command($client,$code);
		echo "POWER TRACK OFF".PHP_EOL;
	break;
	
	case 'RESTART_ENGINE':
		send_command($client,$codes['toZ21']['LOCO_DRIVE_1_STOP']); 
		send_command($client,$codes['toZ21']['LOCO_DRIVE_2_STOP']); 
		send_command($client,$codes['toZ21']['LOCO_DRIVE_3_STOP']); 
		send_command($client,$codes['toZ21']['LAN_X_SET_TRACK_POWER_OFF']);
		init_feedback();
		send_command($client,$codes['toZ21']['LAN_X_SET_TRACK_POWER_ON']);
		locating(1,0,$client);
		locating(2,0,$client);
		locating(3,0,$client);
		echo "RESTARTING ENGINE DONE".PHP_EOL;
	break;
	
	case 'SHUTDOWN':
		send_command($client,$codes['toZ21']['LAN_X_SET_TRACK_POWER_OFF']);	
		echo "SHUTTING DOWN...".PHP_EOL;
		$process->terminate();
		$ret = exec("sudo shutdown -P now", $out, $err);
	break;
	
	case 'PAUSE_1_ON':
	$paused[1]=1;
	break;
	
	case 'PAUSE_1_OFF':
	$paused[1]=0;	
	break;
	
	case 'PAUSE_2_ON':
	$paused[2]=1;	
	break;

	case 'PAUSE_2_OFF':
	$paused[2]=0;	
	break;

	case 'PAUSE_3_ON':
	$paused[3]=1;	
	break;

	case 'PAUSE_3_OFF':
	$paused[3]=0;	
	break;
	}
}

//function saves the current position on the train and runs routing
function locating($train,$sensor,$client) {
	global $current_place;
	global $command_place;
	global $process;
	//fixing sensor info if sensor gave unpossible result 
	if (($current_place[1]>0)&&($current_place[2]>0)&&($train!==3)) {
		if (($sensor===$current_place[2])&&($train===1)) {$train=2;}
	}
	$current_place[$train]=$sensor;
	$process->stdin->write('CRP'.$train.'='.$current_place[$train].',');
	$process->stdin->write('COM'.$train.'='.$command_place[$train].',');
	print_r($current_place);	
	routing($train,$current_place[$train],0,$client,0);
}

//function is used for stop train if the distance  btw trains is dangerous
function collision_control($client,$train,$pos) {
	global $current_place;
	global $command_place;
	global $codes;
	$code = $codes['parking_place']['PARKING'];
	$parking_array=explode(",",$code);
	
	if (($current_place[1]===0)||($current_place[2]===0)) {
		return 0;
	}
	if (($current_place[1]>$current_place[2])&&($current_place[1]>0)&&($current_place[2]>0)) {
		if (($current_place[1]-$current_place[2]<4)&&(in_array($current_place[1], $parking_array))) {
		 return stop_and_renew(2,$client,$pos);
		}
	}
	if (($current_place[1]<$current_place[2])&&($current_place[1]>0)&&($current_place[2]>0)) {
		if (($current_place[2]-$current_place[1]<4)&&(in_array($current_place[2], $parking_array))) {
		 return stop_and_renew(1,$client,$pos);
		}
	}
	if (($current_place[1]>$current_place[2])&&($current_place[1]>0)&&($current_place[2]>0)) {
		if (($current_place[1]-$current_place[2]<4)&&(!in_array($current_place[1], $parking_array))) {
		 return stop_and_renew(2,$client,$pos);
		}
	}
	if (($current_place[1]<$current_place[2])&&($current_place[1]>0)&&($current_place[2]>0)) {
		if (($current_place[2]-$current_place[1]<4)&&(!in_array($current_place[2], $parking_array))) {
		 return stop_and_renew(1,$client,$pos);
		}
	}	
	if (($current_place[1]===16)&&($current_place[2]===1)){
		return stop_and_renew(1,$client,$pos);
	}
	if (($current_place[2]===16)&&($current_place[1]===1)){
		return stop_and_renew(2,$client,$pos);
	}
	return 0;
}

//function make promise for recover current command when collision is possible
function stop_and_renew($train,$client,$pos) {
	global $codes;
	global $current_place;
	global $loop;
	$cur_place=$current_place[$train];
	send_command($client,$codes['toZ21']['LOCO_DRIVE_'.$train.'_STOP']); 
	$loop->addTimer(0.5, function() use ($train,$cur_place,$client,$pos) {
						routing($train,$cur_place,$pos,$client,1);	
						});
	return $train;
}

//main function that works with map commands
function routing($train,$cur_place,$pos,$client,$colis_stat) {
	global $loop;
	global $codes;
	global $current_place;
	global $command_place;
	global $last_command_train_time;
	global $last_command_train;
	global $paused;
	if ($cur_place!==$current_place[$train]){return 0;}
	$col_train=collision_control($client,$train,$pos);
	if ($col_train===$train) { 
		echo "COLLISION:STOPING TRAIN ".$col_train." ";
		return 0; 
		}
	//recover last drive command after collision control
	if ($colis_stat===1){ send_command($client,$codes['toZ21'][$last_command_train[$train]]); }
	
	$code = $codes['route_tr'.$train][$current_place[$train].'-'.$command_place[$train]];
	$command_array=explode(",",$code);
	for( $d=$pos; $d <count($command_array) ; $d++ ) {
			if (substr($command_array[$d],0,4)==='GOTO') {
				$command_place[$train]=intval(substr($command_array[$d],4,2));
				echo "GOTO".$command_place[$train].",";
				return routing($train,$cur_place,0,$client,0);
			} else	{
				if (substr($command_array[$d],0,4)==='PLAY') {
						$sound_number=substr($command_array[$d],4,2);
						echo "PLAY".$sound_number.PHP_EOL;
						$sound = new React\ChildProcess\Process('php '.__DIR__.'/sounds.php '.$sound_number);
						$sound->start($loop);
					} else {
						if (substr($command_array[$d],0,4)==='RELE') {
							$relay_status=substr($command_array[$d],4,2);
							if ($relay_status==='00') {
								echo "RELAY is off".PHP_EOL;
								system("gpio mode 11 out");
								system("gpio write 11 0");
							}
							if ($relay_status==='01') {
								echo "RELAY is on".PHP_EOL;
								system("gpio mode 11 out");
								system("gpio write 11 1");
							}							
						} else {
							$parts = preg_split('/[\s]+/', $command_array[$d]);
							$com_part=trim($parts[0]);
							if (stripos($com_part, "LOCO_DRIVE")!==false) {
								$last_command_train[$train]=$com_part;
							}
							$code = $codes['toZ21'][$com_part];	
							echo $command_array[$d].',';
							if ($client!=NULL) {send_command($client,$code);} 	
							if (count($parts)>1) {
								$tick_value=trim($parts[1]);
								$last_command_train_time[$train]=microtime(true);
								$tick[$train.$current_place[$train].$command_place[$train]]=$loop->addTimer($tick_value, function() use ($d,$train,$cur_place,$client) {
									return routing($train,$cur_place,$d+1,$client,0);
								});
								return 0;
							}
						}
					}
			}	
	}
}

//auxiliary function for converting hex
function Hex2String($hex) {
	$st='';
	for ($i=0;$i<strlen($hex)-1;$i+=2){
		$st.=chr(hexdec($hex[$i].$hex[$i+1]));
	}
	return $st;
}

//auxiliary function for check bit in the byte
function extract_bits($value, $start_pos, $end_pos) {
    $mask = (1 << ($end_pos - $start_pos)) - 1;
    return ($value >> $start_pos) & $mask;
}

//first initialization of the variables
function init_feedback() {
	global $feedback;
	global $current_place;
	global $command_place;
	global $paused;
	$paused[1]=0;
	$paused[2]=0;
	$paused[3]=0;
	$current_place[1]=0;
	$current_place[2]=0;
	$current_place[3]=0;
	$command_place[1]=0;
	$command_place[2]=0;
	$command_place[3]=0;
	for( $i=0; $i <2 ; $i++ ) {
		for( $b=0; $b <=7 ; $b++ ) {
		$time_current=microtime(true);
		$feedback["0".$i][$b][0]=0.0;	
		$feedback["0".$i][$b][1]='0';
		$feedback["1".$i][$b][0]=0.0;	
		$feedback["1".$i][$b][1]='0';
		}
	}
}

$loop->run();
