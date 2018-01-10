<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/includes/cli.php
 *
 * Various functions for UNetLab CLI handler.
 *
 * @author Andrea Dainese <andrea.dainese@gmail.com>
 * @copyright 2014-2016 Andrea Dainese
 * @license BSD-3-Clause https://github.com/dainok/unetlab/blob/master/LICENSE
 * @link http://www.unetlab.com/
 * @version 20160719
 */

/**
 * Function to add a network.
 *
 * @param   Array   $p                  Parameters
 * @return  int                         0 means ok
 */
function addNetwork($p) {
	if (!isset($p['name']) || !isset($p['type'])) {
		// Missing mandatory parameters
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80021]);
		return 80021;
	}
	error_log(date('M d H:i:s ').'INFO: Adding Network '.$p['name'].' '.$p['type']);
	switch ($p['type']) {
		default:
			if (in_array($p['type'], listClouds())) {
				error_log(date('M d H:i:s ').'INFO: Is Cloud '.$p['type']);
				// Cloud already exists
			} else if (preg_match('/^pnet[0-9]+$/', $p['type'])) {
				// Cloud does not exist
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80056]);
				return 80056;
			} else {
				// Should not be here
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80020]);
				return 80020;
			}
			break;
		case 'bridge':
			if (!isInterface($p['name'])) {
				// Interface does not exist -> create bridge
				return addOvs($p['name']);
			} else if (isOVS($p['name'])) {
				// Bridge already present
				return 0;
			} else {
				// Non bridge/OVS interface exist -> cannot create
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80022]);
				return 80022;
			}
			break;
		case 'ovs':
			if (!isInterface($p['name'])) {
				// Interface does not exist -> create OVS
				return addOvs($p['name']);
			} else if (isOvs($p['name'])) {
				// OVS already present
				return 0;
			} else {
				// Non bridge/OVS interface exist -> cannot create
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80022]);
				return 80022;
			}
			break;
	}
	return 0;
}

/*
 * Function to create an OVS
 *
 * @param   string  $s                  OVS name
 * @return  int                         0 means ok
 */

function captureInterface($lab, $id) {
        // Docker capture node container name
        $config_ini = parse_ini_file('.config');
        $captureNodeName = $config_ini['docker_container_name'];

        $cmd = 'docker -H=tcp://127.0.0.1:4243 inspect --format "{{ .State.Pid }}" '.$captureNodeName.' 2>&1';
        exec($cmd, $pida, $rc);

        $pid = $pida[0];

        $uriSplit = explode('/', $_SERVER['REQUEST_URI']);
        $interface = current(explode('?',end($uriSplit)));

        $sif=$interface;
        $dif='cap'.$interface;

        // find the bridge which the port uses
        $cmd = 'ovs-vsctl  port-to-br '.$sif.' 2>&1';
        exec($cmd, $bridgea, $rc);
        error_log('INFO: Bridge found '.$cmd.'');

        $bridge = $bridgea[0];


        $cmd = 'ovs-vsctl add-port '.$bridge.' '.$dif.' -- set interface '.$dif.' type=internal';
        exec($cmd, $output, $rc);
        error_log('INFO: Adding port to capture node '.$cmd.'');

      // create mirror on ovs
        $cmd = "ovs-vsctl ".
                "-- --id=@m create mirror name=mirror".$bridge." ".
                " -- add bridge ".$bridge." mirrors @m ".
                " -- --id=@".$sif." get port ".$sif." ".
                " -- set mirror mirror".$bridge." select_src_port=@".$sif." select_dst_port=@".$sif." ".
                " -- --id=@".$dif." get port ".$dif." ".
                " -- set mirror mirror".$bridge." output-port=@".$dif." ";

        exec($cmd, $output, $rc);

        // Attach capture interface to docker wireshark node
        $cmd = "ip link set netns ".$pid." ".$dif." name ".$dif."  up 2>&1";

        exec($cmd, $output, $rc);
        if ($rc == 0) {
                $output['code'] = 200;
                $output['status'] = 'success';
                $output['message'] = 'Interface Added';
        }
        else {
                $output['code'] = 400;
                $output['status'] = 'fail';
                $output['message'] = 'Failed to Add Interface';
        }
        return $output;

}

function addOvs($s) {

	if (!isOvs($s)) {
		$cmd = 'ovs-vsctl add-br '.$s.' 2>&1';
		exec($cmd, $o, $rc);
		if ($rc != 0) {
			// Failed to add the OVS
			//error_log(date('M d H:i:s ').'INFO: Failed adding net'.$cmd);
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80023]);
			error_log(date('M d H:i:s ').implode("\n", $o));
			return 80023;
		}
		// ADD BPDU CDP option
		$cmd = "ovs-vsctl set bridge ".$s." other-config:forward-bpdu=true";
    	         error_log(date('M d H:i:s ').'INFO: Setting bpdu forwarding '.$cmd);

		exec($cmd, $o, $rc);
		if ($rc == 0) {
			return 0;
		} else {
			// Failed to add  OVS OPTION
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80023]);
			error_log(date('M d H:i:s ').implode("\n", $s));
			return 80023;
		}
	}
}

/**
 * Function to create a TAP interface
 *
 * @param   string  $s                  Network name
 * @return  int                         0 means ok
 */
