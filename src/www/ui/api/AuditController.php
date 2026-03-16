<?php
namespace Fossology\UI\Api\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuditController extends RestController
{
    public function post(Request $request)
    {
        $uploadId = $request->get('uploadId');
        $result = $this->getObject('dao.audit')->saveDecision($request);

        if ($result) {
            // Real-time sync trigger
            $this->getObject('dao.dashboard')->refreshUploadStats($uploadId);
            return new JsonResponse(['status' => 'success', 'message' => 'Decision synced'], 200);
        }
        return new JsonResponse(['status' => 'error', 'message' => 'Failed to save decision'], 400);
    }
}
