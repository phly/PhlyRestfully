<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

return array(
    'service_manager' => array(
        'invokables' => array(
            // API-Problem render listener
            'PhlyRestfully\ApiProblemListener' => 'PhlyRestfully\Listener\ApiProblemListener'
        ),
    ),
);