function addTap($s, $u) {
	// TODO if already exist should fail?
	$cmd = 'tunctl -u '.$u.' -g root -t '.$s.' 2>&1';
	error_log(date('M d H:i:s ').'INFO: '.$cmd);
	exec($cmd, $o, $rc);
	if ($rc != 0) {
		// Failed to add the TAP interface
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80032]);
		error_log(date('M d H:i:s ').implode("\n", $o));
		return 80032;
	}

	$cmd = 'ip link set dev '.$s.' up 2>&1';
	exec($cmd, $o, $rc); 
	if ($rc != 0) {
		// Failed to activate the TAP interface
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80033]);
		error_log(date('M d H:i:s ').implode("\n", $o));
		return 80033;
	}

	$cmd = 'ip link set dev '.$s.' mtu 9000';
	exec($cmd, $o, $rc); 
	if ($rc != 0) {
		// Failed to activate the TAP interface
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80085]);
		error_log(date('M d H:i:s ').implode("\n", $o));
		return 80085;
	}

	return 0;
}

/**
 * Function to check if a tenant has a valid username.
 *
 * @param   int     $i                  Tenant ID
 * @return  bool                        True if valid
 */
function checkUsername($i) {
	if ((int) $i < 0) {
		// Tenand ID is not valid
		return False;
	} else {
		// Just to be sure
		$i = (int) $i;
	}

	$path = '/opt/unetlab/tmp/'.$i;
	$uid = 32768 + $i;

	$cmd = 'id unl'.$i.' 2>&1';
	exec($cmd, $o, $rc);
	if ($rc != 0) {
		// Need to add the user
		$cmd = '/usr/sbin/useradd -c "Unified Networking Lab TID='.$i.'" -d '.$path.' -g unl -M -s /bin/bash -u '.$uid.' unl'.$i.' 2>&1';
		exec($cmd, $o, $rc);
		if ($rc != 0) {
			// Failed to add the username
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80009]);
			error_log(date('M d H:i:s ').implode("\n", $o));
			return False;
		}
	}

	// Now check if the home directory exists
	if (!is_dir($path) && !mkdir($path)) {
		// Failed to create the home directory
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80010]);
		return False;
	}

	// Be sure of the setgid bit
	$cmd = 'chmod 2775 '.$path;
	exec($cmd, $o, $rc);
	if ($rc != 0) {
		// Failed to set the setgid bit
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80011]);
		error_log(date('M d H:i:s ').implode("\n", $o));
		return False;
	}

	// Set permissions
	if (!chown($path, 'unl'.$i)) {
		// Failed to set owner and/or group
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80012]);
		return False;
	}

	// Last, link the profile
	if (!file_exists($path.'/.profile') && !symlink('/opt/unetlab/wrappers/unl_profile', $path.'/.profile')) {
		// Failed to link the profile
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80013]);
		return False;
	}

	return True;
}

/**
 * Function to connect an interface (TAP) to a network (Bridge/OVS)
 *
 * @param   string  $n                  Network name
 * @param   string  $p                  Interface name
 * @param   string  $nodeType                  Node type 
 * @return  int                         0 means ok
 */
function connectInterface($n, $p, $nodeType) {
	// Make sure bridge exists before adding port
	if (isOvs($n)) {
		if($nodeType == 'docker') {
			$cmd = 'ovs-vsctl add-port '.$n.' '.$p.' -- set interface '.$p.' type=internal 2>&1';
		} else {
			$cmd = 'ovs-vsctl --may-exist add-port '.$n.' '.$p.' 2>&1';
		}
		exec($cmd, $o, $rc);
		error_log(date('M d H:i:s ').'INFO: adding port to ovs '.$cmd);
		if ($rc == 0) {
			return 0;
		} else {
			// Failed to add interface to OVS
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80031]);
			error_log(date('M d H:i:s ').implode("\n", $o));
			return 80031;
		}
	} else {
		// Network not found
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80029]);
		return 80029;
	}
}

/**
 * Function to delete an OVS
 *
 * @param   string  $s                  OVS name
 * @return  int                         0 means ok
 */
function delOvs($s) {
	$cmd = 'ovs-vsctl del-br '.$s.' 2>&1';
	exec($cmd, $o, $rc);
	if ($rc == 0) {
		return 0;
	} else {
		// Failed to delete the OVS
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80024]);
		error_log(date('M d H:i:s ').implode("\n", $o));
		return 80024;
	}
}
/**
 * Function to disconnect a node port from an ovs bridge or pnet
 *
 * @param   string  $s                  OVS name
 * @return  int                         0 means ok
 */
function disconnectNodePort($s) {
	$cmd = 'ovs-vsctl del-port '.$s.' 2>&1';
        exec($cmd, $o, $rc);
	return 0;

}
/**
 * Function to delete a TAP interface
 *
 * @param   string  $s                  Interface name
 * @return  int                         0 means ok
 */
function delTap($s) {
	if (isInterface($s)) {
		// Remove interface from OVS switches
		$cmd = 'ovs-vsctl del-port '.$s.' 2>&1';
		exec($cmd, $o, $rc);

		// Delete TAP (so it's removed from bridges too)
		$cmd = 'tunctl -d '.$s.' 2>&1';
		exec($cmd, $o, $rc);
		if (isInterface($s)) {
			// Failed to delete the TAP interface
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80034]);
			error_log(date('M d H:i:s ').implode("\n", $o));
			return 80034;
		} else {
			return 0;
		}
	} else {
		// Interface does not exist
		return 0;
	}
}
/**
 * Function to delete a ovs-port interface
 *
 * @param   string  $s                  Interface name
 * @return  int                         0 means ok
 */
