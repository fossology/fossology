<?php
/*
 * SPDX-FileCopyrightText: © Fossology contributors
 * SPDX-License-Identifier: GPL-2.0-only
 */

/**
 * @file agent-ossdetect.php
 * @brief UI plugin to display OSS detection results
 */

use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

class OssDetectAgentPlugin extends DefaultPlugin
{
    const NAME = "agent_ossdetect";

    function __construct()
    {
        parent::__construct(self::NAME, array(
            self::TITLE => _("OSS Components"),
            self::PERMISSION => Auth::PERM_READ,
            self::REQUIRES_LOGIN => false
        ));
    }

    /**
     * @copydoc Fossology\Lib\Plugin\DefaultPlugin::preInstall()
     */
    function preInstall()
    {
        // Nothing special needed before installation
    }

    /**
     * Display the OSS detection results for a file
     * 
     * @param Request $request
     * @return Response
     */
    protected function handle(Request $request)
    {
        $uploadId = intval($request->get('upload'));
        $uploadTreeId = intval($request->get('item'));

        if (empty($uploadTreeId) || empty($uploadId)) {
            return $this->render('include/base.html.twig', 
                $this->mergeWithDefault(array('message' => _("Invalid parameters"))));
        }

        // Get pfile_fk for this upload tree item
        $uploadDao = $GLOBALS['container']->get('dao.upload');
        $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
        $pfileId = $itemTreeBounds->getItemId();

        if (empty($pfileId)) {
            return $this->render('include/base.html.twig', 
                $this->mergeWithDefault(array('message' => _("File not found"))));
        }

        // Query database for dependencies and matches
        $dependencies = $this->getDependencies($uploadId, $pfileId);
        
        $vars = array(
            'uploadId' => $uploadId,
            'pfileId' => $pfileId,
            'dependencies' => $dependencies,
            'hasDependencies' => !empty($dependencies)
        );

        return $this->render('ossdetect_view.html.twig', 
            $this->mergeWithDefault($vars));
    }

    /**
     * Retrieve dependencies and their matches from the database
     * 
     * @param int $uploadId
     * @param int $pfileId  
     * @return array Array of dependencies with their similarity matches
     */
    private function getDependencies($uploadId, $pfileId)
    {
        $dbManager = $GLOBALS['container']->get('db.manager');
        
        // Get all dependencies for this file
        $stmt = __METHOD__ . '.getDeps';
        $sql = "SELECT dependency_name, dependency_version, dependency_scope, source_line 
                FROM ossdetect_dependency 
                WHERE upload_fk = $1 AND pfile_fk = $2
                ORDER BY dependency_name";
        
        $dbManager->prepare($stmt, $sql);
        $result = $dbManager->execute($stmt, array($uploadId, $pfileId));
        
        $dependencies = array();
        
        while ($row = $dbManager->fetchArray($result)) {
            $depName = $row['dependency_name'];
            
            // Get similarity matches for this dependency
            $matches = $this->getSimilarityMatches($uploadId, $pfileId, $depName);
            
            $dependencies[] = array(
                'name' => $depName,
                'version' => $row['dependency_version'],
                'scope' => $row['dependency_scope'],
                'line' => $row['source_line'],
                'matches' => $matches,
                'hasMatches' => !empty($matches)
            );
        }
        
        $dbManager->freeResult($result);
        
        return $dependencies;
    }

    /**
     * Get similarity matches for a specific dependency
     * 
     * @param int $uploadId
     * @param int $pfileId
     * @param string $depName
     * @return array Array of similarity matches
     */
    private function getSimilarityMatches($uploadId, $pfileId, $depName)
    {
        $dbManager = $GLOBALS['container']->get('db.manager');
        
        $stmt = __METHOD__ . '.getMatches';
        $sql = "SELECT component_name, component_version, similarity_score, match_type
                FROM ossdetect_match
                WHERE upload_fk = $1 AND pfile_fk = $2 AND dependency_name = $3
                ORDER BY similarity_score DESC";
        
        $dbManager->prepare($stmt, $sql);
        $result = $dbManager->execute($stmt, array($uploadId, $pfileId, $depName));
        
        $matches = array();
        
        while ($row = $dbManager->fetchArray($result)) {
            $score = floatval($row['similarity_score']);
            
            // Determine CSS class based on score
            $scoreClass = 'score-low';  
            if ($score >= 90) {
                $scoreClass = 'score-high';
            } elseif ($score >= 70) {
                $scoreClass = 'score-medium';
            }
            
            $matches[] = array(
                'componentName' => $row['component_name'],
                'componentVersion' => $row['component_version'],
                'score' => number_format($score, 1),
                'matchType' => $row['match_type'],
                'scoreClass' => $scoreClass
            );
        }
        
        $dbManager->freeResult($result);
        
        return $matches;
    }
}

// Register the plugin
register_plugin(new OssDetectAgentPlugin());
