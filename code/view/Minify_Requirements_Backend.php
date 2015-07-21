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
	public function process_combine_and_minify($checksum) {
		
		$this->process_combined_files();
		
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

		$this->process_combined_files();
		
	}

	/**
	 * Update the given HTML content with the appropriate include tags for the registered
	 * requirements. Needs to receive a valid HTML/XHTML template in the $content parameter,
	 * including a head and body tag.
	 *
	 * @param string $templateFile No longer used, only retained for compatibility
	 * @param string $content      HTML content that has already been parsed from the $templateFile
	 *                             through {@link SSViewer}
	 * @return string HTML content augmented with the requirements tags
	 */
	public function includeInHTML($templateFile, $content) {
		if(
			(strpos($content, '</head>') !== false || strpos($content, '</head ') !== false)
			&& ($this->css || $this->javascript || $this->customCSS || $this->customScript || $this->customHeadTags)
		) {
			
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

			$cache = SS_Cache::factory('MINIFY_CACHE');
			if(!$Minified = $cache->load($checksum)){
				// fill cache
				$requirements = '';
				$jsRequirements = '';

				if(Controller::has_curr()) $url = explode('/',  Controller::curr()->request->getURL());
				else $url = false;
				// Set Requirements for all custom Controllers
				if($url && !in_array($url[0], array('admin', 'dev', 'interactive'))) $this->process_combine_and_minify($checksum);
				else $this->process_combined_files();


				foreach(array_diff_key($this->javascript,$this->blocked) as $file => $dummy) {
					$path = Convert::raw2xml($this->path_for_file($file));
					if($path) {
						$jsRequirements .= "<script type=\"text/javascript\" src=\"$path\"></script>\n";
					}
				}

				// Add all inline JavaScript *after* including external files they might rely on
				if($this->customScript) {
					foreach(array_diff_key($this->customScript,$this->blocked) as $script) {
						$jsRequirements .= "<script type=\"text/javascript\">\n//<![CDATA[\n";
						$jsRequirements .= "$script\n";
						$jsRequirements .= "\n//]]>\n</script>\n";
					}
				}

				foreach(array_diff_key($this->css,$this->blocked) as $file => $params) {
					$path = Convert::raw2xml($this->path_for_file($file));
					if($path) {
						$media = (isset($params['media']) && !empty($params['media']))
							? " media=\"{$params['media']}\"" : "";
						$requirements .= "<link rel=\"stylesheet\" type=\"text/css\"{$media} href=\"$path\" />\n";
					}
				}

				foreach(array_diff_key($this->customCSS, $this->blocked) as $css) {
					$requirements .= "<style type=\"text/css\">\n$css\n</style>\n";
				}

				foreach(array_diff_key($this->customHeadTags,$this->blocked) as $customHeadTag) {
					$requirements .= "$customHeadTag\n";
				}

				// Remove all newlines from code to preserve layout
				$jsRequirements = preg_replace('/>\n*/', '>', $jsRequirements);


				$cache->save(serialize(array(
					'requirements' => $requirements,
					'jsRequirements' => $jsRequirements
				)));

			}else{
				$Minified = unserialize($Minified);
				$requirements = $Minified['requirements'];
				$jsRequirements = $Minified['jsRequirements'];
			}

			if ($this->force_js_to_bottom) {

				// Forcefully put the scripts at the bottom of the body instead of before the first
				// script tag.
				$content = preg_replace("/(<\/body[^>]*>)/i", $jsRequirements . "\\1", $content);

				// Put CSS at the bottom of the head
				$content = preg_replace("/(<\/head>)/i", $requirements . "\\1", $content);
			} elseif($this->write_js_to_body) {

				// If your template already has script tags in the body, then we try to put our script
				// tags just before those. Otherwise, we put it at the bottom.
				$p2 = stripos($content, '<body');
				$p1 = stripos($content, '<script', $p2);

				$commentTags = array();
				$canWriteToBody = ($p1 !== false)
					&&
					// Check that the script tag is not inside a html comment tag
					!(
						preg_match('/.*(?|(<!--)|(-->))/U', $content, $commentTags, 0, $p1)
						&&
						$commentTags[1] == '-->'
					);

				if($canWriteToBody) {
					$content = substr($content,0,$p1) . $jsRequirements . substr($content,$p1);
				} else {
					$content = preg_replace("/(<\/body[^>]*>)/i", $jsRequirements . "\\1", $content);
				}

				// Put CSS at the bottom of the head
				$content = preg_replace("/(<\/head>)/i", $requirements . "\\1", $content);
			} else {
				$content = preg_replace("/(<\/head>)/i", $requirements . "\\1", $content);
				$content = preg_replace("/(<\/head>)/i", $jsRequirements . "\\1", $content);
			}
		}

		return $content;
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