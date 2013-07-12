<?php 
/**
 * Up : upload your file, and share it
 * author : dmeloni
 * source : drag and drop script (lehollandaisvolant)
 */


/**
 * Verify if filename is correct
 * @param string $titreDocument
 * @return boolean
 */
function isValidName($titreDocument){
	if(!(strlen($titreDocument) >= 3 && strlen($titreDocument) <= 100))
		return false;
	if($titreDocument == '.htaccess' || $titreDocument == '.htpassword' || $titreDocument == 'robots.txt'){
		return false;
	}

	return preg_match('#^([&a-zA-Zéèàç\(\)0-9_\[\]\-\.: \?\=])+$#', $titreDocument);
}

/**
 * Generate a random name
 * @param int $car : number of character
 * @return string
 */
function random($car) {
	$string = "";
	$chaine = "abcdefghijklmnpqrstuvwxy";
	srand((double)microtime()*1000000);
	for($i=0; $i<$car; $i++) {
		$string .= $chaine[rand()%strlen($chaine)];
	}
	return $string;
}

$nbRandomCharacters = 10;
$serverMsg = '';
$folder = 'data';
$dataMappingFile = 'dataMapping.json'; // Simple array for mapping name of files
$dataMappingFilePath = $folder . '/' . $dataMappingFile;
/**
 * Upload file on server
 */
if (is_writable($folder) && isset($_FILES['myfile'])) {
	if(!isValidName($_FILES['myfile']['name'])){
		return;
	}

	/*
	 * Data mapping recuperation
	 */
	if(!is_file($dataMappingFilePath)){
		$dataMapping = array();
		file_put_contents($dataMappingFilePath, json_encode($dataMapping));
	}else{
		$dataMapping = json_decode(file_get_contents($dataMappingFilePath), true);		
	}
	
	$sFileName = $_FILES['myfile']['name'];
	do{
		$randomName = random(10);
	}while($randomName === $dataMappingFile || is_file($folder . '/' . $randomName));

	if(true === move_uploaded_file($_FILES['myfile']['tmp_name'], $folder . '/' . $randomName)){
		$nbAvailableCopies = 1;
		
		if(isset($_GET['copy']) && (int)$_GET['copy'] > 0){
			$nbAvailableCopies = $_GET['copy'];
		}
		$dataMapping[$randomName] = array('name' => $_FILES['myfile']['name'], 'nb' => $nbAvailableCopies);
		if(false !== file_put_contents($dataMappingFilePath, json_encode($dataMapping))){
			echo $randomName;
			return;
		}
	}
}

/**
 * Download file 
 */
if(isset($_GET['f']) && strlen($_GET['f']) == $nbRandomCharacters){
	if(is_file($folder . '/' . $_GET['f'])){
		/*
		 * Data mapping recuperation
		*/
			
		if(is_file($dataMappingFilePath)){
			$dataMapping = json_decode(file_get_contents($dataMappingFilePath), true);
			if(isset($dataMapping[$_GET['f']]) && $dataMapping[$_GET['f']]['nb'] > 0){
				$file = $folder . '/' . $_GET['f'];
				header('Content-type:application/octet-stream');
				$size = filesize("./" . $file);
				header("Content-Type: application/force-download; name=\"" . $dataMapping[$_GET['f']]['name'] . "\"");
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: $size");
				header("Content-Disposition: attachment; filename=\"" . $dataMapping[$_GET['f']]['name'] . "\"");
				header("Expires: 0");
				header("Cache-Control: no-cache, must-revalidate");
				header("Pragma: no-cache");
				readfile("./" . $file);
				
				$dataMapping[$_GET['f']]['nb']--;
				if($dataMapping[$_GET['f']]['nb'] == 0){
					// Remove the file && the mapping
					// the @s doesn't corrupt data output if error
					@unlink($file); 
					unset($dataMapping[$_GET['f']]);
				}
				@file_put_contents($dataMappingFilePath, json_encode($dataMapping));
				return;
			}else{
				$serverMsg = 'File not available';
			}
		}
	}else{
		$serverMsg = 'File not found';
	}
}


if(!(is_dir($folder) && is_writable($folder))){
	$serverMsg = 'Data folder is not writable';
}

/*
 * Get max upload size
 */
$max_upload = (int)(ini_get('upload_max_filesize'));
$max_post = (int)(ini_get('post_max_size'));
$memory_limit = (int)(ini_get('memory_limit'));
$upload_mb = min($max_upload, $max_post, $memory_limit);
$upload_b = $upload_mb * 1024 * 1024;