function delOvsPort($s) {
                // Remove interface from OVS switches
                $cmd = 'ovs-vsctl del-port '.$s.' 2>&1';
                exec($cmd, $o, $rc);

                // Interface does not exist
                return 0;
}
/**
 * Function to push startup-config to a file
 *
 * @param   string  $config_data        The startup-config
 * @param   string  $file_path          File with full path where config is stored
 * @return  bool                        true if config dumped
 */
function dumpConfig($config_data, $file_path) {
	$fp = fopen($file_path, 'w');
	if (!isset($fp)) {
		// Cannot open file
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80068]);
		return False;
	}

	if (!fwrite($fp, $config_data)) {
		// Cannot write file
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80069]);
		return False;
	}

	return True;
}

/**
 * Function to export a node running-config.
 *
 * @param   int     $node_id            Node ID
 * @param   Node    $n                  Node
 * @param   Lab     $lab                Lab
 * @return  int                         0 means ok
 */
function export($node_id, $n, $lab , $uid ) {
	$tmp = tempnam(sys_get_temp_dir(), 'unl_cfg_'.$node_id.'_');

	if (is_file($tmp) && !unlink($tmp)) {
		// Cannot delete tmp file
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80059]);
		return 80059;
	}

	switch ($n -> getNType()) {
		default:
			// Unsupported
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80061]);
			return 80061;
			break;
		case 'dynamips':
			foreach (scandir($n -> getRunningPath()) as $filename) {
				if (preg_match('/_nvram$/', $filename)) {
					$nvram = $n -> getRunningPath().'/'.$filename;
					break;
				} else if (preg_match('/_rom$/', $filename)) {
					$nvram = $n -> getRunningPath().'/'.$filename;
					break;
				}
			}

			if (!isset($nvram) || !is_file($nvram)) {
				// NVRAM file not found
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80066]);
				return 80066;
			}
			$cmd='/opt/unetlab/scripts/wrconf_dyn.py -p '.$n -> getPort().' -t 15';
			exec($cmd, $o, $rc);
			error_log(date('M d H:i:s ').'INFO: force write configuration '.$cmd);
			if ($rc != 0) {
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80060]);
				error_log(date('M d H:i:s ').(string) $o);
				return 80060;
			}
			$cmd = '/usr/bin/nvram_export '.$nvram.' '.$tmp;
			exec($cmd, $o, $rc);
			error_log(date('M d H:i:s ').'INFO: exporting '.$cmd);
			if ($rc != 0) {
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80060]);
				error_log(date('M d H:i:s ').(string) $o);
				return 80060;
			}
			break;
		case 'vpcs':
			if (!is_file($n->getRunningPath().'/startup.vpc')) {
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80062]);
			} else {
				copy($n->getRunningPath().'/startup.vpc',$tmp);
			}
			break;
		case 'iol':
			// (( device_id & 0x3f ) << 4 ) | ( tenant_id & 0xf )
			$nvram = $n -> getRunningPath().'/nvram_'.sprintf('%05u', (( $node_id & 0x3f ) << 4 | ( $uid & 0xf)) );
			if (!is_file($nvram)) {
				// NVRAM file not found
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80066].'for iolID '.(( $node_id & 0x3f ) << 4 | ( $uid & 0xf)) );
				return 80066;
			}
			$cmd='/opt/unetlab/scripts/wrconf_iol.py -p '.$n -> getPort().' -t 15';
			exec($cmd, $o, $rc);
			error_log(date('M d H:i:s ').'INFO: force write configuration '.$cmd);
			if ($rc != 0) {
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80060]);
				error_log(date('M d H:i:s ').(string) $o);
				return 80060;
			}
			$cmd = '/opt/unetlab/scripts/iou_export '.$nvram.' '.$tmp;
			exec($cmd, $o, $rc);
			usleep(1);
			error_log(date('M d H:i:s ').'INFO: exporting '.$cmd);
			if ($rc != 0) {
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80060]);
				error_log(date('M d H:i:s ').implode("\n", $o));
				return 80060;
			}
			// Add no shut
			if (is_file($tmp)) file_put_contents($tmp,preg_replace('/(\ninterface.*)/','$1'.chr(10).' no shutdown',file_get_contents($tmp)));
			break;
		case 'qemu':
			if ($n -> getStatus() < 2 || !isset($GLOBALS['node_config'][$n -> getTemplate()])) {
				// Skipping powered off nodes or unsupported nodes
				error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][80084]);
				return 80084;
			} else {
				$timeout = 15;
				// Depending on configuration's size, export from mikrotik could take longer than 15 seconds
				if ($n -> getTemplate() == 'mikrotik') {
					$timeout = 45;
				}
				$cmd = '/opt/unetlab/scripts/'.$GLOBALS['node_config'][$n -> getTemplate()].' -a get -p '.$n -> getPort().' -f '.$tmp.' -t '.$timeout;
				exec($cmd, $o, $rc);
				error_log(date('M d H:i:s ').'INFO: exporting '.$cmd);
				if ($rc != 0) {
					error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80060]);
					error_log(date('M d H:i:s ').implode("\n", $o));
					return 80060;
				}
				// Add no shut
				if ( ( $n->getTemplate() == "csr1000vng" || $n->getTemplate() == "csr1000v" || $n->getTemplate() == "crv" || $n->getTemplate() == "vios" || $n->getTemplate() == "viosl2" || $n->getTemplate() == "xrv" || $n->getTemplate() == "xrv9k" ) && is_file($tmp) ) file_put_contents($tmp,preg_replace('/(\ninterface.*)/','$1'.chr(10).' no shutdown',file_get_contents($tmp)));
			}
	}

	if (!is_file($tmp)) {
		// File not found
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80062]);
		return 80062;
	}

	// Now save the config file within the lab
	clearstatcache();
	$fp = fopen($tmp, 'r');
	if (!isset($fp)) {
		// Cannot open file
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80064]);
		return 80064;
	}
	$config_data = fread($fp ,filesize($tmp));
	if ($config_data === False || $config_data === ''){
		// Cannot read file
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80065]);
		return 80065;
	}

	if ($lab -> setNodeConfigData($node_id, $config_data) !== 0) {
		// Failed to save startup-config
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80063]);
		return 80063;
	}

	if(!unlink($tmp)) {
		// Failed to remove tmp file
		error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][80070]);
	}

	return 0;
}

