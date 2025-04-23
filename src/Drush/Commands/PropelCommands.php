<?php

namespace Drupal\propel\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Serialization\Yaml;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Defines Drush commands for the Custom Module.
 */
class PropelCommands extends DrushCommands {

  protected $api_url = "https://api.github.com/repos/elevatedthird/propel-components";

  protected $client = NULL;

  protected $component_list = [];

  public function __construct() {
    parent::__construct();
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
  protected function getSDCList() {
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
   * Download a single SDC component.
   */
  protected function downloadSDC(string $component_path) {
    if (empty($component_path)) {
      throw new \Exception("Error: No component path provided.");
    }
    $fs = new Filesystem();
    $theme_path = \Drupal::service('extension.list.theme')->getPath('kinetic');
    // Check if this folder exists in the theme.
    $destination = "{$theme_path}/{$component_path}";
    if ($fs->exists($destination)) {
      $this->output()->writeln("Component already exists at: {$component_path}.");
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
    foreach ($content as $file) {
      $file_name = $destination . '/' . $file['name'];
      $fs->dumpFile($file_name, file_get_contents($file['download_url']));
      if (str_ends_with($file_name, 'component.yml')) {
        $component_yaml_file_path = $file_name;
      }
    }
    // Check for dependencies.
    $component_yaml = Yaml::decode(file_get_contents($component_yaml_file_path)) ?? [];
    if (isset($component_yaml['needs']) && gettype($component_yaml['needs']) === 'array') {
      foreach ($component_yaml['needs'] as $dependency) {
        $this->output()->writeln("Adding SDC dependency: {$dependency}");
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
  protected function getSDCPath(string $name): string {
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
   * A custom Drush command that prints a message.
   *
   * @command propel:add
   * @usage propel:add my_component
   *   Adds a Propel component.
   */
  public function add($name = '') {
    if (empty($name)) {
      throw new \Exception("Error: No component name provided.");
    }
    $component_path = $this->getSDCPath($name);
    $this->downloadSDC($component_path);
    $this->output()->writeln('Added propel component: ' . $name);
  }

}
