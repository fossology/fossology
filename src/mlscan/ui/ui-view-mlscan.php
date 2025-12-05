<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file ui-view-mlscan.php
 * @brief View ML scan results for a file
 */

namespace Fossology\\MLScan\\Ui;

use Fossology\\Lib\\Plugin\\DefaultPlugin;
use Symfony\\Component\\HttpFoundation\\Request;

class ViewMLScanResults extends DefaultPlugin
{
  const NAME = "view-mlscan";

  function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => _("ML Scan Results"),
      self::PERMISSION => Auth::PERM_READ,
      self::REQUIRES_LOGIN => false
    ));
  }

  /**
   * @brief Get ML scan results for a file
   * @param int $uploadId Upload ID
   * @param int $pfileId File ID
   * @return array Array of license results with confidence scores
   */
  protected function getMLResults($uploadId, $pfileId)
  {
    global $container;
    $dbManager = $container->get('db.manager');
    
    $sql = "SELECT lr.rf_shortname, ml.confidence, ml.detection_method 
            FROM mlscan_license ml
            INNER JOIN license_ref lr ON ml.rf_fk = lr.rf_pk
            WHERE ml.pfile_fk = $1
            ORDER BY ml.confidence DESC";
    
    $stmt = __METHOD__;
    $results = $dbManager->getRows($sql, array($pfileId), $stmt);
    
    return $results;
  }

  /**
   * @brief Render the page content
   * @param Request $request HTTP request
   * @return Response HTTP response
   */
  protected function handle(Request $request)
  {
    $uploadId = intval($request->get('upload'));
    $pfileId = intval($request->get('item'));
    
    if (!$uploadId || !$pfileId) {
      return $this->render('include/base.html.twig', array(
        'content' => 'Invalid parameters'
      ));
    }
    
    $results = $this->getMLResults($uploadId, $pfileId);
    
    $html = "<div class='mlscan-results'>";
    $html .= "<h3>ML License Scanner Results</h3>";
    
    if (empty($results)) {
      $html .= "<p>No ML scan results found for this file.</p>";
    } else {
      $html .= "<table class='table table-striped'>";
      $html .= "<thead><tr>";
      $html .= "<th>License</th>";
      $html .= "<th>Confidence</th>";
      $html .= "<th>Method</th>";
      $html .= "<th>Confidence Bar</th>";
      $html .= "</tr></thead>";
      $html .= "<tbody>";
      
      foreach ($results as $row) {
        $license = htmlspecialchars($row['rf_shortname']);
        $confidence = floatval($row['confidence']);
        $method = htmlspecialchars($row['detection_method']);
        $confidencePercent = round($confidence * 100, 1);
        
        // Color code based on confidence
        $barColor = 'success';
        if ($confidence < 0.5) {
          $barColor = 'danger';
        } elseif ($confidence < 0.75) {
          $barColor = 'warning';
        }
        
        $html .= "<tr>";
        $html .= "<td><strong>$license</strong></td>";
        $html .= "<td>$confidencePercent%</td>";
        $html .= "<td><span class='badge badge-info'>$method</span></td>";
        $html .= "<td>";
        $html .= "<div class='progress'>";
        $html .= "<div class='progress-bar progress-bar-$barColor' role='progressbar' ";
        $html .= "style='width: $confidencePercent%' ";
        $html .= "aria-valuenow='$confidencePercent' aria-valuemin='0' aria-valuemax='100'>";
        $html .= "$confidencePercent%";
        $html .= "</div></div>";
        $html .= "</td>";
        $html .= "</tr>";
      }
      
      $html .= "</tbody></table>";
      
      // Legend
      $html .= "<div class='mlscan-legend'>";
      $html .= "<h4>Detection Methods:</h4>";
      $html .= "<ul>";
      $html .= "<li><strong>rule:</strong> SPDX rule-based detection</li>";
      $html .= "<li><strong>ml-tfidf:</strong> TF-IDF machine learning classifier</li>";
      $html .= "<li><strong>ml-bert:</strong> BERT semantic classifier</li>";
      $html .= "<li><strong>hybrid:</strong> Combined rule-based and ML detection</li>";
      $html .= "</ul>";
      $html .= "</div>";
    }
    
    $html .= "</div>";
    
    return $this->render('include/base.html.twig', array(
      'content' => $html
    ));
  }
}

register_plugin(new ViewMLScanResults());
