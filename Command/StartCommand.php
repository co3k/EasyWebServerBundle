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
    $connections,

    $request;

  protected function configure()
  {
    $this
      ->setName('easy-web-server:start')
      ->setDescription('Start web server')
      ->setHelp('*WIP*');
  }

  protected function parseRequest($socket)
  {
    $raw = '';
    $begin = time();

    $headers = array();
    $method = $uri = $version = $body = '';

    $isTimedOut = true;

    while (time() < $begin + 30)
    {
      $data = socket_read($socket, 4096, PHP_BINARY_READ);
      $raw = $raw.$data;

      if (empty($headers) && $this->isCompletedHeader($raw))
      {
        list($method, $uri, $version, $headers) = $this->splitHeaderAndBody($raw);;
      }

      if (false === $data)
      {
        return false;
      }
      elseif (!$raw)  // I don't care POST yet
      {
        $isTimedOut = false;
        $body = $raw;

        break;
      }
    }

    if ($isTimedOut)
    {
      throw new RuntimeException('Timed out');
    }

    return array($method, $uri, $version, $headers, $body);
  }

  protected function isCompletedHeader($response)
  {
    return (bool)preg_match('/(\n\s+\n)/', $response);
  }

  protected function splitHeaderAndBody(&$raw)
  {
    $headers = array();
    $method = $uri = $version = '';

    $parts = preg_split('/(\n\s+\n)/', $raw, 2);

    // body
    $raw = $parts[1];

    // parse headers
    $rawHeaders = explode("\n", $parts[0]);
    foreach ($rawHeaders as $i => $v)
    {
      if (0 == $i)
      {
        list($method, $uri, $version) = explode(' ', $v);


        continue;
      }

      $header = explode(":", $v, 2);

      if (count($header) == 2)
      {
        $headers[trim($header[0])] = trim($header[1]);
      }
    }

    return array($method, $uri, $version, $headers);
  }

  protected function processUserHttpRequest($method, $uri, $version, $headers, $body)
  {
    restore_error_handler();

    $originalKernel = $this->application->getKernel();
    $kernelClass = get_class($originalKernel);
    $r = new \ReflectionObject($originalKernel);

    require_once $r->getFileName();

    $requestClass = $originalKernel->getContainer()->getRequestService();
    $request = $requestClass::create($uri, strtolower($method));
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
          list($method, $uri, $version, $headers, $body) = $this->parseRequest($client);

          $response = $this->processUserHttpRequest($method, $uri, $version, $headers, $body);

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