/**
 * Function to check if a interface exists
 *
 * @param   string  $s                  Interface name
 * @return  bool                        True if exists
 */
function isInterface($s) {
	$cmd = 'ip link show '.$s.' 2>&1';
	exec($cmd, $o, $rc);
	if ($rc == 0) {
		return True;
	} else {
		return False;
	}
}

/**
 * Function to check if an OVS exists
 *
 * @param   string  $s                  OVS name
 * @return  bool                        True if exists
 */
function isOvs($s) {
	$cmd = 'ovs-vsctl br-exists '.$s.' 2>&1';
	exec($cmd, $o, $rc);
	if ($rc == 0) {
		return True;
	} else {
		return False;
	}
}

/**
 * Function to check if a node is running.
 *
 * @param   int     $p                  Port
 * @return  bool                        true if running
 */
function isRunning($p) {
	// If node is running, the console port is used
	$cmd = 'fuser -n tcp '.$p.' 2>&1';
	exec($cmd, $o, $rc);
	if ($rc == 0) {
		return True;
	} else {
		return False;
	}
}

/**
 * Function to check if a TAP interface exists
 *
 * @param   string  $s                  Interface name
 * @return  bool                        True if exists
 */
function isTap($s) {
	if (is_dir('/sys/class/net/'.$s)) {
		// TODO can be bridge or OVS
		return True;
	} else {
		return False;
	}
}

/**
 * Function to prepare a node before starging it
 *
 * @param   Node    $n                  The Node
 * @param   Int     $id                 Node ID
 * @param   Int     $t                  Tenant ID
 * @param   Array   $nets               Array of networks
 * @return  int                         0 Means ok
 */
