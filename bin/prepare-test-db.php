#!/usr/bin/env php
<?php

use App\Kernel;
use App\Tests\Support\TestDatabase;
use Doctrine\ORM\Tools\SchemaTool;

require dirname(__DIR__).'/tests/bootstrap.php';
$kernel = new Kernel('test', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
TestDatabase::guard($em->getConnection(), $kernel->getEnvironment());
$metadata = $em->getMetadataFactory()->getAllMetadata();
$tool = new SchemaTool($em);
$tool->dropSchema($metadata);
$tool->createSchema($metadata);
echo "Schéma de tests recréé dans ".$em->getConnection()->getDatabase().".\n";
