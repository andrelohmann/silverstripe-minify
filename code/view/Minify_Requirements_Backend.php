<?php

/**
 * @package minify
 * @subpackage view
 */
class Minify_Requirements_Backend extends Requirements_Backend {

	/**
	 * Do the heavy lifting involved in combining (and, in the case of JavaScript minifying) the
	 * combined files.
	 */
	public function process_combined_files() {
		
		if(Controller::has_curr()) $url = explode('/',  Controller::curr()->request->getURL());
		else $url = false;
		// Set Requirements for all custom Controllers
        if($url && !in_array($url[0], array('admin', 'dev', 'interactive'))){
		
			$checksum = md5(json_encode(array(
				'js' => $this->javascript,
				'css' => $this->css,
				'customScript' => $this->customScript,
				'customCss' => $this->customCSS,
				'customHeadTags' => $this->customHeadTags,
				'disabled' => $this->disabled,
				'blocked' => $this->blocked,
				'combine_filed' => $this->combine_files
			)));
			//$combineFolder = Director::baseFolder() . '/' . $this->getCombinedFilesFolder() . '/';
			//if(!file_exists($combineFolder . $checksum . ".js") || count(glob($combineFolder . $checksum . "*.css")) == 0) {

				parent::process_combined_files();

				$newJavascript = array();
				$combinedJs = array();
				foreach($this->javascript as $js => $bool){
					if(strstr($js, '://') > -1) $newJavascript[$js] = $bool;
					else $combinedJs[] = $js;
				}
				$this->javascript = $newJavascript;

				$newCss = array();
				$combinedCss = array();
				foreach($this->css as $css => $media){
					if(strstr($css, '://') > -1){
						$newCss[$css] = $media;
					}else{
						$media = $media['media']?$media['media']:'NULL';
						if(!isset($combinedCss[$media])) $combinedCss[$media] = array();
						$combinedCss[$media][] = $css;
					}
				}
				$this->css = $newCss;

				$this->combine_files = array();
				Requirements::combine_files($checksum.".js", $combinedJs);
				foreach($combinedCss as $media => $Files){
					if($media == 'NULL'){
						Requirements::combine_files($checksum.".css", $Files);
					}else{
						Requirements::combine_files($checksum."-".$media.".css", $Files, $media);
					}
				}

				parent::process_combined_files();

			/*}else{
				$newJavascript = array();
				foreach($this->javascript as $js){
					if(strstr($js, '://') > -1) $newJavascript[] = $js;
				}
				$newJavascript[] = $this->getCombinedFilesFolder() . '/' . $checksum . ".js";
				$this->javascript = $newJavascript;

				$newCss = array();
				foreach($this->css as $css => $media){
					if(strstr($css, '://') > -1) $newCss[$css] = $media;
				}
				$newCss[] = $this->getCombinedFilesFolder() . '/' . $checksum . ".css";
				$this->css = $newCss;
			}*/
		}else{
			parent::process_combined_files();
		}
		
	}

	/**
	 * Minify the given $content according to the file type indicated in $filename
	 *
	 * @param string $filename
	 * @param string $content
	 * @return string
	 */
	protected function minifyFile($filename, $content) {
		// if we have a javascript file and jsmin is enabled, minify the content
		$isJS = stripos($filename, '.js');
		require_once('thirdparty/jsmin/jsmin.php');
		require_once(BASE_PATH .'/minify/code/thirdparty/Compressor.php');
		require_once(BASE_PATH .'/minify/code/thirdparty/UriRewriter.php');
		increase_time_limit_to();
		if($isJS) {
			$content = JSMin::minify($content).";\n";
		}else{
			$content = Minify_CSS_UriRewriter::rewrite($content, Director::baseFolder()."/".dirname($filename), Director::baseFolder());
			$content = Minify_CSS_Compressor::process($content)."\n";
		}
		
		return $content;
	}

}