<?php

/**
 * @file controllers/grid/toc/TocGridCategoryRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TocGridCategoryRow
 *
 * @ingroup controllers_grid_admin_systemInfo
 *
 * @brief System Info grid category row definition
 */

namespace APP\controllers\grid\toc;

use PKP\controllers\grid\GridCategoryRow;

class TocGridCategoryRow extends GridCategoryRow
{
    //
    // Overridden methods from GridCategoryRow
    //
    /**
     * Use a label if the actions in the grid are disabled.
     * return string
     */
    public function getCategoryLabel()
    {
        $section = $this->getData();
        return $section->getLocalizedTitle();
    }
}
