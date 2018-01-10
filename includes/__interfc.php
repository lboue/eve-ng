<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/includes/__interfc.php
 *
 * Class for UNetLab interfaces. 
 *
 * @author Andrea Dainese <andrea.dainese@gmail.com>
 * @copyright 2014-2016 Andrea Dainese
 * @license BSD-3-Clause https://github.com/dainok/unetlab/blob/master/LICENSE
 * @link http://www.unetlab.com/
 * @version 20160719
 * @property type $id Interface ID. It's mandatory and automatically set during contruction phase.
 * @property type $name Name of the interface. It's optional.
 * @property type $color Color of the interface. It's optional.
 * @property type $style Style of the interface. It's optional.
 * @property type $label label of the interface. It's optional.
 * @property type $network_id Remote network ID for Ethernet interfaces. If not set, it's automatically set to 0.
 * @property type $remote_id Remote node ID for Serial interfaces. If not set, it's automatically set to 0.
 * @property type $remote_if Remote interface ID for Serial interfaces. If not set, it's automatically set to 0. 
 * @property type $type Type of the interface. It's mandatory.
 */

class Interfc {
	private $id;
	private $name;
	private $color;
    private $style;
    private $label;
    private $anchor;
    private $dash;
	private $network_id;
	private $remote_id;
	private $remote_if;
	private $type;

