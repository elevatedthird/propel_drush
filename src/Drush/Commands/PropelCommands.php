<?php

namespace Drupal\propel\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Defines Drush commands for the Custom Module.
 */
class PropelCommands extends DrushCommands {

  private $api_url = "https://api.github.com/repos/elevatedthird/propel-components";

  protected $component_list = [];

  /**
   * Get the list of components from the propel-components repo.
   */
  protected function getSDCList() {
    $client = HttpClient::create();
    $response = $client->request('GET', $this->api_url . "/git/trees/main?recursive=1");
    $status = $response->getStatusCode();
    if ($status !== 200) {
      throw new \Exception("Error: Could not get component list from propel-components repo.");
    }
    $response = $response->toArray();
    $this->component_list = $response['tree'];
    // dump($response); die;
  }

  /**
   * Download a single SDC component.
   */
  protected function downloadSDC(string $component_path) {
    if (empty($component_path)) {
      throw new \Exception("Error: No component path provided.");
    }
    $client = HttpClient::create();
    $fs = new Filesystem();
    $theme_path = \Drupal::service('extension.list.theme')->getPath('kinetic');
    // Check if this folder exists in the theme.
    $destination = "{$theme_path}/components/{$component_path}";
    if ($fs->exists($destination)) {
      $this->output()->writeln("Component already exists at: {$component_path}.");
    }
    // Attempt to download the component and all it's files into the theme.
    $content_url = $this->api_url . "/contents/{$component_path}"; ;
    $content = $client->request('GET', $content_url);
    if ($content->getStatusCode() !== 200) {
      throw new \Exception("Error: Could not download component from URL {$content_url}.");
    }
    $content = $content->toArray();
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
    $this->getSDCList();
    $component_path = $this->getSDCPath($name);
    $this->downloadSDC($component_path);
    $this->output()->writeln('Added propel component: ' . $name);
  }

}
