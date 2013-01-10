<?php
/**
 * "Job" page.
 *
 * @author John Resig, 2008-2011
 * @author Jörn Zaefferer, 2012
 * @since 0.1.0
 * @package TestSwarm
 */

class JobPage extends Page {

	public function execute() {
		$action = JobAction::newFromContext( $this->getContext() );
		$action->doAction();

		$this->setAction( $action );
		$this->content = $this->initContent();
	}

	protected function initContent() {
		$request = $this->getContext()->getRequest();

		$this->setTitle( "Job status" );
		$this->setRobots( "noindex,nofollow" );
		$this->bodyScripts[] = swarmpath( "js/job.js" );

		$this->bodyScripts[] = swarmpath( "js/load-image.min.js" );
		$this->bodyScripts[] = swarmpath( "js/bootstrap-image-gallery.min.js" );
		$this->styleSheets[] = swarmpath( "css/bootstrap-image-gallery.min.css" );

		$error = $this->getAction()->getError();
		$data = $this->getAction()->getData();
		$html = '';

		if ( $error ) {
			$html .= html_tag( 'div', array( 'class' => 'alert alert-error' ), $error['info'] );
		}

		if ( !isset( $data["jobInfo"] ) ) {
			return $html;
		}

		$this->setSubTitle( '#' . $data["jobInfo"]["id"] );

		$isAuth = $request->getSessionData( "auth" ) === "yes" && $data["jobInfo"]["ownerName"] == $request->getSessionData( "username" );

		$html .=
			'<h2>' . $data["jobInfo"]["name"] .'</h2>'
			. '<p><em>Submitted by '
			. html_tag( "a", array( "href" => swarmpath( "user/{$data["jobInfo"]["ownerName"]}" ) ), $data["jobInfo"]["ownerName"] )
			. ' on ' . htmlspecialchars( date( "Y-m-d H:i:s", gmstrtotime( $data["jobInfo"]["creationTimestamp"] ) ) )
			. ' (UTC)' . '</em>.</p>';

		if ( $isAuth ) {
			$html .= '<script>SWARM.jobInfo = ' . json_encode( $data["jobInfo"] ) . ';</script>';
			$action_bar = '<div class="form-actions swarm-item-actions">'
				. ' <button class="swarm-reset-runs-failed btn btn-info">Reset failed runs</button>'
				. ' <button class="swarm-reset-runs btn btn-info">Reset all runs</button>'
				. ' <button class="swarm-delete-job btn btn-danger">Delete job</button>'
				. '</div>'
				. '<div class="alert alert-error" id="swarm-wipejob-error" style="display: none;"></div>';
		} else {
			$action_bar = '';
		}

		$html .= $action_bar;
		$html .= '<table class="table table-bordered swarm-results"><thead>'
			. self::getUaHtmlHeader( $data['userAgents'] )
			. '</thead><tbody>'
			. self::getUaRunsHtmlRows( $data['runs'], $data['userAgents'], $isAuth )
			. '</tbody></table>';

		$html .= $action_bar;

		$html .= self::getScreenshots($data['jobInfo']['name'], $data['runs']);

		return $html;
	}

	public static function getUaHtmlHeader( $userAgents ) {
		$html = '<tr><th>&nbsp;</th>';
		foreach ( $userAgents as $userAgent ) {
			$displayInfo = $userAgent['displayInfo'];
			$html .= '<th>'
				. html_tag( 'div', array(
					'class' => $displayInfo['class'] . ' swarm-icon-small',
					'title' => $displayInfo['title'],
				) )
				. '<br>'
				. html_tag_open( 'span', array(
					'class' => 'label swarm-browsername',
				) ) . $displayInfo['labelHtml'] . '</span>'
				. '</th>';
		}

		$html .= '</tr>';
		return $html;
	}

