<?php
/*
 * FancyUploadCallback is a helper file for the FancyUpload MediaWiki extention.
 * In many ways it is a bad hack to MediaWiki's current inability to have
 * users upload files smoothly, even with the API.
 * FancyUploadCallback.php is the target of a submission by FancyUpload.php
 * within an <iframe> to get Ajax-like functionality.
 * It saves the file to upload on the server in a traditional PHP/non-mediaWiki like way.
 * Then, FancyUpload.php can actually upload the file using a web link 
 * (uploads through a web link are reasonably well supported by MediaWiki)
 * This has the advantage of registering all the hooks for files through MediaWiki
 * without having to worry about the details of file uploading.
 * Yes its somewhat bad style... Yes it works.
 */


//These two directories represent the same directory
//One is the directory on your server where you store the file
//The other is the web address to that directory
//These are the only configurables.
$uploadDirectory = "/var/www/html/users/save/";
$urlDirectory = "http://bev-linux-essex/users/save/";

if ($_POST) {
	print_r($_POST);
}

if ($_FILES) { //see if there is a valid $_FILES array
	print_r($_FILES);
	$fileLocation = '';
	$errorString = "";
	// Make sure that the filetype is ok, and that the file is not too large.
	if ((($_FILES["fancyUploadFileInput"]["type"] == "image/gif")
			|| ($_FILES["fancyUploadFileInput"]["type"] == "image/jpeg")
			|| ($_FILES["fancyUploadFileInput"]["type"] == "image/pjpeg")
			|| ($_FILES["fancyUploadFileInput"]["type"] == "image/png")
			|| ($_FILES["fancyUploadFileInput"]["type"] == "image/x-png")
			|| ($_FILES["fancyUploadFileInput"]["type"] == "image/bmp")
			|| ($_FILES["fancyUploadFileInput"]["type"] == "text/plain")
			|| ($_FILES["fancyUploadFileInput"]["type"] == "image/gif"))
		&& ($_FILES["fancyUploadFileInput"]["size"] < 500000))
	{ //if file is right type and size:
		if ($_FILES["fancyUploadFileInput"]["error"] > 0) { //If there has been an error, spit it out.
			$errorString .= "Return Code: " . htmlentities($_FILES["fancyUploadFileInput"]["error"]) . "<br />";
		} else if (!checkFileExtensionName($_FILES["fancyUploadFileInput"]["name"])) {
			$errorString .= "Error: File extension is not ok.";
		} else { //if no error, then append this information to what the <iframe> will give back
			$errorString .=  "Upload: " . htmlentities($_FILES["fancyUploadFileInput"]["name"]) . "<br />";
			$errorString .=  "Type: " . htmlentities($_FILES["fancyUploadFileInput"]["type"]) . "<br />";
			$errorString .=  "Size: " . htmlentities(($_FILES["fancyUploadFileInput"]["size"] / 1024)) . " Kb<br />";
			$errorString .=  "Temp file: " . htmlentities($_FILES["fancyUploadFileInput"]["tmp_name"]) . "<br />";
	
			//take the file from temporary storage in php to a url-safe place for transfer.
			move_uploaded_file($_FILES["fancyUploadFileInput"]["tmp_name"], $uploadDirectory . $_FILES["fancyUploadFileInput"]["name"]);
			$fileLocation = $urlDirectory . rawurlencode($_FILES["fancyUploadFileInput"]["name"]);
			$errorString .=  "Upload temp location: " . "<a href=$fileLocation>$fileLocation</a>";
			$errorString .=  "<div id='fileLocation'>$fileLocation</div>";
			
		}
	} else { //if not right type and size:
		$mtype = $_FILES["fancyUploadFileInput"]["type"];
		$mname = $_FILES["fancyUploadFileInput"]["name"];
		$msize = $_FILES["fancyUploadFileInput"]["size"];	
		if (!$mname) {
			$errorString .= "Error: No file specified.";
		} else if (!$mtype) {
			$errorString .= "Error: File has no header information.";
		} else if ($msize >= 500000) {
			$errorString .= "Error: File is too large.";
		} else {
			$errorString .= "Error: Bad filetype: " . htmlentities($mtype);
		}

	} //spit out the error in a way that our FancyUpload.php code can pull out of the <iframe>
	echo "<div id='errorString'>$errorString</div>";


}

function checkFileExtensionName($name) {
	$splitName = explode(".", $name);
	$extension = strtoupper(end($splitName));
	switch ($extension) {
		case "JPG":
		case "JPEG":
		case "PNG":
		case "TXT":
		case "BMP":
		case "GIF":
			return true;
		default:
			return false;
	}
}

?>



























