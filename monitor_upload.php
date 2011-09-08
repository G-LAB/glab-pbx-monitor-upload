<?php

$monitor_dir = '/var/spool/asterisk/monitor';

$link = mysql_connect('localhost:3306','asteriskuser','amp109');
if ($link === false) {
    die('Could not connect: ' . mysql_error());
}
else
{
	// Initialize Amazon S3 Support
	require_once 'S3.php';
	S3::setAuth('AKIAJV4IUPEV4MWFF7TA','HNikf2ZgOcjgaN4iPcjer7ZU5tg7unQhBJ7qLbvN');
	
	// Get Names of Recordings
	$files = glob($monitor_dir.'/*.wav');
	
	foreach ($files as $file)
	{
		preg_match('/\-([\d]+\.[\d]+)\.([a-z\d]{3})/i', $file, $match);
		
		if (isset($match[1]))
		{
			$uniqueid = $match[1];
			$file_audio_ext = $match[2];
			
			$q = mysql_query("SELECT * FROM asteriskcdrdb.cdr WHERE uniqueid = '$uniqueid' LIMIT 1",$link);
			
			if (mysql_num_rows($q) > 0)
			{
				passthru('sh convert_audio.sh '.$file);
				
				$file_audio = realpath(preg_replace('/wav/i', 'mp3', $file));
				
				if (is_string($file_audio) === true)
				{	
					$r = mysql_fetch_assoc($q);
					
					$cdr_data = serialize($r);
					
					$file_cdr = tempnam("/tmp", "cdr_upload_");
					
					$handle = fopen($file_cdr, "w");
					fwrite($handle, $cdr_data);
					fclose($handle);
					
					// Upload Audio File
					echo 'Uploading '.$file_audio."\r\n";
					$upload_audio = S3::putObject(
					    S3::inputFile($file_audio),
					    'glab-attachments',
					    'pbx/'.$uniqueid.'.mp3',
					    S3::ACL_PRIVATE,
					    array(),
					    array( // Custom $requestHeaders
					        "Cache-Control" => "max-age=315360000",
					        "Expires" => gmdate("D, d M Y H:i:s T", strtotime("+5 years"))
					    )
					);
					
					// Upload CDR
					echo 'Uploading '.$file_cdr."\r\n";
					$upload_cdr = S3::putObject(
					    S3::inputFile($file_cdr),
					    'glab-attachments',
					    'pbx/cdr_'.$uniqueid,
					    S3::ACL_PRIVATE,
					    array(),
					    array( // Custom $requestHeaders
					        "Cache-Control" => "max-age=315360000",
					        "Expires" => gmdate("D, d M Y H:i:s T", strtotime("+5 years"))
					    )
					);
					
					// Check for Success
					if ($upload_audio === true AND $upload_cdr === true)
					{
						// Remove Temporary CDR File
						unlink($file_cdr);
						
						// Remove Audio File
						unlink($file_audio);
					}
					elseif ($upload_audio !== true)
					{
						echo 'ERROR: Could not upload '.$file_audio."\r\n";
					}
					elseif ($upload_cdr !== true)
					{
						echo 'ERROR: Could not upload '.$file_audio."\r\n";
					}
				}
				else
				{
					echo 'ERROR: Converted file not found.'."\r\n";
				}
			}
			else
			{
				echo 'NOTICE: '.$uniqueid.' not yet in CDR. Skipping file.'."\r\n";
			}
		}
		else
		{
			echo 'ERROR: Cannot determine uniqueid from filename.'."\r\n";
		}
	}
	
	mysql_close($link);
}