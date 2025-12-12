<?php

/*!
 * Perlite v1.6 (https://github.com/secure-77/Perlite)
 * Author: sec77 (https://secure77.de)
 * Licensed under MIT (https://github.com/secure-77/Perlite/blob/main/LICENSE)
 */

use Perlite\PerliteParsedown;

require_once __DIR__ . '/vendor/autoload.php';
include('helper.php');

// check get params
if (isset($_GET['mdfile'])) {
	$requestFile = $_GET['mdfile'];

	if (is_string($requestFile)) {
		if (!empty($requestFile)) {
			parseContent($requestFile);
		}
	}
}

// parse content for about modal
if (isset($_GET['about'])) {

	if (is_string($_GET['about'])) {
		parseContent('/' . $about);
	}
}

// search request
if (isset($_GET['search'])) {

	$searchString = $_GET['search'];
	if (is_string($searchString)) {
		if (!empty($searchString)) {
			echo doSearch($rootDir, $searchString);
		}
	}
}


// parse content for home site
if (isset($_GET['home'])) {

	if (is_string($_GET['home'])) {
		parseContent('/' . $index);
	}
}


// parse the md to html
function parseContent($requestFile)
{

	global $path;
	global $uriPath;
	global $cleanFile;
	global $rootDir;
	global $startDir;
	global $lineBreaks;
	global $allowedFileLinkTypes;
	global $htmlSafeMode;
	global $relPathes;

	$Parsedown = new PerliteParsedown();
	$Parsedown->setSafeMode($htmlSafeMode);
	$Parsedown->setBreaksEnabled($lineBreaks);
	$cleanFile = '';

	// call menu again to refresh the array
	menu($rootDir);
	$path = '';


	// get and parse the content, return if no content is there
	$content = getContent($requestFile);
	if ($content === '') {
		return;
	}

	$wordCount = str_word_count($content);
	$charCount = strlen($content);

	// FIX: Pre-process Standard Markdown Links/Images BEFORE any parsing
	// Convert relative paths like ../../image.png to /Vault/Folder/../../image.png
	
	// Calculate src_path early (before Parsedown)
	// Need to reconstruct the full path including the vault name
	$early_src_path = $uriPath . $startDir . $path;
	
	// 1. Images: ![alt](url) - convert relative paths to absolute
	$content = preg_replace_callback('/!\[(.*?)\]\((.*?)\)/', function($matches) use ($early_src_path) {
		$alt = $matches[1];
		$url = $matches[2];
		// Skip absolute URLs, root paths, or data URIs
		if (preg_match('/^(http|https|\/|data:)/', $url)) {
			return $matches[0];
		}
		return "![$alt]($early_src_path/$url)";
	}, $content);

	// Note: Regular links [text](url) are processed AFTER Parsedown in HTML stage
	// to avoid encoding issues and path duplication in special contexts

	$content = $Parsedown->text($content);

	// Relative or absolute pathes
	if ($relPathes) {
		$path = $startDir;
		$mdpath = '';
	} else {
		$mdpath = $path;
		$path = $startDir . $path;
	}

	// FIX: Post-process standard Markdown links in HTML
	// Convert relative href paths to absolute for non-md files
	// Use the modified $path which already contains startDir
	$content = preg_replace_callback('/<a\s+([^>]*?)href="([^"]+)"([^>]*?)>/', function($matches) use ($uriPath, $path) {
		$before = $matches[1];
		$href = $matches[2];
		$after = $matches[3];
		
		// At this point $path = "DigitalGarden/技术笔记/.../folder"
		$src_path = $uriPath . $path;
		
		// Skip absolute URLs, anchors, mailto, etc.
		if (preg_match('/^(http|https|\/|#|mailto:|tel:)/', $href)) {
			return $matches[0];
		}
		
		// Skip if already has internal-link class (processed by Perlite's Wiki Link logic)
		if (strpos($before . $after, 'internal-link') !== false) {
			return $matches[0];
		}
		
		// For md files: leave them as-is (should use Wiki Links [[...]] for internal navigation)
		if (preg_match('/\.md(\?|#|$)/', $href)) {
			return $matches[0];
		}
		
		// For other files (zip, pdf, etc.), convert relative path to absolute
		// Add internal-link class and target="_blank" for consistency
		$newHref = $src_path . '/' . $href;
		$newAttrs = $before . 'href="' . $newHref . '" class="internal-link" target="_blank" rel="noopener noreferrer"' . $after;
		// Remove duplicate attributes
		$newAttrs = preg_replace('/\s*(class|target|rel)="[^"]*"/i', '', $newAttrs);
		return '<a ' . $newAttrs . ' href="' . $newHref . '" class="internal-link" target="_blank" rel="noopener noreferrer">';
	}, $content);

	// FIX: Add popup functionality to standard Markdown images
	// Wrap <img> tags with <a href="#" class="pop"> to enable image popup like Wiki Links
	$content = preg_replace(
		'/<img([^>]+)class="([^"]*)external-link([^"]*)"([^>]*)>/',
		'<p><a href="#" class="pop"><img$1class="$2images$3"$4></a></p>',
		$content
	);

	// Calculate src_path for file links (using already modified $path)
	$linkFileTypes = implode('|', $allowedFileLinkTypes);

	$allowedImageTypes = '(\.png|\.jpg|\.jpeg|\.svg|\.gif|\.bmp|\.tif|\.tiff|\.webp)';

	// At this point, $path already contains the full path (startDir + original path)
	// So we just need to prepend uriPath
	$src_path = $uriPath . $path;

	// embedded pdf links
	$replaces = '<embed src="' . $src_path . '/\\2" type="application/pdf" style="min-height:100vh;width:100%">';
	$pattern = array('/(\!\[\[)(.*?.(?:pdf))(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);

	// embedded mp4 links
	$replaces = '
	<video controls src="' . $src_path . '/\\2" type="video/mp4">
		<a class="internal-link" target="_blank" rel="noopener noreferrer" href="' . $src_path . '/' . '\\2">Your browser does not support the video tag: Download \\2</a>
  	</video>';
	$pattern = array('/(\!\[\[)(.*?.(?:mp4))(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);


	// embedded m4a links
	$replaces = '
	 <video controls src="' . $src_path . '/\\2" type="audio/x-m4a">
			 <a class="internal-link" target="_blank" rel="noopener noreferrer" href="' . $src_path . '/' . '\\2">Your browser does not support the audio tag: Download \\2</a>
	 </video>';
	$pattern = array('/(\!\[\[)(.*?.(?:m4a))(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);


	// links to other files with Alias
	$replaces = '<a class="internal-link" target="_blank" rel="noopener noreferrer" href="' . $src_path . '/' . '\\2">\\3</a>';
	$pattern = array('/(\[\[)(.*?.(?:' . $linkFileTypes . '))\|(.*)(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);

	// links to other files without Alias
	$replaces = '<a class="internal-link" target="_blank" rel="noopener noreferrer" href="' . $src_path . '/' . '\\2">\\2</a>';
	$pattern = array('/(\[\[)(.*?.(?:' . $linkFileTypes . '))(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);

	// img links with external target link
	$replaces = 'noreferrer"><img class="images" width="\\4" height="\\5" alt="image not found" src="' . $src_path . '/\\2\\3' . '"/>';
	$pattern = array('/noreferrer">(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(\d*)x?(\d*)(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);

	// img links with size
	$replaces = '<p><a href="#" class="pop"><img class="images" width="\\4" height="\\5" alt="image not found" src="' . $src_path . '/\\2\\3' . '"/></a></p>';
	$pattern = array('/(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(\d*)x?(\d*)(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);

	// centerise or right align images with "center"/"right" directive
	$pattern = '/(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(center|right)\|?(\d*)x?(\d*)(\]\])/';
	$replaces = function ($matches) use ($src_path) {
		$class = "images";  // Default class for all images
		if (strpos($matches[4], 'center') !== false) {
			$class .= " center";  // Add 'center' class
		} elseif (strpos($matches[4], 'right') !== false) {
			$class .= " right";  // Add 'right' class
		}
		$width = $matches[5] ?? 'auto';
		$height = $matches[6] ?? 'auto';
		return '<p><a href="#" class="pop"><img class="' . $class . '" src="' . $src_path . '/' . $matches[2] . $matches[3] . '" width="' . $width . '" height="' . $height . '"/></a></p>';
	};
	$content = preg_replace_callback($pattern, $replaces, $content);

	// img links with captions and size
	$replaces = '<p><a href="#" class="pop"><img class="images" width="\\5" height="\\6" alt="\\4" src="' . $src_path . '/\\2\\3' . '"/></a></p>';
	$pattern = array('/(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(.+\|)\|?(\d*)x?(\d*)(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);

	// img links with captions
	$replaces = '<p><a href="#" class="pop"><img class="images" alt="\\4" src="' . $src_path . '/\\2\\3' . '"/></a></p>';
	$pattern = array('/(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(.+|)(\]\])/');
	$content = preg_replace($pattern, $replaces, $content);


	// handle internal site links
	// search for links outside of the current folder
	$pattern = array('/(\[\[)(?:\.\.\/)+(.*?)(\]\])/');
	$content = translateLink($pattern, $content, $path, false);

	// search for links in the same folder
	$pattern = array('/(\[\[)(.*?)(\]\])/');
	$content = translateLink($pattern, $content, $path, true);


	// add some meta data
	$content = '
	<div style="display: none">
		<div class="mdTitleHide">' . $cleanFile . '</div>
		<div class="wordCount">' . $wordCount . '</div>
		<div class="charCount">' . $charCount . '</div>
	</div>' . $content;

	echo $content;
	return;

}


// translate relativ links (not used)
// function fixLinks($pattern, $content, $path, $sameFolder) {

// 	return preg_replace_callback($pattern, 
// 	function($matches) use ($path, $sameFolder) {

// 		$newAbPath = $path;
// 		echo "path: " . $path;
// 		echo "<br>";
// 		$pathSplit = explode("/",$path);
// 		$linkFilePart = $matches[2];
// 		$esapeSequence = "#regex_run#";
// 		echo '$matches[1]: ' . $matches[1];
// 		echo '<br>';
// 		echo '$matches[2]: ' . $linkFilePart;
// 		echo '<br>';

// 		$linkDesc = "";

// 		# handle custom link comments and sizes
// 		$splitLink = explode("|", $matches[2]);
// 		if (count($splitLink) > 1) {
// 			$linkFilePart = $splitLink[0];
// 			array_shift($splitLink);
// 			$linkDesc = '|' .implode("|", $splitLink);
// 		}


// 		// do extra stuff to get the absolute path
// 		if ($sameFolder == false) {
// 			$countDirs = count(explode("../",$linkFilePart));
// 			$countDirs = $countDirs -1;
// 			$newPath = array_splice($pathSplit, 1, -$countDirs);			
// 			$newAbPath = implode('/', $newPath);
// 			echo "new file path: " . $newAbPath;
// 			echo "<br>";
// 			echo "old file path: " . $linkFilePart;
// 			echo "<br>";
// 		}

// 		if (substr($newAbPath,0,1) == '/') {
// 			$newAbPath = substr($newAbPath,1);
// 		}


// 		$origPath = explode('/', $linkFilePart);
// 		array_pop($origPath);
// 		$origPath = implode('/', $origPath);
// 		//check if its already an absolut path
// 		echo "new file path: " . $newAbPath;
// 		echo "<br>";
// 		echo "old file path: " . $origPath;
// 		echo "<br>";


// 		if (count_chars($origPath) >= count_chars($newAbPath)) {

// 			$urlPath = $linkFilePart;

// 		} else {

// 			$linkFile = str_replace("../","",$linkFilePart);
// 			$urlPath = $newAbPath. '/'. $linkFile;
// 		}


// 		return '[['.$urlPath.$linkDesc.']]';
// 	}
// ,$content);
// }



//internal links
// can be simplified (no need of path translation)
function translateLink($pattern, $content, $path, $sameFolder)
{

	return preg_replace_callback(
		$pattern,
		function ($matches) use ($path, $sameFolder) {


			global $uriPath;
			$newAbPath = $path;
			$pathSplit = explode("/", $path);
			$linkName_full = $matches[2];
			$linkName = $linkName_full;
			$linkFile = $matches[2];

			# handle custom internal obsidian links
			$splitLink = explode("|", $matches[2]);
			if (count($splitLink) > 1) {

				$linkFile = $splitLink[0];
				$linkName = $splitLink[1];
			}

			# handle internal popups
			$popupClass = '';
			$popUpIcon = '';

			if (count($splitLink) > 2) {

				$popupClass = ' internal-popup';
				$popUpIcon = '<svg class="popup-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon lucide-maximize"><path d="M8 3H5a2 2 0 0 0-2 2v3"></path><path d="M21 8V5a2 2 0 0 0-2-2h-3"></path><path d="M3 16v3a2 2 0 0 0 2 2h3"></path><path d="M16 21h3a2 2 0 0 0 2-2v-3"></path></svg>';
			}


			// do extra stuff to get the absolute path
			if ($sameFolder == false) {
				$countDirs = count(explode("../", $matches[0]));
				$countDirs = $countDirs - 1;
				// FIX: Start from index 0 instead of 1 to preserve DigitalGarden prefix
				$newPath = array_splice($pathSplit, 0, -$countDirs);
				$newAbPath = implode('/', $newPath);
			}


			$urlPath = $newAbPath . '/' . $linkFile;
			if (substr($urlPath, 0, 1) == '/') {
				#$urlPath = '/' . $urlPath;
				$urlPath = substr($urlPath, 1);
			}

			$refName = '';

			# if same document heading reference
			if (substr($linkName_full, 0, 1) == '#') {

				$splitLink = explode("#", $urlPath);
				$urlPath = '';
				$refName = $splitLink[1];
				$refName = '#' . $refName;
				$href = 'href="';
			} else {
				#$href = 'href="?link=';
				$href = 'href="' . $uriPath;
			}

			$urlPath = str_replace('&amp;', '&', $urlPath);

			#$urlPath = rawurlencode($urlPath);
			$urlPath = str_replace('%23', '#', $urlPath);

			$urlPath = str_replace('~', '%80', $urlPath);
			$urlPath = str_replace('-', '~', $urlPath);
			$urlPath = str_replace(' ', '-', $urlPath);


			return '<a class="internal-link' . $popupClass . '"' . $href . $urlPath . $refName . '">' . $linkName . '</a>' . $popUpIcon;
		}
		,
		$content
	);
}


// read content from file
function getContent($requestFile)
{
	global $avFiles;
	global $path;
	global $cleanFile;
	global $rootDir;
	$content = '';

	// check if file is in array
	if (in_array($requestFile, $avFiles, true)) {
		$cleanFile = $requestFile;
		$n = strrpos($requestFile, "/");
		$path = substr($requestFile, 0, $n);
		$content .= file_get_contents($rootDir . $requestFile . '.md', true);
	}

	return $content;
}

?>