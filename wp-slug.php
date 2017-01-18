<?php
/*
Plugin Name: WP Slug Translate
Plugin URI: https://github.com/ixiumu/wp-slug/
Version: 2.0
Description: 翻译中文固定链接为英语或者拼音。
Author: 朽木
Author URI: http://www.xiumu.org
*/

if(!class_exists('wp_slug')):
class wp_slug{
	var $slug_name = '';
	var $slug_title = '';

	function __construct(){
		global $wp_version;
		add_filter('title_save_pre', array(&$this,'get_from_title'), 0);
		add_filter('name_save_pre', array(&$this,'put_to_name'), 0);
		if($wp_version > 2.4 && strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') && $_POST['action'] === 'sample-permalink'){
			add_filter('sanitize_title', array(&$this,'wp_tr_ajax_slug'),0);
			register_shutdown_function(array(&$this,'wp_tr_ajax_remove'));
		}
	}

	function wp_tr_ajax_slug($name){
		remove_filter('sanitize_title', array(&$this,'wp_tr_ajax_slug'), 0);
		if(isset($_POST['new_title'])){
			if( !(strpos($_POST['new_title'], '@@') === false) ){
			$post_titlename = explode('@@', $_POST['new_title']);
			$name = $post_titlename[1];
			unset($post_titlename);
			}
		}
		$name = $this->put_to_name($name);
		add_filter('sanitize_title', array(&$this,'wp_tr_ajax_slug'), 0);
		return $name;
	}

	function wp_tr_ajax_remove(){
		remove_filter('sanitize_title', array(&$this,'wp_tr_ajax_slug'), 0);
	}

	function get_from_title($title){
		$this->slug_name = '';
		$this->slug_title = $title;
		if( !(strpos($title, '@@') === false) ){
			$post_titlename = explode('@@', $title);
		    $this->slug_title = $title = $post_titlename[0];
			$this->slug_name = $post_titlename[1];
	    }
		unset($post_titlename);
		return $title;
	}
  
  function get_pinyin($str, $ishead=0, $separator='-'){
    $restr = '';
    $str = trim($str);
    $slen = strlen($str);
    $pinyins = array();
    if($slen < 2)    {
        return $str;
    }
    if(count($pinyins) == 0)    {
        $fp = fopen( dirname(__FILE__).'/pinyin.dat','r' );
        while(!feof($fp))        {
            $line = trim(fgets($fp));
            $pinyins[$line[0].$line[1]] = substr($line, 3, strlen($line)-3);
        }
        fclose($fp);
    }
    for($i=0; $i<$slen; $i++){
        if(ord($str[$i])>0x80){
            $c = $str[$i].$str[$i+1];
            $i++;
            if(isset($pinyins[$c])){
                if($ishead==0){
                    $restr .= $separator . $pinyins[$c];
                }
                else{
                    $restr .= $pinyins[$c][0];
                }
            }else{
                $restr .= $separator;
            }
        }else if( preg_match("/[a-z0-9]/i", $str[$i]) ){
            $restr .= $str[$i];
        }
        else{
            $restr .= $separator;
        }
    }
    return $restr;
  }

	function put_to_name($name){
		if(!empty($this->slug_name)){
			return $this->slug_name;
		}elseif(empty($name) && !empty($this->slug_title)){
			$name = $this->slug_title;
		}else{}

		$name = strip_tags($name);
		
		if(empty($name) || !seems_utf8($name) || !preg_match("/[\x7f-\xff]/", $name))
			return $name;

		if(!class_exists('Snoopy'))
			require_once(ABSPATH.WPINC."/class-snoopy.php");

		$snoopy = new Snoopy();
    //youdao
    $url = "http://fanyi.youdao.com/openapi.do";
    $submit_vars["keyfrom"] = 'XiuMuBlog';
    $submit_vars["key"] = '1242574350';
    $submit_vars["type"] = 'data';
    $submit_vars["doctype"] = 'json';
    $submit_vars["version"] = '1.0';
		$submit_vars["q"] = $name;
    $snoopy->agent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.8.1.11) Gecko/20071127 Firefox/2.0.0.11';
    $snoopy->submit($url,$submit_vars);
    
    if($snoopy->status >= 200 && $snoopy->status < 300){
      $results = json_decode($snoopy->results, true);
      if ($results['errorCode'] == 0) {
        $name_tmp = sanitize_user(sanitize_title($results['translation'][0]), true);
				if(!empty($name_tmp))
					return $name_tmp;
      }
    }
    
    //pinyin
		$name = sanitize_user(sanitize_title($this->get_pinyin(iconv("UTF-8", "GB2312", $name))), true);
		unset($this->slug_name,$this->slug_title);
    
		return $name;
    
    //Google
		$url = "http://translate.google.com/translate_t?langpair=zh|en";
		$submit_vars["hl"] = "zh-CN";
		$submit_vars["text"] = $name;
		$submit_vars["ie"] = "UTF8";
		$submit_vars["langpair"] = "zh|en";
		$snoopy->agent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.8.1.11) Gecko/20071127 Firefox/2.0.0.11';

		$snoopy->submit($url,$submit_vars);

		if($snoopy->status >= 200 && $snoopy->status < 300){
			$htmlret = $snoopy->results;

			if(preg_match('/<div.*?id\s*=\s*("|\')?\s*result_box\s*("|\')?.*?>/ius', $htmlret, $matchs) == 1){
				$out = explode($matchs[0],$htmlret);
				unset($matchs);
				$out = explode('</div>',$out[1]);
				$name_tmp = sanitize_user(sanitize_title($out[0]), true);
				
				unset($out,$htmlret);
				
				if(!empty($name_tmp))
					return $name_tmp;
				
				unset($name_tmp);	
			}
		}

	}
}
endif;

$new_wp_slug = new wp_slug();
?>