function prepareNode($n, $id, $t, $nets) {
	$user = 'unl'.$t;

	// Get UID from username
	$cmd = 'id -u '.$user.' 2>&1';
	exec($cmd, $o, $rc);
	$uid = $o[0];
	

	// Creating TAP interfaces
	 if ($n -> getNType() != 'docker') {
	foreach ($n -> getEthernets() as $interface_id => $interface) {
		error_log(date('M d H:i:s ').'INFO: interface found '.print_r($interface));
		$tap_name = 'vunl'.$t.'_'.$id.'_'.$interface_id;
		if (isset($nets[$interface -> getNetworkId()]) && $nets[$interface -> getNetworkId()] -> isCloud()) {
			// Network is a Cloud
			$net_name = $nets[$interface -> getNetworkId()] -> getNType();
		} else {
			$net_name = 'vnet'.$t.'_'.$interface -> getNetworkId();
		}

		// Remove interface
		$rc = delTap($tap_name);
		if ($rc !== 0) {
			// Failed to delete TAP interface
			return $rc;
		}


		// Add interface
		$rc = addTap($tap_name, $user);
		if ($rc !== 0) {
			// Failed to add TAP interface
			return $rc;
		}
		//error_log(date('M d H:i:s ').'INFO: Adding Interface    '.$interface -> getNetworkId());
		if ($interface -> getNetworkId() !== 0) {
			// Connect interface to network
			$rc = connectInterface($net_name, $tap_name,$n -> getNType());
			if ($rc !== 0) {
				// Failed to connect interface to network
				return $rc;
			}
		}
	}
	}
	// Prepare SNAT for RDP 
	// iptables -t nat -I INPUT -p tcp --dport 11455  -j SNAT --to 169.254.1.102
	if ($n -> getConsole() == 'rdp' ) {
		$cmd = 'iptables -t nat -D INPUT -p tcp --dport '.$n -> getPort().' -j SNAT --to 169.254.1.102' ;
		exec($cmd, $o, $rc);
		$cmd = 'iptables -t nat -I INPUT -p tcp --dport '.$n -> getPort().' -j SNAT --to 169.254.1.102' ; 
		exec($cmd, $o, $rc);
	}
	// Dropping privileges
	posix_setsid();
	posix_setgid(32768);
	if ($n -> getNType() == 'iol' && !posix_setuid($uid)) {
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80036]);
		return 80036;
	}

	// Transition fix: mark the node as prepared (TODO)
	if (is_dir($n -> getRunningPath())) !touch($n -> getRunningPath().'/.prepared');

	if (!is_file($n -> getRunningPath().'/.prepared') && !is_file($n -> getRunningPath().'/.lock')) {

		// Node is not prepared/locked
		if (!is_dir($n -> getRunningPath()) && !mkdir($n -> getRunningPath(), 0775, True)) {
			// Cannot create running directory
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80037]);
			return 80037;
		}

		switch ($n -> getNType()) {
			default:
				// Invalid node_type
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80038]);
				return 80038;
			case 'iol':
				// Check license
				if (!is_file('/opt/unetlab/addons/iol/bin/iourc')) {
					// IOL license not found
					error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80039]);
					return 80039;
				}

				if (!file_exists($n -> getRunningPath().'/iourc') && !symlink('/opt/unetlab/addons/iol/bin/iourc', $n -> getRunningPath().'/iourc')) {
					// Cannot link IOL license
					error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80040]);
					return 80040;
				}

				break;
			case 'docker':
				if (!is_file('/usr/bin/docker')) {
					// docker.io is not installed
					error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80082]);
					return 80082;
				}
				if($n -> getCustomConsolePort() != '' ) {
					$connPort = $n -> getCustomConsolePort();
				}
				elseif ($n -> getConsole() == 'vnc') {
					$connPort = 5900;
				}
				elseif ($n -> getConsole() == 'rdp' ) {
					$connPort = 3389;
				}
				elseif ($n -> getConsole() == 'ssh' ) {
					$connPort = 22;
				}
				else {
					$connPort = 23;
				}
				$cmd = 'docker -H=tcp://127.0.0.1:4243 inspect --format="{{ .State.Running }}" '.$n -> getUuid();

				exec($cmd, $o, $rc);
				if ($rc != 0) {
					// Must create docker.io container
					$cmd = 'docker -H=tcp://127.0.0.1:4243 create -ti --memory '.$n -> getRam().'M --privileged --net=bridge  -p '.$n -> getPort().':'.$connPort.' --name='.$n -> getUuid().' -h '.$n -> getName().' '.$n -> getImage();
					error_log(date('M d H:i:s ').'INFO: starting '.$cmd);		
					exec($cmd, $o, $rc);
					if ($rc != 0) {
						// Failed to create container
						error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80083]);
						return 80083;
					}
				}
				break;
			case 'vpcs':
				if (!is_file('/opt/vpcsu/bin/vpcs')) {
					error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80088]);
					return 80082;
				}
				break;
			case 'dynamips':
				// Nothing to do
				break;
			case 'qemu':
				$image = '/opt/unetlab/addons/qemu/'.$n -> getImage();

				if (!touch($n -> getRunningPath().'/.lock')) {
					// Cannot lock directory
					error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80041]);
					return 80041;
				}

				// Copy files from template
				foreach(scandir($image) as $filename) {
					if (preg_match('/^[a-zA-Z0-9]+.qcow2$/', $filename)) {
						// TODO should check if file exists
						$cmd = '/opt/qemu/bin/qemu-img create -b "'.$image.'/'.$filename.'" -f qcow2 "'.$n -> getRunningPath().'/'.$filename.'"';
						exec($cmd, $o, $rc);
						if ($rc !== 0) {
							// Cannot make linked clone
							error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80045]);
							error_log(date('M d H:i:s ').implode("\n", $o));
							return 80045;
						}
					}

				}

				if (!unlink($n -> getRunningPath().'/.lock')) {
					// Cannot unlock directory
					error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80042]);
					return 80042;
				}
				break;
		}

		if ($n -> getConfig() == '1' && $n -> getConfigData() != '') {
			// Node should use saved startup-config
			if (!dumpConfig($n -> getConfigData(), $n -> getRunningPath().'/startup-config')) {
				// Cannot dump config to startup-config file
				error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][80067]);
			} else {
				switch ($n -> getTemplate()) {
					default:
						break;
					case 'xrv':
					case 'xrv9k':
						copy (  $n -> getRunningPath().'/startup-config',  $n -> getRunningPath().'/iosxr_config.txt');
						$isocmd = 'mkisofs -o '.$n -> getRunningPath().'/config.iso -l --iso-level 2 '.$n -> getRunningPath().'/iosxr_config.txt' ;
						exec($isocmd, $o, $rc);
						break;
					case 'csr1000v':
					case 'csr1000vng':
						copy (  $n -> getRunningPath().'/startup-config',  $n -> getRunningPath().'/iosxe_config.txt');
						$isocmd = 'mkisofs -o '.$n -> getRunningPath().'/config.iso -l --iso-level 2 '.$n -> getRunningPath().'/iosxe_config.txt' ;
						exec($isocmd, $o, $rc);
						break;
					case 'asav':
						copy (  $n -> getRunningPath().'/startup-config',  $n -> getRunningPath().'/day0-config');
						$isocmd = 'mkisofs -r -o '.$n -> getRunningPath().'/config.iso -l --iso-level 2 '.$n -> getRunningPath().'/day0-config' ;
						exec($isocmd, $o, $rc);
						break;
					case 'titanium':
					case 'nxosv9k':
						copy (  $n -> getRunningPath().'/startup-config',  $n -> getRunningPath().'/nxos_config.txt');
						$isocmd = 'mkisofs -o '.$n -> getRunningPath().'/config.iso -l --iso-level 2 '.$n -> getRunningPath().'/nxos_config.txt' ;
						exec($isocmd, $o, $rc);
						break;
					case 'timos':
					case 'timoscpm':
						$floppycmd = 'mkdosfs -C '.$n ->  getRunningPath().'/floppy.img 1440';
						exec($floppycmd, $o, $rc);
						$floppycmd = 'mkdir '.$n ->  getRunningPath().'/floppy';
						exec($floppycmd, $o, $rc);
						$floppycmd = 'modprobe loop ; mount '.$n ->  getRunningPath().'/floppy.img '.$n ->  getRunningPath().'/floppy';
						exec($floppycmd, $o, $rc);
						copy (  $n -> getRunningPath().'/startup-config', $n -> getRunningPath().'/floppy/config.cfg');
						$floppycmd = 'umount '.$n ->  getRunningPath().'/floppy';
						exec($floppycmd, $o, $rc);
						break;	
					case 'vios':
					case 'viosl2':
						copy (  $n -> getRunningPath().'/startup-config',  $n -> getRunningPath().'/ios_config.txt');
						$diskcmd = '/opt/unetlab/scripts/createdosdisk.sh '.$n -> getRunningPath() ;
						exec($diskcmd, $o, $rc);
						break;
					case 'vsrxng':
					case 'vmxvcp':
					case 'vmx':
					case 'vqfxre':
					case 'vsrx':
					case 'junipervrr':
						copy (  $n -> getRunningPath().'/startup-config',  $n -> getRunningPath().'/juniper.conf');
						$isocmd = 'mkisofs -o '.$n -> getRunningPath().'/config.iso -l --iso-level 2 '.$n -> getRunningPath().'/juniper.conf' ;
						exec($isocmd, $o, $rc);
						break;
					case 'veos':
						$diskcmd = '/opt/unetlab/scripts/veos_diskmod.sh '.$n -> getRunningPath() ;
						exec($diskcmd, $o, $rc);
						break;
					case 'vpcs':
						copy ($n -> getRunningPath().'/startup-config',  $n -> getRunningPath().'/startup.vpc');
						break;
					case 'pfsense':
						copy (  $n -> getRunningPath().'/startup-config',  $n -> getRunningPath().'/config.xml');
						$isocmd = 'mkisofs -o '.$n -> getRunningPath().'/config.iso -l --iso-level 2 '.$n -> getRunningPath().'/config.xml' ;
						exec($isocmd, $o, $rc);
						break;
				}
			}
		}
		// Mark the node as prepared
		if (!touch($n -> getRunningPath().'/.prepared')) {
			// Cannot write on directory
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80044]);
			return 80044;
		}

	}

	return 0;
}