    /**
     * Constructor which creates an interface.
     * Parameters:
     * - $name
     * - $network_id
     * - $remote_id
     * - $remote_if
     * - $type*
     * *mandatory
     *
     * @param   Array $p Parameters
     * @param $id
     * @throws Exception
     */
	public function __construct($p, $id) {
		// Mandatory parameters
		if (!isset($p['type'])) {
			// Missing mandatory parameters
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][10000]);
			throw new Exception('10000');
			return 10000;
		}

		if (!checkInterfcType($p['type'])) {
			// Type is not valid
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][10001]);
			throw new Exception('10001');
			return 10001;
		}

		// Optional parameters
		if (isset($p['name']) && $p['name'] === '') {
			// Name is empty, ignored
			unset($p['name']);
			error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10002]);
		}
		if (isset($p['color']) && $p['color'] === '') {
		    // Color is empty, ignored
            unset($p['color']);
            error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10002]);
		}
		if (isset($p['style']) && $p['style'] === '') {
		    // Style is empty, ignored
            unset($p['style']);
            error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10002]);
		}
        if (isset($p['label']) && $p['label'] === '') {
            // Label is empty, ignored
            unset($p['label']);
            error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10002]);
        }
        if (isset($p['dash']) && $p['dash'] === '') {
            // Dash is empty, ignored
            unset($p['dash']);
            error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10002]);
        }
        if (isset($p['anchor']) && $p['anchor'] === '') {
            // Anchor is empty, ignored
            unset($p['anchor']);
            error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10002]);
        }

		if ($p['type'] == 'ethernet') {
			if (isset($p['network_id']) && (int) $p['network_id'] <= 0) {
				// Network ID is not valid
				unset($p['network_id']);
				error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10003]);
			}
		}
				
		if ($p['type'] == 'serial') {
			if (isset($p['remote_id']) && (int) $p['remote_id'] <= 0) {
				// Remote ID is not valid
				unset($p['remote_id']);
				error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10006]);
			}

			if (isset($p['remote_if']) && (int) $p['remote_if'] < 0) {
				// Remote interface is not valid
				unset($p['remote_if']);
				error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10007]);
			}
		}

		// Now building the interface
		$this -> id = (int) $id;
		$this -> type = $p['type'];
		if (isset($p['color'])) $this -> color = htmlentities($p['color']);
        if (isset($p['style'])) $this ->  style = htmlentities($p['style']);
        if (isset($p['label'])) $this -> label = htmlentities($p['label']);
		if (isset($p['name'])) $this -> name = htmlentities($p['name']);
        if (isset($p['dash'])) $this -> dash = htmlentities($p['dash']);
        if (isset($p['anchor'])) $this -> anchor = htmlentities($p['anchor']);

		// Building ethernet interface
		if ($p['type'] == 'ethernet') {
			if (isset($p['network_id'])) $this -> name = (int) $p['network_id'];
		}

		// Building serial interface
		if ($p['type'] == 'serial') {
			if (isset($p['remote_id'])) $this -> name = (int) $p['remote_id'];
			if (isset($p['remote_if'])) $this -> name = (int) $p['remote_if'];
		}
	}

	/**
	 * Method to add or replace the interface metadata.
	 * Editable attributes:
	 * - left
	 * - name
	 * - top
	 * If an attribute is set and is valid, then it will be used. If an
	 * attribute is not set, then the original is maintained. If in attribute
	 * is set and empty '', then the current one is deleted.
	 *
	 * @param   Array   $p                  Parameters
	 * @return  int                         0 means ok
	 */
	public function edit($p) {
		$modified = False;

		if (isset($p['name']) && $p['name'] === '') {
			// Name is empty, unset the current one
			unset($p['name']);
			$modified = True;
		} else if (isset($p['name'])) {
			$this -> name = htmlentities($p['name']);
			$modified = True;
		}
		 if (isset($p['color']) && $p['color'] === '') {
		     // Color is empty, unset the current one
			 unset($p['color']);
			 $modified = True;
		} else if (isset($p['color'])) {
			$this -> color = htmlentities($p['color']);
			$modified = True;
        }
        if (isset($p['style']) && $p['style'] === '') {
            // Style is empty, unset the current one
            unset($p['style']);
            $modified = True;
        } else if (isset($p['style'])) {
            $this -> style = htmlentities($p['style']);
            $modified = True;
        }
        if (isset($p['label']) && $p['label'] === '') {
            // Style is empty, unset the current one
            unset($p['label']);
            $modified = True;
        } else if (isset($p['label'])) {
            $this -> label = htmlentities($p['label']);
            $modified = True;
        }
        if (isset($p['anchor']) && $p['anchor'] === '') {
            // Anchor is empty, unset the current one
            unset($p['anchor']);
            $modified = True;
        } else if (isset($p['anchor'])) {
            $this -> anchor = htmlentities($p['anchor']);
            $modified = True;
        }
        if (isset($p['dash']) && $p['dash'] === '') {
            // Dash is empty, unset the current one
            unset($p['dash']);
            $modified = True;
        } else if (isset($p['dash'])) {
            $this -> dash = htmlentities($p['dash']);
            $modified = True;
        }


        if ($this -> type == 'ethernet') {
			if (isset($p['remote_id']) || isset($p['remote_if'])) {
				// Unneeded attributes
				unset($p['remote_id']);
				unset($p['remote_if']);
				error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10004]);
			}

			if (isset($p['network_id']) && $p['network_id'] === '') {
				// Remote network is 0, unset the current one
				unset($this -> network_id);
				$modified = True;
			} else if (isset($p['network_id']) && (int) $p['network_id'] <= 0) {
				// Network ID is not valid
				error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10003]);
			} else if (isset($p['network_id'])) {
				$this -> network_id = (int) $p['network_id'];
				$modified = True;
			}
		}
				
		if ($this -> type == 'serial') {
			if (isset($p['network_id'])) {
				// Unneeded attributes
				unset($p['network_id']);
				error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10005]);
			}

			if (isset($p['remote_id']) && $p['remote_id'] === '') {
				// Remote node ID is 0, unset the current one
				unset($this -> remote_id);
				unset($this -> remote_if);
				$modified = True;
			} else {
				if (isset($p['remote_id']) && (int) $p['remote_id'] <= 0) {
					// Remote ID is not valid
					error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10006]);
				} else if (isset($p['remote_id'])) {
					$this -> remote_id = (int) $p['remote_id'];
					$modified = True;
				}

				if (isset($p['remote_if']) && (int) $p['remote_if'] < 0) {
					// Remote IF is not valid
					error_log(date('M d H:i:s ').'WARNING: '.$GLOBALS['messages'][10007]);
				} else if (isset($p['remote_if'])) {
					$this -> remote_if = (int) $p['remote_if'];
					$modified = True;
				}
			}
		}

		if ($modified) {
			// At least an attribute is changed
			return 0;
		} else {
			// No attribute has been changed
			error_log(date('M d H:i:s ').'ERROR: '.$GLOBALS['messages'][10008]);
			return 10008;
		}
	}
        /**
         * Method to get network name.
         *
         * @return  string                      ID
         */
        public function getId() {
                if (isset($this -> id)) {
                        return $this -> id;
                } else {
                        // By default return an empty string
                        return '';
                }
        }

	/**
	 * Method to get network name.
	 *
	 * @return  string                      Network name
	 */
	public function getName() {
		if (isset($this -> name)) {
			return $this -> name;
		} else {
			// By default return an empty string
			return '';
		}
	}

	/**
	 * Method to get remote network ID.
	 *
	 * @return	string                      Remote network ID or 0 if not set or not "ethernet" type
	 */
	public function getNetworkId() {
		if ($this -> type == 'ethernet' && isset($this -> network_id)) {
			return $this -> network_id;
		} else {
			return 0;
		}
	}



     /**
	  * Method to get interface color.
	  *
	  * @return      string                      Interface color
	  */
     public function getInterfaceColor() {
		if (isset($this -> color)) {
                	return $this -> color;
		}
		else {
			return "#346789";
		}
     }
    /**
     * Method to get interface label.
     *
     * @return      string                      Interface label
     */
    public function getInterfaceLabel() {
        if (isset($this -> label)) {
            return $this -> label;
        }
        else {
            return "";
        }
    }
    /**
     * Method to get interface style.
     *
     * @return      string                      Interface Style
     */
    public function getInterfaceStyle() {
        if (isset($this -> style)) {
            return $this -> style;
        }
        else {
            return "Straight";
        }
    }
    /**
     * Method to get interface anchor.
     *
     * @return      string                      Interface anchor
     */
    public function getInterfaceAnchor() {
        if (isset($this -> anchor)) {
            return $this -> anchor;
        }
        else {
            return "";
        }
    }
    /**
     * Method to get interface anchor.
     *
     * @return      string                      Interface dash
     */
    public function getInterfaceDash() {
        if (isset($this -> dash)) {
            return $this -> dash;
        }
        else {
            return "";
        }
    }

	/**
	 * Method to get interface type.
	 * 
	 * @return	string                      Interface type
	 */
	public function getNType() {
		return $this -> type;
	}

	/**
	 * Method to get remote node ID.
	 * 
	 * @return	int                         Remote node ID or 0 if not connected or not "serial" type
	 */
	public function getRemoteId() {
		if ($this -> type == 'serial' && isset($this -> remote_id)) {
			return $this -> remote_id;
		} else {
			return 0;
		}
	}

	/**
	 * Method to get remote interface ID.
	 * 
	 * @return	int                         Remote interface ID or 0 if not connected or not "serial" type
	 */
	public function getRemoteIf() {
		if ($this -> type == 'serial' && isset($this -> remote_if)) {
			return $this -> remote_if;
		} else {
			return 0;
		}
	}
}
?>
