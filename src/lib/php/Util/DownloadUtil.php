<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Util;

use Symfony\Component\HttpFoundation\Response;

class DownloadUtil
{
  /**
   * Creates a response with download confirmation
   * @param string $downloadUrl The URL to download from
   * @param string $fileName The name of the file to be downloaded
   * @param string $referer The page to redirect to after download
   * @return Response
   */
  public static function getDownloadConfirmationResponse($downloadUrl, $fileName, $referer)
  {
    if (empty($referer)) {
      $referer = "?mod=browse";
    }

    $script = '<script>
      if (confirm("Do you want to download the file ' . $fileName . '?")) {
        fetch("' . $downloadUrl . '")
          .then(response => response.blob())
          .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "' . $fileName . '";
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            window.location.href = "' . $referer . '";
          });
      } else {
        window.location.href = "' . $referer . '";
      }
    </script>';

    return new Response($script, Response::HTTP_OK, ['Content-Type' => 'text/html']);
  }

  /**
   * Creates a response for file download
   * @param string $content The file content
   * @param string $fileName The name of the file
   * @param string $contentType The content type of the file
   * @return Response
   */
  public static function getDownloadResponse($content, $fileName, $contentType = 'text/csv')
  {
    $headers = array(
      'Content-type' => $contentType . ', charset=UTF-8',
      'Content-Disposition' => 'attachment; filename=' . $fileName,
      'Pragma' => 'no-cache',
      'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
      'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT'
    );

    return new Response($content, Response::HTTP_OK, $headers);
  }
}