/**
 * Function to start a node.
 *
 * @param   Node    $n                  Node
 * @param   Int     $id                 Node ID
 * @param   Int     $t                  Tenant ID
 * @param   Array   $nets               Array of networks
 * @param   int     $scripttimeout      Config Script Timeout
 * @return  int                         0 means ok
 */
function start($n, $id, $t, $nets, $scripttimeout) {
	//	exec('cat '.print_r($id).' > out2.txt');
	$user = 'unl'.$t;
	//	file_put_contents("out2.txt",print_r($n).print_r($id));
	if ($n -> getStatus() !== 0) {
		// Node is in running or building state
		return 0;
	}

	$rc = prepareNode($n, $id, $t, $nets);
	if ($rc !== 0) {
		error_log(date('M d H:i:s ').'INFO: Failed to Prepare Node ');
		// Failed to prepare the node
		return $rc;
	}

	list($bin, $flags) = $n -> getCommand();

	if ($bin == False || $flags == False) {
		// Invalid CMD line
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80046]);
		return 80046;
	}

	if(!chdir($n -> getRunningPath())) {
		// Failed to change directory
		error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80047]);
		return 80047;
	}

	// Starting the node
	switch ($n -> getNType()) {
		default:
			// Invalid node_type
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80038]);
			return 80028;
		case 'iol':
			$cmd = '/opt/unetlab/wrappers/iol_wrapper -T '.$t.' -D '.$id.' -t "'.$n -> getName().'" -F /opt/unetlab/addons/iol/bin/'.$n -> getImage().' -d '.$n -> getDelay().' -e '.$n -> getEthernetCount().' -s '.$n -> getSerialCount();
			// Adding Serial links
			foreach ($n -> getSerials() as $interface_id => $interface) {
				if ($interface -> getRemoteId() > 0) {
					//$cmd .= ' -l '.$interface_id.':localhost:'.$interface -> getRemoteId().':'.$interface -> getRemoteIf();
					$cmd .= ' -l '.$interface_id.':localhost:'.(((( $interface -> getRemoteId()) & 0x3f ) << 4 ) | ( $t & 0xf )).':'.$interface -> getRemoteIf();
				}
			}
			break;
		case 'docker':
			$cmd = 'docker -H=tcp://127.0.0.1:4243 start '.$n -> getUuid();
			error_log(date('M d H:i:s ').'INFO: starting '.$cmd);
			break;
		case 'vpcs':
			$cmd ='/opt/vpcsu/bin/vpcs -m '.$id.' -N '.$n -> getName();
			break;
		case 'dynamips':
			$cmd = '/opt/unetlab/wrappers/dynamips_wrapper -T '.$t.' -D '.$id.' -t "'.$n -> getName().'" -F /opt/unetlab/addons/dynamips/'.$n -> getImage().' -d '.$n -> getDelay();
			break;
		case 'qemu':
			$cmd = '/opt/unetlab/wrappers/qemu_wrapper -T '.$t.' -D '.$id.' -t "'.$n -> getName().'" -F '.$bin.' -d '.$n -> getDelay();
			if ($n -> getConsole() == 'vnc'  || $n -> getConsole() == 'rdp' ) {
				// Disable telnet (wrapper) console
				$cmd .= ' -x';
			} 
			break;
	}
	// Special Case for xrv - csr1000v - vIOS - vIOSL - Docker
	if (( $n->getTemplate() == 'xrv' || $n->getTemplate() == 'xrv9k' || $n->getTemplate() == 'csr1000vng' || $n->getTemplate() == 'csr1000v' || $n->getTemplate() == 'asav' || $n->getTemplate() == 'titanium' )  && is_file($n -> getRunningPath().'/config.iso') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0)  {
		$flags .= ' -cdrom config.iso' ;
	}

	if (( $n->getTemplate() == 'vios'  || $n->getTemplate() == 'viosl2') && is_file($n -> getRunningPath().'/minidisk') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0)  {
		$flags .= ' -drive file=minidisk,if=virtio,bus=0,unit=1,cache=none' ;
	}

	if (( $n->getTemplate() == 'vmx'  || $n->getTemplate() == 'vsrx' || $n->getTemplate() == 'vqfxre' || $n->getTemplate() == 'junipervrr' ) && is_file($n -> getRunningPath().'/config.iso') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0)  {	
	$flags .= ' -drive file=config.iso,if=virtio,media=cdrom,index=2' ;
	}

	if ((  $n->getTemplate() == 'vsrxng' ) && is_file($n -> getRunningPath().'/config.iso') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0)  {
		$flags .= ' -drive file=config.iso,if=ide,media=cdrom,index=2' ;
	}

	if ((  $n->getTemplate() == 'vmxvcp' ) && is_file($n -> getRunningPath().'/config.iso') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0)  {
		$flags .= ' -drive file=config.iso,if=ide,media=cdrom,index=3' ;
	}

	if ((  $n->getTemplate() == 'nxosv9k' ) && is_file($n -> getRunningPath().'/config.iso') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0)  {
		$flags .= ' -drive file=config.iso,if=ide,media=cdrom,index=3' ;
	}

	if (( $n -> getTemplate() == 'pfsense')   && is_file($n -> getRunningPath().'/config.iso') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0)  {
		$flags .= ' -cdrom config.iso' ;
	}
	if (( $n -> getTemplate() == 'timos' || $n -> getTemplate() == 'timoscpm' )   && is_file($n -> getRunningPath().'/floppy.img') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0)  {
		$flags = preg_replace('/Timos:/','Timos: primary-config=cf1:config.cfg ',$flags) ;
		$flags .= ' -hdb floppy.img' ;
	}
	if ( $n -> getNType() != 'docker' && $n -> getNType() != 'vpcs')  {
		$cmd .= ' -- '.$flags.' > '.$n -> getRunningPath().'/wrapper.txt 2>&1 &';
	}

	if ( $n -> getNType() == 'vpcs')  {
		$cmd .= $flags.' > '.$n -> getRunningPath().'/wrapper.txt 2>&1 &';
	}


	error_log(date('M d H:i:s ').'INFO: CWD is '.getcwd());
	error_log(date('M d H:i:s ').'INFO: starting '.$cmd);

	// Clean TCP port
	exec("fuser -k -n tcp ".(32768 + 128 * $t + $id));
	exec($cmd, $o, $rc);
    error_log(date('M d H:i:s ').'INFO: started rc '.$rc);
    error_log(date('M d H:i:s ').'INFO: started output '.json_encode($o));
	if ($rc == 0 && $n -> getNType() == 'qemu' && is_file($n -> getRunningPath().'/startup-config') && !is_file($n -> getRunningPath().'/.configured') && $n -> getConfig() != 0 ) {
		// Start configuration process or check if bootstrap is done
		touch($n -> getRunningPath().'/.lock');
		$cmd = 'nohup /opt/unetlab/scripts/'.$GLOBALS['node_config'][$n -> getTemplate()].' -a put -p '.$n -> getPort().' -f '.$n -> getRunningPath().'/startup-config -t '.($n -> getDelay() + $scripttimeout).' > /dev/null 2>&1 &';
		exec($cmd, $o, $rc);
		error_log(date('M d H:i:s ').'INFO: importing '.$cmd);
	}

	
	if ($rc == 0 && $n -> getNType() == 'qemu' && $n -> getCpuLimit() === 1 ) {
               sleep (1) ;
               exec("grep T".$t."D".$id."- /proc/*/environ | cut -d/ -f3",$tpid,$rc);
               if ( $rc == 0 && isset($tpid) && $tpid > 0 ) {
                         error_log(date('M d H:i:s ').'INFO: qemu pid is '.$tpid[0]);
                       exec("cgclassify -g pids:/cpulimit ".$tpid[0], $ro, $rc );
               }
        }
	
	/*
	   Network initialization of docker network
	   eth0 is attached to the docker0 interface and is used for management E.G VNC

	   Each subsequent interface will available to be attached to other nodes/networks in the web interface
	   eth1 will be the first available interface

	 */
	if ($rc == 0 && $n -> getNType() == 'docker') {

		foreach ($n -> getEthernets() as $interface_id => $interface) {


			// We specify the names of our interfaces
			$tap_name = 'vunl'.$t.'_'.$id.'_'.$interface_id;

			// For the peer which we bridge to we need to find the peer interface name
			if (isset($nets[$interface -> getNetworkId()]) && $nets[$interface -> getNetworkId()] -> isCloud()) {
				// Network is a Cloud
				$net_name = $nets[$interface -> getNetworkId()] -> getNType();
			} else {
				$net_name = 'vnet'.$t.'_'.$interface -> getNetworkId();
			}

			// Here we brdige the interfaces
			if ($interface -> getNetworkId() !== 0) {
			// Connect interface to network
			      $rc = connectInterface($net_name, $tap_name, $n -> getNType());
                        if ($rc !== 0) {
                                // Failed to connect interface to network
                                return $rc;
                        }

			}


		}
			$cmd = 'docker -H=tcp://127.0.0.1:4243 inspect --format "{{ .State.Pid }}" '.$n -> getUuid();
			exec($cmd, $o, $rc);

			$cmd = "ip link set netns ".$o['1']." ".$tap_name." name ".$tap_name."  up 2>&1";
			exec($cmd, $o, $rc);

		$cmd = 'nohup /opt/unetlab/scripts/'.$GLOBALS['node_config'][$n -> getTemplate()].' -a put -i '.$n -> getUuid().' -f '.$n -> getRunningPath().'/startup-config -t '.($n -> getDelay() + 300).' > /dev/null 2>&1 &';
		exec($cmd, $o, $rc);
		error_log(date('M d H:i:s ').'INFO: importing '.$cmd);
	}

	return 0;
}

