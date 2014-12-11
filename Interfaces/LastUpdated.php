<?php
/**
 * This file is part of the CBI project codebase.
 * (c) Cloudtroopers Intl <info@cloudtroopers.com>
 */

namespace CTI\MongoServiceBundle\Interfaces;

/**
 * Ensures an entity exposes a lastUpdated field
 *
 * @package TeeSnap\CoreBundle\Interfaces
 *
 * @author  Georgiana Gligor <g@lolaent.com>
 */
interface LastUpdated
{
    /**
     * @param \DateTime $lastUpdated
     *
     * @return LastUpdated
     */
    public function setLastUpdated(\DateTime $lastUpdated = null);

    /**
     * @return \DateTime
     */
    public function getLastUpdated();

}
