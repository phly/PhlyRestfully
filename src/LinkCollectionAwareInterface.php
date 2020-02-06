<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

interface LinkCollectionAwareInterface
{
    /**
     * @return self
     */
    public function setLinks(LinkCollection $links);

    /**
     * @return LinkCollection
     */
    public function getLinks();
}
