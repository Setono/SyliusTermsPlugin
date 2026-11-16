<?php

declare(strict_types=1);

use Setono\SyliusTermsPlugin\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require __DIR__ . '/../Application/config/bootstrap.php';

$kernel = new Kernel('test', true);
$kernel->boot();

return new Application($kernel);
