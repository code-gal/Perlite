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
	$content = $Parsedown->text($content);
	$documentRelativePath = $path;
	
	if (!empty($documentRelativePath)) {
		$fullDocumentPath = $startDir . $documentRelativePath;
	} else {
		$fullDocumentPath = $startDir;
	}
	$fullDocumentPath = preg_replace('#/+#', '/', $fullDocumentPath);
	
	$src_path = $uriPath . $fullDocumentPath;
	$src_path = preg_replace('#/+#', '/', $src_path);
	
	if ($relPathes) {
		$absolutePathBase = $uriPath . $startDir;
		$absolutePathBase = preg_replace('#/+#', '/', $absolutePathBase);
	} else {
		$absolutePathBase = null;
	}
	
	$mdpath = $relPathes ? '' : $documentRelativePath;
	$extendedFileTypes = $allowedFileLinkTypes;
	if (!in_array('ppt', $extendedFileTypes)) {
		$extendedFileTypes[] = 'ppt';
	}
	if (!in_array('pptx', $extendedFileTypes)) {
		$extendedFileTypes[] = 'pptx';
	}
	if (!in_array('7z', $extendedFileTypes)) {
		$extendedFileTypes[] = '7z';
	}
	if (!in_array('zip', $extendedFileTypes)) {
		$extendedFileTypes[] = 'zip';
	}
	if (!in_array('rar', $extendedFileTypes)) {
		$extendedFileTypes[] = 'rar';
	}
	$linkFileTypes = implode('|', $extendedFileTypes);
	$allowedImageTypes = '(\.png|\.jpg|\.jpeg|\.svg|\.gif|\.bmp|\.tif|\.tiff|\.webp)';
	$pattern = '/<img\s+([^>]*?)src=["\'](?!http:\/\/|https:\/\/|\/\/|\/)([^"\']+)["\'](.*?)>/i';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$beforeSrc = $matches[1];
		$imgPath = $matches[2];
		$afterSrc = $matches[3];
		
		$classAttr = 'class="images"';
		if (preg_match('/class=["\']([^"\']*)["\']/', $beforeSrc . $afterSrc, $classMatch)) {
			$existingClass = $classMatch[1];
			if (strpos($existingClass, 'images') === false) {
				$beforeSrc = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 images"', $beforeSrc . $afterSrc, 1);
				$afterSrc = '';
			} else {
				$beforeSrc = $beforeSrc;
			}
		} else {
			$beforeSrc = trim($beforeSrc) . ' ' . $classAttr;
		}
		
		$imgPath = preg_replace('/^\.\//', '', $imgPath);
		
		$fullPath = $src_path . '/' . $imgPath;
		
		return '<p><a href="#" class="pop"><img ' . trim($beforeSrc) . ' src="' . $fullPath . '"' . $afterSrc . '/></a></p>';
	}, $content);
	
	if ($relPathes && $absolutePathBase) {
		$pattern = '/<img\s+([^>]*?)src=["\']\/(?!\/)([^"\']+)["\'](.*?)>/i';
		$content = preg_replace_callback($pattern, function($matches) use ($absolutePathBase) {
			$beforeSrc = $matches[1];
			$imgPath = $matches[2];
			$afterSrc = $matches[3];
			if (strpos($beforeSrc . $afterSrc, 'class=') === false) {
				$beforeSrc = trim($beforeSrc) . ' class="images"';
			} elseif (strpos($beforeSrc . $afterSrc, 'images') === false) {
				$beforeSrc = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 images"', $beforeSrc . $afterSrc, 1);
				$afterSrc = '';
			}
			
			$fullPath = $absolutePathBase . '/' . $imgPath;
			$fullPath = preg_replace('#/+#', '/', $fullPath);
			return '<p><a href="#" class="pop"><img ' . trim($beforeSrc) . ' src="' . $fullPath . '"' . $afterSrc . '/></a></p>';
		}, $content);
	}
	
	$pattern = '/<a\s+([^>]*?)href=["\'](?!http:\/\/|https:\/\/|\/\/|\/)([^"\']+\.(?:' . $linkFileTypes . '))["\'](.*?)>/i';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$beforeHref = $matches[1];
		$filePath = $matches[2];
		$afterHref = $matches[3];
		$filePath = preg_replace('/^\.\//', '', $filePath);
		$fullPath = $src_path . '/' . $filePath;
		$classAttr = 'class="internal-link"';
		$targetAttr = 'target="_blank"';
		$relAttr = 'rel="noopener noreferrer"';
		if (strpos($beforeHref . $afterHref, 'class=') === false) {
			$beforeHref = trim($beforeHref) . ' ' . $classAttr;
		} elseif (strpos($beforeHref . $afterHref, 'internal-link') === false) {
			$beforeHref = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 internal-link"', $beforeHref . $afterHref, 1);
			$afterHref = '';
		}
		
		if (strpos($beforeHref . $afterHref, 'target=') === false) {
			$beforeHref = trim($beforeHref) . ' ' . $targetAttr;
		}
		
		if (strpos($beforeHref . $afterHref, 'rel=') === false) {
			$beforeHref = trim($beforeHref) . ' ' . $relAttr;
		}
		
		return '<a ' . trim($beforeHref) . ' href="' . $fullPath . '"' . $afterHref . '>';
	}, $content);
	
	if ($relPathes && $absolutePathBase) {
		$pattern = '/<a\s+([^>]*?)href=["\']\/(?!\/)([^"\']+\.(?:' . $linkFileTypes . '))["\'](.*?)>/i';
		$content = preg_replace_callback($pattern, function($matches) use ($absolutePathBase) {
			$beforeHref = $matches[1];
			$filePath = $matches[2];
			$afterHref = $matches[3];
			
			$fullPath = $absolutePathBase . '/' . $filePath;
			$fullPath = preg_replace('#/+#', '/', $fullPath);
			if (strpos($beforeHref . $afterHref, 'class=') === false) {
				$beforeHref = trim($beforeHref) . ' class="internal-link"';
			} elseif (strpos($beforeHref . $afterHref, 'internal-link') === false) {
				$beforeHref = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 internal-link"', $beforeHref . $afterHref, 1);
				$afterHref = '';
			}
			if (strpos($beforeHref . $afterHref, 'target=') === false) {
				$beforeHref = trim($beforeHref) . ' target="_blank"';
			}
			
			if (strpos($beforeHref . $afterHref, 'rel=') === false) {
				$beforeHref = trim($beforeHref) . ' rel="noopener noreferrer"';
			}
			return '<a ' . trim($beforeHref) . ' href="' . $fullPath . '"' . $afterHref . '>';
		}, $content);
	}
	$pattern = '/<a\s+([^>]*?)href=["\'](?!http:\/\/|https:\/\/|\/\/|\/)([^"\']+\.md)["\'](.*?)>/i';
	$content = preg_replace_callback($pattern, function($matches) use ($fullDocumentPath, $startDir) {
		$beforeHref = $matches[1];
		$mdPath = $matches[2];
		$afterHref = $matches[3];
		$mdPath = preg_replace('/^\.\//', '', $mdPath);
		
		$currentDir = dirname($fullDocumentPath);
		$currentParts = explode('/', trim($currentDir, '/'));
		$relativeParts = explode('/', trim($mdPath, '/'));
		
		$resultParts = $currentParts;
		foreach ($relativeParts as $part) {
			if ($part === '..') {
				if (count($resultParts) > 0) {
					array_pop($resultParts);
				}
			} elseif ($part !== '.' && $part !== '') {
				$resultParts[] = $part;
			}
		}
		
		$absolutePath = implode('/', $resultParts);
		if (strpos($absolutePath, $startDir . '/') === 0) {
			$absolutePath = substr($absolutePath, strlen($startDir) + 1);
		}
		$linkPath = preg_replace('/\.md$/', '', $absolutePath);
		$linkPath = urldecode($linkPath);
		$perliteLink = '?link=' . $linkPath;
		if (strpos($beforeHref . $afterHref, 'class=') === false) {
			$beforeHref = trim($beforeHref) . ' class="internal-link"';
		} elseif (strpos($beforeHref . $afterHref, 'internal-link') === false) {
			$beforeHref = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 internal-link"', $beforeHref . $afterHref, 1);
			$afterHref = '';
		}
		
		return '<a ' . trim($beforeHref) . ' href="' . $perliteLink . '"' . $afterHref . '>';
	}, $content);
	if ($relPathes && $absolutePathBase) {
		$pattern = '/<a\s+([^>]*?)href=["\']\/(?!\/)([^"\']+\.md)["\'](.*?)>/i';
		$content = preg_replace_callback($pattern, function($matches) {
			$beforeHref = $matches[1];
			$mdPath = $matches[2];
			$afterHref = $matches[3];
			$linkPath = preg_replace('/\.md$/', '', $mdPath);
			$linkPath = urldecode($linkPath);
			$perliteLink = '?link=' . $linkPath;
			if (strpos($beforeHref . $afterHref, 'class=') === false) {
				$beforeHref = trim($beforeHref) . ' class="internal-link"';
			} elseif (strpos($beforeHref . $afterHref, 'internal-link') === false) {
				$beforeHref = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 internal-link"', $beforeHref . $afterHref, 1);
				$afterHref = '';
			}
			
			return '<a ' . trim($beforeHref) . ' href="' . $perliteLink . '"' . $afterHref . '>';
		}, $content);
	}
	$pattern = '/(\!\[\[)(.*?.(?:pdf))(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '<embed src="' . $fullPath . '" type="application/pdf" style="min-height:100vh;width:100%">';
	}, $content);
	$pattern = '/(\!\[\[)(.*?.(?:mp4))(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '
	<video controls src="' . $fullPath . '" type="video/mp4">
		<a class="internal-link" target="_blank" rel="noopener noreferrer" href="' . $fullPath . '">Your browser does not support the video tag: Download ' . $filePath . '</a>
  	</video>';
	}, $content);
	// embedded m4a links
	$pattern = '/(\!\[\[)(.*?.(?:m4a))(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '
	 <video controls src="' . $fullPath . '" type="audio/x-m4a">
			 <a class="internal-link" target="_blank" rel="noopener noreferrer" href="' . $fullPath . '">Your browser does not support the audio tag: Download ' . $filePath . '</a>
	 </video>';
	}, $content);
	// links to other files with Alias
	$pattern = '/(\[\[)(.*?.(?:' . $linkFileTypes . '))\|(.*)(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2];
		$alias = $matches[3];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '<a class="internal-link" target="_blank" rel="noopener noreferrer" href="' . $fullPath . '">' . $alias . '</a>';
	}, $content);
	// links to other files without Alias
	$pattern = '/(\[\[)(.*?.(?:' . $linkFileTypes . '))(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '<a class="internal-link" target="_blank" rel="noopener noreferrer" href="' . $fullPath . '">' . $filePath . '</a>';
	}, $content);
	// img links with external target link
	$pattern = '/noreferrer">(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(\d*)x?(\d*)(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2] . $matches[3];
		$width = $matches[4];
		$height = $matches[5];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return 'noreferrer"><img class="images" width="' . $width . '" height="' . $height . '" alt="image not found" src="' . $fullPath . '"/>';
	}, $content);
	// img links with size
	$pattern = '/(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(\d*)x?(\d*)(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2] . $matches[3];
		$width = $matches[4];
		$height = $matches[5];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '<p><a href="#" class="pop"><img class="images" width="' . $width . '" height="' . $height . '" alt="image not found" src="' . $fullPath . '"/></a></p>';
	}, $content);
	// centerise or right align images with "center"/"right" directive
	$pattern = '/(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(center|right)\|?(\d*)x?(\d*)(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$class = "images";  // Default class for all images
		if (strpos($matches[4], 'center') !== false) {
			$class .= " center";  // Add 'center' class
		} elseif (strpos($matches[4], 'right') !== false) {
			$class .= " right";  // Add 'right' class
		}
		$width = $matches[5] ?? 'auto';
		$height = $matches[6] ?? 'auto';
		$filePath = $matches[2] . $matches[3];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '<p><a href="#" class="pop"><img class="' . $class . '" src="' . $fullPath . '" width="' . $width . '" height="' . $height . '"/></a></p>';
	}, $content);
	// img links with captions and size
	$pattern = '/(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(.+\|)\|?(\d*)x?(\d*)(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2] . $matches[3];
		$alt = $matches[4];
		$width = $matches[5];
		$height = $matches[6];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '<p><a href="#" class="pop"><img class="images" width="' . $width . '" height="' . $height . '" alt="' . $alt . '" src="' . $fullPath . '"/></a></p>';
	}, $content);
	// img links with captions
	$pattern = '/(\!?\[\[)(.*?)' . $allowedImageTypes . '\|?(.+|)(\]\])/';
	$content = preg_replace_callback($pattern, function($matches) use ($src_path) {
		$filePath = $matches[2] . $matches[3];
		$alt = $matches[4];
		$fullPath = $src_path . '/' . $filePath;
		$fullPath = preg_replace('#/+#', '/', $fullPath);
		return '<p><a href="#" class="pop"><img class="images" alt="' . $alt . '" src="' . $fullPath . '"/></a></p>';
	}, $content);
	// handle internal site links
	// search for links outside of the current folder
	$pattern = array('/(\[\[)(?:\.\.\/)+(.*?)(\]\])/');
	$content = translateLink($pattern, $content, $path, false);
	// search for links in the same folder
	$pattern = array('/(\[\[)(.*?)(\]\])/');
	$content = translateLink($pattern, $content, $mdpath, true);
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
				$newPath = array_splice($pathSplit, 1, -$countDirs);
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