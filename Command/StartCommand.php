<?php

namespace Bundle\EasyWebServerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class StartCommand extends Command
{
  protected function configure()
  {
    $this
      ->setName('easy-web-server:start')
      ->setDescription('Start web server')
      ->setHelp('*WIP*');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $output->writeln('*WIP*'.PHP_EOL);
  }
}
