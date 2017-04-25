<?php
 echo '<html><head><title>DropBox App</title></head>
<body>
<div name="bdiv">
<form enctype="multipart/form-data" action="album.php" id="form1" method="POST">
<b>Image Upload: </b><input type="file" name="imgfile" id="imgupload">
<input type="submit" name="upload" value="Upload">
</form></div>';

// these 2 lines are just to enable error reporting and disable output buffering (don't include this in you application!)
error_reporting(E_ALL);
enable_implicit_flush();
// -- end of unneeded stuff

// if there are many files in your Dropbox it can take some time, so disable the max. execution time
set_time_limit(0);

require_once("DropboxClient.php");


//move_uploaded_file ($_FILES['imgfile']['tmp_name'],"somedir/".$file );

// you have to create an app at https://www.dropbox.com/developers/apps and enter details below:
$dropbox = new DropboxClient(array(
	'app_key' => "##",      // Put your Dropbox API key here
	'app_secret' => "##",   // Put your Dropbox API secret here
	'app_full_access' => false,
),'en');


// first try to load existing access token
$access_token = load_token("access");
if(!empty($access_token)) {
	$dropbox->SetAccessToken($access_token);
	//echo "loaded access token:";
	//print_r($access_token);
}
elseif(!empty($_GET['auth_callback'])) // are we coming from dropbox's auth page?
{
	// then load our previosly created request token
	$request_token = load_token($_GET['oauth_token']);
	if(empty($request_token)) die('Request token not found!');
	
	// get & store access token, the request token is not needed anymore
	$access_token = $dropbox->GetAccessToken($request_token);	
	store_token($access_token, "access");
	delete_token($_GET['oauth_token']);
}

// checks if access token is required
if(!$dropbox->IsAuthorized())
{
	// redirect user to dropbox auth page
	$return_url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?auth_callback=1";
	$auth_url = $dropbox->BuildAuthorizeUrl($return_url);
	$request_token = $dropbox->GetRequestToken();
	store_token($request_token, $request_token['t']);
	die("Authentication required. <a href='$auth_url'>Click here.</a>");
}

try
{
echo "<pre>";


if(isset($_POST['upload']))
{
	$target_file = "".basename($_FILES['imgfile']['name']);
	//print_r($_FILES['imgfile']);
	if (move_uploaded_file($_FILES["imgfile"]["tmp_name"], $target_file)) 
	{
		$dropbox->UploadFile($target_file);
        echo "The file ". basename( $_FILES["imgfile"]["name"]). " has been uploaded.";
    } 
	else {
        echo "Sorry, there was an Error ".$_FILES['imgfile']['error']." while uploading your file.<br/>";
    }
}
elseif(isset($_POST['delete']))
{
	echo 'Deleted file '.$_POST['delete'];
	$dropbox->Delete($_POST['delete']);
	unset($_POST['delete']);
	header("Location:album.php");
}
if(isset($_GET['download'])){
	print_r("Downloaded file ".$_GET['download']);
	$file=$_GET['download'];
	$test_file = "download_".basename($file);
	$dropbox->DownloadFile($file, $test_file);
	$img=$file;
	header("Location:album.php");
}
echo '<table style="width:100%" border="1"><tr><td>Image Name</td><td>Thumbnail</td><td>Delete</td></tr>';
//$jpg_files = $dropbox->Search("/", ".jpg");
$jpg_files = $dropbox->GetFiles("", false);
if(empty($jpg_files))
	echo "No images found in DropBox.";
else {
	//print_r($jpg_files);
	foreach(array_keys($jpg_files) as $jpg_file)
	{
	echo '<tr><td><a href="album.php?download='.$jpg_file.'">'.$jpg_file.'</a></td>';
	$img_data = base64_encode($dropbox->GetThumbnail($jpg_file));
	echo "<td><img src=\"data:image/jpeg;base64,$img_data\" alt=\"Generating PDF thumbnail failed!\" style=\"border: 1px solid black;\" /></td>";
	echo '<td><button type="submit" name="delete" value="'.$jpg_file.'" form="form1" formaction="album.php?delete='.$jpg_file.'">Delete</button></td></tr>';
	}
echo "</table><br/><br/>";
if($img != null)
{
	echo'<img id="imgid" src="'.$dropbox->GetLink($img,false).'" />';
}

}
}catch(Exception $e)
{
	echo 'Exception: '.$e->getMessage();
}
function store_token($token, $name)
{
	if(!file_put_contents("tokens/$name.token", serialize($token)))
		die('<br />Could not store token! <b>Make sure that the directory `tokens` exists and is writable!</b>');
}

function load_token($name)
{
	if(!file_exists("tokens/$name.token")) return null;
	return @unserialize(@file_get_contents("tokens/$name.token"));
}

function delete_token($name)
{
	@unlink("tokens/$name.token");
}

function enable_implicit_flush()
{
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);
	for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
	ob_implicit_flush(1);
	echo "<!-- ".str_repeat(' ', 2000)." -->";
}
echo '</body></html>';

?>
