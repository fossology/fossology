<?php
namespace Fossology\UI\Api\Models;

class DashboardDao extends RestModel
{
    public function refreshUploadStats($uploadId)
    {
        $sql = "SELECT refresh_dashboard_stats($1)";
        return $this->dbManager->getSingleRow($sql, array($uploadId));
    }
}
