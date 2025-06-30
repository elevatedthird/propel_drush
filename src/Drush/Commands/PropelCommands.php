<?php

namespace Drupal\propel\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\propel\PropelComponentsManager;

/**
 * Defines Drush commands for the Propel Module.
 */
class PropelCommands extends DrushCommands {

  /**
   * A command that downloads an SDC from Propel.
   *
   * @command propel:add
   * @usage propel:add my_component
   *   Adds a Propel component.
   */
  public function add($name = '') {
    if (empty($name)) {
      throw new \Exception("Error: No component name provided.");
    }
    $propelComponentsManager = \Drupal::service('propel.components_manager');
    $component_path = $propelComponentsManager->getSDCPath($name);
    $propelComponentsManager->downloadSDC($component_path);
    $this->output()->writeln('Added propel component: ' . $name);
  }

  /**
   * A command that downloads the base stylesheet.
   *
   * @command propel:init
   */
  public function init() {
    $propelComponentsManager = \Drupal::service('propel.components_manager');
    $component_path = $propelComponentsManager->downloadStylesheet();
  }

}
