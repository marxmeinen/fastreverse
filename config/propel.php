<?php

$serviceContainer = \Propel\Runtime\Propel::getServiceContainer();
$serviceContainer->checkVersion('2.0.0-dev');
$serviceContainer->setAdapterClass('unilever', 'mysql');
$manager = new \Propel\Runtime\Connection\ConnectionManagerSingle();
$manager->setConfiguration(array (
  'dsn' => 'mysql:host=muntendam.quickwins.nl;dbname=unilever_dev',
  'user' => 'steven',
  'password' => 'f6a2240e73879ecb9c251164761',
  'settings' =>
  array (
    'charset' => 'utf8',
    'collation' => 'utf8_general_ci'
  ),
  /*'dsn' => 'mysql:host=46.165.251.23;dbname=unilever',
  'user' => 'unilever',
  'password' => 'h9PtXfnuTnEqwCHf',
  'settings' =>
  array (
    'charset' => 'utf8',
    'collation' => 'utf8_general_ci'
  ),*/
));
$manager->setName('unilever');
$serviceContainer->setConnectionManager('unilever', $manager);
$serviceContainer->setDefaultDatasource('unilever');