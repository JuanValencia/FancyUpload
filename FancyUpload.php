<?php
/**
 * MediaWiki Fancy Upload v 0.2
 * Register the <fancyupload> tag
 * Created by Juan Valencia
 */

/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 * (I copied this comment from somewhere... don't recall where)
 */

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "FancyUpload is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

/**
 * Register hooks and credits
 */

$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Fancy Upload',
        'author' => 'Juan Valencia',
        'description' => 'Allows easy uploads from anywhere where a fancyupload tag is included.',
        'url' => 'http://www.mediawiki.org/wiki/Extension:FancyUpload',
        'version' => '0.2'
);
 
/**
 * 
 */

$wgExtensionFunctions[] = "wfFancyUpload"; //register the hook that gets MW to call this extension
$wgAjaxExportList[] = 'efWikiUpload'; //register ajax function
$wgAjaxExportList[] = 'efWikiUploadFull'; //register ajax function
$wgAjaxExportList[] = 'efFancyUploadCheckName'; //register a checkName function


function wfFancyUpload() {
	global $wgParser;
	$wgParser->setHook( "fancyupload", "RenderFancyUpload" ); //set the <fancyupload> tag to point to the following function
}

function RenderFancyUpload( $input, $args, $parser, $frame ) {
        global $wgUploadPath; #defaults to {$wgScriptPath}/images
        global $wgOut; #You can use this to call $wgOut->addScript($someScript)
        global $wgUseAjax;
 	global $wgScriptPath;
	global $wgUploadDirectory, $wgUrlDirectory;
	
	$FULL = false;
	if (strpos($input, 'full') !== false) {
		$FULL = true;
	}
	
	$parser->disableCache();

	if (!$wgUseAjax) { //We rely heavily on ajax.
		return "Error: Ajax must be enabled for the extension FancyUpload to work!";
	}
// Load css
	$wgOut->addScript("<style type='text/css'>@import '$wgScriptPath/extensions/FancyUpload/FancyUpload.css';</style>");


/**
 * In essence, everything within the EOJS tag below is the javascript that 
 * that FancyUpload extension uses.  One could port it to its own file
 * however for convenience, I have left it inside the PHP file for reference and editing.
 */
        
        $wgOut->addScript(<<<EOJS

<script>
	/*
	 * doMySubmit() submits the form to the hidden <iframe>
	 * This works around the fact that we cannot POST using ajax
	 * From there FancyUploadCallback.php uploads our file to an
	 * appropriate location on the server (outside of MediaWiki)
	 */
	var doFullness = false;

	function doMySubmit(isFull) {
		if (isFull == "full") {
			doFullness=true;
		}
		try { 		
			document.getElementById('fancyUploadForm').target = 'upload_target';
			document.getElementById('fancyUploadForm').submit();

		} catch (err) {alert("doMySubmit(): " + err.description);}
		
	}

	/*
	 * parseIFrame() gets called every time that the hidden <iframe>
	 * is done loading.  When the page loads, it does nothing because we try to access
	 * elements that are not there.
	 * If it is returning from doMySubmit(), it will have the elements
	 * that tell us exactly where we are in the process.
	 * If there is a "fileLocation" <div> in the <iframe>, then that is our file on the server.
	 * If there is a "errorString" <div> in the <iframe>, then there was a problem with the upload.
	 */
	function parseIframe() {
		try {
			var myFrame = document.getElementById('upload_target');
			var urlBox = document.getElementById('locationFromFrame');
			var uploadSuccess = false;
			var error = document.getElementById('error');
			var halfUrl = "";
			var errorString = "";
			try {
				halfUrl = myFrame.contentWindow.document.getElementById('fileLocation').innerHTML;
				urlBox.value = halfUrl;
				uploadSuccess = true;
				
			} catch (err) {
				error.innerHTML = "";
				urlBox.value = "";
				try {
					errorString += myFrame.contentWindow.document.getElementById('errorString').innerHTML;
					error.innerHTML = errorString;
				} catch (err) {
					errorString += "Nothin yet!";
				}
			}
			if (uploadSuccess) {
				error.innerHTML = "Loading...";	
				if (doFullness) {
					var newName = document.getElementById('fancyUploadCheckName').value;
					var mExt = halfUrl.split(".").pop();
					newName = newName + "." + mExt;
					sajax_do_call( 'efWikiUploadFull', [halfUrl, newName, 'html'], error);
				} else {
					sajax_do_call( 'efWikiUpload', [halfUrl, 'html'], error);
				}
			} 	
		} catch (err) {alert(err.description);}
	}

	function showDebug() {
		var ifr = document.getElementById('upload_target');
		ifr.style.display = ifr.style.display == 'none' ? 'block' : 'none';
	}

	function checkName() {	
		var inBox = document.getElementById('fancyUploadCheckName');
		var potentialName = inBox.value;
		var error = document.getElementById('error');
		var fileInput = document.getElementById('fancyUploadFileInput');
		var fileName = fileInput.value;
		var splitFileName = fileName.split(".");
		fileName = splitFileName.pop();
		if (potentialName == '') {
			var fromUrl = document.getElementById('fancyUploadFileInput').value;
			if (fromUrl != '') {
				potentialName = fromUrl;
			}
		} 
		sajax_do_call('efFancyUploadCheckName', [potentialName + "." + fileName, 'html'], error);
	}
	function doFancyUploadTransfer() {
		try { //If this doesn't work, its probably because we are not in "Full" mode
			var fileInput = document.getElementById('fancyUploadFileInput');
			var fileName = fileInput.value;
			var nameInput = document.getElementById('fancyUploadCheckName');
			var newName = fileName.split("/");
			fileName = newName.pop();
			newName = fileName.split(".");
			newName.pop();
			fileName = newName.pop();
			
			fileName = fileName.split('\\\\');
			nameInput.value = fileName.pop();
		} catch (err) {}		
	}
	

</script>
EOJS
	);
/**
 * At this point the javascript Ends
 * make sure you realize you are back in the 
 * PHP extension after this line
 */

/**
 * Now we write the html that actually replaces the <fancyUpload> tag
 * Essentially, there are sever hidden <input> tags for use in debugging
 * Also, there is a hidden <iframe> for submitting and passing arguments
 * without having to refresh the page.
 */
	$fullTag = "";
	$fullCode = "";
	$fullArgument = "empty";
	if ($FULL) {
		$fullTag = "<h5 id='FancyUploadMiniHead' class='FancyUploadMiniHead'>File to Upload:</h5>";
		$fullArgument = "full";
		$fullCode=<<<FULL
<h5 id='FancyUploadMiniHead' class='FancyUploadMiniHead'>MediaWiki Name for File:</h5>
<input class='FancyUploadCheck' type='text' id='fancyUploadCheckName' name='fancyUploadCheckName' />
<a class='FancyUploadLinkButtonB' onClick='checkName()'>Check Name</a><br />
<div  class='FancyUploadSpacer'></div>
FULL;
	}

	$uploadCode = <<<FORM
<div class='FancyUpload'>
	<div id='fancyBackgroundDiv' class='FancyBackground'>
		<form id='fancyUploadForm' class='FancyUploadForm' name='fancyUploadForm' method='post' target='upload_target' enctype='multipart/form-data' action='../extensions/FancyUpload/FancyUploadCallback.php' onsubmit="return false;">
			<h4 id='FancyUploadHead' class='FancyUploadHead'>Upload Files to the Wiki</h4>
			<p id='FancyUploadExplanationA' class='FancyUploadExplanation'>(Mediwiki names for uploaded files must be unique, so we suggest names that include the project, dates or other features to make them unique.   The suggested format is as follows: “Project-File_Description-Year-Month-Day-Time”.  Use the "Mediawiki name for file" field below to rename common file names.)</p>
			<p id='FancyUploadExplanationB' class='FancyUploadExplanation'>(MediaWiki requires approximate 1 minute to reserve a new file name.  Be careful not to overwrite files.  The name checking capability will not catch repeated attempts to immediately upload a file to the same Mediawiki name. If you would like to overwrite your files, please go to: <a href="http://bev-linux-essex/essexwiki/index.php/Special:Upload">Special:Upload</a>)</p>
			$fullTag
			<input class='FancyUploadFile' type='file' id='fancyUploadFileInput' name='fancyUploadFileInput' onchange='doFancyUploadTransfer()'/><br />
			<input class='FancyUpload' type='text' id='locationFromFrame' name='locationFromFrame'></input>
			<div  class='FancyUploadSpacer'></div>
			$fullCode
			<a class='FancyUploadLinkButton' onClick='doMySubmit("$fullArgument")'>Upload</a>
			<a class='FancyUploadDebug' onClick='showDebug()'>debug&gt;&gt;</a>
			<div class='FancyUploadError' id='error'></div>
			<div class='FancyUploadClear' style='clear:both;'></div>
		</form>
	
		<iframe id='upload_target' class='FancyUpload' onload='parseIframe()' name='upload_target' src=''></iframe>
	</div>
</div>
FORM;
	return $uploadCode;
}

