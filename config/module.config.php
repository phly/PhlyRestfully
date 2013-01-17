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
            // Forces a distinct instance of the JsonRenderer for the RestfulJsonStrategy
            'PhlyRestfully\JsonRenderer' => 'Zend\View\Renderer\JsonRenderer',
            // API-Problem render listener
            'PhlyRestfully\ApiProblemListener' => 'PhlyRestfully\Listener\ApiProblemListener'
        ),
    ),
    'controller_plugins' => array(
        'invokables' => array(
            'apiProblemResult' => 'PhlyRestfully\Plugin\ApiProblemResult',
        ),
    ),
);
