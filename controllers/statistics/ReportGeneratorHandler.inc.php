<?php

/**
 * @file controllers/statistics/ReportGeneratorHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReportGeneratorHandler
 * @ingroup controllers_statistics
 *
 * @brief Handle requests for report generator functions.
 */

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

class ReportGeneratorHandler extends Handler {
	/**
	 * Constructor
	 **/
	function ReportGeneratorHandler() {
		parent::Handler();
		$this->addRoleAssignment(
			array(ROLE_ID_JOURNAL_MANAGER),
			array('fetchReportGenerator', 'saveReportGenerator', 'fetchArticlesInfo', 'fetchRegions'));
	}

	/**
	* Fetch form to generate custom reports.
	* @param $args array
	* @param $request Request
	*/
	function fetchReportGenerator(&$args, &$request) {
		$this->setupTemplate();
		$reportGeneratorForm =& $this->_getReportGeneratorForm($request);
		$reportGeneratorForm->initData($request);

		$json =& new JSONMessage(true, $reportGeneratorForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Save form to generate custom reports.
	 * @param $args array
	 * @param $request Request
	 */
	function saveReportGenerator(&$args, &$request) {
		$this->setupTemplate();

		$reportGeneratorForm =& $this->_getReportGeneratorForm($request);
		$reportGeneratorForm->readInputData();
		$json = new JSONMessage(true);
		if ($reportGeneratorForm->validate()) {
			$reportUrl = $reportGeneratorForm->execute($request);
			$json->setAdditionalAttributes(array('reportUrl' => $reportUrl));
		} else {
			$json->setStatus(false);
		}

		return $json->getString();
	}

	/**
	 * Fetch articles title and id from
	 * the passed request variable issue id.
	 * @param $args array
	 * @param $request Request
	 * @return string JSON response
	 */
	function fetchArticlesInfo(&$args, &$request) {
		$this->validate();

		$issueId = (int) $request->getUserVar('issueId');
		import('lib.pkp.classes.core.JSONMessage');
		$json = new JSONMessage();

		if (!$issueId) {
			$json->setStatus(false);
		} else {
			$articleDao =& DAORegistry::getDAO('PublishedArticleDAO'); /* @var $articleDao PublishedArticleDAO */
			$articles =& $articleDao->getPublishedArticles($issueId);
			$articlesInfo = array();
			foreach ($articles as $article) {
				$articlesInfo[] = array('id' => $article->getId(), 'title' => $article->getLocalizedTitle());
			}

			$json->setContent($articlesInfo);
		}

		return $json->getString();
	}

	/**
	* Fetch regions from the passed request
	* variable country id.
	* @param $args array
	* @param $request Request
	* @return string JSON response
	*/
	function fetchRegions(&$args, &$request) {
		$this->validate();

		$countryId = (string) $request->getUserVar('countryId');
		import('lib.pkp.classes.core.JSONMessage');
		$json = new JSONMessage(false);

		if ($countryId) {
			$plugin =& PluginRegistry::getPlugin('generic', 'usagestatsplugin'); /* @var $plugin UsageStatsPlugin */
			if (is_a($plugin, 'UsageStatsPlugin')) {
				$geoLocationTool =& $plugin->getGeoLocationTool();
				if ($geoLocationTool) {
					$regions = $geoLocationTool->getRegions($countryId);
					if (!empty($regions)) {
						$regionsData = array();
						foreach ($regions as $id => $name) {
							$regionsData[] = array('id' => $id, 'name' => $name);
						}
						$json->setStatus(true);
						$json->setContent($regionsData);
					}
				}
			}
		}

		return $json->getString();
	}

	/**
	 * @see PKPHandler::setupTemplate()
	 */
	function setupTemplate() {
		parent::setupTemplate();
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_OJS_MANAGER);
	}


	//
	// Private helper methods.
	//
	/**
	 * Get report generator form object.
	 * @return ReportGeneratorForm
	 */
	function &_getReportGeneratorForm(&$request) {
		$columns = unserialize($request->getUserVar('columns'));
		$objects = unserialize($request->getUserVar('objects'));
		$fileTypes = unserialize($request->getUserVar('fileTypes'));
		$metricType = $request->getUserVar('metricType');

		// Metric column is always present in reports.
		unset($columns[STATISTICS_METRIC]);
		// Metric type will be presented in header.
		unset($columns[STATISTICS_DIMENSION_METRIC_TYPE]);

		import('controllers.statistics.form.ReportGeneratorForm');
		$reportGeneratorForm =& new ReportGeneratorForm($columns,
			$objects, $fileTypes, $metricType);

		return $reportGeneratorForm;
	}
}

?>
