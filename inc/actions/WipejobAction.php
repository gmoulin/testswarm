<?php
/**
 * "Wipejob" action
 *
 * @author John Resig, 2008-2011
 * @since 0.1.0
 * @package TestSwarm
 */

class WipejobAction extends Action {

	/**
	 * @actionMethod POST: Required.
	 * @actionParam int job_id
	 * @actionParam string type: one of 'delete', 'reset'.
	 * @actionParam string authUsername
	 * @actionParam string authToken
	 * @actionAuth: Yes.
	 */
	public function doAction() {
		$db = $this->getContext()->getDB();
		$request = $this->getContext()->getRequest();

		$jobID = $request->getInt( 'job_id' );
		$wipeType = $request->getVal( 'type' );

		if ( !$jobID || !$wipeType ) {
			$this->setError( 'missing-parameters' );
			return;
		}

		if ( !in_array( $wipeType, array( 'delete', 'reset' ) ) ) {
			$this->setError( 'invalid-input', 'Invalid wipeType' );
			return;
		}

		$jobOwner = $db->getOne(str_queryf(
			'SELECT
				users.name as user_name
			FROM jobs, users
			WHERE jobs.id = %u
			AND   users.id = jobs.user_id
			LIMIT 1;',
			$jobID
		));

		if ( !$jobOwner ) {
			$this->setError( 'invalid-input', 'Job not found' );
			return;
		}

		// Check authentication
		$userId = $this->doRequireAuth( $jobOwner );
		if ( !$userId ) {
			return;
		}

		$runRows = $db->getRows(str_queryf(
			'SELECT id, name
			FROM runs
			WHERE job_id = %u;',
			$jobID
		));

		//get jobInfo
		$jobInfo = JobAction::getJobInfoFromId( $db, $jobID );
		$jobName = $jobInfo['name'];
		$path = realpath(__DIR__.'/../../screenshots/'.$jobName.'/');

		if ( $runRows ) {
			foreach ( $runRows as $runRow ) {
				if ( $wipeType === 'delete' ) {
					$db->query(str_queryf(
						'DELETE
						FROM run_useragent
						WHERE run_id = %u;',
						$runRow->id
					));
				} elseif ( $wipeType === 'reset' ) {
					$db->query(str_queryf(
						'UPDATE run_useragent
						SET
							status = 0,
							completed = 0,
							results_id = NULL,
							updated = %s
						WHERE run_id = %u;',
						swarmdb_dateformat( SWARM_NOW ),
						$runRow->id
					));
				}

				//cleaning screenshots
				if( is_dir($path) && is_readable($path) ){
					$runDir = $path.'/'.$runRow->name;
					if( is_dir($runDir) && is_readable($runDir) && $handle = opendir($runDir) ){
						while( false !== ($entry = readdir($handle)) ){
							if( $entry != "." && $entry != ".." ){
								unlink($runDir.'/'.$entry);
							}
						}

						unlink($runDir);
					}
					closedir($handle);
				}
			}
		}

		unlink($path);

		// This should be outside the if for $runRows, because jobs
		// can sometimes be created without any runs (by accidently).
		// Those should be deletable as well, thus this has to be outside the loop.
		// Also, no  need to do this in a loop, just delete them all in one query.
		if ( $wipeType === 'delete' ) {
			$db->query(str_queryf(
				'DELETE
				FROM runs
				WHERE job_id = %u;',
				$jobID
			));
			$db->query(str_queryf(
				'DELETE
				FROM jobs
				WHERE id = %u;',
				$jobID
			));
		}

		$this->setData( array(
			'jobID' => $jobID,
			'type' => $wipeType,
			'result' => 'ok',
		) );
	}
}
