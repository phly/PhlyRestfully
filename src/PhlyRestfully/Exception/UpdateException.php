<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Exception;

/**
 * Throw this exception from a "update" resource listener in order to indicate 
 * an inability to update an item and automatically report it.
 */
class UpdateException extends DomainException
{
}
