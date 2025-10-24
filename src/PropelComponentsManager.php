<?php

namespace Drupal\propel;

use Drupal\Core\Logger\RfcLogLevel as LogLevel;
use Drupal\Core\Serialization\Yaml;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Filesystem\Filesystem;

class PropelComponentsManager implements LoggerAwareInterface {
  use LoggerAwareTrait;

  protected $api_url = "https://api.github.com/repos/elevatedthird/propel-components";

  protected $client = NULL;

  protected $component_list = [];

  public function __construct() {
    $this->client = new Client([
      'headers' => [
        'Accept' => 'application/vnd.github+json',
        'X-GitHub-Api-Version' => '2022-11-28'
      ]
    ]);
    $this->getSDCList();
  }

  /**
   * Get the list of components from the propel-components repo.
   */
  public function getSDCList() {
    $response = $this->client->request('GET', $this->api_url . "/git/trees/main?recursive=1");
    if ($response instanceof Response) {
      $status = $response->getStatusCode();
      if ($status !== 200) {
        throw new \Exception("Error: Could not get component list from propel-components repo.");
      }
      $body = $response->getBody();
      $body = json_decode($body->getContents(), TRUE);
      $this->component_list = $body['tree'];
    } else {
      throw new \Exception("Error: Could not get component list from propel-components repo.");
    }
  }

  /**
   * Download a single SDC component and all its dependencies.
   */
  public function downloadSDC(string $component_path) {
    if (empty($component_path)) {
      throw new \Exception("Error: No component path provided.");
    }
    $fs = new Filesystem();
    $theme_path = \Drupal::service('extension.list.theme')->getPath('kinetic');
    // Check if this folder exists in the theme.
    $destination = "{$theme_path}/{$component_path}";
    $exists = FALSE;
    if ($fs->exists($destination)) {
      $this->logger->warning("Component already exists at: {$component_path}.");
      $exists = TRUE;
    }
    // Attempt to download the component and all it's files into the theme.
    $content_url = $this->api_url . "/contents/{$component_path}"; ;
    $response = $this->client->request('GET', $content_url);
    if ($response->getStatusCode() !== 200) {
      throw new \Exception("Error: Could not download component from URL {$content_url}.");
    }
    $content = $response->getBody();
    $content = json_decode($content->getContents(), TRUE);
    $component_yaml_file_path = '';
    // Loop through all files in the folder.
    foreach ($content as $file) {
      $file_name = $destination . '/' . $file['name'];
      if (!$exists) {
        $fs->dumpFile($file_name, file_get_contents($file['download_url']));
      }
      if (str_ends_with($file_name, 'component.yml')) {
        $component_yaml_file_path = $file_name;
      }
    }
    $sdc_name = basename($component_path);
    $this->logger->success("Adding SDC: {$sdc_name}");
    // Check for dependencies.
    $component_yaml = Yaml::decode(file_get_contents($component_yaml_file_path)) ?? [];
    if (isset($component_yaml['needs']) && gettype($component_yaml['needs']) === 'array') {
      foreach ($component_yaml['needs'] as $dependency) {
        $path = $this->getSDCPath($dependency);
        if (!empty($path)) {
          $this->downloadSDC($path);
        }
      }
    }
  }

  /**
   * Get the path of a single SDC component from the repo.
   */
  public function getSDCPath(string $name): string {
    // Check if the component exists in the repo.
    $sdc = '';
    foreach ($this->component_list as $file) {
      if (str_ends_with($file['path'], $name)) {
        $sdc = $file['path'];
        break;
      }
    }
    if (!$sdc) {
      throw new \Exception("Error: Could not find component: {$name}.");
    }
    return $sdc;
  }

  /**
   * Download index.pcss.css from Propel Components.
   */
  public function downloadStylesheets() {
    $fs = new Filesystem();
    $theme_path = \Drupal::service('extension.list.theme')->getPath('kinetic');
    // Check if this file already exists.
    if ($fs->exists("{$theme_path}/source/01-base/global/css/index.pcss.css")) {
      $this->logger->warning("Base stylesheet already exists, exiting.");
      return;
    }
    $stylesheets = [];
    // Attempt to download the Base Stylesheet.
    $index_css = $this->client->request('GET', $this->api_url . "/contents/01-base/global/css/index.pcss.css");
    if ($index_css->getStatusCode() !== 200) {
      throw new \Exception("Error: Could not reach index.pcss.css from URL");
    }
    $stylesheets[] = json_decode($index_css->getBody()->getContents(), TRUE);
    // Download all other general stylesheet files.
    $response = $this->client->request('GET', $this->api_url . "/contents/01-base/global/css/general");
    if ($response->getStatusCode() !== 200) {
      throw new \Exception("Error: Could not reach general stylesheets from URL");
    }
    $stylesheets = array_merge($stylesheets, json_decode($response->getBody()->getContents(), TRUE));
    try {
      foreach ($stylesheets as $file) {
        $fs->dumpFile("{$theme_path}/source/{$file['path']}", file_get_contents($file['download_url']));
      }
      $this->logger->success("Downloaded general stylesheets to {$theme_path}/source/01-base/global/css");
    }
    catch (\Exception $e) {
      throw new \Exception("Error: Could not download general stylesheets from URL");
    }
  }
}
