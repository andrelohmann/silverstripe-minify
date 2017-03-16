<?php

/**
 * @package minify
 * @subpackage view
 */
class Minify_Requirements_Backend extends Requirements_Backend {

	/**
	 * Whether to add the minified and combined css inline or as link tag
	 * 
	 * @var bool
	 */
	protected $inline_css = true;

	/**
	 * Enable or disable inline css
	 * 
	 * @param $enable
	 */
	public function set_inline_css($enable) {
		$this->inline_css = (bool) $enable;
	}

	/**
	 * Check whether inline css is enabled.
	 * 
	 * @return bool
	 */
	public function get_inline_css() {
		return $this->inline_css;
	}

	/**
	 * Do the heavy lifting involved in combining (and, in the case of JavaScript minifying) the
	 * combined files.
	 */
	public function process_combine_and_minify($checksum) {
		
		$this->process_combined_files();
		
		$newJavascript = array();
		$combinedJs = array();
		foreach(array_diff_key($this->javascript,$this->blocked) as $js => $bool){
			if(strpos($js, '://') !== false || (strpos($js, '//') === 0)) $newJavascript[$js] = $bool;
			else $combinedJs[] = $js;
		}
		$this->javascript = $newJavascript;

		$newCss = array();
		$combinedCss = array();
		foreach(array_diff_key($this->css,$this->blocked) as $css => $media){
			if(strpos($css, '://') !== false || (strpos($css, '//') === 0)){
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
				'combine_files' => $this->combine_files
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
				if(is_array($this->customScript) && count($this->customScript) > 0) {
					
                                        $jsRequirements .= "<script type=\"text/javascript\">\n//<![CDATA[\n";
                                                
					foreach(array_diff_key($this->customScript,$this->blocked) as $script) {
                                            $jsRequirements .= "$script\n";
					}
                                        
					$jsRequirements .= "\n//]]>\n</script>\n";
				}

				foreach(array_diff_key($this->css,$this->blocked) as $file => $params) {
					$path = Convert::raw2xml($this->path_for_file($file));
					if($path){
                                            $media = (isset($params['media']) && !empty($params['media']))?" media=\"{$params['media']}\"" : "";
                                            if(!$this->get_inline_css() || preg_match('{^//|http[s]?}', $path)){
                                                $requirements .= "<link rel=\"stylesheet\" type=\"text/css\"{$media} href=\"$path\" />\n";
                                            }else{
                                                // put css inline
                                                $requirements .= "<style type=\"text/css\"{$media}>\n";
                                                $requirements .= file_get_contents(preg_replace('/\?.*/', '', Director::baseFolder() . '/' . $file))."\n";
                                                $requirements .= "</style>\n";
                                            }
                                        }
				}
                                
                                if(is_array($this->customCSS) && count($this->customCSS) > 0) {
                                    $requirements .= "<style type=\"text/css\">\n";
                                    foreach(array_diff_key($this->customCSS, $this->blocked) as $css) {
					$requirements .= $css."\n";
                                    }
                                    $requirements .= "</style>\n";
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