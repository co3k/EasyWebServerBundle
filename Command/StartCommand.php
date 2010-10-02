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
  protected
    $socket,
    $connections;

  protected function configure()
  {
    $this
      ->setName('easy-web-server:start')
      ->setDescription('Start web server')
      ->setHelp('*WIP*');
  }

  protected function processUserRequest()
  {
    restore_error_handler();

    $originalKernel = $this->application->getKernel();
    $kernelClass = get_class($originalKernel);
    $r = new \ReflectionObject($originalKernel);

    require_once $r->getFileName();

    $requestClass = $originalKernel->getContainer()->getRequestService();
    $request = $requestClass::create('/statuses');
    $request->setRequestFormat('html');

    $newKernel = new $kernelClass('prod', true);
    $result = (string)$newKernel->handle($request);

    $this->container->getErrorHandlerService()->register();

    return $result;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->socket = socket_create_listen(8080);
    if (!$this->socket)
    {
      throw new \RuntimeException(sprintf('Failed to start server: %s', socket_last_error($this->socket)));
    }

    $this->listen();
  }

  protected function listen()
  {
    while (true)
    {
      $client = $this->getConnection();
      if ($client)
      {
        $pid = pcntl_fork();

        if ($pid)
        {
          $this->connections[$pid] = $client;
        }
        else
        {
          $response = $this->processUserRequest();

          socket_write($client, $response, strlen($response));

          exit;
        }
      }

      $this->cleanConnections();
    }
  }

  protected function getConnection()
  {
    $sockets = array($this->socket);
    $client = null;
    $w = $e = null;

    if (socket_select($sockets, $w, $e, 0))
    {
      $client = socket_accept($this->socket);
    }
    else
    {
      usleep(100);
    }

    return $client;
  }

  protected function cleanConnections()
  {
    while (($child = pcntl_wait($status, WNOHANG)) > 0)
    {
      socket_shutdown($this->connections[$child]);
      socket_close($this->connections[$child]);
      unset($this->connections[$child]);
    }
  }
}
