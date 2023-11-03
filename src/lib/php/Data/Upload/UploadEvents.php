<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\Lib\Data\Upload;

/**
 * @class UploadEvents
 * @brief This class contains the events for the upload_events table
 */
class UploadEvents
{
  /**
   * @var int ASSIGNEE_EVENT
   * Event when upload assignee is changed
   */
  const ASSIGNEE_EVENT = 1;
  /**
   * @var int UPLOAD_CLOSED_EVENT
   * Event when upload is closed
   */
  const UPLOAD_CLOSED_EVENT = 2;
}
