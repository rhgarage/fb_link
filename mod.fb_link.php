<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed.');

// require_once PATH_THIRD.'fb_link/libraries/facebook.php';

class Fb_link {

	var $return_data = '';
	
	function __construct() {
		$this->EE =& get_instance();
		
		$this->EE->load->model('base_facebook');
	}
	
	function graph() {
	
		// Load Typography Class to parse data
		$this->EE->load->library('typography');
		$this->EE->typography->initialize();
		$this->EE->load->helper('url');
	
		$output = '';
		$parsed_row = '';
		
		$params = array(
			'graph'		=>	$this->EE->TMPL->fetch_param('graph'),
			'query'		=>	$this->EE->TMPL->fetch_param('query'),
		);
		
		// Set the path
		if(!empty($params['graph'])) {
			$path = $params['graph'];
		}
		
		if(!empty($params['query'])) {
			$path = 'fql?q='.urlencode($params['query']);
		}
		
		try {
			// We need to set the index for the parser later
			$data = $this->EE->base_facebook->graph($path);
						
		} catch (FacebookApiException $e) {
			error_log($e);
			return $output;
		}
				
		// We need to make some "rows" for the EE parser.
		$rows[] = $this->make_rows($data);
				
		/*
		//
		// This may be handy for pagination later but for now it's just filed away.
		//
		if (preg_match("/".LD."paging".RD."(.+?)".LD.'\/'."paging".RD."/s", $this->EE->TMPL->tagdata, $page_match)) {
			// The pattern was found and we set aside the paging tagdata for later and created a copy of all the other tagdata for use
			$paging = $page_match[1];
			// Replace the {paging} variable pairs with nothing and set this aside for later.
			$tag_data = preg_replace("/".LD."paging".RD.".+?".LD.'\/'."paging".RD."/s", "", $this->EE->TMPL->tagdata);
		*/
		
		$tag_data = $this->EE->TMPL->tagdata;
						
		$output = $this->EE->TMPL->parse_variables($tag_data, $rows);
														
		return $output;
		
	}
	
	// Create rows for the EE parser.  Some FB data is an array that is not indexed.  For example the from data is an associative array.  THe EE parser needs a "row" to work with.  This function will recursively work through the data and if an array is not indexed will create the index.  It's a beast of a function but necessary for now and should be flexible enough to cope with FB structure changes.
	public function make_rows($array, $parent=NULL) {
		$var = array();
		
		// Let work through each item to catch arrays and format them for parsing
		foreach($array as $k => $v) {
			
			// Not an array so pass over to the formatting function after adding the parent key to the current key.  This is necessary for the EE parser which trips when keys have the same name - regardless of array context.
			if(!is_array($v) && $parent != NULL) {
				$var[$parent.':'.$k] = $this->format($k,$v);
				
				// Create a permalink from post id if the key is id and it matches the pattern
				if($k == 'id' && (preg_match('/^\d+_{1}\d+$/', $v)) === 1) {
					$p = explode('_', $v);
					$var[$parent.':permalink'] = 'http://www.facebook.com/'.$p[0].'/posts/'.$p[1];
					unset($p);
				}
				
			} elseif(!is_array($v)) {
				$var[$k] = $this->format($k,$v);
				
			// Is an array so hold up we need some more work.
			} elseif(is_array($v)){
			
				// We need to rename the "row" named data based on it's parent or else the parser gets confused. Here we rename it and merge the array up to eliminate and unnecessary tag.
				if(isset($v['data']) && !is_numeric(array_shift(array_keys($v['data'])))) {
					$v[$k.':data'][] = $v['data'];
					unset($v['data']);
				} elseif (isset($v['data']) && is_numeric(array_shift(array_keys($v['data'])))) {
					$v[$k.':data'] = $v['data'];
					unset ($v['data']);
				}
				
				// If it's not numeric we need to create that.
				if(!is_numeric($k) && !is_numeric(array_shift(array_keys($v)))) {
				
					if ($parent != NULL) {
						$var[$parent.':'.$k][0] = $this->make_rows($v, $k);
					} else {
						$var[$k][0] = $this->make_rows($v, $k);
					}
				} else {
					$var[$k] = $this->make_rows($v, $parent);
				}
			}
		}
		
		return $var;
	}

	// Function to handle the formatting of certain fields.
	public function format($k, $v) {
		
		if(($k == 'message') || ($k == 'story')) {
			// As of January 2013 'auto_links' is set to no.  The Graph now returns formatted links inside of posts.  If links are causing problems set 'auto_links' to yes and see if that corrects the issue.
			$v = auto_link($this->EE->typography->parse_type($v, array('text_format' => 'lite', 'html_format' => 'safe', 'auto_links' => 'n')));
		}
		
		if(($k == 'created_time') || ($k == 'updated_time')) {
			$v = strtotime($v);
		}
		
		return $v;
	}
}

/* End of file mod.fb_link.php */
/* Location: ./system/expressionengine/third_party/fb_link/mod.fb_link.php */