/*
 * efWikiUpload($url) is an Ajax function that submits the file we 
 * uploaded to the server using FancyUploadCallback.php
 * It takes the Url of the file on the server and submits it to
 * MediaWiki using the API's internal FauxRequest methods.
 * 
 */

function efWikiUpload( $urlOfFile) {
	return efWikiUploadFull($urlOfFile, '');
}

function efWikiUploadFull($urlOfFile, $newFilename) {
	global $wgScriptPath;
	if (($newFilename) == '') {
		$fileName = explode("/", $urlOfFile); //split the url so that you can get just the filename.
		$mImgName = end($fileName);
		$mImgName = ucfirst(str_replace(' ', '_', $mImgName));
	} else {
		$mImgName = ucfirst(str_replace(' ', '_', $newFilename));
	}
	//return $mImgName;

	$params = new FauxRequest(array (
		'action' => 'query',
		'titles' => 'File:' . $mImgName,
		'prop' => 'imageinfo',
		'format' => 'php'
	)); // Create a FauxRequest to call MediaWiki's Api, want to see if the image exists.
	try {
	$enableWrite = true; // This is set to false by default, in the ApiMain constructor
	$api = new ApiMain($params,$enableWrite);
	$api->execute();
	$data = & $api->getResultData();
        } catch (Exception $e) {
		return "Error: " . $e->getMessage(); //The Api didn't like us.
	}

	#If the image does not exist, go ahead

	if ($data['query']['pages'][-1]) {

		$params = new FauxRequest(array (
			'action' => 'query',
			'prop' => 'info',
			'intoken' => 'edit',
			'titles' => 'Something',
			'format' => 'php'
		)); // Create a FauxRequest to call MediaWiki's Api
	 	// on the first go, we want the Api to give us a token for editing (used for uploading as well)
		try {
		$enableWrite = true; // This is set to false by default, in the ApiMain constructor
		$api = new ApiMain($params,$enableWrite);
		$api->execute();
		$data = & $api->getResultData();
	        } catch (Exception $e) {
			return "Error: " . $e->getMessage(); //The Api didn't like us.
		}
		$token = ($data['query']['pages'][-1]['edittoken']); //gets the token from the Api
	
		
		$params = new FauxRequest(array (
			'action' => 'upload',
			'filename' => rawurldecode($mImgName), //TODO: get the filename earlier, and just plug it in here
			'url' => $urlOfFile,
			'token' => $token,
			'format' => 'xml'
		));
		try { // This request is to have mediawiki take the file from our temp place in the server to a permanent home on the wiki.
		$enableWrite = true; // This is set to false by default, in the ApiMain constructor
		$api = new ApiMain($params,$enableWrite);
		$api->execute();
		$data = & $api->getResultData();
	        } catch (Exception $e) {
			return "Error: " . $e->getMessage();
		}
		return "Success - $mImgName has been sent to MediaWiki for processing.<input type='hidden' name='form_FancyUploadPassAlong' id='form_FancyUploadPassAlong' style='display:none' value='[[File:$mImgName]]'><br /><a class='fancyUploadSuccessLink' href='$wgScriptPath/index.php/File:$mImgName'>File:$mImgName</a></input>";

	} else { #If the image does exist, then that name has already been uploaded.
		return "File With that Name Already Uploaded<input type='hidden' name='form_FancyUploadPassAlong' id='form_FancyUploadPassAlong' style='display:none' value='[[File:$mImgName]]'><br /><a class='fancyUploadSuccessLink'  href='$wgScriptPath/index.php/File:$mImgName'>File:$mImgName</a></input>";
	}
}

function efFancyUploadCheckName($potentialName) {

	$newPotentialName = "";

	if (($potentialName) == '') {
		return "I will not check a blank name!";
	} else if (strpos($potentialName, "\\") !== false) {
		$nameSplit = explode('\\', $potentialName);
		$newPotentialName = end($nameSplit);
	} else {
		$newPotentialName = $potentialName;
	}

	$fileName = ucfirst(str_replace(' ', '_', $newPotentialName));

	$params = new FauxRequest(array (
		'action' => 'query',
		'titles' => 'File:' . $fileName,
		'prop' => 'imageinfo',
		'format' => 'php'
	)); 
	try {
	$enableWrite = true; // This is set to false by default, in the ApiMain constructor
	$api = new ApiMain($params,$enableWrite);
	$api->execute();
	$data = & $api->getResultData();
        } catch (Exception $e) {
		return "Error: " . $e->getMessage(); //The Api didn't like us.
	}

	if ($data['query']['pages'][-1]) {
		return "Name: $fileName - available.";
	} else {
		return "Name: $fileName - unavailable.";
	}
}

?>















