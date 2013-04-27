<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Exception;

/**
 * Interface for exceptions that can provide additional API Problem details
 */
interface ProblemExceptionInterface
{
    public function getAdditionalDetails();
    public function getDescribedBy();
    public function getTitle();
}