?><!DOCTYPE html><html lang="fr">
<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>Up</title><meta charset="utf-8">
	<meta name="description" content="">
	<meta name="author" content="">
	<meta name="viewport" content="width=device-width, user-scalable=yes">
	<link rel="apple-touch-icon" href="favicon.png">
	<link rel="shortcut icon" href="favicon.ico">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<body>
		<div id="content">
            <section id="midle">
            	<div class="upload_form_cont">
            		<?php if(!empty($serverMsg)){?>
	            		<div class="error"><?php echo $serverMsg;?></div>
            		<?php }else{?>
	            		<div class="info">
	            			<div id="upload-zone">Drop your files :-)</div>
	            		</div>
	            		<div>Number of available copies : <input type="text" id="upload-copy" value="1"></input></div>
            		<?php }?>
            		<div>Max upload size : <?php echo $upload_mb;?> Mb</div>
            		<div id="progress"></div>
            	</div>
            </section>
			<div class="progress"></div>
			<script type="text/javascript">
			// variables
			var dropArea = document.documentElement; // drop area zone JS object
			var progress = document.getElementById('progress'); // text zone where informations about uploaded files are displayed
			var list = []; // file list
			var nbDone = 0; // initialisation of nb files already uploaded during the process.
			var nb=null;
			var nbUploaded = null;
			var oldColor = null;
			var uploadError = false;

			// main initialization
			(function(){

			    // init handlers
			    function initHandlers() {
			        dropArea.addEventListener('drop', handleDrop, false);
			        dropArea.addEventListener('dragover', handleDragOver, false);
			    }

			    // drag over
			    function handleDragOver(event) {
			        event.stopPropagation();
			        event.preventDefault();
			        oldColor = dropArea.style.color;
			        dropArea.style.color='red';
			        //dropArea.className = 'hover';
			    }

			    // drag drop
			    function handleDrop(event) {
			        dropArea.style.color=oldColor;
			        event.stopPropagation();
			        event.preventDefault();

			        processFiles(event.dataTransfer.files);
			    }

			    // process bunch of files
			    function processFiles(filelist) {
			        if (!filelist || !filelist.length || list.length) return;
			        for (var i = 0; i < filelist.length && i < 500; i++) { // limit is 500 files (only for not having an infinite loop)
			            nbUploaded=filelist.length;
			            list.push(filelist[i]);
			        }
			        uploadNext();
			    }

			    // upload file
			    function uploadFile(file, status) {
			        // prepare XMLHttpRequest
			        var xhr = new XMLHttpRequest();
			        xhr.open('POST', 'index.php?copy='+document.getElementById('upload-copy').value);
			        xhr.onload = function() {
			            uploadNext();
			            nbUploaded--;
			        };
			        var totalTmp = 0;
			        if ( xhr.upload ) {
			            xhr.upload.onprogress = function(e) {
			                var done = e.position || e.loaded, total = e.totalSize || e.total;
			                totalTmp = e.total;
			                var pourcentage = Math.floor(done/total*1000)/10;
			                var progressMessage = "File : " + file['name'] + " ("+file['type']+") progress : " + pourcentage + "%" + " ("+done+"/"+total+" octets)";
			                var fileDiv = document.getElementById('file_'+nbDone+'');
			                fileDiv.textContent = progressMessage;
			            };
			        }
			        
			        xhr.onreadystatechange = function() {
			            if(xhr.readyState == 4){
				            console.log(xhr);
			                var progressMessage = "File : " + file['name'] + " ("+file['type']+") progress : 100%" + " (" + file['size'] + " octets) : ";
			                urlMessage = '<a href="index.php?f='+xhr.responseText+'">'+xhr.responseText+'</a>';
			                var fileDiv = document.getElementById('file_'+nbDone+'');
			                fileDiv.innerHTML =  progressMessage + urlMessage;
			                console.log(nbUploaded);
					        if(nbUploaded==1){
						        dropArea.style.color='#000';
					        }			            
			            }
			        };

			        xhr.onerror = function() {
			            var progressMessage = "File : " + file['name'] + " ("+file['type']+") upload error";
			            var fileDiv = document.getElementById('file_'+nbDone+'');
			            fileDiv.textContent = progressMessage;
			            uploadNext();
			            nbUploaded--;
				        if(nbUploaded==1){
					        dropArea.style.color='#000';
				        }				            
			        };

			        // prepare and send FormData
			        var formData = new FormData();  
			        formData.append('myfile', file);
			        xhr.send(formData);
			    }

			    // upload next file
			    function uploadNext() {
			        if (list.length) {
			            nb = list.length - 1;
			            
			            nbDone +=1;
			            
			            var strTemp = '<div id="file_'+nbDone+'"></div>';
			            progress.innerHTML += strTemp;
			            
			            var nextFile = list.shift();
			            var sizeMax = <?php echo $upload_b;?>;
			            if (nextFile.size >= sizeMax) { // 20Mb = generally the max file size on PHP hosts
			                var progressMessage = "File : " + nextFile['name'] + " ("+nextFile['type']+") File Too big (" + nextFile['size'] + " > "+sizeMax+")";
			                var fileDiv = document.getElementById('file_'+nbDone+'');
			                fileDiv.textContent = progressMessage;
			                uploadError = true;
			                uploadNext();
				            nbUploaded--;
					        if(nbUploaded==1){
						        dropArea.style.color='#000';
					        }				                
			            } else {
			                uploadFile(nextFile, status);
			            }
			        }
			    }

			    initHandlers();
			})();			
			</script>
		</div>
	</body>
</html>