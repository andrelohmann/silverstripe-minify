<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * Date: 10/24/14
 * Time: 7:00 PM
 * 
 * This script minifies the HTML which silverstripe outputs, if you want to format your HTMLs 
 * this can be a handy snippet of code to use.
 * 
 * To get it to work, download this file to your project folder and add this configs to the config.yml 
 * 
 * Controller:
 *  extensions:
 *   - SS_MinifiedResponseExtension
 * 
 * This uses 
 * https://code.google.com/p/minify/source/browse/min/lib/Minify/HTML.php
 * and changed in a way to work with SilverStripe 
 * 
 * 
 */

class SS_MinifiedResponseExtension extends Extension {


	function onBeforeInit(){
		if(is_a($this->owner, 'Controller')){
			$this->owner->response = new SS_MinifiedResponse();
		}
	}


}

class SS_MinifiedResponse extends SS_HTTPResponse {


	private static $clean_js_comments = true;
	private static $is_xhtml = false;


	private $arrPlaceHolders;
	private $strReplacementHash;

	public function setBody($body) {
		$this->body = $body ? (string)$body : $body;
		$this->MinifyHTML();
	}

	function MinifyHTML(){
		
		// Require once to minify inline css and javascript
		require_once('thirdparty/jsmin/jsmin.php');
		require_once(BASE_PATH .'/minify/code/thirdparty/Compressor.php');


		$this->strReplacementHash = 'MINIFYHTML' . md5($this->body);
		$this->arrPlaceHolders = array();

		// scripts
		$this->body = preg_replace_callback(
			'/(\\s*)<script(\\b[^>]*?>)([\\s\\S]*?)<\\/script>(\\s*)/i'
			,array($this, 'removeScriptCallBack')
			,$this->body);

		// styles
		$this->body = preg_replace_callback(
			'/\\s*<style(\\b[^>]*>)([\\s\\S]*?)<\\/style>\\s*/i'
			,array($this, 'removeStylesCallBack')
			,$this->body);

		// comments
		$this->body = preg_replace_callback(
			'/<!--([\\s\\S]*?)-->/'
			,array($this, 'commentCallBack')
			,$this->body);


		// replace PREs with placeholders
		$this->body = preg_replace_callback('/\\s*<pre(\\b[^>]*?>[\\s\\S]*?<\\/pre>)\\s*/i'
			,array($this, 'removePreCallBacl')
			,$this->body);

		$this->body = preg_replace_callback(
			'/\\s*<textarea(\\b[^>]*?>[\\s\\S]*?<\\/textarea>)\\s*/i'
			,array($this, 'removeTextareaCallBack')
			,$this->body);

		$this->body = preg_replace('/^\\s+|\\s+$/m', '', $this->body);

		$this->body = preg_replace('/\\s+(<\\/?(?:area|base(?:font)?|blockquote|body'
			.'|caption|center|col(?:group)?|dd|dir|div|dl|dt|fieldset|form'
			.'|frame(?:set)?|h[1-6]|head|hr|html|legend|li|link|map|menu|meta'
			.'|ol|opt(?:group|ion)|p|param|t(?:able|body|head|d|h||r|foot|itle)'
			.'|ul)\\b[^>]*>)/i', '$1', $this->body);

		$this->body = preg_replace(
			'/>(\\s(?:\\s*))?([^<]+)(\\s(?:\s*))?</'
			,'>$1$2$3<'
			,$this->body);

		$this->body = preg_replace('/(<[a-z\\-]+)\\s+([^>]+>)/i', "$1 $2", $this->body);

		$this->body = str_replace(
			array_keys($this->arrPlaceHolders)
			,array_values($this->arrPlaceHolders)
			,$this->body
		);

		$this->body = str_replace(
			array_keys($this->arrPlaceHolders)
			,array_values($this->arrPlaceHolders)
			,$this->body
		);
		
		return $this->body;
	}



	private function commentCallBack($m){
		return (0 === strpos($m[1], '[') || false !== strpos($m[1], '<!['))
			? $m[0]
			: '';
	}

	private function reservePlace($content){
		$placeholder = '%' . $this->strReplacementHash . count($this->arrPlaceHolders) . '%';
		$this->arrPlaceHolders[$placeholder] = $content;
		return $placeholder;
	}


	private function removePreCallBacl($m){
		return $this->reservePlace("<pre{$m[1]}");
	}

	private function removeTextareaCallBack($m){
		return $this->reservePlace("<textarea{$m[1]}");
	}

	private function removeStylesCallBack($m){
		$openStyle = "<style{$m[1]}";
		$css = $m[2];
		$css = preg_replace('/(?:^\\s*<!--|-->\\s*$)/', '', $css);
		$css = $this->removeCdata($css);
		$css = call_user_func('trim', $css);

		$css = Minify_CSS_Compressor::process($css);
		
		return $this->reservePlace($this->needsCdata($css)
				? "{$openStyle}/*<![CDATA[*/{$css}/*]]>*/</style>"
				: "{$openStyle}{$css}</style>"
		);
	}

	private function removeScriptCallBack($m){
		$openScript = "<script{$m[2]}";
		$js = $m[3];


		$ws1 = ($m[1] === '') ? '' : ' ';
		$ws2 = ($m[4] === '') ? '' : ' ';

		if (Config::inst()->get('SS_MinifiedResponse', 'clean_js_comments')) {
			$js = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/', '', $js);
		}

		$js = $this->removeCdata($js);
		$js = call_user_func('trim', $js);
		
		$js = JSMin::minify($js);
		
		return $this->reservePlace($this->needsCdata($js)
				? "{$ws1}{$openScript}/*<![CDATA[*/{$js}/*]]>*/</script>{$ws2}"
				: "{$ws1}{$openScript}{$js}</script>{$ws2}"
		);
	}

	private function removeCdata($str){
		return (false !== strpos($str, '<![CDATA['))
			? str_replace(array('<![CDATA[', ']]>'), '', $str)
			: $str;
	}

	private function needsCdata($str){
		return (Config::inst()->get('SS_MinifiedResponse', 'is_xhtml') && preg_match('/(?:[<&]|\\-\\-|\\]\\]>)/', $str));
	}

} 