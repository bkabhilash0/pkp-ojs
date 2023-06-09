<?php

/**
 * @file classes/mail/variables/SiteEmailVariable.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SiteEmailVariable
 *
 * @ingroup mail_variables
 *
 * @brief Represents variables that are associated with a website
 */

namespace PKP\mail\variables;

use PKP\mail\Mailable;
use PKP\site\Site;

class SiteEmailVariable extends Variable
{
    public const SITE_TITLE = 'siteTitle';
    public const SITE_CONTACT = 'siteContactName';
    public const SITE_EMAIL = 'siteContactEmail';
    public const SITE_SIGNATURE = 'siteSignature';

    protected Site $site;

    public function __construct(Site $site, Mailable $mailable)
    {
        parent::__construct($mailable);

        $this->site = $site;
    }

    /**
     * @copydoc Variable::descriptions()
     */
    public static function descriptions(): array
    {
        return
        [
            self::SITE_TITLE => __('emailTemplate.variable.site.siteTitle'),
            self::SITE_CONTACT => __('emailTemplate.variable.site.siteContactName'),
            self::SITE_EMAIL => __('emailTemplate.variable.site.siteContactEmail'),
            self::SITE_SIGNATURE => __('emailTemplate.variable.site.siteSignature'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
       [
           self::SITE_TITLE => htmlspecialchars($this->site->getLocalizedData('title', $locale)),
           self::SITE_CONTACT => htmlspecialchars($this->site->getLocalizedData('contactName', $locale)),
           self::SITE_EMAIL => htmlspecialchars($this->site->getLocalizedData('contactEmail', $locale)),
           self::SITE_SIGNATURE => '<p>' .
               htmlspecialchars($this->site->getLocalizedData('contactName', $locale)) . '<br/>' .
               htmlspecialchars($this->site->getLocalizedData('title', $locale)) .
               '</p>',
       ];
    }
}