/**
 * Function to stop a node.
 *
 * @param   Node    $n                  Node
 * @return  int                         0 means ok
 */
function stop($n) {


	//extract the tenant and node id from uuid, a bad way of doing things :/
	$tenantNodeId = explode("-",$n -> getUuid());

	if ($n -> getStatus() != 0) {
      if  (($n -> getNType() == 'docker') || ($n -> getNType() == 'qemu')) {
          foreach ($n->getEthernets() as $interface_id => $interface) {
              // We specify the names of our interfaces
              $tap_name = 'vunl' . $tenantNodeId[5] . '_' . $tenantNodeId[6] . '_' . $interface_id;

              // For the peer which we bridge to we need to find the peer interface name
              if (isset($nets[$interface->getNetworkId()]) && $nets[$interface->getNetworkId()]->isCloud()) {
                  // Network is a Cloud
                  $net_name = $nets[$interface->getNetworkId()]->getNType();
              } else {
                  $net_name = 'vnet' . $tenantNodeId[5] . '_' . $interface->getNetworkId();
              }

              // remove network port from ovs when deleting bridge
              if ($interface->getNetworkId() !== 0) {
                  // Connect interface to network
                  $cmd = 'ovs-vsctl del-port ' . $net_name . ' ' . $tap_name . '';
                  error_log(date('M d H:i:s ') . 'INFO: Deleting interface' . $cmd);
                  exec($cmd, $o, $rc);
              }


          }
      }
          if  ($n -> getNType() == 'docker')  {
			// Stop docker node
			$cmd = 'docker -H=tcp://127.0.0.1:4243 stop '.$n -> getUuid();
			error_log(date('M d H:i:s ').'INFO: stopping aa'.$cmd);
                        exec($cmd, $o, $rc);
			
			
		} else {
			$cmd = 'fuser -n tcp -k -TERM '.$n -> getPort().' > /dev/null 2>&1';
		
			error_log(date('M d H:i:s ').'INFO: stopping '.$cmd);
			exec($cmd, $o, $rc);
			// DELETE SNAT RULE RDP if needed
			if ( $n -> getConsole() == 'rdp' ) {
				$cmd = 'iptables -t nat -D INPUT -p tcp --dport '.$n -> getPort().' -j SNAT --to 169.254.1.102' ;
				exec($cmd, $o, $rc);
			}

			if ($rc  == 0) {
				return 0;
			} else {
				// Node is still running
				error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][80035]);
				error_log(date('M d H:i:s ').implode("\n", $o));
				return 80035;
			}
		}
	} else {
		return 0;
	}
}

/**
 * Function to print how to use the unl_wrapper
 *
 * @return  string                      usage output
 */
function usage() {
	global $argv;
	$output = '';
	$output .= "Usage: ".$argv[0]." -a <action> <options>\n";
	$output .= "-a <s>     Action can be:\n";
	$output .= "           - delete: delete a lab file even if it's not valid\n";
	$output .= "                     requires -T, -F\n";
	$output .= "           - export: export a runnign-config to a file\n";
	$output .= "                     requires -T, -F, -D is optional\n";
	$output .= "           - fixpermissions: fix file/dir permissions\n";
	$output .= "           - platform: print the hardware platform\n";
	$output .= "           - start: start one or all nodes\n";
	$output .= "                     requires -T, -F, -D is optional\n";
	$output .= "           - stop: stop one or all nodes\n";
	$output .= "                     requires -T, -F, -D is optional\n";
	$output .= "           - wipe: wipe one or all nodes\n";
	$output .= "                     requires -T, -F, -D is optional\n";
	$output .= "Options:\n";
	$output .= "-F <n>     Lab file\n";
	$output .= "-T <n>     Tenant ID\n";
	$output .= "-D <n>     Device ID (if not used, all devices will be impacted)\n";
	print($output);
}
?>

