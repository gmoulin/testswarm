<?php
header("Access-Control-Allow-Origin: *");

define( 'SWARM_ENTRY', 'API' );
require_once 'inc/init.php';

$browserInfo = $swarmContext->getBrowserInfo();
$uaData = $browserInfo->getUaData();

$request = $swarmContext->getRequest();

$image = $request->getVal('imageData', null);
$runID = $request->getInt( 'run_id', 0 );

$response = array('success' => 0, 'filename' => '');

if( !is_null($image) ){
	$imgres = imagecreatefromstring( base64_decode(str_replace('data:image/png;base64,', '', $image)) );
	if( $imgres != false ){
		$db = $swarmContext->getDB();

		$row = $db->getRow(str_queryf('
			SELECT
				r.name as run_name, j.name as job_name
			FROM runs r
			INNER JOIN jobs j ON r.job_id = j.id
			WHERE r.id = %u
			',
			$runID
		));

		if( $row != false ){
			$root = dirname(__FILE__).'/screenshots/';

			if( !file_exists($root.$row->job_name) ){
				mkdir($root.$row->job_name);
			}

			if( !file_exists($root.$row->run_name) ){
				mkdir($root.$row->job_name.'/'.$row->run_name);
			}

			$filename = $row->job_name.'/'.$row->run_name.'/'.str_replace('/', ' - ', $uaData->displayInfo['title']).'.jpg';

			imagejpeg($imgres, $root.$filename);

			$response['success'] = 1;
			$response['filename'] = $filename;
		}
	}
}

echo json_encode($response);
die;
?>