	/**
	 * @param Array $runs
	 * @param Array $userAgents
	 * @param bool $showResetRun: Whether to show the reset buttons for individual runs.
	 *  This does not check authororisation or load related javascript for the buttons.
	 */
	public static function getUaRunsHtmlRows( $runs, $userAgents, $showResetRun = false ) {
		$html = '';

		foreach ( $runs as $run ) {
			$html .= '<tr><th><a href="' . htmlspecialchars( $run['info']['url'] ) . '">'
				. $run['info']['name'] . '</a></th>';

			// Looping over $userAgents instead of $run["uaRuns"],
			// to avoid shifts in the table (github.com/jquery/testswarm/issues/13)
			foreach ( $userAgents as $uaID => $uaInfo ) {
				if ( isset( $run['uaRuns'][$uaID] ) ) {
					$uaRun = $run['uaRuns'][$uaID];
					$html .= html_tag_open( 'td', array(
						'class' => 'swarm-status swarm-status-' . $uaRun['runStatus'],
						'data-run-id' => $run['info']['id'],
						'data-run-status' => $uaRun['runStatus'],
						'data-useragent-id' => $uaID,
						// Un-ran tests don't have a client id
						'data-client-id' => isset( $uaRun['clientID'] ) ? $uaRun['clientID'] : '',
					));
					if ( isset( $uaRun['runResultsUrl'] ) && isset( $uaRun['runResultsLabel'] ) ) {
						$title = $userAgents[$uaID]['displayInfo']['title'];
						$runResultsTooltip = "Open run results for $title";
						$runResultsTagOpen = html_tag_open( 'a', array(
							'rel' => 'nofollow',
							'href' => $uaRun['runResultsUrl'],
							'title' => $runResultsTooltip,
						) );
						$html .=
							$runResultsTagOpen
							. ( $uaRun['runResultsLabel']
								? $uaRun['runResultsLabel']
								: UserPage::getStatusIconHtml( $uaRun['runStatus'] )
							). '</a>'
							. $runResultsTagOpen
							. html_tag( 'i', array(
								'class' => 'swarm-show-results icon-list-alt pull-right',
								'title' => $runResultsTooltip,
							) )
							. '</a>'
							. ( $showResetRun ?
								html_tag( 'i', array(
									'class' => 'swarm-reset-run-single icon-remove-circle pull-right',
									'title' => "Re-schedule run for $title",
								) )
								: ''
							);
					} else {
						$html .= UserPage::getStatusIconHtml( $uaRun['runStatus'] );
					}
					$html .= '</td>';
				} else {
					// This run isn't schedules to be ran in this UA
					$html .= '<td class="swarm-status swarm-status-notscheduled"></td>';
				}
			}
		}

		return $html;
	}

	/**
	 * parse the screenshots folder for the jobName subfolder
	 * display the available screenshots for the runs
	 * @param String $jobName
	 * @param Array $runs
	 */
	public static function getScreenshots( $jobName, $runs ){
		$html = '';
		$path = realpath(__DIR__.'/../../screenshots/'.$jobName.'/');

		if( is_dir($path) && is_readable($path) ){
			$html .= '<h3>Screenshots <small>(some images might not be 100% accurate)</small></h3>';
			$html .= '<div id="gallery" data-toggle="modal-gallery" data-target="#modal-gallery">';
			foreach( $runs as $run ){
				$runDir = $path.'/'.$run['info']['name'];
				if( is_dir($runDir) && is_readable($runDir) && $handle = opendir($runDir) ){
					while( false !== ($entry = readdir($handle)) ){
						if( $entry != "." && $entry != ".." ){
							$name = $run['info']['name'].' - '.substr($entry, 0, -4);
							$html .= '<a href="/screenshots/'.$jobName.'/'.$run['info']['name'].'/'.$entry.'" title="'.$name.'" data-gallery="gallery">'.$name.'</a>';
						}
					}

					$html .= '</ul>';

					closedir($handle);
				}
			}
			$html .= '</div>';
		}


		$html .= '
			<!-- modal-gallery is the modal dialog used for the image gallery -->
			<div id="modal-gallery" class="modal modal-gallery hide fade" tabindex="-1">
				<div class="modal-header">
					<a class="close" data-dismiss="modal">&times;</a>
					<h3 class="modal-title"></h3>
				</div>
				<div class="modal-body"><div class="modal-image"></div></div>
				<div class="modal-footer">
					<a class="btn btn-primary modal-next">Next <i class="icon-arrow-right icon-white"></i></a>
					<a class="btn btn-info modal-prev"><i class="icon-arrow-left icon-white"></i> Previous</a>
					<a class="btn btn-success modal-play modal-slideshow" data-slideshow="5000"><i class="icon-play icon-white"></i> Slideshow</a>
					<a class="btn modal-download" target="_blank"><i class="icon-download"></i> Download</a>
				</div>
			</div>
		';

		return $html;
	}
}
